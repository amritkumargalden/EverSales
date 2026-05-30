<?php
/**
 * Authentication API Handler
 * Example usage of the Database utility
 */

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../util/Database.php';

$request = isset($_GET['action']) ? $_GET['action'] : '';
$database = null;

try {
    switch ($request) {
        case 'login':
            $database = new Database();
            $database->connect();
            handleLogin($database);
            break;
        case 'register':
            $database = new Database();
            $database->connect();
            handleRegister($database);
            break;
        case 'me':
            handleCurrentUser();
            break;
        case 'logout':
            handleLogout();
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if ($database) {
    $database->close();
}

/**
 * Handle user login
 */
function handleLogin($database) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and password required']);
        return;
    }

    $stmt = $database->prepare("SELECT id, full_name, email, role, password FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare login statement']);
        return;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        return;
    }

    $storedPassword = $user['password'];
    $passwordMatches = password_verify($password, $storedPassword);

    // Legacy compatibility for plaintext seed passwords; upgrade to bcrypt after successful login.
    if (!$passwordMatches && hash_equals((string)$storedPassword, (string)$password)) {
        $passwordMatches = true;
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        $upgradeStmt = $database->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($upgradeStmt) {
            $upgradeStmt->bind_param('si', $newHash, $user['id']);
            $upgradeStmt->execute();
            $upgradeStmt->close();
        }
    }

    if (!$passwordMatches) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        return;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];

    unset($user['password']);

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => $user
    ]);
}

/**
 * Handle user registration
 */
function handleRegister($database) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $full_name = trim($input['fullName'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $phone_number = trim($input['phoneNumber'] ?? '');

    if (!$full_name || !$email || !$password) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Full name, email and password required']);
        return;
    }

    // Check if email already exists
    $checkStmt = $database->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    if (!$checkStmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare email check']);
        return;
    }
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $existingResult = $checkStmt->get_result();
    $existing = $existingResult ? $existingResult->fetch_assoc() : null;
    $checkStmt->close();

    if ($existing) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        return;
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    $insertStmt = $database->prepare("INSERT INTO users (full_name, email, password, phone_number, role) VALUES (?, ?, ?, ?, 'customer')");
    if (!$insertStmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare registration statement']);
        return;
    }
    $insertStmt->bind_param('ssss', $full_name, $email, $hashed_password, $phone_number);
    $ok = $insertStmt->execute();
    $insertId = $insertStmt->insert_id;
    $insertError = $insertStmt->error;
    $insertStmt->close();

    if ($ok) {
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'user_id' => $insertId
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $insertError]);
    }
}

/**
 * Handle user logout
 */
function handleLogout() {
    $_SESSION = [];
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

/**
 * Return the current session user for frontend route protection.
 */
function handleCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        return;
    }

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'] ?? '',
            'full_name' => $_SESSION['user_name'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'customer'
        ]
    ]);
}
