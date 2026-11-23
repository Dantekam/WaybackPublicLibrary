<?php
require __DIR__ . '/script/db_connect.php';
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['staff_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action   = $input['action'] ?? '';
$patronId = isset($input['patron_id']) ? (int)$input['patron_id'] : 0;

if ($patronId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing patron ID.']);
    exit;
}

try {
    switch ($action) {
        case 'pay_fine':         handlePayFine($pdo, $patronId, $input);         break;
        case 'pay_all_fines':    handlePayAllFines($pdo, $patronId, $input);     break;
        case 'renew_membership': handleRenewMembership($pdo, $patronId, $input); break;
        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    }
} catch (Throwable $e) {
    error_log('patron-action: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Action failed. Please try again.']);
}

function handlePayFine(PDO $pdo, int $patronId, array $input): void
{
    $fineId = (int)($input['fine_id'] ?? 0);
    $amount = round((float)($input['amount'] ?? 0), 2);

    if ($fineId <= 0 || $amount <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid fine or amount.']);
        return;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT FineID, Amount, AmountPaid
         FROM Fine
         WHERE FineID = ? AND PatronID = ?
         FOR UPDATE'
    );
    $stmt->execute([$fineId, $patronId]);
    $fine = $stmt->fetch();

    if (!$fine) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Fine not found for this patron.']);
        return;
    }

    $remaining = (float)$fine['Amount'] - (float)$fine['AmountPaid'];
    if ($amount > $remaining + 0.001) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'ok'    => false,
            'error' => sprintf('Payment exceeds remaining balance of $%.2f.', $remaining)
        ]);
        return;
    }

    $update = $pdo->prepare('UPDATE Fine SET AmountPaid = AmountPaid + ? WHERE FineID = ?');
    $update->execute([$amount, $fineId]);

    $log = $pdo->prepare(
        'INSERT INTO Payment_Log (PatronID, FineID, Amount, ProcessedBy, Notes)
         VALUES (?, ?, ?, ?, ?)'
    );
    $log->execute([
        $patronId, $fineId, $amount,
        $_SESSION['staff_id'] ?? null,
        $input['notes'] ?? null
    ]);

    $pdo->commit();

    echo json_encode([
        'ok'          => true,
        'fine_id'     => $fineId,
        'amount_paid' => $amount,
        'new_balance' => round($remaining - $amount, 2)
    ]);
}

function handlePayAllFines(PDO $pdo, int $patronId, array $input): void
{
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT FineID, Amount, AmountPaid
         FROM Fine
         WHERE PatronID = ? AND AmountPaid < Amount
         FOR UPDATE'
    );
    $stmt->execute([$patronId]);
    $fines = $stmt->fetchAll();

    if (!$fines) {
        $pdo->rollBack();
        echo json_encode(['ok' => true, 'amount_paid' => 0, 'fines_paid' => 0]);
        return;
    }

    $total  = 0.0;
    $count  = 0;
    $update = $pdo->prepare('UPDATE Fine SET AmountPaid = Amount WHERE FineID = ?');
    $log    = $pdo->prepare(
        'INSERT INTO Payment_Log (PatronID, FineID, Amount, ProcessedBy, Notes)
         VALUES (?, ?, ?, ?, ?)'
    );

    foreach ($fines as $fine) {
        $remaining = (float)$fine['Amount'] - (float)$fine['AmountPaid'];
        if ($remaining <= 0) continue;
        $update->execute([$fine['FineID']]);
        $log->execute([
            $patronId, $fine['FineID'], $remaining,
            $_SESSION['staff_id'] ?? null,
            $input['notes'] ?? 'Pay all fines'
        ]);
        $total += $remaining;
        $count++;
    }

    $pdo->commit();

    echo json_encode([
        'ok'          => true,
        'amount_paid' => round($total, 2),
        'fines_paid'  => $count
    ]);
}

function handleRenewMembership(PDO $pdo, int $patronId, array $input): void
{
    $months = isset($input['months']) ? (int)$input['months'] : 12;
    if ($months < 1 || $months > 60) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid renewal duration.']);
        return;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT PatronID, MembershipExpiration, MembershipRenewalCount
         FROM Patron
         WHERE PatronID = ?
         FOR UPDATE'
    );
    $stmt->execute([$patronId]);
    $patron = $stmt->fetch();

    if (!$patron) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Patron not found.']);
        return;
    }

    $oldExpiration = $patron['MembershipExpiration'];
    $today = new DateTimeImmutable('today');
    $baseDate = $oldExpiration && new DateTimeImmutable($oldExpiration) > $today
        ? new DateTimeImmutable($oldExpiration)
        : $today;
    $newExpiration = $baseDate->modify("+{$months} months")->format('Y-m-d');

    $update = $pdo->prepare(
        'UPDATE Patron
         SET MembershipExpiration   = ?,
             MembershipRenewalCount = MembershipRenewalCount + 1
         WHERE PatronID = ?'
    );
    $update->execute([$newExpiration, $patronId]);

    $log = $pdo->prepare(
        'INSERT INTO Membership_Renewal_Log
            (PatronID, OldExpiration, NewExpiration, FeeCharged, ProcessedBy)
         VALUES (?, ?, ?, ?, ?)'
    );
    $log->execute([
        $patronId, $oldExpiration, $newExpiration,
        round((float)($input['fee'] ?? 0), 2),
        $_SESSION['staff_id'] ?? null
    ]);

    $pdo->commit();

    echo json_encode([
        'ok'             => true,
        'old_expiration' => $oldExpiration,
        'new_expiration' => $newExpiration,
        'months_added'   => $months,
        'renewal_count'  => (int)$patron['MembershipRenewalCount'] + 1
    ]);
}