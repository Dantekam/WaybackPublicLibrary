# Sprint 2 – Revised User Stories and Acceptance Criteria

## Overview

These user stories outline the proposed functionality for Sprint 2 of the Wayback Public Library system. They build on the checkout and collection management functionality implemented during Sprint 1, with a focus on completing the library's check-in process.

The selected stories will guide the development of activity diagrams, updates to the conceptual and relational data models, wireframes, implementation, and testing.

Scope and priorities may be adjusted based on team capacity, technical dependencies, and continued client feedback.

## Definition of Done

For a Sprint 2 user story to be considered complete, the team must have:

1. A clearly defined user story with acceptance criteria.
2. An activity diagram representing the relevant process.
3. A conceptual data model supporting the required information.
4. An updated relational model when database changes are necessary.
5. Wireframes or mockups showing the relevant user interface.
6. Working functionality implemented and integrated into the system on the ADA server.
7. Testing completed to verify that the functionality satisfies the acceptance criteria.
8. A demonstration of the completed functionality and supporting models.
9. Updated GitHub tasks and documentation reflecting the completed work.

A use case narrative may also be included when additional detail is needed.

Existing models may be reused or updated where appropriate rather than recreated for every user story.

---

## User Story-05 – Record Item Return

**Priority:** High

**User Story:**

As a librarian, I want to check in a returned item so that the patron is no longer responsible for the borrowed item and WPL can accurately track the return.

### Acceptance Criteria

1. The librarian can identify a returned item using its item-copy identifier, representing a successful scan.
2. The system locates the active checkout record associated with the identified item copy.
3. The system displays relevant item and checkout information, including the item, patron, and due date.
4. The librarian can record the branch where the item was returned.
5. The system records the return date and return branch associated with the checkout.
6. The system updates the active checkout so that the item is no longer counted among the patron's currently borrowed items.
7. The system confirms that the return was successfully recorded.
8. The returned item remains unavailable for checkout until it has been routed and placed back into an available state.
9. The system prevents duplicate returns and provides an appropriate message when no active checkout exists for the identified item.

### Client Clarifications

- Items may be returned to a branch other than their home branch.
- Returned items must be removed from the patron's active checkouts.
- A returned item is not considered available until it is back on the shelf.
- WPL needs to record when and where an item was returned.

### Sprint 2 Notes

This story establishes the basic check-in functionality and serves as a dependency for routing returned items.

---

## User Story-06 – Route Returned Item

**Priority:** High (revised from Medium)

**User Story:**

As a librarian, I want the system to determine where a returned item should go so that it can be routed to the appropriate location after check-in.

### Acceptance Criteria

1. The system identifies the returned item's home branch.
2. The system compares the item's home branch with the branch where the item was returned.
3. If the item is returned to its home branch, the system identifies it for placement on the reshelving cart.
4. If the item is returned to a different branch, the system identifies it for placement in the return truck bin for transport to its home branch.
5. The system displays the appropriate routing instruction to the librarian.
6. The system maintains an unavailable status while the item is awaiting reshelving or transport.
7. The system retains the routing information needed to distinguish items awaiting reshelving from those awaiting transport.
8. An item can be marked available only after the appropriate receiving or reshelving step has been completed.

### Client Clarifications

- Items returned to their home branch are placed on a reshelving cart.
- Items returned to another branch are placed in a return truck bin.
- The return truck transports items between branches daily.
- Returned items remain unavailable until they are back on the shelf.

### Sprint 2 Notes

This story extends US-05 and depends on accurate return branch and home branch information.

The interface should provide a clear routing instruction after a successful return.

---

## User Story-07 – Handle Unscannable Return

**Priority:** Medium

**User Story:**

As a librarian, I want to record that a returned item could not be scanned and has been placed in the error bin so that it can be identified and processed later.

### Acceptance Criteria

1. The librarian can indicate that a returned item could not be scanned or identified through the normal check-in process.
2. The system allows the librarian to record an unresolved return without requiring a known item-copy identifier.
3. The system records the date and branch where the unscannable item was received.
4. The system records that the item has been placed in the error bin for later handling.
5. The system allows the librarian to provide additional identifying information or comments when available.
6. Unresolved error-bin items are not treated as successfully checked in or made available for checkout.
7. The system allows staff to review unresolved error-bin records during the manual identification process.
8. Once the item is identified, the librarian can associate the error-bin record with the correct item copy and complete the normal check-in process without creating a duplicate return.

### Client Clarifications

- An item may be unscannable because it was damaged.
- Unscannable items are placed in an error bin.
- Librarians manually identify and process items in the error bin twice daily.
- Items in the error bin must not be treated as available for checkout.

### Sprint 2 Notes

This story represents an exception to the normal check-in process.

Because the item may initially be unidentified, the team must determine how to store an error-bin record without requiring an existing item-copy identifier.

The team should also distinguish between recording an unresolved physical return and completing the item's actual check-in.

---

## Additional Business Requirements States From Client

## User Story-11 - Renewal of Items

