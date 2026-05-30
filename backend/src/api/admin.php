<?php
/**
 * Admin API
 * Provides marketplace administration data and actions.
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

$database = new Database();
$connection = $database->connect();
$action = $_GET['action'] ?? 'overview';

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

try {
    ensureAdminSchema($connection);

    switch ($action) {
        case 'overview':
            handleOverview($database, $connection);
            break;
        case 'update-seller-status':
            handleUpdateSellerStatus($connection);
            break;
        case 'update-user-role':
            handleUpdateUserRole($connection);
            break;
        case 'update-product-status':
            handleUpdateProductStatus($connection);
            break;
        case 'update-order-status':
            handleUpdateOrderStatus($connection);
            break;
        case 'update-feedback-status':
            handleUpdateFeedbackStatus($connection);
            break;
        case 'save-banner':
            handleSaveBanner($connection);
            break;
        case 'delete-banner':
            handleDeleteBanner($connection);
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$database->close();

function ensureAdminSchema($connection) {
    $productStatus = $connection->query("SHOW COLUMNS FROM products LIKE 'product_status'");
    if ($productStatus && $productStatus->num_rows === 0) {
        $connection->query("ALTER TABLE products ADD COLUMN product_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved'");
    }

    $sellerBlocked = $connection->query("SHOW COLUMNS FROM users LIKE 'is_blocked'");
    if ($sellerBlocked && $sellerBlocked->num_rows === 0) {
        $connection->query("ALTER TABLE users ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0");
    }

    $connection->query("
        CREATE TABLE IF NOT EXISTS banners (
            banner_id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            subtitle VARCHAR(255),
            image_url VARCHAR(500),
            target_url VARCHAR(500),
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $connection->query("
        CREATE TABLE IF NOT EXISTS order_feedback (
            feedback_id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            user_id INT NOT NULL,
            feedback_type ENUM('review', 'complaint') NOT NULL,
            rating INT NULL,
            message TEXT NOT NULL,
            is_resolved TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $resolvedColumn = $connection->query("SHOW COLUMNS FROM order_feedback LIKE 'is_resolved'");
    if ($resolvedColumn && $resolvedColumn->num_rows === 0) {
        $connection->query("ALTER TABLE order_feedback ADD COLUMN is_resolved TINYINT(1) NOT NULL DEFAULT 0");
    }
}

function handleOverview($database, $connection) {
    $users = $database->getResults("SELECT id, full_name, email, role, phone_number, created_at FROM users ORDER BY created_at DESC");
    $products = $database->getResults("
        SELECT p.product_id, p.name, p.description, p.price, p.stock, p.product_status, p.created_at, u.full_name AS seller_name
        FROM products p
        LEFT JOIN users u ON u.id = p.seller_id
        ORDER BY p.created_at DESC
    ");
    $orders = $database->getResults("
        SELECT o.order_id, o.user_id, u.full_name AS user_name, u.email AS user_email, o.total_amount, o.status, o.created_at, o.updated_at
        FROM orders o
        INNER JOIN users u ON u.id = o.user_id
        ORDER BY o.created_at DESC
    ");
    $feedback = $database->getResults("
        SELECT f.feedback_id, f.order_id, f.user_id, f.feedback_type, f.rating, f.message, f.is_resolved, f.created_at,
               u.full_name AS user_name, u.email AS user_email
        FROM order_feedback f
        INNER JOIN users u ON u.id = f.user_id
        ORDER BY f.created_at DESC
    ");
    $banners = $database->getResults("SELECT * FROM banners ORDER BY sort_order ASC, created_at DESC");
    $sellers = $database->getResults("
        SELECT
            u.id,
            u.full_name,
            u.email,
            u.is_blocked,
            COUNT(DISTINCT p.product_id) AS product_count,
            COALESCE(SUM(p.stock), 0) AS total_stock,
            COUNT(DISTINCT oi.order_id) AS order_count,
            COALESCE(SUM(oi.quantity * oi.price), 0) AS revenue
        FROM users u
        LEFT JOIN products p ON p.seller_id = u.id
        LEFT JOIN order_items oi ON oi.product_id = p.product_id
        WHERE u.role = 'seller'
        GROUP BY u.id
        ORDER BY revenue DESC, product_count DESC
    ");

    $reports = buildSalesReports($database);
    $stats = [
        'totalUsers' => count($users),
        'totalSellers' => count($sellers),
        'totalProducts' => count($products),
        'totalOrders' => count($orders),
        'totalRevenue' => $reports['totalRevenue'],
        'pendingProducts' => count(array_filter($products, fn($p) => ($p['product_status'] ?? 'approved') === 'pending'))
    ];

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'users' => $users,
        'sellers' => $sellers,
        'products' => $products,
        'orders' => $orders,
        'feedback' => $feedback,
        'banners' => $banners,
        'reports' => $reports
    ]);
}

function buildSalesReports($database) {
    $summary = $database->getRow("
        SELECT
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) AS total_revenue,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_orders,
            COALESCE(AVG(CASE WHEN status = 'completed' THEN total_amount END), 0) AS average_order_value
        FROM orders
    ");

    $topProducts = $database->getResults("
        SELECT p.name AS product_name, SUM(oi.quantity) AS units_sold, SUM(oi.quantity * oi.price) AS revenue
        FROM order_items oi
        INNER JOIN products p ON p.product_id = oi.product_id
        INNER JOIN orders o ON o.order_id = oi.order_id
        WHERE o.status = 'completed'
        GROUP BY p.product_id
        ORDER BY revenue DESC
        LIMIT 5
    ");

    $sellerRevenue = $database->getResults("
        SELECT u.full_name AS seller_name, SUM(oi.quantity * oi.price) AS revenue
        FROM order_items oi
        INNER JOIN products p ON p.product_id = oi.product_id
        INNER JOIN users u ON u.id = p.seller_id
        INNER JOIN orders o ON o.order_id = oi.order_id
        WHERE o.status = 'completed'
        GROUP BY u.id
        ORDER BY revenue DESC
        LIMIT 5
    ");

    return [
        'totalRevenue' => (float)($summary['total_revenue'] ?? 0),
        'completedOrders' => (int)($summary['completed_orders'] ?? 0),
        'averageOrderValue' => (float)($summary['average_order_value'] ?? 0),
        'topProducts' => $topProducts,
        'sellerRevenue' => $sellerRevenue
    ];
}

function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

function handleUpdateUserRole($connection) {
    $input = getJsonInput();
    $userId = (int)($input['user_id'] ?? 0);
    $role = $input['role'] ?? '';
    $allowed = ['admin', 'customer', 'seller'];

    if ($userId <= 0 || !in_array($role, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid user role update']);
        return;
    }

    $stmt = $connection->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param('si', $role, $userId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'User role updated']);
}

function handleUpdateSellerStatus($connection) {
    $input = getJsonInput();
    $sellerId = (int)($input['seller_id'] ?? 0);
    $isBlocked = (int)($input['is_blocked'] ?? 0);

    if ($sellerId <= 0 || ($isBlocked !== 0 && $isBlocked !== 1)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid seller status update']);
        return;
    }

    $stmt = $connection->prepare("UPDATE users SET is_blocked = ? WHERE id = ? AND role = 'seller'");
    $stmt->bind_param('ii', $isBlocked, $sellerId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Seller status updated']);
}

function handleUpdateProductStatus($connection) {
    $input = getJsonInput();
    $productId = (int)($input['product_id'] ?? 0);
    $status = $input['status'] ?? '';
    $allowed = ['pending', 'approved', 'rejected'];

    if ($productId <= 0 || !in_array($status, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid product moderation update']);
        return;
    }

    $stmt = $connection->prepare("UPDATE products SET product_status = ? WHERE product_id = ?");
    $stmt->bind_param('si', $status, $productId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Product status updated']);
}

function handleUpdateOrderStatus($connection) {
    $input = getJsonInput();
    $orderId = (int)($input['order_id'] ?? 0);
    $status = $input['status'] ?? '';
    $allowed = ['pending', 'completed', 'cancelled'];

    if ($orderId <= 0 || !in_array($status, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid order status update']);
        return;
    }

    $stmt = $connection->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->bind_param('si', $status, $orderId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Order status updated']);
}

function handleUpdateFeedbackStatus($connection) {
    $input = getJsonInput();
    $feedbackId = (int)($input['feedback_id'] ?? 0);
    $isResolved = (int)($input['is_resolved'] ?? 0);

    if ($feedbackId <= 0 || ($isResolved !== 0 && $isResolved !== 1)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid feedback update']);
        return;
    }

    $stmt = $connection->prepare("UPDATE order_feedback SET is_resolved = ? WHERE feedback_id = ?");
    $stmt->bind_param('ii', $isResolved, $feedbackId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Feedback status updated']);
}

function handleSaveBanner($connection) {
    $input = getJsonInput();
    $bannerId = (int)($input['banner_id'] ?? 0);
    $title = trim($input['title'] ?? '');
    $subtitle = trim($input['subtitle'] ?? '');
    $imageUrl = trim($input['image_url'] ?? '');
    $targetUrl = trim($input['target_url'] ?? '');
    $sortOrder = (int)($input['sort_order'] ?? 0);
    $isActive = (int)($input['is_active'] ?? 0);

    if ($title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Banner title is required']);
        return;
    }

    if ($bannerId > 0) {
        $stmt = $connection->prepare("UPDATE banners SET title = ?, subtitle = ?, image_url = ?, target_url = ?, sort_order = ?, is_active = ? WHERE banner_id = ?");
        $stmt->bind_param('ssssiii', $title, $subtitle, $imageUrl, $targetUrl, $sortOrder, $isActive, $bannerId);
    } else {
        $stmt = $connection->prepare("INSERT INTO banners (title, subtitle, image_url, target_url, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssii', $title, $subtitle, $imageUrl, $targetUrl, $sortOrder, $isActive);
    }

    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Banner saved']);
}

function handleDeleteBanner($connection) {
    $input = getJsonInput();
    $bannerId = (int)($input['banner_id'] ?? 0);

    if ($bannerId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Banner ID required']);
        return;
    }

    $stmt = $connection->prepare("DELETE FROM banners WHERE banner_id = ?");
    $stmt->bind_param('i', $bannerId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Banner deleted']);
}
?>
