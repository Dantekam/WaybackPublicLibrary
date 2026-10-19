
# Sprint 1 – Conceptual Data Model Notes

## Overview

The Sprint 1 conceptual data model builds on the three-table proof of concept developed during Sprint 0. The original model demonstrated the relationship between patrons, item copies, and checkout transactions.

The updated model expands this structure to support the Sprint 1 user stories, including patron identification, checkout eligibility, item identification, recording checkouts, and adding items to the library's collection.

The purpose of this model is to establish a consistent structure for the team to follow when developing the relational database model, implementing functionality, and preparing future models.

## Changes from Sprint 0

The following changes were made to the original conceptual data model:

- **Patron:** Retained the original patron information and its relationship with checkout records.
- **Checkout:** Retained checkout transactions, including checkout date, due date, and return date.
- **Item:** Added to store information shared by copies of the same library item, including title, item type, publisher, Library of Congress code, and cost.
- **Item_Copy:** Updated to represent individual physical copies rather than storing general item information. Each copy references an item definition and a branch.
- **Branch:** Added to track the branch associated with each item copy.
- **Author:** Added to store author information separately from item definitions.
- **Item_Author:** Added as an associative entity to support the many-to-many relationship between items and authors.
- **Fine:** Added as a proposed structure for tracking patron fines and supporting checkout eligibility checks.

The original Patron–Checkout–Item_Copy relationship remains in place, but the expanded model allows the system to manage additional library information without repeating the same item details for every copy.

## Entity Relationships

The main relationships represented in the diagram are:

1. **Patron to Checkout:** One patron can have zero or many checkout records. Each checkout belongs to exactly one patron.
2. **Item_Copy to Checkout:** One item copy can appear in zero or many checkout records over time. Each checkout refers to exactly one item copy.
3. **Item to Item_Copy:** One item definition can have zero or many individual copies. Each copy belongs to exactly one item definition.
4. **Branch to Item_Copy:** One branch can have zero or many item copies. Each copy is associated with exactly one branch.
5. **Item to Author:** An item can have multiple authors and at least one, and an author can be associated with multiple items. This relationship is represented through Item_Author.
6. **Patron to Fine:** One patron can have zero or many fines. Each fine belongs to exactly one patron.

Item_Author uses ItemID and AuthorID together as a composite primary key. Both attributes also serve as foreign keys to their respective entities.

The model includes primary and foreign key information to help guide the relational database design.

## Sprint 1 Development Guidelines

### Item Definitions and Copies

Item and Item_Copy represent different information.

Item stores the general information about a library item, such as its title, publisher, type, and associated authors.

Item_Copy stores information about an individual physical copy, including its assigned branch, acquisition date, availability status, and shelf location.

Multiple copies can reference the same item definition.

When adding a new copy, the system should first determine whether the item definition already exists. If it does, the new copy should reference that existing item rather than creating a duplicate definition.

### Patron and Item Identification

The system should retrieve patron information using PatronID and item-copy information using ItemCopyID.

When displaying an item copy, the application may need to retrieve information from both Item_Copy and Item, since the title and item type are now stored in Item.

The system should provide appropriate feedback when a patron or item copy cannot be found.

### Checkout Processing

Checkout records connect patrons with individual item copies.

Before recording a checkout, the system should verify that:

- The patron exists.
- The patron is eligible to borrow materials.
- The item copy exists.
- The item copy is available.
- The checkout will not create multiple active loans for the same copy.

A successful checkout should record the patron, item copy, checkout date, and calculated due date.

The due date should follow the client's established loan periods:

- Books: 14 days
- Movies: 7 days
- Periodicals: 1 day

The system should update the copy's availability when a checkout is completed.

The checkout record and availability update should succeed together. If the transaction fails, the database should not be left with a checkout record and an inconsistent item status.

### Checkout Eligibility

The system must eventually support the eligibility rules established in the requirements:

- The patron's membership must not be expired.
- The patron must not have outstanding fines.
- The patron must have fewer than 20 items currently checked out.

The Fine entity is included as a proposed way to represent outstanding fines. The team must confirm how fine balances and payments will be handled.

Active checkout records should be distinguished from historical records so that returned items do not count toward the 20-item limit.

### Author Information and Searching

Author and Item_Author allow author information to be associated with multiple item definitions.

This structure supports future catalog searches by author without storing the same author information repeatedly across different item records.

The team should ensure that item information can be retrieved through the appropriate relationships.

Full catalog search functionality is not necessarily part of Sprint 1, but the model should support its development during a later sprint.

### Branches and Availability

Each item copy is associated with a branch.

A newly added copy may be assigned to the central library or another branch. Copies assigned to another branch may initially be unavailable until they are received and processed.

ShelfLocationCode may remain empty until a copy has been assigned a shelf location.

The team should agree on the possible item statuses and how those statuses change during checkout, return, transfer, and receiving processes.

## Questions that I thought of and noted for Team Review

1. **Item types:** Should ItemType remain an attribute of Item, or should we create a separate Item_Type table containing item types and their loan periods?

2. **Authors:** Do we want to support multiple authors per item immediately, or simply retain the relationship for future catalog functionality?

3. **Fine information:** How should outstanding fines be calculated and recorded? Is the current Fine structure sufficient for Sprint 1?

4. **Active checkouts:** Should an active checkout be identified by an empty ReturnDate? How will the system prevent multiple active checkout records for the same copy?

5. **Item status:** What status values should be supported, and how will they remain consistent with checkout records?

6. **Branch handling:** How should the system distinguish a copy's assigned branch from a copy that is currently being transferred or has not yet been received?

7. **Database changes:** Which existing Sprint 0 tables can be modified, and what data or functionality must be preserved during the transition?

8. **Sprint scope:** Can we complete all selected Sprint 1 user stories, or should some larger stories be divided into smaller pieces?

## Considerations for Future Sprints

Although Sprint 1 focuses primarily on adding library items and recording checkouts, the expanded model provides a foundation for additional functionality.

### Sprint 2 – Check-In and Catalog Improvements

The existing Checkout entity includes ReturnDate, which can support recording when an item is returned.

Future development can use the checkout history and item-copy status to support check-in processing.

Additional requirements may include:

- Recording item returns and updating availability.
- Identifying the branch where an item was returned.
- Routing returned items to the appropriate branch.
- Handling damaged or unscannable items.
- Searching the catalog by title, author, item type, or other supported criteria.

Additional entities or relationships may be required as these requirements are refined.

### Sprint 3 – Additional Library Operations

Later functionality might include:

- More detailed branch transfer and receiving processes.
- Overdue fine calculations and payment handling.
- Membership renewal.
- Additional item types and loan rules.
- Final integration, testing, and usability improvements.
