# Sprint 3 – Revised User Stories and Acceptance Criteria

## 1. Overview

These user stories outline the proposed functionality for Sprint 3, the final development sprint of the Wayback Public Library (WPL) information system.

Sprint 3 builds upon the circulation, collection management, patron management, renewal, reservation, and item status functionality developed during previous sprints. The primary objective is to improve the reliability and completeness of existing workflows while implementing a manageable selection of the new features requested by the client.

Based on feedback from the Sprint 2 review, the team will place greater emphasis on ensuring that user stories, acceptance criteria, activity diagrams, data models, wireframes, implementation, and testing remain consistent with one another.

The client has also expressed a preference for delivering a smaller number of fully functional features rather than partially implementing numerous features.

### Proposed Sprint Goal

Deliver a reliable and integrated WPL information system that completes essential circulation and patron-management workflows, improves administrative search and reporting, and demonstrates functionality through clearly documented and testable acceptance criteria.

### Sprint 3 Priorities

The proposed priorities are:

1. **High Priority:** Correct and complete existing circulation, reservation, routing, membership, and fine-management functionality.
2. **High Priority:** Improve administrative search and reporting capabilities.
3. **Medium Priority:** Implement additional functionality such as room reservations if development capacity permits.
4. **Lower Priority:** Consider ratings and reviews, AI-based recommendations, and community purchase suggestions as additional backlog items.

The priorities represent the team's initial planning recommendations and are subject to available development capacity and continued client feedback.

Not every story documented below is automatically committed to Sprint 3.

---

## 2. Definition of Done

For a Sprint 3 user story to be considered complete, the team must have:

1. A clearly defined user story with testable acceptance criteria.
2. An activity diagram representing the relevant workflow, where applicable.
3. An updated conceptual data model supporting the required information.
4. An updated relational data model when database changes are necessary.
5. Wireframes or mockups representing the relevant user interface.
6. Working functionality implemented and integrated into the WPL system on the ADA server.
7. Testing completed to verify that the functionality satisfies the acceptance criteria, including relevant unsuccessful and edge-case scenarios.
8. A demonstration of the completed functionality and its supporting models.
9. Updated GitHub tasks and documentation reflecting completed work and outstanding issues.
10. Consistency between the documented requirements, models, implemented functionality, and demonstrated behavior.

Existing diagrams, models, and wireframes may be revised and reused rather than recreated unnecessarily.

The team will seek client feedback on proposed workflows and interfaces before implementing complex or substantially revised functionality.

---

# 3. Existing User Stories Requiring Completion or Refinement

The following stories originated in previous sprints. They are retained under their existing identifiers to maintain traceability.

## User Story-02 – Verify Checkout Eligibility (Validation and Refinement)

**Priority:** High

**User Story:**

As a librarian, I want the system to verify a patron's checkout eligibility so that patrons with expired memberships, outstanding fines, or the maximum number of borrowed items cannot check out additional materials.

### Acceptance Criteria

1. The system identifies the patron and retrieves their current membership information.
2. The system determines whether the patron's library membership has expired.
3. If the membership is expired, the system prevents checkout and clearly identifies the reason.
4. The system provides a way for the librarian to access the membership renewal process.
5. The system checks whether the patron has any outstanding fines.
6. Patrons with outstanding fines cannot complete a new checkout until the applicable balance is resolved.
7. The system counts the patron's currently active checkouts.
8. A patron with 20 active checkouts cannot borrow another item.
9. A patron with fewer than 20 active checkouts may proceed if all other eligibility requirements are satisfied.
10. The system displays an appropriate explanation when checkout is denied.

### Client Clarifications

- Library memberships must be renewed every two years.
- Patrons with expired memberships cannot complete checkouts.
- Patrons with outstanding fines cannot complete additional checkouts.
- Patrons may have a maximum of 20 items checked out simultaneously.
- Membership renewal should be accessible during the checkout process when an expired membership is identified.

### Sprint 3 Notes

This story focuses on validating and completing existing checkout eligibility functionality rather than creating an entirely new checkout workflow.

The team should demonstrate expired-membership handling, outstanding-fine restrictions, and enforcement of the 20-item limit.

**Supporting Models:** Updated checkout eligibility activity diagram, membership renewal wireframe, and existing Patron, Fine, and CheckOut relationships.

---

## User Story-05 – Record Item Return (Validation and Refinement)

**Priority:** High

**User Story:**

As a librarian, I want to record the return of a borrowed item so that the patron's checkout is closed and the item can begin the appropriate return-processing workflow.

