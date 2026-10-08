# WPL Initial Release Plan

This is an initial release plan rather than a fixed commitment. User story
priorities and sprint assignments may change as requirements are clarified,
development effort becomes better understood, and client feedback is
received.

# Sprint 0 – Proof of Concept

- Establish the initial development environment using PHP, MySQL, GitHub, and the ADA server.
- Develop a basic website connected to a database.
- Able to read data from three related tables.
- Gather initial client requirements and develop prioritized user stories with acceptance criteria.
- Create initial conceptual data model.

# Sprint 1 – Collection Management and Basic Checkout

- Review and refine the existing database structure to support the library's collection and checkout processes.
- Develop functionality for adding and maintaining library item records.
- Allow librarians to identify patrons and retrieve available library items.
- Implement a basic checkout process that records the patron, item, checkout date, and due date.
- Create or refine the supporting activity diagrams, conceptual data model, wireframes, and acceptance criteria.
- Test the implemented functionality and demonstrate an integrated working system.

# Sprint 2 – Checkout, Check-In, and Catalog Improvements

- Expand checkout functionality to verify patron eligibility, including membership status, outstanding fines, and the maximum checkout limit.
- Implement the basic check-in process to record returned items and update checkout records.
- Improve catalog searching by supporting searches for items using information such as title, author, and item type.
- Expand collection management to support adding new item definitions and related information.
- Begin implementing return handling, including item status and branch-related information.
- Update models, wireframes, and acceptance criteria as functionality is refined.

# Sprint 3 – Additional Library Operations and Final Integration

- Refine return processing, including items returned to different branches and items requiring reshelving.
- Implement additional handling for overdue items, fines, and damaged or unscannable returns as time allows.
- Improve item availability, branch locations, and collection information.
- Refine membership-related functionality and remaining high-priority requirements.
- Complete system integration, usability improvements, and testing.
- Finalize documentation and prepare the working system for demonstration.

## Models

The models directory contains initial supporting models developed from the
WPL narrative and client clarification, including the proof-of-concept data
model and checkout process models.

## Requirements

The requirements directory contains:

- Initial prioritized user stories and acceptance criteria
- Client requirement clarifications
- Initial release plan

## Proof of Concept

The Sprint 0 proof of concept uses the Patron, CheckOut, and Item_Copy
tables to demonstrate database-backed web functionality. The POC supports
searching library items and displaying related data from the three-table
relationship. User input is also stored in the database to demonstrate the
required write capability.

The POC is intentionally minimal and establishes the technical foundation
for functionality developed during later sprints.