**Priority:** High

**User Story:**

As a librarian, I want to renew an eligible patron's currently checked-out item so that the patron can keep the item for an additional loan period without incurring an overdue fine during the extended period.

### Acceptance Criteria

1. The librarian can indicate that a returned item copy has been renewed to the same patron bringing in the item they'd like renewed.
2. The system allows the librarian to record the renewed item and automatically calculates the new due date based on when it was renwed.
3. The system tracks how many times the item copy has been renewed by the patron that has the item copy checked out.
4. The librarian denies renewal of the item copy if that item has been checked out by the same patron 3 times (initial + 2 renewal periods).

### Client Clarifications

- Renewed items will reset the due date for the duration defaulted by item type
- The item can be renewed two times (three times total, has to be brought back to make a new transaction)

### Sprint 2 Notes

This story represents a new process defined by a clients updated business requirements.

The team should be able to implement this system with the working process and proper error handling.

---

## User Story-12 - Reservation of Items

**Priority:** High

**User Story:**

As a librarian, I want to record that an Item copy has been reserved so that it can be properly taken off shelf and placed on hold so the proper patron may come in and check it our when ready.

### Acceptance Criteria

1. The librarian can update an items status to be reserved so the item copy is unable to be checked out by other patrons.
2. The system keeps record of the date at which an item copy was placed on hold.
3. The librarian can allow the patron to come in within the 5 day period to check out their reserved item if eligible.
4. The system notifies when the 5 day period is up in case of the patron not checking it out within the allotted time.
5. The librarian is able to place any item past the allotted 5 day reserve period back on shelf for public checkout.

### Client Clarifications

- Reserved for 5 days before taken off hold and the item copy is placed back on the shelf.

### Sprint 2 Notes

This story represents a new process defined by a clients updated business requirements.

The team should be able to implement this system with the working process and proper error handling.

---

## Proposed Additional Scope – US-09: Process Overdue Fine

**Priority:** High for fine calculation during check-in; remaining fine-management functionality to be refined separately.

**User Story:**

As a librarian, I want the system to calculate any overdue fine when an item is returned so that WPL can accurately determine the amount owed by the patron.

### Proposed Acceptance Criteria

1. The system compares the item's actual return date with its recorded due date.
2. If the item is returned on or before its due date, no overdue fine is assessed.
3. If the item is returned after its due date, the system calculates the number of overdue days.
4. The system calculates the fine using the applicable daily fine rate for the item's type.
5. The system records the calculated fine against the appropriate patron or checkout record.
6. The system displays the calculated fine when the return is processed.
7. The system prevents the same return from generating duplicate fine charges.

### Client Clarifications

- Overdue fines are calculated using the per-day rate for the item's type.
- Overdue fines continue accumulating without a maximum amount.
- Patrons with outstanding fines cannot check out additional items.

### Sprint 2 Notes

The WPL case narrative explicitly identifies automatic fine calculation as part of the improved check-in process.

The team must clarify the applicable daily fine rates and how assessed fines will be stored before implementation.

This refinement focuses on calculating and recording fines during check-in. Fine notices, cash payment processing, and broader fine management can remain separate backlog work.

---

## Supporting Requirements and Dependencies

The Sprint 2 check-in functionality depends on the checkout and collection management features developed during Sprint 1.

The team will review the existing data model to ensure it can support:

- Item-copy identification and active checkout lookup
- Return dates and return branches
- Home branch identification
- Reshelving and interbranch transport states
- Error-bin records for unidentified items
- Item availability updates
- Overdue fine calculation and recording, if selected

The team will update the conceptual data model and relational model where necessary before implementing database changes.

The existing PHP, JavaScript, and MySQL application will be reused and expanded rather than rebuilt.

## Requirements for Later Sprints

The following functionality remains part of the overall product backlog but is not automatically included in Sprint 2:

- US-08 – Renew Patron Membership
- Remaining US-09 functionality, including fine payment or notices
- Any incomplete collection management functionality carried forward from Sprint 1 which may evolve into their own user story
- Additional item receiving, reshelving, and availability workflows not completed during Sprint 2

These requirements will be reviewed and prioritized based on client feedback and remaining project capacity.

## General Notes

The following changes are proposed for Sprint 2:

1. **US-05:** Expanded acceptance criteria to clarify active checkout lookup, return branch recording, duplicate return prevention, and item availability.
2. **US-06:** Raised priority from Medium to High because routing is an essential continuation of the check-in process.
3. **US-07:** Expanded acceptance criteria to distinguish unresolved unscannable returns from completed check-ins and support later manual identification.
4. **US-09:** Proposed including overdue fine calculation during check-in, while leaving broader fine management for a later sprint.

## Planning Notes

The requirements in this document represent the proposed Sprint 2 scope rather than a fixed commitment.

The team will select stories based on priority, effort, dependencies, and available capacity.

A user story will be considered complete only when its requirements, supporting models, working implementation, and acceptance tests satisfy the project's Definition of Done.
