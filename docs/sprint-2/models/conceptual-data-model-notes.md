# Sprint 2 – Conceptual and Relational Data Model Notes

## Overview

The Sprint 2 data model builds upon the Wayback Public Library's Sprint 1 circulation and catalog functionality.

Sprint 2 extends the model to support item returns, branch routing, reshelving, unscannable returns, overdue fines, renewals, and reservations.

The current relational model represents the established checkout and check-in entities, including the recently added RenewalCount attribute and separate home/current branch relationships. Reservation-related changes are proposed based on subsequent client clarification and must be incorporated into the next model revision.

This document explains the business purpose of the entities, their relationships, and the implementation considerations for the development team.

## 1. Existing Entities and Responsibilities

### Patron

Represents a registered library patron.

Current attributes:
- PatronID (PK)
- FirstName
- LastName
- Email
- MembershipExpiration

A patron may have multiple historical checkouts, fines, and reservations.

**Proposed addition:** HomeBranchID (FK to Branch), representing the patron's default reservation pickup location.

The existing model does not yet include this attribute. Its addition is recommended because the client confirmed that reservation pickup defaults to the patron's home branch.

### Checkout

Represents the borrowing of one physical item copy by one patron.

Current attributes:
- CheckoutID (PK)
- PatronID (FK)
- ItemCopyID (FK)
- ReturnBranchID (FK, nullable until return)
- CheckoutDate
- DueDate
- ReturnDate (nullable until return)
- RenewalCount

RenewalCount tracks successful renewals within the current continuous checkout. The initial value is zero, and a maximum of two renewals is permitted.

Renewal updates the existing checkout's DueDate without closing the checkout or changing the item's CheckedOut status.

The system must prevent multiple simultaneously active checkouts for the same item copy.

### Branch

Represents a physical library branch.

Current attributes:
- BranchID (PK)
- BranchName

Branch participates in several distinct relationships:
- Home branch of an item copy
- Recorded current branch of an item copy
- Return branch of a checkout
- Receiving branch of an error-bin entry

The same Branch entity may participate in multiple relationships with another entity when each relationship has a different business meaning.

### Item_Copy

Represents an individual physical copy of a catalog item.

Current attributes:
- ItemCopyID (PK)
- ItemID (FK)
- HomeBranchID (FK)
- CurrentBranchID (FK)
- AcquisitionDate
- Status
- ShelfLocationCode

HomeBranchID identifies the copy's assigned branch.

CurrentBranchID identifies the branch currently associated with the copy's location. Status provides additional information when the copy is checked out, awaiting transport, awaiting reshelving, or reserved.

CurrentBranchID is not necessarily sufficient to describe an item physically traveling between branches.

### Item

Represents a catalog item or title.

Current attributes:
- ItemID (PK)
- ItemTypeID (FK)
- Title
- Publisher
- LibraryOfCongressCode
- Cost
- PublicationDate

An item may have multiple physical copies.

### Item_Type

Represents an item category and its shared circulation rules.

Current attributes:
- ItemTypeID (PK)
- TypeName
- LoanPeriodDays
- DailyFineRate

LoanPeriodDays supports checkout and renewal due-date calculations.

DailyFineRate supports overdue fine assessment.

### Author and Item_Author

Author stores individual author information.

Item_Author associates catalog items with authors using a composite primary key consisting of ItemID and AuthorID.

The client confirmed that some catalog items have no authors and others have several.

Therefore, an Item may have zero or many author associations.

### Fine

Represents an assessed charge associated with a patron and, where applicable, a checkout.

Current attributes:
- FineID (PK)
- PatronID (FK)
- CheckoutID (FK)
- Amount
- AmountPaid
- Status

The client confirmed that multiple separate fine records may be associated with one checkout.

The system must prevent duplicate assessment of the same overdue charge.

Fines are normally assessed during return processing. Overdue renewal attempts also trigger fine assessment.

Outstanding fines on other items must be displayed and addressed during relevant circulation transactions.

### Error_Bin_Entry

Represents an unidentified or unscannable physical return.

Current attributes:
- ErrorEntryID (PK)
- BranchID (FK)
- ItemCopyID (FK, nullable)
- ReceivedDateTime
- Description
- ResolutionStatus
- ResolvedDateTime