### Acceptance Criteria

1. The librarian can identify a returned item using its item-copy identifier.
2. The system retrieves the active checkout associated with the item.
3. The system displays the item, patron, due date, and relevant checkout information.
4. The librarian can select the branch where the item was returned.
5. The system records the return date and return branch.
6. The active checkout is closed and no longer counts toward the patron's 20-item borrowing limit.
7. The system calculates and records applicable overdue fines.
8. The system determines whether an active reservation affects the item's next destination.
9. The system assigns an appropriate unavailable processing status after check-in.
10. The system provides clear instructions for the item's next physical handling step.
11. The system prevents duplicate check-ins and provides an appropriate message when no active checkout exists.
12. A returned item cannot become available for checkout until the required receiving and reshelving steps are completed.

### Client Clarifications

- Items may be returned to branches other than their home branches.
- Returns must record when and where the item was received.
- An item is not available for checkout merely because its return was recorded.
- Returned items with active reservations must be handled according to their reservation requirements.

### Sprint 3 Notes

The existing successful check-in workflow should be preserved. The team should focus on correcting inconsistencies between check-in, routing, reservations, and item availability.

**Supporting Models:** Revised check-in activity diagram, check-in wireframe, and existing CheckOut, Item_Copy, Branch, Fine, and Reservation relationships.

---

## User Story-06 – Route Returned Item (Validation and Refinement)

**Priority:** High

**User Story:**

As a librarian, I want the system to determine the appropriate destination for a returned item so that it can be transported, reserved, or reshelved correctly.

### Acceptance Criteria

1. The system identifies the returned item's home branch and return branch.
2. The system checks whether the item has an active reservation before determining its destination.
3. If an unreserved item is returned to its home branch, the system assigns an awaiting-reshelving status.
4. If an unreserved item is returned to another branch, the system identifies its home branch as the transport destination.
5. If a reserved item requires pickup at another branch, the system identifies the reservation pickup branch as its destination.
6. The system displays the destination and appropriate handling instructions to the librarian.
7. Items awaiting transport remain unavailable for checkout.
8. Staff can record that an item has been sent for transport.
9. Staff can confirm receipt of an item at its destination branch.
10. The system updates the item's current branch when its arrival is confirmed.
11. Items awaiting reshelving remain unavailable until staff confirm they have been shelved.
12. The system prevents invalid routing transitions and provides clear explanations when an action cannot be completed.

### Client Clarifications

- Items returned to their home branch should be placed on the reshelving cart.
- Items returned elsewhere should be transported to the appropriate destination.
- Active reservations must be considered before determining the final destination.
- Detailed shipment tracking is not required.
- The client has encouraged simplifying the routing workflow where possible.

### Sprint 3 Notes

This story requires particular attention because the Sprint 2 review identified difficulties moving items from check-in to Available.

The team should simplify the routing process where appropriate, verify that all required transitions are possible, and ensure that the activity diagram accurately represents the implemented workflow.

**Supporting Models:** Revised return-routing activity diagram, item status management wireframe, and existing Item_Copy, Branch, and Reservation relationships.

---

## User Story-08 – Renew Patron Membership

**Priority:** High

**User Story:**

As a librarian, I want to renew an expired or expiring patron's library membership so that the patron can continue borrowing items from WPL.

### Acceptance Criteria

1. The librarian can identify an existing patron using their patron identifier or available search functionality.
2. The system displays the patron's identifying information and current membership expiration date.
3. The librarian can initiate a membership renewal.
4. The system allows the librarian to review relevant patron information before confirming renewal.
5. The system calculates the new membership expiration date as two years from the current renewal date.
6. The renewal period does not compound onto the previous membership expiration date.
7. The system updates the patron's membership expiration date after successful renewal.
8. The system displays the updated expiration date and confirms that the renewal was completed.
9. An expired membership can be renewed without creating a duplicate patron record.
10. The system provides an appropriate error message when the patron cannot be found or the renewal cannot be completed.
11. The updated membership status is immediately reflected in checkout eligibility checks.

### Client Clarifications

- Library memberships must be renewed every two years.
- Renewal periods are calculated from the date the membership is renewed.
- Patrons with expired memberships cannot complete new checkouts until their membership is renewed.
- Staff should be able to address expired memberships during checkout.

### Sprint 3 Notes

This story was identified in the original product backlog but has not been fully completed.

Membership renewal should be implemented as a distinct process that integrates with patron identification and checkout eligibility.

**Supporting Models:** Membership renewal activity diagram, membership renewal wireframe, and updated Patron relational model if additional attributes are necessary.

