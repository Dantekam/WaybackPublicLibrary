INSERT into Patron (PatronID, FirstName, LastName, MembershipExpiration) VALUES
(0001, 'John', 'Doe', '2027-04-01'),
(0002, 'Jane', 'Smith', '2027-10-07'),
(0003, 'Alice', 'Johnson', '2027-09-12'),
(0004, 'Bob', 'Brown',  '2027-12-15),
(0005, 'Charlie', 'Davis', '2026-05-04);

INSERT into Item_Copy (Item_CopyID, ItemType, Status) VALUES
(0001, 'Book','Open'
(0002, 'Recording', 'CheckedOut')
(0003, 'CD','Open'),
(0004, 'CD', 'CheckedOut ),
(0005, 'Book,'Open');

INSERT into CheckOut (CheckOutId, CheckOutDate, DueDate, PatronId, Item_CopyID) VALUES
(0001, '2023-01-15', '2023-02-15', 0001, 0001),
(0002, '2023-01-20', '2023-02-20', 0002, 0002),
(0003, '2023-01-25', '2023-02-25', 0003, 0003),
(0004, '2023-02-01', '2023-03-01', 0004, 0004),
(0005, '2023-02-05', '2023-03-05', 0005, 0005);
