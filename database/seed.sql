USE eversales;

INSERT INTO users (full_name, email, password, role, phone_number) VALUES
('Admin', 'amritkumargaldentamang@gmail.com', 'amrikumargalden', 'admin', '1234567890'),
('John Doe', 'johndoe@example.com', 'password123', 'customer', '0987654321');

INSERT INTO products (name, description, price, stock)
VALUES
('Wireless Mouse', '2.4G wireless mouse', 799.00, 50),
('Mechanical Keyboard', 'Blue switch keyboard', 2499.00, 25),
('USB-C Charger', '65W fast charger', 1499.00, 40);