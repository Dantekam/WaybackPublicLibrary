CREATE TABLE Patron
(
  PatronID INT NOT NULL PRIMARY KEY,
  FirstName VARCHAR(50) NOT NULL,
  LastName VARCHAR(50) NOT NULL,
  MembershipExpiration DATE NOT NULL
);

CREATE TABLE Item_Copy
(
  ItemCopyID INT NOT NULL PRIMARY KEY,
  ItemType VARCHAR(200) NOT NULL,
  Status VARCHAR(50) NOT NULL,
);

CREATE TABLE CheckOut
(
  CheckOutID INT NOT NULL PRIMARY KEY,
  CheckOutDate DATE NOT NULL,
  DueDate DATE NULL,
  PatronID INT NOT NULL,
  ItemCopyID INT NOT NULL,
  Constraint FK_CheckOut_Patron FOREIGN KEY (PatronID) REFERENCES Patron(PatronID),
  Constraint FK_CheckOut_Item_Copy FOREIGN KEY (ItemCopyID) REFERENCES Item_Copy(ItemCopyID)
);
