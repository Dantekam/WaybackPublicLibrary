# WPL Client Clarifications

The following requirements were clarified with the WPL client
during Sprint 0 requirements analysis meeting.

## Checkout

- Patrons with any outstanding fines may not check out additional items.
- Patrons with expired memberships may not check out items until their
  membership is renewed.
- The maximum of 20 checked-out items applies to a patron across all
  WPL branches.
- Loan periods are determined by item type.
- Overdue fines continue accumulating without a maximum fine amount.

## Check-In

- Check-in must account for items returned to a branch other than their
  home branch.
- Returned items should be removed from the patron's account when
  checked in.
- A returned item is not considered available for checkout until it is
  back on the shelf.
- Damaged or unscannable returned items should be identified in the
  system as being in the error bin for later handling.

## Development Priorities & Suggestions

- Basic checkout and check-in are the current highest-priority processes.
- Initial development should focus on the normal or most common
  successful path for an item's checkout/check-in cycle.
- User stories should be kept small enough to develop and test
  independently.
- Membership renewal, fines, and adding collection items are lower
  priorities for initial development.
- Adding collection items will be handled by a librarian when that
  functionality is addressed.
