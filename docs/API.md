# Eversales — Backend API Reference (concise)

Base URL (local): `http://localhost/<project-folder>/backend/src/api/<file>.php`

Notes:
- Most endpoints expect either `GET` query parameters or `POST` JSON body / form-data depending on the action.
- Authentication: endpoints that require a logged-in user rely on PHP sessions (start a session after `auth.php?action=login`).
- File upload endpoints use `multipart/form-data`.

---

## `auth.php`
- POST `auth.php?action=login` — body JSON: `{ "email": "...", "password": "..." }` — logs user in, returns `user` object and sets session.
- POST `auth.php?action=register` — body JSON: `{ "fullName": "...", "email": "...", "password": "...", "phoneNumber": "..." }` — creates customer account.
- GET/POST `auth.php?action=logout` — clears session.

## `products.php`
- GET `products.php?action=get-all` — returns list of visible products (images concatenated), respects seller visibility.
- GET `products.php?action=get-single&product_id=<id>` — returns full product details and images.
- GET `products.php?action=search&q=<query>` — search by name/description/seller.

Response format: JSON `{ success: true|false, products|product: ..., message?: ... }`.

## `seller.php`
- POST `seller.php?action=become-seller` — convert logged-in user to `seller` role.
- POST `seller.php?action=add-product` — `multipart/form-data` fields: `name`, `description`, `price`, `wholesale_price` (optional), `min_wholesale_qty` (optional), `stock`, `images[]` (up to 5). Creates product (status `pending`).
- POST `seller.php?action=update-product` — `multipart/form-data` with `product_id` and updated fields (same as add). Requires seller ownership.
- GET `seller.php?action=get-seller-products` — returns products for logged-in seller.
- POST `seller.php?action=delete-product` — JSON body `{ "product_id": <id> }` — deletes product if owned by seller.

## `orders.php`
- POST `orders.php?action=create` — body JSON: `{ "items": [{ "productId": <id>, "quantity": <n>, "purchaseType": "retail|wholesale" }], "paymentMethod": "credit_card|paypal|bank_transfer" }` — creates order, validates stock and MOQ, updates stock, returns `order_id` and `total_amount`.
- GET `orders.php?action=my-orders` — returns orders for logged-in user (default action).
- GET `orders.php?action=admin-dashboard` — admin-only overview (stats, all orders and users).

## `image-upload.php`
- POST `image-upload.php` (form-data) with `action=upload-product-image`, `product_id`, and `image` file — compresses (GD) when available, saves file and metadata.
- POST `image-upload.php` with `action=delete-image` and `image_id` (form-data) — deletes image if seller owns it.

## `asset.php`
- GET `asset.php?path=uploads/products/<filename>` — serves files from `backend/uploads/` only. Use this to fetch product images safely through PHP.

## `admin.php`
All admin endpoints require `$_SESSION['user_role'] === 'admin'`.
- GET `admin.php?action=overview` — returns stats, users, sellers, products, orders, banners, and reports.
- POST `admin.php?action=update-user-role` — JSON `{ "user_id": <id>, "role": "admin|customer|seller" }`.
- POST `admin.php?action=update-product-status` — JSON `{ "product_id": <id>, "status": "pending|approved|rejected" }`.
- POST `admin.php?action=update-order-status` — JSON `{ "order_id": <id>, "status": "pending|completed|cancelled" }`.
- POST `admin.php?action=save-banner` — JSON with banner fields to insert/update.
- POST `admin.php?action=delete-banner` — JSON `{ "banner_id": <id> }`.

---

Usage tips
- Start by calling `auth.php?action=login` to create a session for protected endpoints.
- For file uploads use `multipart/form-data` and ensure `uploads/products/` is writable.
- When testing via curl, use `-c` and `-b` to persist cookies (PHP session).

Example curl login and get products:

```bash
# login (save cookies)
curl -c cookies.txt -H "Content-Type: application/json" -X POST -d '{"email":"user@example.com","password":"secret"}' "http://localhost/<project>/backend/src/api/auth.php?action=login"

# get products using saved cookies
curl -b cookies.txt "http://localhost/<project>/backend/src/api/products.php?action=get-all"
```
