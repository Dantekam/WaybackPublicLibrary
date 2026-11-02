# PHP TEST FILES - TEST CASES

## TEST CASES FOR testFindPatron.php

- Empty ID:
  Expected result: HTTP 400 error because the ID is missing.

- Letters in ID:
  Expected result: HTTP 400 error because the ID must be numeric.

- Mixed ID:
  Expected result: HTTP 400 error because the ID is not entirely numeric.

- Negative ID:
  Expected result: HTTP 400 error because the ID must be numeric digits only.

- Decimal ID:
  Expected result: HTTP 400 error because the ID must be numeric digits only.

- Missing patron:
  Expected result: HTTP 200 response containing "Patron not found."

- Existing patron:
  Expected result: The response contains "ok":true.

- Patron has a name:
  Expected result: The response contains the "name" field.

- Patron has expiration:
  Expected result: The response contains the "expiration" field.

- Patron has expired field:
  Expected result: The response contains the "expired" field.


## TEST CASES FOR testFindItem.php

- Empty ID:
  Expected result: HTTP 400 error because the ID is missing.

- Letters in ID:
  Expected result: HTTP 400 error because the ID must be numeric.

- Mixed ID:
  Expected result: HTTP 400 error because the ID is not entirely numeric.

- Negative ID:
  Expected result: HTTP 400 error because the ID must be numeric digits only.

- Decimal ID:
  Expected result: HTTP 400 error because the ID must be numeric digits only.

- Missing item:
  Expected result: HTTP 200 response containing "Itemcopy not found."

- Existing item:
  Expected result: The response contains "ok":true.

- Item has type:
  Expected result: The response contains the "itemType" field.

- Item has status:
  Expected result: The response contains the "status" field.

- Item has availability:
  Expected result: The response contains the "available" field.


## TEST CASES FOR testRecordCheckout.php

- Empty IDs:
  Expected result: HTTP 400 error because both IDs are missing.

- Invalid patron ID:
  Expected result: HTTP 400 error because the Patron ID must be numeric.

- Invalid item ID:
  Expected result: HTTP 400 error because the Item Copy ID must be numeric.

- Negative patron ID:
  Expected result: HTTP 400 error because the ID must contain numeric digits only.

- Decimal item ID:
  Expected result: HTTP 400 error because the ID must contain numeric digits only.

- Missing patron:
  Expected result: The response contains "Patron not found."

- Missing item:
  Expected result: The response contains "Item copy not found."

Successful Checkout:

- Successful checkout:
  Expected result: The response contains "ok":true.

- Checkout has patron name:
  Expected result: The response contains the "patron" field.

- Checkout has due date:
  Expected result: The response contains the "dueDate" field.


## RESULTS

- PASS:
  The test received the expected HTTP status code and response text.

- FAIL:
  The test did not receive the expected response. It prints the status code and response.