---

## User Story-09 – Assess and Manage Overdue Fines

**Priority:** High

**User Story:**

As a librarian, I want the system to assess overdue fines and record payments so that WPL can accurately track outstanding balances and determine patron borrowing eligibility.

### Acceptance Criteria

1. The system compares an item's actual return date with its recorded due date.
2. If an item is returned on or before its due date, no overdue fine is assessed.
3. If an item is returned after its due date, the system calculates the number of overdue days.
4. The system calculates the fine using the daily rate associated with the item's type.
5. The system records the assessed fine against the appropriate patron and checkout.
6. The system prevents duplicate assessment of the same overdue charge.
7. During an overdue renewal attempt, the system identifies and assesses any applicable overdue fine while denying the renewal.
8. The librarian can view a patron's individual fines, total assessed amount, amount paid, and remaining balance.
9. The librarian can record a payment toward an outstanding fine.
10. The system updates the remaining balance after a successful payment.
11. The system prevents payments from exceeding the outstanding amount or producing a negative balance.
12. The system records sufficient information to identify a completed payment.
13. A fine is considered resolved when its outstanding balance reaches zero.
14. Checkout eligibility reflects the patron's remaining outstanding fines.
15. The system confirms successful fine assessments and payments and displays appropriate messages when an operation fails.

### Client Clarifications

- Books incur a fine of $0.25 per overdue day.
- Movies incur a fine of $1.00 per overdue day.
- Periodicals incur a fine of $0.50 per overdue day.
- Fines continue accumulating without a predefined maximum.
- Multiple separate fine records may be associated with a checkout.
- Patrons with outstanding fines cannot complete new checkouts.
- Fines are normally assessed during return processing, although overdue renewal attempts must also be considered.

### Sprint 3 Notes

The fine-assessment portion of this story may already be partially implemented.

Sprint 3 should focus on validating fine calculations, preventing duplicate charges, displaying outstanding balances, and completing payment functionality.

A separate FinePayment entity may be appropriate if the team needs to preserve individual payment transactions. This decision should be reflected in the relational model before implementation.

**Supporting Models:** Fine assessment/payment activity diagram, fine management wireframe, and Fine, Patron, CheckOut, Item_Type, and any approved payment relationships.

---

## User Story-10 – Add Library Item to Collection (Validation and Refinement)

**Priority:** High

**User Story:**

As a head librarian, I want to add catalog items and individual copies to WPL's collection so that newly acquired materials can be tracked and assigned to the appropriate library branch.

### Acceptance Criteria

1. The librarian can search for an existing catalog item before creating a new item definition.
2. If the item definition already exists, the librarian can add another physical copy without duplicating the catalog record.
3. If the item definition does not exist, the librarian can create one using the required catalog information.
4. The system associates each catalog item with an existing, valid item type.
5. The system records individual physical copies separately from their catalog item definitions.
6. The acquisition date defaults to the current date.
7. Newly received copies default to the Central Library branch.
8. The librarian can assign a copy to another destination branch.
9. Copies assigned to another branch remain unavailable until they are physically received and processed.
10. The system retains the copy's assigned branch and current location information.
11. Staff can confirm receipt of a transferred copy and assign its shelf location.
12. The item becomes available only after the required receiving and shelving steps are completed.
13. The system prevents invalid or incomplete item records from being saved.
14. The system confirms successful item creation and provides clear error messages when an operation fails.

### Client Clarifications

- New items normally arrive at the Central Library.
- Staff must check whether the catalog item already exists before creating a new definition.
- Individual copies are recorded separately from catalog definitions.
- Copies assigned to another branch must remain unavailable until received.
- New item types require a separate administrative process before items of that type can be added.

### Sprint 3 Notes

This story requires refinement because item creation and initial branch routing have shown inconsistencies during testing.

The team should ensure that item types are linked consistently and that creating a copy does not incorrectly mark an item available at a branch where it has not yet arrived.

**Supporting Models:** Revised item creation activity diagram, item creation wireframe, and ITEM, Item_Type, Item_Copy, Author, Item_Author, and Branch relationships.

---

## User Story-11 – Renew Item Checkout (Validation)

**Priority:** High

**User Story:**

As a librarian, I want to renew an eligible patron's existing checkout so that the patron can retain the item for another loan period without creating a new checkout transaction.

### Acceptance Criteria

