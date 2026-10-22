# Sprint 2 – Conceptual Data Model Notes

## Overview

The Sprint 2 conceptual data model builds on the model developed during Sprint 1 of the Wayback Public Library project.

Sprint 1 established the relationships needed to identify patrons and library items, record checkouts, and add items to the library's collection.

Sprint 2 expands the model to support the check-in process, including recording returns, determining item routing, and handling unscannable items.

The updated model will guide the relational database design, development, and testing of the selected Sprint 2 user stories.

## Proposed Changes from Sprint 1

The following changes are proposed:

- **Patron:** Retain the existing patron information and checkout relationship.
- **CheckOut:** Extend return information to include the branch where an item was returned.
- **Item_Copy:** Expand circulation statuses to represent items awaiting reshelving, transport, or processing.
- **Branch:** Retain the existing entity and clarify its relationships with item copies, returns, and error-bin entries.
- **Item_Type:** Add an entity for item categories, loan periods, and daily fine rates.
- **Error_Bin_Entry:** Add an entity for recording unidentified or unscannable returns.
- **Fine:** Review the existing proposed entity to support overdue fine assessment during check-in.
- **Item, Author, and Item_Author:** Retain the existing catalog relationships.

## Sprint 2 Development Guidelines

### Recording Returns

A return must be associated with the correct active checkout.

The system records the return date and branch, then removes the item from the patron's active checkouts.

Recording a return does not automatically make the item available for checkout.

### Branches and Routing

Each item copy has an assigned home branch.

The branch where an item is returned may differ from its home branch.

Items returned to their home branch are routed to the reshelving cart. Items returned elsewhere are routed to the return truck bin.

The model must preserve the home branch while supporting temporary routing and location information.

### Item Availability

The model must distinguish checked-out, returned, awaiting-reshelving, and in-transit items.

Only items that have completed the appropriate receiving or reshelving process should be marked available.

### Unscannable Returns

An unscannable return may initially have no known item-copy identifier.

The model must allow an error-bin record to be created without immediately identifying the item.

When the item is identified, the record can be linked to the correct copy and the normal check-in process can be completed.

### Item Types

The proposed Item_Type entity stores information shared by items of the same type, including loan periods and fine rates.

This avoids creating separate subtypes where no additional type-specific attributes or relationships are currently required.

### Overdue Fines

If overdue fine calculation is included in Sprint 2, the model must support associating an assessed fine with the appropriate patron and checkout.

Fine calculation should use the recorded due date, return date, and applicable daily fine rate.

## General Questions

1. Which circulation statuses will the system support, and what transitions are permitted?
2. Is the existing Branch entity sufficient once return and current-location relationships are added?
3. How will staff confirm that an item has been reshelved and is available again?
4. Should unidentified error-bin entries be stored separately from identified item-copy records?
5. How will the team avoid duplicate returns and duplicate overdue fine assessments?
6. Does the existing Fine structure support the Sprint 2 requirements?
7. Which proposed database changes are necessary for Sprint 2, and which can be deferred?
8. Can the selected stories be completed and demonstrated by the Sprint 2 review?

## Modeling and Implementation Notes

The conceptual data model should represent business entities, relationships, and rules independently of the physical database implementation.

The relational model will then specify primary keys, foreign keys, and attributes needed to implement those requirements.

The team should preserve existing Sprint 1 functionality and test database migrations before deploying changes to the shared application.

## Sprint 2 Modeling Status

The completed models will be used to guide development and acceptance testing for US-05, US-06, US-07, and any additional approved Sprint 2 scope.
