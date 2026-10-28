# Wayback Public Library – Sprint 2

This directory contains the documentation and supporting models developed or revised during Sprint 2 of the Wayback Public Library project.

## Sprint Goal

**Sprint 2 Goal**

Complete the core library check-in workflow so that returned items are accurately recorded, removed from patrons' active checkouts, and routed to their appropriate locations while remaining unavailable until reshelving is completed.

In response to updated client priorities, the team will also refine requirements for item renewals (US-11), reservations (US-12), and checkout usability improvements.

The team will prioritize check-in functionality and select additional stories based on client validation, business value, technical dependencies, and available development capacity.

Sprint 2 will build on Sprint 1 functionality while improving consistency between requirements, activity diagrams, database models, wireframes, implementation, and testing.


## Requirements

The `requirements` directory contains:

- Revised Sprint 2 user stories and acceptance criteria
- Requirements carried forward from Sprint 1.
- Client clarifications relevant to the check-in process
- Any changes identified through product backlog refinement

The primary focus is recording item returns, routing returned items to the appropriate location, and handling unscannable items.

Overdue fine calculation will also be reviewed as part of the check-in requirements and included based on the team's agreed Sprint 2 scope.

## Models

The `models` directory contains or will contain supporting models developed or revised during Sprint 2, including:

- Updated conceptual data model and accompanying design notes
- Relational database model reflecting approved schema changes
- Combined check-in activity diagram covering item identification, return recording, overdue fine assessment, routing, and exception handling
- Check-in wireframes aligned with the activity diagram and acceptance criteria
- Renewal and reservation activity diagrams, subject to client clarification
- Additional process diagrams or use case narratives where needed

The combined check-in activity diagram supports US-05, US-06, US-07, and the proposed check-in portion of US-09.

US-11 (Renewals) and US-12 (Reservations) are new high-priority items that will be represented with activity diagrams as well as per the clients validation of their importance.

## Development

Sprint 2 development will build on the PHP, JavaScript, and MySQL implementation established during Sprint 1.

The team will review the existing database structure and identify any changes needed to support return dates, return branches, item routing, and error-bin handling.

The new check-in functionality will be integrated into the existing website rather than developed as a separate system.

## Testing

The team will validate each implemented user story against its approved acceptance criteria.

Check-in testing will include:

- Successfully identifying a returned item copy
- Retrieving the correct active checkout and patron information
- Recording the return date and return branch
- Removing returned items from active checkout counts
- Preventing duplicate returns
- Handling item copies with no active checkout
- Recording unidentified or unscannable returns in the error bin
- Routing home-branch returns to the reshelving cart
- Routing other-branch returns to the return truck bin
- Keeping returned items unavailable until the appropriate reshelving process is completed
- Calculating and recording overdue fines when included in the approved Sprint 2 scope
- Verifying database records, status transitions, and error handling

Renewal and reservation test cases will be developed after the client confirms their business rules and the team selects the implementation scope.

Testing should begin during development rather than only at the end of the sprint. Results and identified defects will be documented alongside the relevant GitHub tasks.

## Task Management

Sprint 2 tasks are tracked through the Wayback Public Library GitHub Projects board.

The board separates sprint work through dedicated views and includes tasks for requirements, modeling, frontend development, backend development, and testing.

Product backlog refinement will be performed to review remaining requirements, update priorities, clarify acceptance criteria, and identify any work that needs to be carried forward.

## Definition of Done

A Sprint 2 user story will be considered complete when its acceptance criteria are satisfied by the implemented functionality, supported by the required models and documentation, verified through testing, and demonstrated to the client.

The team will update the GitHub task board and supporting documentation to reflect completed work.

## Sprint Review

**Sprint 2 Review Date:** November 9, 2026
