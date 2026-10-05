INSERT into Customers (CustomerId, FirstName, LastName, Email, PhoneNumber) VALUES
(0001, 'John', 'Doe', 'john.doe@library.com', '100-555-1234'),
(0002, 'Jane', 'Smith', 'jane.smith@library.com', '900-555-5678'),
(0003, 'Alice', 'Johnson', 'alice.johnson@library.com', '919-555-9012'),
(0004, 'Bob', 'Brown', 'bob.brown@library.com', '910-555-3456'),
(0005, 'Charlie', 'Davis', 'charlie.davis@library.com', '120-555-7890');

INSERT into Books (BookId, Title, Author, Genre, ReleaseDate) VALUES
(0001, 'The Great Gatsby', 'F. Scott Fitzgerald', 'Fiction', '1925-04-10'),
(0002, 'To Kill a Mockingbird', 'Harper Lee', 'Fiction', '1960-07-11'),
(0003, '1984', 'George Orwell', 'Dystopian', '1949-06-08'),
(0004, 'Pride and Prejudice', 'Jane Austen', 'Romance', '1813-01-28'),
(0005, 'The Catcher in the Rye', 'J.D. Salinger', 'Fiction', '1951-07-16');

INSERT into CheckOut (OrderId, CheckOutDate, ReturnDate, CustomerId, BookId) VALUES
(0001, '2023-01-15', '2023-02-15', 0001, 0001),
(0002, '2023-01-20', '2023-02-20', 0002, 0002),
(0003, '2023-01-25', '2023-02-25', 0003, 0003),
(0004, '2023-02-01', '2023-03-01', 0004, 0004),
(0005, '2023-02-05', '2023-03-05', 0005, 0005);
