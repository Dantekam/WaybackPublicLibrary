# Sprint 1 – Revised User Stories and Acceptance Criteria

## Overview

These user stories outline the proposed functionality for Sprint 1 of the Wayback Public Library system. They build on the initial requirements and proof of concept from Sprint 0, with a focus on adding library items, identifying patrons and items, and recording checkouts.

The selected stories will guide the development of activity diagrams, the conceptual data model, wireframes, implementation, and testing. Scope and priorities may be adjusted based on team capacity as well as client feedback.

## Definition of Done

For a Sprint 1 user story to be considered complete, the team must have:

1. A clearly defined user story with acceptance criteria.
2. An activity diagram representing the relevant process.
3. A conceptual data model (ERD) supporting the required information.
4. Wireframes or mockups showing the relevant user interface.
5. Working functionality implemented and integrated into the system on the ADA server.
6. Testing completed to verify that the functionality satisfies the acceptance criteria.
7. A demonstration of the completed functionality and supporting models.
8. Updated GitHub tasks and documentation reflecting the completed work.

A use case narrative may also be included when additional detail is needed.

These requirements follow the project's Definition of Done for Sprints 1–3. Existing models may be reused or updated where appropriate rather than recreated for every user story.

## User Story-01 – Identify Patron

**Priority:** High

**User Story:**

As a librarian, I want to retrieve a patron's information so that I can identify the correct patron when processing a checkout.

**Acceptance Criteria:**

1. The librarian can enter a patron identifier to retrieve an existing patron record.
2. The system displays the matching patron's identifying information and membership expiration date.
3. The system provides a message when the patron cannot be found.
4. The system does not allow a checkout to proceed without an identified patron.

## User Story-02 – Verify Checkout Eligibility

**Priority:** High

**User Story:**

As a librarian, I want the system to verify a patron's checkout eligibility so that patrons with expired memberships, outstanding fines, or too many checked-out items cannot borrow additional materials.

**Acceptance Criteria:**

1. The system checks whether the patron's membership is expired.
2. The system checks whether the patron has outstanding fines.
3. The system checks whether the patron already has 20 items checked out.
4. The system identifies the reason when a patron is ineligible.
5. An ineligible patron cannot complete a checkout.

**Sprint 1 Note:** This is a required part of the complete checkout process. The team must determine whether all eligibility checks can be implemented during Sprint 1 or whether the checkout feature needs to be scoped differently.

## User Story-03 – Identify Checkout Item

**Priority:** High

**User Story:**

As a librarian, I want to retrieve information about a library item so that I can identify the correct item copy and determine whether it is available for checkout.

**Acceptance Criteria:**

1. The librarian can enter an item-copy identifier to retrieve an existing copy.
2. The system displays the item's title, type, and availability status.
3. The system provides a message when the item copy cannot be found.
4. The system identifies when an item copy is unavailable.
5. An unavailable item copy cannot be checked out.

## User Story-04 – Record Item Checkout

**Priority:** High

**User Story:**

As a librarian, I want to record an item checkout so that the system can track which patron borrowed the item and when it is due.

**Acceptance Criteria:**

1. The system requires a valid patron and an available item copy before completing a checkout.
2. The system records the patron, item copy, checkout date, and due date.
3. The due date is calculated based on the item's loan period.
4. The system updates the item copy's availability after a successful checkout.
5. The system confirms that the checkout was recorded.
6. The system does not create a checkout record when the transaction fails.

**Client Clarifications:**

- Books: 14-day loan period.
- Movies: 7-day loan period.
- Periodicals: 1-day loan period.

Loan periods for other item types require clarification.

## User Story-10 – Add Library Item to Collection (Revised)

**Priority:** High

**User Story:**

As a head librarian, I want to add new items and copies to the library's collection so that they can be tracked and made available to patrons.

**Acceptance Criteria:**

1. The librarian can search for an existing item definition before adding a copy.
2. If an item definition does not exist, the librarian can create one with the required information, including title, author, publisher, etc.
3. The librarian can add an individual copy associated with an existing item definition.
4. The system records an acquisition date, defaulting to the current date.
5. The system defaults the copy's branch location to the central library but allows another branch to be selected.
6. Copies assigned to another branch are initially marked unavailable until received and processed there.
7. The system confirms when the item information has been saved successfully.

**Sprint 1 Note:** This story may need to be divided into smaller stories for creating item definitions and adding individual copies. Branch receiving and shelf-location assignment can be considered for a later sprint if necessary.

## Supporting Requirements and Dependencies

The selected user stories depend on a consistent database structure supporting patrons, item definitions, individual item copies, checkout transactions, and applicable eligibility information.

Before implementation, the team will review the conceptual data model and agree on the relationships and information required for Sprint 1.

The existing Sprint 0 proof of concept will be reused where appropriate, rather than rebuilding functionality that already meets the requirements.

## Requirements for Later Sprints

The following previously identified user stories remain part of the overall product backlog but are not the primary focus of Sprint 1:

- US-05 – Record Item Return
- US-06 – Route Returned Item
- US-07 – Handle Unscannable Return
- US-08 – Renew Patron Membership
- US-09 – Process Overdue Fine

These stories will be refined when they are selected for development.

## Planning Notes

The requirements in this document represent the proposed Sprint 1 scope.

A user story will be considered complete only when its requirements, supporting models, working implementation, and acceptance tests satisfy the project's Definition of Done.
