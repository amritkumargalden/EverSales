# Backend Setup Guide - PHP Database Connection

## Overview
This backend provides a PHP database utility and API endpoints to connect your EverSales frontend to the MySQL database.

## File Structure
```
backend/
├── src/
│   ├── util/
│   │   └── Database.php          # Database connection class
│   ├── api/
│   │   └── auth.php              # Authentication endpoints
│   └── config.php                # Configuration file
```

## Setup Instructions

### 1. Configure Database Credentials
Edit `backend/src/config.php` and update your MySQL credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'eversales');
define('DB_USER', 'root');
define('DB_PASSWORD', 'your_password');
```

### 2. Create the Database
Run the SQL script in `database/schema.sql` to create the database and tables:
```bash
mysql -u root -p < database/schema.sql
```

### 3. Set Up PHP Server
You can use PHP's built-in server or Apache/Nginx.

**Using PHP's built-in server:**
```bash
cd backend/src
php -S localhost:8000
```

Then your API will be accessible at: `http://localhost:8000/api/auth.php`

## Usage

### Database Class Methods

#### Connect
```php
$database = new Database();
$database->connect();
```

#### Query (SELECT)
```php
$result = $database->getResults("SELECT * FROM users");
```

#### Get Single Row
```php
$user = $database->getRow("SELECT * FROM users WHERE id = 1");
```

#### Execute (INSERT/UPDATE/DELETE)
```php
$result = $database->execute("INSERT INTO users ...");
if ($result['success']) {
    echo "Inserted! ID: " . $result['insert_id'];
}
```

#### Escape String (SQL Injection Prevention)
```php
$email = $database->escape($_POST['email']);
```

#### Prepared Statements
```php
$stmt = $database->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
```

### API Endpoints

#### Login
**POST** `/api/auth.php?action=login`
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

#### Register
**POST** `/api/auth.php?action=register`
```json
{
  "fullName": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "phoneNumber": "+1234567890"
}
```

#### Logout
**POST** `/api/auth.php?action=logout`

## Connecting from Frontend

Update your `frontend/assets/js/api.js` to point to your PHP backend:

```javascript
const API_URL = 'http://localhost:8000/api/auth.php';

async function login(email, password) {
    const response = await fetch(`${API_URL}?action=login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
    });
    return await response.json();
}

async function register(fullName, email, password, confirmPassword, phoneNumber) {
    const response = await fetch(`${API_URL}?action=register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ fullName, email, password, phoneNumber })
    });
    return await response.json();
}
```

## Security Notes

⚠️ **Important Security Tips:**
- Always hash passwords using `password_hash()` before storing
- Use prepared statements to prevent SQL injection
- Validate and escape all user inputs
- Use HTTPS in production
- Implement rate limiting on API endpoints
- Add CORS configuration as needed
- Use environment variables for sensitive data
- Add authentication middleware for protected routes

## Troubleshooting

### Connection Failed
- Verify MySQL server is running
- Check credentials in `config.php`
- Ensure the `eversales` database exists

### CORS Errors
- Add `Access-Control-Allow-Origin` headers (already in auth.php)
- Adjust based on your frontend URL

### Session Issues
- Ensure `session_start()` is called before sending headers
- Check PHP session save path permissions


# Detached (recommended)
Start-Process php -ArgumentList '-S','localhost:8000' -WorkingDirectory 'c:\Users\amrit\Desktop\Bachelor\BIT5\Web\Project V\source code\backend\src'
Start-Process python -ArgumentList '-m','http.server','5500','--directory','frontend' -WorkingDirectory 'c:\Users\amrit\Desktop\Bachelor\BIT5\Web\Project V\source code'

# Foreground (interactive terminals)
cd 'c:\Users\amrit\Desktop\Bachelor\BIT5\Web\Project V\source code\backend\src'; php -S localhost:8000
cd 'c:\Users\amrit\Desktop\Bachelor\BIT5\Web\Project V\source code'; python -m http.server 5500 --directory frontend