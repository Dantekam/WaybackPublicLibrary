# WPL User Stories

This document contains the initial user stories identified during
Sprint 0 requirements analysis. The priorities reflect current client
feedback and may be adjusted throughout future sprints as requirements
are further clarified.

The initial user stories and priorities focus on completing a full
cycle of checking out and returning an item at WPL.

---

## Checkout

### User Story-01 - Identify Patron
**Priority:** High

**As a librarian, I want to identify a patron during checkout, so that
I can access the patron information needed to process the checkout.**

#### Acceptance Criteria

1. The system allows the librarian to identify an existing patron.
2. The system displays the identified patron's relevant checkout
   information.
3. The system indicates when the patron cannot be found.

---

### User Story-02 - Verify Checkout Eligibility
**Priority:** High

**As a librarian, I want the system to verify a patron's checkout
eligibility, so that ineligible patrons cannot check out additional
items.**

#### Acceptance Criteria

1. The system prevents checkout when the patron's membership is expired.
2. The system prevents checkout when the patron has an outstanding fine.
3. The system prevents checkout when adding another item would cause the
   patron to exceed 20 checked-out items across all WPL branches.
4. The system allows checkout to continue when all eligibility
   requirements are satisfied.

---

### User Story-03 - Identify Checkout Item
**Priority:** High

**As a librarian, I want to identify an item copy being checked out,
so that the correct physical item is associated with the patron.**

#### Acceptance Criteria

1. The system allows the librarian to identify an item copy.
2. The system displays the identified item's relevant information.
3. The system indicates when an item cannot be identified.
4. The system does not allow an unavailable item to be checked out.

---

### User Story-04 - Record Item Checkout
**Priority:** High

**As a librarian, I want to record an eligible item's checkout to a
patron, so that WPL can accurately track borrowed items.**

#### Acceptance Criteria

1. The system creates a checkout record associating the identified
   patron with the identified item copy.
2. The system records the checkout date.
3. The system determines the due date based on the item's type.
4. The system records the calculated due date with the checkout.
5. The item is no longer available for another patron to check out
   after the checkout is completed.

---

## Check-In

### User Story-05 - Record Item Return
**Priority:** High

**As a librarian, I want to check in a returned item, so that the
patron is no longer responsible for the borrowed item.**

#### Acceptance Criteria

1. The system allows the librarian to identify the returned item.
2. The system records the date and branch where the item was returned.
3. The system associates the return with the item's active checkout.
4. The returned item is removed from the patron's active checked-out
   items.
5. The item is not marked available merely because it has been returned.

---

### User Story-06 - Route Returned Item
**Priority:** Medium

**As a librarian, I want the system to determine where a returned item
should go, so that it can be routed appropriately after check-in.**

#### Acceptance Criteria

1. The system compares the return branch with the item's home branch.
2. An item returned to its home branch is identified for reshelving.
3. An item returned to another branch is identified for transport to
   its home branch.
4. The item remains unavailable until it has returned to an appropriate
   available state.

---

### User Story-07 - Handle Unscannable Return
**Priority:** Medium

**As a librarian, I want to record that an unscannable returned item
has been placed in the error bin, so that it can be identified and
processed later.**

#### Acceptance Criteria

1. The librarian can indicate that a returned item could not be scanned.
2. The system records a status or comment indicating that the item is
   in the error bin.
3. An item in the error bin is not shown as available for checkout.

---

## Lower Functionality

### User Story-08 - Renew Patron Membership
**Priority:** Medium

**As a librarian, I want to renew an expired patron membership, so that
an eligible patron can regain checkout privileges.**

### User Story-09 - Process Overdue Fine
**Priority:** Medium

**As a librarian, I want the system to calculate an overdue fine, so
that WPL can accurately determine the amount owed by a patron.**

### User Story-10 - Add Collection Item
**Priority:** Low

**As a librarian, I want to add an item to WPL's collection, so that
the library can maintain accurate records of its holdings.**
