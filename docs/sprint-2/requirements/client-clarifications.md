# Sprint 2 – Client Clarifications

## 1. Purpose

This document records the client's responses to questions regarding Sprint 2 requirements, including check-in, routing, reshelving, renewals, reservations, overdue fines, and catalog information.

The purpose is to clarify business rules, identify dependencies between user stories, and provide the development team with a consistent basis for refining activity diagrams, data models, interfaces, and acceptance criteria.

## 2. Item Renewals – US-11

**Q1. Must patrons physically bring items to the library to renew them?**

**Client response:** No. Patrons may contact the library by phone or visit in person and identify themselves using their library card.

**Decision:** Renewal does not require physical presentation of the borrowed item.

**Q2. Does renewal extend an existing checkout or create a new checkout transaction?**

**Client response:** A renewal extends the existing checkout. An item may be renewed twice beyond its initial loan period.

**Decision:** Track successful renewals against the active checkout. A separate checkout after a completed return begins a new loan with its own renewal allowance.

**Q3. How is the renewed due date calculated?**

**Client response:** The new due date is calculated from the date the patron requests renewal.

**Decision:** Calculate the new due date using the renewal date and the loan period associated with the item's type.

**Q4. Can overdue items or reserved items be renewed?**

**Client response:** No. An overdue item cannot be renewed, and an item with an existing reservation cannot be renewed.

**Decision:** The system must check overdue status and active reservations before approving renewal.

**Q5. Does the two-renewal limit apply across all historical loans?**

**Client response:** The limit applies to the continuous checkout, not to every time the patron has borrowed that item historically.

**Decision:** A new checkout begins with zero renewals. The active checkout permits up to two successful renewals.

**Q6. What happens when a patron requests renewal while owing fines?**

**Client response:** If the requested item is overdue, a fine is assessed immediately. If the patron owes a fine on another item, the librarian should address that fine without necessarily terminating the entire interaction.

**Decision:** The system should display relevant outstanding fines and support staff review. Renewal of an overdue item remains prohibited. The exact conditions under which an otherwise eligible renewal may proceed with unpaid fines require further clarification.

## 4. Item Reservations – US-12

**Q7. Can patrons reserve any copy of a title or a specific physical copy?**

**Client response:** Ideally, patrons can choose either the first available copy or a specific copy. Supporting specific-copy reservations alone is acceptable for the initial implementation.

**Decision:** Prioritize specific-copy reservations for the MVP. First-available-copy reservations may be implemented as a later enhancement.

**Q8. Can patrons reserve items that are currently checked out?**

**Client response:** Yes, including specific copies that are currently borrowed.

**Decision:** A reservation can be created while an item is unavailable. The reservation must be retained until the item can be prepared for pickup or the reservation is otherwise resolved.

**Q9. Can multiple patrons reserve the same item?**

**Client response:** A waiting queue is desirable, but lower priority. A simpler initial implementation without a queue is acceptable.

**Decision:** Support one active reservation per physical copy for the initial implementation, unless the team elects to implement a queue.

**Q10. Which branch should patrons use for reservation pickup?**

**Client clarification:** The patron's home branch is the default pickup branch. The library is responsible for transporting the reserved item to the appropriate branch.

**Decision:** Reservation routing must use the patron's pickup branch, not automatically the item's home branch. The system must distinguish patron home branch, item home branch, and current item location.

**Q11. When does the five-day reservation pickup period begin?**

**Client clarification:** The pickup period begins when library staff physically place the reserved item on the reservation shelf and update its status.

**Decision:** The system records when the item becomes ready for pickup and calculates the expiration date from that point.

**Q12. Are the five days calendar days or business days?**

**Client response:** Assume the library is open every day.

**Decision:** Use five calendar days for the initial implementation.

**Q13. How are patrons notified that reserved items are ready?**

**Client response:** Email is the intended notification method, but automatic email notification does not need to be implemented now.

**Decision:** The system must provide staff with reservation information and routing instructions. Automated patron email notifications are deferred.

**Q14. What happens when a reservation expires?**

**Client response:** The item should become available to the next waiting patron when a queue exists. Otherwise, it should be released for normal circulation.

**Decision:** The system must recognize expired reservations and support removing items from the reservation shelf. Queue advancement is a lower-priority enhancement.

**Q15. What happens when a reserved item is returned?**

**Client clarification:** Staff should be informed that the returned item is reserved so it is not placed into ordinary circulation. It should be routed to the reservation pickup location.

**Decision:** Check-in must check for active reservations before determining the item's final routing destination.

## 5. Check-In, Routing, and Error Handling – US-05, US-06, US-07

**Q16. What makes an item available for checkout again?**

**Client response:** The item becomes available when staff physically place it on the appropriate shelf.

