# Frontend Guide — Eversales

This document summarizes the frontend pages, user flows, and developer notes to help contributors understand and extend the UI.

## Pages (location: `frontend/`)
- `index.html` — Public product listing and search; main storefront for buyers.
- `login.html` — Login and registration UI used by customers and sellers.
- `dashboard.html` — Buyer dashboard (order history, profile).
- `admin-login.html` — Admin login page.
- `admin-dashboard.html` — Admin control panel (user/product/order management).
- `seller-dashboard.html` — Seller dashboard for product management and orders.

## Key frontend assets
- `frontend/assets/css/style.css` — Main styling.
- `frontend/assets/js/api.js` — Core API helper and auth/session helpers. Note: `BACKEND_ORIGIN` is configured here and must match your local server.
- `frontend/assets/js/auth.js` — Login/registration UI glue.
- `frontend/assets/js/products.js` — Product listing and rendering logic.
- `frontend/assets/js/cart.js` — Shopping cart actions and local storage management.
- `frontend/assets/js/order.js` — Order creation / checkout behavior.
- `frontend/assets/js/image-upload.js` & `seller-api.js` — Seller product and image upload flows.
- `frontend/assets/js/admin.js` — Admin-side AJAX interactions.

## Typical user flows
- Buyer browse → add to cart → checkout:
  - Open `index.html`, use search or product cards (data fetched from `products.php?action=get-all`).
  - Add items to cart (stored in localStorage), then proceed to checkout which POSTs to `orders.php?action=create`.

- Seller add product:
  - Login via `login.html`, become seller by calling `seller.php?action=become-seller`.
  - Use `seller-dashboard.html` to add product (multipart/form-data POST to `seller.php?action=add-product`), then upload images (either combined or via `image-upload.php`).

- Admin moderation:
  - Login as admin, open `admin-dashboard.html` which calls `admin.php?action=overview`.
  - Use admin UI to change product status (`update-product-status`) or user roles (`update-user-role`).

## Developer notes
- API base URL: open `frontend/assets/js/api.js` and set `BACKEND_ORIGIN` to your running backend host (e.g. `http://localhost` or `http://localhost:80`). The frontend uses `credentials: 'include'` for session cookies.
- Session handling: the app stores a simple `auth_token` + user fields in `localStorage` for UI; the real protection is PHP session on the backend.
- Asset loading: product images are served through `asset.php` using `resolveBackendAssetUrl()` in `api.js` — do not hardcode direct filesystem links.
- Uploads: ensure `uploads/products/` permissions allow the webserver to write files.
- Styling: the UI uses Bootstrap but is customized in `assets/css/style.css` — prefer small CSS changes or add utility classes.

## Testing and debugging
- Open browser devtools (Console / Network) to inspect API calls. Look for CORS or cookie issues when `credentials: 'include'` is used.
- To test protected endpoints with curl, persist cookies with `-c`/`-b` as described in `docs/API.md`.

## Useful quick commands (Windows, PowerShell)
```powershell
# Start XAMPP control panel (GUI) and ensure Apache + MySQL are running.
# From project root you can run PHP scripts manually if needed:
php backend/seed_images.php
```

## Where to change common behavior
- Change `BACKEND_ORIGIN` in `frontend/assets/js/api.js` for API host.
- To change maximum images per product, update both frontend validation (`seller-api.js` / `image-upload.js`) and backend limits in `backend/src/api/seller.php` and `backend/src/api/image-upload.php`.

If you want, I can also add short inline comments to the main frontend JS files to explain key functions — tell me which file to annotate first.
