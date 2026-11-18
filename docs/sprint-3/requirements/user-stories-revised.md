# Sprint 3 – User Stories and Acceptance Criteria

## 1. Sprint Overview

Sprint 3 is the final development sprint for the Wayback Public Library (WPL) information system.

Building on functionality implemented during previous sprints, the team will focus on completing essential patron-management and fine-management functionality while introducing improved search, administrative reporting, and community room reservations.

In addition to the new development work, the team will continue stabilizing existing circulation, reservation, item creation, and routing workflows.

The client has emphasized a preference for a smaller number of complete, thoroughly tested features rather than a larger number of partially implemented features.

### Sprint Goal

Complete essential patron membership and fine-management functionality, introduce improved search and administrative reporting, and support community room reservations while improving the reliability and usability of existing WPL workflows.

### Committed User Stories

- US-08 – Renew Patron Membership
- US-09 – Assess and Manage Overdue Fines
- US-14 – Search Library Items and Patrons
- US-15 – View Administrative Dashboard and Reports
- US-16 – Reserve Community Room

### Existing Functionality Requiring Stabilization

The team will also review and improve existing functionality developed during previous sprints, particularly:

- Checkout eligibility and enforcement of the 20-item limit.
- Check-in and physical item routing.
- Reservation processing and pickup workflows.
- Item status management and bulk actions.
- Catalog item creation and branch assignment.
- Item checkout renewals and due-date calculations.

These activities will be tracked separately in the Sprint 3 stabilization and testing document.

### Deferred Functionality

The following client-requested enhancements are not included in the Sprint 3 implementation commitment:

- US-17 – Patron Ratings and Reviews
- US-18 – AI-Based Item Recommendations
- US-19 – Community Purchase Suggestions

These remain potential future enhancements.

---

# 2. Sprint 3 User Stories

## US-08 – Renew Patron Membership

**Priority:** High

### User Story

As a librarian, I want to renew a patron's library membership so that patrons with expired or expiring memberships can continue borrowing library materials.

### Acceptance Criteria

1. The librarian can locate an existing patron using their patron ID or available patron search functionality.
2. The system displays the patron's current membership expiration date.
3. The librarian can initiate membership renewal for an existing patron.
4. The librarian can review the patron's identifying information before confirming renewal.
5. The system calculates the new membership expiration date as two years from the current renewal date.
6. Renewing a membership early does not compound the renewal period onto the previous expiration date.
7. The system updates the existing patron record without creating a duplicate.
8. The system displays a confirmation containing the updated membership expiration date.
9. If checkout is denied because of an expired membership, the librarian can access the membership renewal process.
10. After successful renewal, the patron's updated membership expiration date is recognized during checkout eligibility verification.
11. Invalid patron identifiers and unsuccessful renewal attempts produce clear error messages.

### Business Rules

- Library memberships must be renewed every two years.
- Renewals are calculated from the date of renewal rather than the previous expiration date.
- Patrons with expired memberships cannot check out additional items until their memberships are renewed.
- Membership renewal does not automatically override other checkout restrictions, including outstanding fines or the 20-item borrowing limit.

### Supporting Models

- Membership renewal activity diagram.
- Membership renewal interface wireframe.
- Existing Patron entity and checkout eligibility relationships.

---

## US-09 – Assess and Manage Overdue Fines

**Priority:** High

### User Story

As a librarian, I want to assess overdue fines, review patron balances, and record payments so that WPL can accurately manage outstanding fines and enforce borrowing restrictions.

### Acceptance Criteria

1. The system compares the item's return date with its recorded due date when a checkout is completed.
2. An item returned on or before its due date does not generate an overdue fine.
3. For a late return, the system calculates the number of overdue days.
4. The system calculates the overdue fine using the daily fine rate associated with the item's type.
5. The system records the assessed fine against the appropriate patron and checkout.
6. The system prevents duplicate assessment of the same overdue charge.
7. When an overdue renewal is attempted, the system identifies the applicable overdue fine and denies the renewal without creating duplicate charges.
8. The librarian can view a patron's individual fines, total assessed amount, payments, and remaining outstanding balance.
9. The librarian can select an outstanding fine and enter a payment amount.
10. The system accepts valid payments that do not exceed the remaining balance.
11. The system rejects invalid, negative, zero, or excessive payment amounts.
12. Successful payments reduce the outstanding balance and preserve a record of the payment.
13. The system identifies fines as resolved when their outstanding balances reach zero.
14. Checkout eligibility reflects the patron's current outstanding fine balance.
15. The system displays clear confirmation or error messages following fine assessments and payment attempts.

### Business Rules

| Item Type | Loan Duration | Daily Overdue Fine |
|---|---|---|
| Book | 14 days | $0.25 |
| Movie | 7 days | $1.00 |
| Periodical | 1 day | $0.50 |

