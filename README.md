# Eversales

Eversales is a PHP/MySQL based web application that supports both retail and wholesale buying and selling (MOQ support, quantity-based pricing). This repo contains the backend (PHP), frontend static pages, and database schema used for the project.

## Tech stack
- Backend: PHP (procedural), files under `backend/`
- Frontend: HTML, CSS, JavaScript, Bootstrap, files under `frontend/`
- Database: MySQL (schema in `database/schema.sql`)
- Dev environment: XAMPP / WAMP on Windows

## Quick setup (local)
1. Install XAMPP (or WAMP) and start Apache + MySQL.
2. Place the project folder inside XAMPP's `htdocs` (recommended name: `eversales` — avoid spaces).
3. Import the database schema using phpMyAdmin: open `database/schema.sql` and run it.
4. Configure database connection: edit `backend/config.php` and update DB host, name, user, and password.
5. Ensure upload folders are writable by the webserver: `backend/uploads/` and `backend/uploads/products/`.
6. (Optional) Run seed scripts if needed:

```powershell
php backend/seed_images.php
``` 

## Run the app
- Open the frontend in your browser:

```
http://localhost/<your-project-folder>/frontend/index.html
```

- Admin and seller pages are under the `frontend/` folder (example: `admin-login.html`, `seller-dashboard.html`).

## API / Backend notes
Backend API endpoints are in `backend/src/api/` and include (not exhaustive):
- `admin.php` — admin actions
- `asset.php` — asset handling
- `auth.php` — authentication (login/signup)
- `image-upload.php` — upload images
- `orders.php` — order handling
- `products.php` — product CRUD and listing
- `seller.php` — seller-specific actions

Configuration and helper utilities are in `backend/util/` (for example `Database.php`). Adjust or extend these when developing.

## Development tips
- Use VS Code and enable PHP and PHP Intelephense extensions for a better developer experience.
- If you rename the project folder, update any hard-coded paths in `backend` files.
- Keep backups before running seed scripts or destructive SQL.

## Where to start reading code
- Backend entry points: `backend/src/api/` files
- DB schema: `database/schema.sql`
- Frontend pages: `frontend/index.html`, `frontend/admin-login.html`, `frontend/admin-dashboard.html`.

## License & Contact
This project is academic coursework. For questions or contributions, contact the original authors listed in `Final_document - Copy.txt`.
