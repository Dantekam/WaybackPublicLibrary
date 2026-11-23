
<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db_connect.php';

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond([
        'ok' => false,
        'error' => 'POST required.'
    ], 405);
}

$fineRaw = $_POST['fineID'] ?? '';

if (!is_string($fineRaw) ||
    !ctype_digit($fineRaw) ||
    (int)$fineRaw <= 0) {
    respond([
        'ok' => false,
        'error' => 'Valid fine ID required.'
    ], 400);
}

$amountRaw = $_POST['paymentAmount'] ?? '';

if (!is_string($amountRaw) ||
    !preg_match('/^\d+(\.\d{1,2})?$/', $amountRaw) ||
    (float)$amountRaw <= 0) {
    respond([
        'ok' => false,
        'error' => 'Valid positive payment amount required.'
    ], 400);
}

$paymentMethod = trim($_POST['paymentMethod'] ?? '');

if (!is_string($paymentMethod) || $paymentMethod === '') {
    respond([
        'ok' => false,
        'error' => 'Payment method required.'
    ], 400);
}

$fineID = (int)$fineRaw;
$paymentCents = (int)round((float)$amountRaw * 100);

try {
    $pdo->beginTransaction();

    // 1. Retrieve fine
    $stmt = $pdo->prepare(
        'SELECT
            FineID,
            PatronID,
            Amount,
            AmountPaid,
            Status
         FROM Fine
         WHERE FineID = ?
         FOR UPDATE'
    );

    $stmt->execute([$fineID]);
    $fine = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fine) {
        throw new DomainException('Fine not found.');
    }

    // 2. Calculate the remaining balance
    $fineCents = (int)round((float)$fine['Amount'] * 100);
    $paidCents = (int)round((float)$fine['AmountPaid'] * 100);
    $remainingCents = $fineCents - $paidCents;

    if ($remainingCents <= 0) {
        throw new DomainException('This fine has already been resolved.');
    }

    // 3. make sure the payment does not exceed the balance
    if ($paymentCents > $remainingCents) {
        throw new DomainException(
            'Payment exceeds the remaining fine balance.'
        );
    }

    // 4. Calculate the updated payment total
    $newAmountPaid = $paidCents + $paymentCents;
    $newBalance = $fineCents - $newAmountPaid;

    $newStatus = ($newBalance === 0)
        ? 'Resolved'
        : 'Outstanding';

    // 5. Record the payment
    $stmt = $pdo->prepare(
        'INSERT INTO FinePayment (
            FineID,
            PaymentAmount,
            PaymentDateTime,
            PaymentMethod
        ) VALUES (?, ?, NOW(), ?)'
    );

    $stmt->execute([
        $fineID,
        number_format($paymentCents / 100, 2, '.', ''),
        $paymentMethod
    ]);

    $paymentID = $pdo->lastInsertId();

    // 6. Update the fine balance and status
    $stmt = $pdo->prepare(
        'UPDATE Fine
         SET AmountPaid = ?,
             Status = ?
         WHERE FineID = ?'
    );

    $stmt->execute([
        number_format($newAmountPaid / 100, 2, '.', ''),
        $newStatus,
        $fineID
    ]);

    // 7. Verify the updated fine
    $stmt = $pdo->prepare(
        'SELECT Amount, AmountPaid, Status
         FROM Fine
         WHERE FineID = ?'
    );

    $stmt->execute([$fineID]);
    $updatedFine = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$updatedFine ||
        (int)round((float)$updatedFine['AmountPaid'] * 100) !== $newAmountPaid ||
        $updatedFine['Status'] !== $newStatus) {
        throw new RuntimeException(
            'Could not update fine payment information.'
        );
    }

    $pdo->commit();

    // 8. Display the updated payment information
    respond([
        'ok' => true,
        'message' => 'Fine payment recorded successfully.',
        'payment' => [
            'paymentID' => $paymentID,
            'fineID' => $fineID,
            'patronID' => $fine['PatronID'],
            'paymentAmount' => number_format($paymentCents / 100, 2, '.', ''),
            'paymentMethod' => $paymentMethod,
            'remainingBalance' => number_format($newBalance / 100, 2, '.', ''),
            'status' => $newStatus
        ]
    ]);

} catch (DomainException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respond([
        'ok' => false,
        'error' => $e->getMessage()
    ], 400);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('payFines: ' . $e->getMessage());

    respond([
        'ok' => false,
        'error' => 'Could not process fine payment.'
    ], 500);
}

?>