- Overdue fines accumulate according to the number of overdue days.
- Patrons with outstanding fines cannot complete additional checkouts.
- Multiple fine records may be associated with a checkout.
- Fine assessment must not create duplicate charges for the same overdue period.
- Payment processing must not result in negative outstanding balances.

### Supporting Models

- Fine assessment and payment activity diagram.
- Fine management interface wireframe.
- Existing Fine, Patron, CheckOut, and Item_Type entities.
- Proposed FinePayment entity for recording individual payment transactions.

---

## US-14 – Search Library Items and Patrons

**Priority:** High

### User Story

As a librarian, I want to search for library items and patrons using partial information and relevant filters so that I can quickly locate records without needing exact identifiers.

### Acceptance Criteria

1. The librarian can search catalog items by title, author, or item identifier.
2. The librarian can search patrons by first name, last name, or patron identifier.
3. Searches support partial text matches rather than requiring exact names or titles.
4. The system provides useful approximate matches or suggestions when a search contains a minor spelling mistake.
5. Item search results display identifying information, item type, and relevant availability information.
6. The librarian can view the individual copies associated with a catalog item and their current availability.
7. Patron search results display sufficient identifying information to distinguish between matching patrons.
8. The librarian can access a selected patron's current checkouts, due dates, and outstanding fines.
9. Search results support relevant filtering and sorting.
10. The system displays an appropriate message when no records match the search criteria.
11. Search operations do not modify item, patron, checkout, or fine records.

### Business Rules

- Item and patron searches are the primary search priorities.
- Search should support partial information and minor spelling variations.
- Relevant filtering and sorting should be available.
- Search must remain read-only.
- Full conversational NLP search is not required for the initial implementation.

### Supporting Models

- Search activity diagram.
- Item and patron search wireframe.
- Existing ITEM, Item_Copy, Author, Item_Author, Patron, CheckOut, Fine, and Item_Type relationships.

---

## US-15 – View Administrative Dashboard and Reports

**Priority:** High

### User Story

As a library administrator, I want to view library activity, circulation trends, collection information, and financial summaries so that I can monitor operations and identify issues requiring attention.

### Acceptance Criteria

1. The dashboard displays checkout transaction counts over time.
2. The dashboard displays current patron and catalog or inventory totals, clearly distinguishing the measures shown.
3. The dashboard displays assessed and outstanding fine amounts.
4. The dashboard displays available collection acquisition-cost information.
5. The system supports viewing operational trends by week.
6. The dashboard supports selecting a date range.
7. Where supported by existing data, the dashboard allows filtering by library branch and item type.
8. The dashboard includes a report identifying currently overdue items, their associated patrons, and due dates.
9. The dashboard includes a report identifying books and movies due within the next three days.
10. Staff can review overdue items and relevant outstanding fine amounts.
11. Dashboard figures are calculated from WPL's recorded data.
12. The dashboard handles empty results and missing information without displaying misleading totals.
13. The dashboard clearly distinguishes assessed fines from payments received and outstanding balances.

### Business Rules

- The dashboard should support at least weekly trend reporting.
- Library-wide reporting is the minimum requirement.
- Branch-specific reporting is desirable where the data supports it.
- The dashboard should focus on circulation, patrons, collection items, acquisition costs, and fines.
- Overdue reports are intended for staff review.
- Automated email or SMS delivery is not required.
- WPL does not currently maintain complete organizational accounting records, so financial reporting must be limited to available data.

### Supporting Models

- Dashboard and reporting activity diagram.
- Administrative dashboard wireframe.
- Existing CheckOut, Patron, ITEM, Item_Copy, Item_Type, Fine, and Branch relationships.

---

## US-16 – Reserve Community Room

**Priority:** Medium

### User Story

As a librarian, I want to reserve a library community room on behalf of an eligible patron so that community events can be scheduled without conflicting reservations.

### Acceptance Criteria

1. The librarian can select a library branch and view its community room.
2. The librarian can identify the registered patron responsible for the reservation.
3. The system verifies that the patron has a current library membership.
4. The librarian can enter the event title, reservation date, start time, end time, and expected number of attendees.
5. The system requires a valid start time earlier than the end time.
6. The system prevents reservations with more than 15 attendees.
7. The system prevents reservations outside the room's operating hours.
8. The system checks existing reservations to prevent overlapping active bookings for the same room.
9. The system allows different rooms to be reserved during overlapping time periods.
10. A valid reservation is confirmed when entered by the librarian without requiring a separate approval process.
11. The system records the responsible patron, room, event information, reservation times, and reservation status.
12. The librarian can view existing reservations for a selected room or date.
13. The librarian can cancel an existing reservation.
14. Canceled reservations no longer block the room's availability.
15. The system displays clear confirmation and error messages for reservation actions.

