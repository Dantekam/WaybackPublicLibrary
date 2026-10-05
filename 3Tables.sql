CREATE TABLE Customers
(
  CustomerId INT NOT NULL PRIMARY KEY,
  FirstName NVARCHAR(50) NOT NULL,
  LastName NVARCHAR(50) NOT NULL,
  Email NVARCHAR(100) NOT NULL UNIQUE,
  PhoneNumber NVARCHAR(20) NULL 

);

CREATE TABLE Books
(
  BookId INT NOT NULL PRIMARY KEY,
  Title NVARCHAR(200) NOT NULL,
  Author NVARCHAR(100) NOT NULL,
  Genre NVARCHAR(50) NOT NULL,
  ReleaseDate DATETIME NOT NULL
);

CREATE TABLE CheckOut
(
  OrderId INT NOT NULL PRIMARY KEY,
  CheckOutDate DATE NOT NULL,
  ReturnDate DATE NULL,
  CustomerId INT NOT NULL,
  BookId INT NOT NULL,
  Constraint FK_CheckOut_Customer FOREIGN KEY (CustomerId) REFERENCES Customers(CustomerId),
  Constraint FK_CheckOut_Book FOREIGN KEY (BookId) REFERENCES Books(BookId)
);