1. The librarian can identify an active checkout for renewal.
2. The system displays the current due date and number of previous renewals.
3. The system verifies that the item is not overdue.
4. The system verifies that no active reservation prevents renewal.
5. The system allows no more than two renewals during one continuous checkout.
6. The renewed due date is calculated using the current renewal date and the loan duration for the item's type.
7. Renewals do not compound by adding loan periods to the existing due date.
8. An early renewal does not shorten the existing loan period.
9. The system increments the renewal count after each successful renewal.
10. The existing checkout remains active and the item remains CheckedOut.
11. The system denies a third renewal and provides a clear explanation.
12. The system displays the resulting due date and renewal count after a successful renewal.

### Client Clarifications

- Books have a 14-day loan period.
- Movies have a 7-day loan period.
- Periodicals have a 1-day loan period.
- Renewal dates are calculated from the date of renewal rather than the previous due date.
- An item may be renewed twice after its initial checkout.
- Overdue items and items with active reservations cannot be renewed.

### Sprint 3 Notes

The incorrect compounding renewal calculation was corrected following Sprint 2 feedback.

This story primarily requires regression testing and a demonstration that renewals use the correct loan period without compounding.

**Supporting Models:** Existing renewal activity diagram and wireframe, updated as necessary to match the implemented behavior.

---

## User Story-12 – Reserve Library Item (Validation and Refinement)

**Priority:** High

**User Story:**

As a librarian, I want to reserve an item for a patron so that the item can be held and prepared for pickup at the appropriate branch.

### Acceptance Criteria

1. The librarian can identify an eligible patron and a specific item copy.
2. The system allows a reservation to be created for an available or currently checked-out copy.
3. The system records the patron, item copy, reservation date, and pickup branch.
4. The system prevents conflicting active reservations for the same physical copy when reservation queues are not supported.
5. The patron's home branch is used as the default pickup branch when available, with an appropriate alternative selection when necessary.
6. The system identifies whether the reserved item is already at the pickup branch.
7. If transportation is required, the system identifies the correct pickup branch as the destination.
8. The system prevents a reserved item from entering ordinary circulation while its reservation is active.
9. Staff can confirm when the reserved item has physically arrived and been placed on the reservation shelf.
10. The five-day pickup period begins when staff mark the item ready for pickup.
11. The system records the reservation's pickup expiration date.
12. Staff can complete a reservation when the patron collects the item and the checkout eligibility requirements are satisfied.
13. Staff can cancel a reservation.
14. The system identifies expired reservations and supports releasing unclaimed items for appropriate processing.
15. The system displays clear reservation and routing information so staff can determine the next required action.

### Client Clarifications

- Patrons may reserve specific physical copies.
- Reservations may be created for items currently checked out.
- A reservation queue is desirable but not required for the initial implementation.
- The patron's home branch is the default pickup location.
- The five-day pickup period begins when the item is physically placed on the reservation shelf.
- Automated email notifications are not required for the current implementation.
- Detailed transportation tracking is not required.

### Sprint 3 Notes

The Sprint 2 review identified the reservation and routing process as difficult to understand and test.

The team should simplify the workflow in collaboration with the client, ensure that the interface clearly identifies each step, and align the activity diagram with the actual implementation.

**Supporting Models:** Revised reservation activity diagram, reservation wireframe, and Reservation, Patron, Item_Copy, Branch, and CheckOut relationships.

---

## User Story-13 – Manage Item Statuses and Routing (Validation and Refinement)

**Priority:** High

**User Story:**

As a librarian, I want to view and update item statuses individually or in groups so that library staff can efficiently manage transportation, receiving, reshelving, and item availability.

### Acceptance Criteria

1. The librarian can view an inventory list containing item-copy identifiers, titles, branches, item statuses, and routing statuses.
2. The librarian can search and filter items by relevant inventory information.
3. The system distinguishes an item's home branch, current branch, and destination branch.
4. The librarian can select one or multiple items for a supported status update.
5. The system provides only valid status transitions for the selected item's current state or clearly rejects invalid transitions.
6. Staff can identify items awaiting transportation and their destinations.
7. Staff can confirm that transported items have arrived at the destination branch.
8. Staff can identify items awaiting reshelving.
9. Staff can mark items Available after confirming they have been physically shelved.
10. Items with active reservations are not incorrectly released for ordinary checkout.
11. Checked-out, in-transit, or otherwise unavailable items cannot be marked Available without completing the required workflow.
12. The system provides clear success or failure messages for status updates.
13. The system preserves consistent item status information across check-in, reservations, and inventory management.
14. The bulk-action interface supports efficient processing without requiring staff to search for each item repeatedly.

### Client Clarifications