### Business Rules

- Each WPL branch may initially be assumed to have one reservable community room.
- A registered patron with a current membership must be responsible for each reservation.
- The maximum number of attendees is 15.
- Reservations must occur during library operating hours.
- Reservations may last an entire operating day.
- Staff enter reservations directly; no additional approval workflow is required.
- Overlapping active reservations for the same room are not permitted.
- Cancellations must be supported.
- Recurring reservations are not required for the initial implementation.

### Supporting Models

- Community room reservation activity diagram.
- Room reservation interface wireframe.
- New Room and RoomReservation entities.
- Existing Branch and Patron entities.

---

## US-20 – View and Manage Patron Library Card

**Priority:** High

### User Story

As a librarian, I want to view and manage a patron's library account through a centralized library card interface so that I can efficiently review their membership, active checkouts, reservations, and outstanding fines without navigating between multiple pages.

### Acceptance Criteria

1. The librarian can locate a patron using their patron ID or the existing patron search functionality.
2. The system displays the patron's name, patron ID, home branch, email, and membership expiration date.
3. The system clearly identifies whether the patron's membership is active or expired.
4. The system displays the number of currently checked-out items relative to the 20-item borrowing limit.
5. The librarian can view the patron's active checkouts, including item titles, due dates, and renewal counts.
6. The librarian can access the existing checkout renewal functionality for eligible items.
7. The system displays the patron's outstanding fine balance and individual fine records.
8. The librarian can record fine payments through the existing fine-management functionality, and the displayed balance updates accordingly.
9. The librarian can view the patron's active item reservations and their current statuses.
10. The librarian can initiate membership renewal through the existing membership renewal functionality.
11. Following a successful membership renewal, the updated expiration date and membership status are displayed.
12. The system displays accurate patron information retrieved from the existing database records.
13. The interface clearly separates membership, checkouts, fines, and reservations into organized sections.
14. Invalid patron identifiers or unavailable records produce appropriate error messages.

### Business Rules

- Each patron has one library membership record.
- Library memberships are valid for two years and renew from the current date.
- Patrons may have no more than 20 active checkouts.
- Outstanding fines and expired memberships restrict checkout eligibility.
- Item renewal limits and due dates remain governed by existing circulation rules.
- Patron balances are calculated from assessed fines and recorded payments.
- The library card interface must not create duplicate patron, checkout, reservation, or fine records.

### Supporting Models

- Patron Library Card wireframe.
- Patron account management activity diagram.
- Existing Patron, CheckOut, Reservation, Fine, FinePayment, Branch, and Item_Copy entities.

---

# 3. Database and Model Considerations

Sprint 3 will build on the existing WPL relational data model.

### Existing Entities

The team will reuse existing entities including:

- Patron
- Branch
- ITEM
- Item_Type
- Item_Copy
- CheckOut
- Fine
- Reservation
- Author
- Item_Author
- Error_Bin_Entry

### Proposed Additions

**FinePayment**

Supports the recording of individual fine payments and provides an auditable payment history.

**Room**

Represents a reservable community room associated with a library branch.

**RoomReservation**

Represents a scheduled community event reservation associated with a room and responsible patron.

### Existing Entity Updates

- Verify that Patron.MembershipExpiration supports membership renewal.
- Verify that Item_Copy includes the destination branch required by the existing routing workflow.
- Review Fine attributes to ensure accurate outstanding balance calculations.
- Consider whether additional date attributes are required for historical dashboard metrics.

Search and dashboard functionality should reuse existing tables wherever possible.

---

# 4. Existing Functionality Stabilization

The following previously developed functionality will be reviewed and improved as part of Sprint 3 quality assurance:

- Checkout eligibility and the 20-item borrowing limit.
- Item check-in and return processing.
- Item routing between branches.
- Item status transitions and bulk inventory updates.
- Reservation creation, pickup processing, and expiration.
- Catalog item creation and initial branch assignment.
- Item checkout renewals and loan-period calculations.

These activities will be documented in `stabilization-and-testing.md`.

The stabilization document will identify the relevant original user story, reported issue, expected behavior, correction, and verification results.

Where a workflow changes materially, its existing activity diagram, wireframe, and acceptance criteria must also be updated.

---

# 5. Definition of Done

A committed Sprint 3 user story is complete when:

1. Its acceptance criteria are satisfied.
2. Required activity diagrams and wireframes are available.
3. The conceptual and relational data models reflect any necessary changes.
4. Functionality is implemented and integrated into the WPL system.
5. Successful, unsuccessful, and relevant edge-case scenarios have been tested.
6. The corresponding documentation matches the implemented workflow.
7. Test results are recorded.
8. The functionality can be demonstrated during the final sprint review.
9. The GitHub task board and sprint backlog reflect its actual completion status.
