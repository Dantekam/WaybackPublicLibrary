# Wayback Public Library – Sprint 2

This directory contains the documentation and supporting models developed or revised during Sprint 2 of the Wayback Public Library project.

## Sprint Goal

**Sprint 2 Goal**

Complete the core library check-in workflow so that returned items are correctly removed from patrons' active checkouts, routed to their appropriate locations, and prevented from being borrowed again until available.

In response to updated client priorities, the team will also refine requirements for item renewals, reservations, and checkout usability improvements. Additional functionality will be selected based on business value, dependencies, and team capacity.

The team will preserve Sprint 1 functionality while improving consistency between requirements, models, implementation, and testing.

## Requirements

The `requirements` directory contains:

- Revised Sprint 2 user stories and acceptance criteria
- Requirements carried forward from Sprint 1.
- Client clarifications relevant to the check-in process
- Any changes identified through product backlog refinement

The primary focus is recording item returns, routing returned items to the appropriate location, and handling unscannable items.

Overdue fine calculation will also be reviewed as part of the check-in requirements and included based on the team's agreed Sprint 2 scope.

## Models

The `models` directory will contain the supporting models developed or revised for Sprint 2, including:

- Updated conceptual data model (ERD)
- Updated relational database model
- Record item return activity diagram
- Route returned item activity diagram
- Handle unscannable return activity diagram
- Check-in and return handling wireframes
- Use case narratives, if additional process details are needed

The existing models will be reviewed against the selected user stories and updated where necessary to support the new functionality.

## Development

Sprint 2 development will build on the PHP, JavaScript, and MySQL implementation established during Sprint 1.

The team will review the existing database structure and identify any changes needed to support return dates, return branches, item routing, and error-bin handling.

The new check-in functionality will be integrated into the existing website rather than developed as a separate system.

## Testing

The team will test the implemented functionality against the acceptance criteria for each selected user story.

Testing will include:

- Recording a valid item return
- Verifying that the correct checkout record is updated
- Confirming that returned items are removed from active patron checkouts
- Verifying that returned items remain unavailable until reshelved
- Testing returns to the item's home branch and another branch
- Testing unscannable items and error-bin handling
- Checking invalid inputs and duplicate return attempts
- Verifying applicable database changes and item statuses

## Task Management

Sprint 2 tasks are tracked through the Wayback Public Library GitHub Projects board.

The board separates sprint work through dedicated views and includes tasks for requirements, modeling, frontend development, backend development, and testing.

Product backlog refinement will be performed to review remaining requirements, update priorities, clarify acceptance criteria, and identify any work that needs to be carried forward.

## Definition of Done

A Sprint 2 user story will be considered complete when its acceptance criteria are satisfied by the implemented functionality, supported by the required models and documentation, verified through testing, and demonstrated to the client.

The team will update the GitHub task board and supporting documentation to reflect completed work.

## Sprint Review

**Sprint 2 Review Date:** November 9, 2026