- Staff should be able to process groups of items, such as an incoming shipment.
- Item availability must reflect the actual physical processing state.
- Detailed shipment tracking is not necessary.
- The client values the existing Item Statuses interface and has suggested exploring its use as a foundation for administrative reporting.

### Sprint 3 Notes

The Sprint 2 review identified errors when attempting to transition checked-in items to Available.

The team should prioritize making the existing workflow reliable and understandable before introducing additional routing complexity.

**Supporting Models:** Revised item status activity diagram, inventory management wireframe, and Item_Copy, Branch, and Reservation relationships.

---

# 4. New Sprint 3 User Stories

The following stories respond to the client's newly requested functionality.

## User Story-14 – Search Library Items and Patrons

**Priority:** High

**User Story:**

As a librarian, I want to search library items and patrons using names, titles, partial information, and relevant filters so that I can quickly locate records without requiring exact identifiers.

### Acceptance Criteria

1. The librarian can search catalog items using a title, author, or item identifier.
2. The librarian can search patrons using a name or patron identifier.
3. The system returns relevant results when only part of a name or title is entered.
4. The system provides useful results or suggestions when a search contains a minor spelling mistake.
5. The system displays relevant identifying information for matching catalog items.
6. Staff can view item availability and relevant circulation information, including active checkout and due-date information when applicable.
7. Staff can view a patron's current checkouts, associated due dates, and outstanding fines.
8. The interface supports relevant filters and sorting to narrow search results.
9. The system clearly indicates when no matching records are found.
10. Search operations do not modify database records.

### Client Clarifications

- Item and patron searches are the highest priority.
- Searching should not require exact identifiers.
- Partial names, titles, and minor spelling errors should be tolerated.
- Filtering and sorting are desirable.
- Full conversational natural-language search is not required if the system provides an effective and forgiving search experience.
- Search functionality should be read-only.

### Sprint 3 Notes

This is one of the client's highest-priority new features.

The team should prioritize reliable keyword and partial-match searching before considering more complex NLP functionality.

**Supporting Models:** Search activity diagram, item/patron search wireframe, and existing ITEM, Author, Patron, CheckOut, Item_Copy, and Fine relationships.

---

## User Story-15 – View Administrative Dashboard and Reports

**Priority:** High

**User Story:**

As a library administrator, I want to view library activity, circulation trends, patron information, and financial summaries so that I can evaluate library operations and identify issues requiring attention.

### Acceptance Criteria

1. The dashboard displays the number of checkout transactions over time.
2. The dashboard displays current patron and collection totals.
3. The dashboard provides historical trends for relevant circulation, patron, and collection information where supporting dates are available.
4. The dashboard displays fine information, including assessed and outstanding amounts.
5. The dashboard displays available acquisition-cost information for library items.
6. The system supports reporting over at least weekly time periods.
7. The dashboard supports date-range filtering.
8. The dashboard supports branch and item-type filtering where the underlying records permit meaningful comparisons.
9. Staff can view overdue items and identify the associated patrons and due dates.
10. Staff can identify books and movies approaching their due dates within the three-day reminder window.
11. Staff can review overdue items and outstanding fines through an internal report.
12. Dashboard totals and summaries are generated from recorded system data.
13. The system distinguishes unavailable or incomplete financial information rather than presenting unsupported totals.

### Client Clarifications

- The dashboard should show how library activities change over time.
- Important measures include loans, patrons, items, acquisition costs, and fines.
- Reporting should support at least weekly trends.
- Library-wide reporting is the minimum expectation.
- Branch, item-type, and date filters are desirable.
- Broader organizational financial information is not currently available.
- Internal overdue and upcoming-due reports may fulfill much of the reminder functionality without automated emails.

### Sprint 3 Notes

This is one of the client's highest-priority new features.

The team should use existing database records wherever possible rather than creating unnecessary duplicate reporting tables.

The existing Item Statuses interface may provide a useful starting point for operational reporting, while historical trends may require separate dashboard components.

**Supporting Models:** Dashboard/reporting activity diagram, dashboard wireframe, and existing CheckOut, Patron, ITEM, Item_Copy, Item_Type, Fine, and Branch relationships.

---

# 5. Additional Product Backlog Stories

The following stories represent desirable functionality identified during client clarification. They should only be committed to Sprint 3 if sufficient capacity remains after essential workflows and higher-priority features are completed.

## User Story-16 – Reserve Community Room

**Priority:** Medium — Candidate for Sprint 3

**User Story:**