**Decision:** Recording a return does not automatically make an item Available. Staff must complete the appropriate receiving or reshelving workflow.

**Q17. How should items be tracked during interbranch transportation?**

**Client response:** Detailed transportation tracking is not a priority.

**Decision:** The system should retain enough status and branch information to support routing, but detailed shipment tracking is outside the current MVP.

**Q18. What happens when an identified item has no active checkout?**

**Client response:** Such an item should normally already be on the shelf.

**Decision:** The system should flag the situation for staff review rather than create another return transaction.

**Q19. What return date should be used when an unidentified item is later processed?**

**Client response:** No specific rule was established.

**Open assumption:** The system should retain the physical receipt timestamp in the error-bin record. The official checkout return date requires a consistent implementation decision.

## 6. Overdue Fines – US-09

**Q20. Can one checkout incur multiple fine records?**

**Client response:** Yes. Multiple separate fines may be associated with a checkout.

**Decision:** The model may support a one-to-many relationship between Checkout and Fine. The implementation must distinguish individual charges and prevent duplicate assessment of the same charge.

**Q21. When are overdue fines assessed?**

**Client response:** Overdue fines are normally assessed when an item is returned. During a renewal request, the system must also identify overdue items and assess applicable fines immediately.

**Decision:** Fine assessment is event-driven during return processing and overdue renewal attempts. The system should not require a daily background process to create fine records.

**Q22. Does an outstanding fine automatically stop the entire renewal interaction?**

**Client response:** No. Staff should address outstanding fines, including fines associated with other items, while continuing the interaction as appropriate.

**Open clarification:** The exact eligibility threshold and payment requirement for approving a different, otherwise eligible renewal were not fully established.

## 7. Catalog Information

**Q23. Does every catalog item require an author?**

**Client response:** No. Some items have no authors, while others have multiple authors.

**Decision:** An Item may have zero or many Author associations through Item_Author.

**Q24. Are there additional patron eligibility requirements?**

**Client response:** Staff should be able to review patron information, library cards, outstanding fines, and currently borrowed items. Address verification may be required periodically, approximately every two years.

**Decision:** Existing patron information and eligibility displays should be reviewed for completeness. New address-verification functionality is not automatically included in Sprint 2.

## 8. Statuses and Routing of Items

**Q25. Should we have an item statuses page to include books in transit, awaiting shelving, on holds, etc.?

**Client response:** Yes for ease of use and accessibility purposes, having a page dedicated to being able to change the routing status and general status of items would help.

**Decision:** A page dedicated to statuses with minimum filters to allow easy transition of statuses of items throughout their lifecycle.

**Q26. How should the changing of statuses really work?

**Client response:** The items should be able to be looked at as lets say a incoming truck with items in transit to our current branch and we are able to view it and change the statuses of books from there, or with newly check in books we are able to put them on hold or awaiting reshelving, etc. With books awaiting to be shelved we are able to make the availability of all of them enabled by group or singularly without having to relook back up new ones constantly.

**Decision:** Statuses for any item searched by group or singular available to be changed to a multitude of statuses including OnHold, AwaitingReshelving, Available, InTransit, AwaitingTransit, MaintenanceRequired, NewArrivals and routing related statuses for CurrentBranch, HomeBranch, ReturnBranch to help keep track of items.

## 9. Modeling and Development Implications

The following changes should be reflected in the Sprint 2 models and implementation planning:

1. Retain RenewalCount on Checkout, initialized to zero and limited to two successful renewals.
2. Add a Reservation entity to track patron reservations, item copies, reservation status, pickup readiness, and expiration.
3. Support the patron's home branch as the default reservation pickup location.
4. Distinguish item home branch, current branch, return branch, and reservation pickup branch.
5. Add reservation-aware routing to the check-in workflow.
6. Implement reshelving confirmation before changing item status to Available.
7. Support overdue fine assessment during both return processing and applicable renewal attempts.
8. Permit catalog items to have zero, one, or multiple authors.
9. Preserve separate activity diagrams for the full renewal and reservation processes.
10. Maintain consistency among acceptance criteria, activity diagrams, relational models, implementation, and tests.

## 10. Remaining Decisions

- Determine whether a patron with unpaid fines on unrelated items may complete an otherwise eligible renewal.
- Confirm the official return date used when an unidentified item is later resolved.
- Determine how reservation pickup-branch selection and patron home-branch information will be represented in the database.
- Establish the exact process for interbranch receiving and completing reservation-shelf placement.
- Determine which optional enhancements, including reservation queues and automated notifications, fit within remaining sprint capacity.

These clarifications represent the client's guidance for refining Sprint 2 requirements. Final implementation commitments remain subject to sprint planning and available team capacity.