An error-bin entry may be created before the item copy is identified.

Once identified, staff may associate the entry with the correct copy and complete the normal check-in process.

## 2. Proposed Reservation Entity

The client confirmed that reservations may be created for unavailable copies and that specific-copy reservations are acceptable for the MVP.

Proposed attributes:
- ReservationID (PK)
- PatronID (FK)
- ItemCopyID (FK)
- PickupBranchID (FK)
- ReservationDateTime
- ReadyForPickupDateTime (nullable)
- ExpirationDateTime (nullable)
- Status

A patron may create multiple reservations over time.

An item copy may have multiple historical reservations, although the initial MVP may restrict it to one active reservation at a time.

PickupBranchID represents the location where the patron should collect the reserved item.

The patron's home branch is the default pickup branch.

The five-day pickup period begins when staff place the item on the reservation shelf and mark it ready for pickup.

The system must support reservation expiration and release of unclaimed items.

A future enhancement may allow patrons to reserve any available copy of a title and may support multiple patrons waiting for the same title.

## 3. Check-In and Return Processing

The system must identify the item copy and locate its active checkout.

A successful check-in:
1. Records the return date.
2. Records the return branch.
3. Closes the active checkout.
4. Assesses any applicable overdue fine.
5. Determines whether an active reservation affects routing.
6. Assigns the appropriate return-processing status.
7. Displays routing instructions.

Recording the return does not immediately make the item available.

The related database updates should be performed atomically to avoid inconsistent circulation records.

## 4. Return Routing and Reshelving

The library must distinguish between normal returns and reserved returns.

For normal returns:
- A copy returned to its home branch is routed for reshelving.
- A copy returned to another branch is routed for transport.
- An item becomes Available only after staff complete the appropriate reshelving process.

For reserved returns:
- The system identifies the reservation and pickup branch.
- The library transports the item to the patron's pickup branch when necessary.
- Staff place the item on the reservation shelf.
- The system records pickup readiness and calculates the five-day expiration deadline.
- The item must remain unavailable for ordinary checkout while the reservation is active.

Detailed truck or shipment tracking is not required for the initial MVP.

## 5. Renewal Processing

The patron may request renewal remotely or in person.

The system must:
1. Identify the patron and active checkout.
2. Check whether the item is overdue.
3. Check whether an active reservation blocks renewal.
4. Check whether RenewalCount is less than two.
5. Display relevant outstanding fines.
6. Calculate the new due date from the renewal date and applicable item-type loan period.
7. Update the existing checkout's DueDate and RenewalCount.
8. Preserve the item's CheckedOut status.
9. Display the renewal result or denial reason.

Overdue items and reserved items are not eligible for renewal.

An overdue renewal request triggers applicable fine assessment without automatically creating a new checkout.

## 6. Reservation Processing

A reservation may be created while an item copy is checked out.

The initial MVP may restrict each copy to one active reservation.

Proposed reservation statuses include:
- Pending
- AwaitingTransport
- ReadyForPickup
- Fulfilled
- Expired
- Cancelled

These statuses describe the reservation lifecycle and should be distinguished from the physical copy's circulation status.

The pickup deadline begins when the item is placed on the reservation shelf and marked ready.

Expired reservations must be released, with the item routed to another waiting patron if queue functionality exists or returned to normal circulation otherwise.

## 7. Circulation Statuses

Proposed Item_Copy statuses include:
- Available
- CheckedOut
- AwaitingReshelving
- AwaitingTransport
- ReservedForPickup
- ErrorBin or NeedsReview, if appropriate

The development team must agree on the exact permitted status values and transitions before implementing database constraints.

Reservation status and physical copy status should not be treated as interchangeable.

## 8. Remaining Design Decisions

The following items require implementation refinement or further clarification:

1. Exact patron home-branch storage and pickup-branch selection.
2. Fine-payment rules when renewing an otherwise eligible item while owing fines on another item.
3. Official return date for previously unidentified items.
4. Reservation queue support beyond the initial MVP.
5. Final item-copy and reservation status values.
6. Whether renewal history beyond RenewalCount must be retained.
7. Which later circulation enhancements can be delivered within Sprint 2 capacity.