As a librarian, I want to reserve a community room on behalf of an eligible patron so that library events can be scheduled without conflicting reservations.

### Acceptance Criteria

1. The librarian can select a library branch and its community room.
2. The system identifies the registered patron responsible for the reservation.
3. The system verifies that the patron's membership is current.
4. The librarian can enter an event title, date, start time, end time, and expected attendance.
5. The system prevents reservations exceeding 15 attendees.
6. The system prevents reservations outside the branch's operating hours.
7. The system prevents overlapping active reservations for the same room.
8. A valid reservation can be confirmed when saved by staff without an additional approval process.
9. The librarian can view existing room reservations.
10. The librarian can cancel an existing reservation.
11. The system provides appropriate messages when a reservation conflicts with an existing booking or violates a business rule.

### Client Clarifications

- Each branch may be assumed to have one community room.
- Rooms may be reserved during library operating hours.
- Only registered patrons with current memberships may be responsible for reservations.
- A maximum of 15 attendees is permitted.
- Reservations may last an entire operating day.
- Staff enter reservations, so a separate approval process is unnecessary.
- Scheduling conflicts must be prevented.
- Cancellation must be supported.

### Sprint 3 Notes

This story requires new room and room-reservation data structures.

The team should confirm operating-hour assumptions before implementation.

**Supporting Models:** Room reservation activity diagram, reservation wireframe, and proposed Room, RoomReservation, Patron, and Branch relationships.

---

## User Story-17 – Record Patron Ratings and Reviews

**Priority:** Lower — Product Backlog Candidate

**User Story:**

As a librarian, I want to record ratings and reviews submitted by patrons so that WPL can preserve feedback about collection items and support future recommendations.

### Acceptance Criteria

1. The librarian can identify the patron submitting a review.
2. The system verifies that the patron has previously borrowed the catalog item being reviewed.
3. The librarian can select the catalog item being reviewed.
4. The system allows a rating from one to five stars.
5. The librarian can enter an optional written review.
6. Ratings and reviews are associated with the catalog item rather than a specific physical copy.
7. The system records the review and its associated patron.
8. Staff can view existing ratings and reviews.
9. Librarians can moderate inappropriate review content.
10. The system supports periodic entry of reviews collected outside the application.

### Client Clarifications

- Ratings should use a five-star scale.
- Written reviews are desirable.
- Patrons may only review items they have previously borrowed.
- Reviews should be associated with catalog items.
- Reviews should eventually be available for broader viewing.
- Librarians must be able to moderate reviews.
- Paper-based collection and periodic staff entry are acceptable.

### Sprint 3 Notes

This feature is dependent on existing patron, catalog, and checkout history.

A patron-facing portal is not required.

**Supporting Models:** Review entry activity diagram, review management wireframe, and proposed ItemReview relationships with Patron and ITEM.

---

## User Story-18 – Generate Item Recommendations

**Priority:** Lower — Product Backlog Candidate

**User Story:**

As a librarian, I want to request recommendations based on a patron's borrowing history and available preferences so that I can suggest additional items the patron may enjoy.

### Acceptance Criteria

1. The librarian can identify the patron for whom recommendations are requested.
2. The system can retrieve the patron's relevant historical borrowing information.
3. When available, the system can consider the patron's ratings and reviews.
4. The librarian can request recommendations on demand.
5. The system displays suggested items with enough identifying information to help staff discuss them with the patron.
6. The system identifies whether recommended items exist in WPL's current collection.
7. The system handles insufficient patron history or unavailable recommendation results gracefully.
8. The recommendation process does not modify the patron's borrowing records.
9. The system does not automatically create checkouts or reservations based on recommendations.

### Client Clarifications

- Recommendations should consider previous loans, ratings, and reviews.
- Recommendations may include items not currently owned by WPL.
- On-demand recommendations are acceptable.
- A librarian-operated interface is sufficient for the initial implementation.
- A patron kiosk may be considered in the future.

### Sprint 3 Notes

This story has significant technical dependencies and should not be committed without an agreed implementation approach.

The team should determine whether a rule-based recommendation prototype or an external AI-based service is feasible and consistent with the client's expectations.

**Supporting Models:** Recommendation workflow activity diagram, recommendation wireframe, and existing Patron, CheckOut, ITEM, and optional ItemReview relationships.

---

## User Story-19 – Record Community Purchase Suggestions

**Priority:** Lower — Product Backlog Candidate

**User Story:**

As a librarian, I want to record community suggestions for new library materials so that WPL can track potential acquisitions before items become part of its physical collection.

### Acceptance Criteria

