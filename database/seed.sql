USE eversales;

INSERT INTO users (full_name, email, password, role, phone_number) VALUES
('Admin', 'amritkumargaldentamang@gmail.com', 'AdminPass123', 'admin', '1234567890'),
('EverSales Seller', 'seller@example.com', 'SellerPass123', 'seller', '9812345678'),
('John Doe', 'johndoe@example.com', 'JohnPass123', 'customer', '0987654321');

INSERT INTO products (seller_id, name, description, price, wholesale_price, min_wholesale_qty, stock, product_status)
VALUES
(2, 'Wireless Mouse', '2.4G wireless mouse', 799.00, 650.00, 5, 50, 'approved'),
(2, 'Mechanical Keyboard', 'Blue switch keyboard', 2499.00, 2100.00, 5, 25, 'approved'),
(2, 'USB-C Charger', '65W fast charger', 1499.00, 1200.00, 5, 40, 'approved');

INSERT INTO banners (title, subtitle, image_url, target_url, is_active, sort_order)
VALUES
('Mega Deals Are Live', 'Curated offers for your next EverSales order', '', 'index.html', 1, 1);