1. The librarian can enter a purchase suggestion received from a community member.
2. A suggestion does not require an existing patron record.
3. The system allows entry of available catalog information such as title, author, and item type.
4. Information not yet known, such as acquisition cost or date, may remain unspecified.
5. The system distinguishes suggested or planned acquisitions from materials already owned by WPL.
6. Suggested items cannot be checked out as physical copies before acquisition.
7. The librarian can search or filter proposed acquisitions by their current acquisition status.
8. The system supports updating an acquisition's status when the library decides to purchase or receives the item.
9. When an item is acquired, staff can complete the normal catalog and physical-copy creation process without creating inconsistent duplicate records.
10. The system confirms successful record creation and status changes.

### Client Clarifications

- Suggestions may come from anyone, including nonregistered community members.
- Suggestions may initially be collected on paper.
- A full digital approval workflow is not required.
- Suggested items should be distinguishable from owned collection items.
- Patron voting is not required.
- Patron notifications are not required.
- Advance reservations for anticipated items are a possible future enhancement.

### Sprint 3 Notes

This story is lower priority because WPL can continue collecting community suggestions through its existing paper-based process.

The team should decide whether suggestions require a separate entity or a catalog acquisition-status extension before modifying the relational model.

**Supporting Models:** Purchase suggestion activity diagram, suggestion entry wireframe, and proposed purchase-suggestion/acquisition relationships.

---

# 6. Supporting Requirements and Dependencies

The Sprint 3 functionality builds upon the database and application developed during Sprints 0–2.

## Existing Data Structures

The team should review and reuse the following existing entities where appropriate:

- Patron
- Branch
- ITEM
- Item_Type
- Item_Copy
- Author
- Item_Author
- CheckOut
- Fine
- Reservation
- Error_Bin_Entry

The team should verify that existing foreign keys, status fields, and business rules are consistent across the relevant workflows.

## Proposed Data Model Extensions

The following changes may be required depending on the stories selected for implementation:

**Membership Renewal (US-08)**

- Confirm that Patron includes a membership expiration date.
- Determine whether the existing Patron structure supports renewal without additional attributes.
- Add renewal-history information only if required by the agreed workflow.

**Fine Management (US-09)**

- Verify the existing Fine attributes and relationships.
- Determine whether individual payments require a separate FinePayment entity.
- Preserve accurate assessed amounts, paid amounts, and outstanding balances.

**Search (US-14)**

- Reuse existing relationships among patrons, items, copies, checkouts, and fines.
- Add indexes or query improvements where necessary.
- Avoid introducing redundant tables solely for search.

**Administrative Dashboard (US-15)**

- Reuse existing circulation, patron, catalog, acquisition, and fine data.
- Identify which historical measures can be calculated from existing timestamps.
- Add attributes only when necessary to support a confirmed reporting requirement.

**Room Reservations (US-16)**

- Add a Room entity associated with Branch.
- Add a RoomReservation entity associated with Room and Patron.
- Support event information, scheduling, attendance, and reservation status.

**Ratings and Reviews (US-17)**

- Add an ItemReview entity associated with Patron and ITEM.
- Support ratings, written reviews, and moderation information.

**AI Recommendations (US-18)**

- Reuse historical checkout and review relationships where possible.
- Determine whether recommendations need to be stored or can be generated on demand.

**Purchase Suggestions (US-19)**

- Determine whether a separate PurchaseSuggestion entity or an acquisition-status extension is most appropriate.
- Ensure suggested materials cannot be confused with physically available item copies.

No proposed entity should be added to the implemented relational database solely because it appears in the product backlog. Database changes should correspond to selected and approved requirements.

---

# 7. Modeling and Documentation Requirements

The team will organize Sprint 3 artifacts by user story and use descriptive file names.

Each committed story should be traceable to:

1. Its user story and acceptance criteria.
2. The relevant client clarification or business rule.
3. Its activity diagram or documented workflow.
4. Its conceptual and relational data model dependencies.
5. Its wireframe or interface design.
6. Its implemented functionality.
7. Its acceptance-test results.
8. Its demonstration status.

Complex workflows, particularly check-in, routing, reservations, membership renewal, and fine management, should be reviewed with the client before substantial implementation changes.

Models must accurately reflect the implemented functionality, including user inputs, decisions, alternate paths, and relevant error handling.

The team should update existing models where appropriate instead of maintaining multiple conflicting versions.

---

# 8. Sprint Backlog and Implementation Planning

The product backlog contains all identified user stories, including those not selected for the final sprint.

The sprint backlog should contain only the stories the team has agreed to complete within Sprint 3.

## Proposed Sprint 3 Commitment Candidates

**Essential stabilization and completion:**

- US-02 – Verify Checkout Eligibility
- US-05 – Record Item Return
- US-06 – Route Returned Item
- US-08 – Renew Patron Membership
- US-09 – Assess and Manage Overdue Fines
- US-10 – Add Library Item to Collection
- US-11 – Renew Item Checkout
- US-12 – Reserve Library Item
- US-13 – Manage Item Statuses and Routing

**High-priority new functionality:**

- US-14 – Search Library Items and Patrons
- US-15 – View Administrative Dashboard and Reports

**Optional additional functionality:**

- US-16 – Reserve Community Room

**Deferred product backlog candidates:**

- US-17 – Record Patron Ratings and Reviews
- US-18 – Generate Item Recommendations
- US-19 – Record Community Purchase Suggestions

The team will determine the final sprint commitment based on estimated effort, dependencies, available time, and client feedback.

Existing functionality that already satisfies its acceptance criteria should require verification and documentation rather than unnecessary redevelopment.

The sprint backlog should identify assigned responsibilities, progress, blockers, and acceptance-test status.

---

# 9. Testing and Demonstration Requirements

Testing must cover both successful operations and relevant unsuccessful scenarios.

The following scenarios are especially important for Sprint 3:

### Checkout Eligibility

- Checkout succeeds for an eligible patron.
- Checkout is denied for an expired membership.
- Checkout is denied for outstanding fines.
- Checkout is denied when the patron already has 20 active loans.
- Membership renewal updates checkout eligibility.

### Item Returns and Routing

- A normal return closes the active checkout.
- A return to the home branch enters the reshelving workflow.
- A return to another branch enters the transportation workflow.
- An active reservation affects the item's destination.
- Staff can confirm arrival and reshelving.
- An item cannot become available before completing the required processing.
- Invalid status transitions are rejected.

### Fine Management

- On-time returns generate no overdue fine.
- Late returns generate the correct fine.
- Duplicate processing does not generate duplicate charges.
- Outstanding balances are displayed correctly.
- Valid payments reduce the outstanding balance.
- Invalid payment amounts are rejected.
- Resolved fines no longer block checkout.

### Renewals

- Item renewals use the correct loan period.
- Same-day renewals do not compound the due date.
- A third renewal is denied.
- Overdue items and items with active reservations cannot be renewed.
- Membership renewals calculate expiration from the current renewal date.

### Reservations

- A valid reservation can be created.
- Conflicting reservations are prevented.
- The pickup branch is correctly recorded.
- Reserved items are routed to the appropriate branch.
- The five-day pickup period begins when the item is marked ready.
- Reservation fulfillment and expiration are handled correctly.

### Search and Dashboard

- Partial item titles and patron names return appropriate results.
- Search tolerates the agreed level of spelling variation.
- Search results show accurate associated information.
- Dashboard totals agree with the underlying database.
- Weekly trends and supported filters return consistent results.
- Overdue and upcoming-due reports identify the correct records.

Each completed story should have documented test evidence linked from the Sprint 3 documentation.

---

# 10. Sprint Review Preparation

For the final sprint review, the team should clearly identify:

1. The sprint goal and the user stories selected for implementation.
2. The acceptance criteria completed for each demonstrated story.
3. The supporting activity diagrams, wireframes, and data models.
4. The corresponding implemented functionality.
5. The testing performed and its results.
6. Any incomplete functionality or known limitations.
7. Which stories are being presented for client acceptance and removal from the product backlog.

The team should not present a story as complete solely because its main interface or successful workflow is operational.

A story should be considered complete only when its agreed acceptance criteria and Definition of Done have been satisfied.

---

# 11. General Notes

Sprint 3 represents the final opportunity to improve the completeness, consistency, and reliability of the WPL system before the final review.

The team will prioritize delivering working functionality that meets the client's actual requirements over expanding the system with unnecessary complexity.

Particular emphasis will be placed on:

- Completing previously identified requirements.
- Simplifying difficult circulation and routing workflows.
- Maintaining consistency between the application and its supporting models.
- Keeping the product backlog and sprint task board current.
- Requesting client feedback throughout development.
- Providing clear, professional documentation and demonstrations.
- Delivering a smaller number of complete, well-tested features rather than numerous incomplete implementations.

The user stories in this document represent the proposed Sprint 3 requirements and overall product backlog. Final sprint commitments remain subject to team planning, available capacity, and client approval.
