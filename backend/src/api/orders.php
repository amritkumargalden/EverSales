<?php
/**
 * Orders API
 * Handles order creation, user order history, and admin order overview
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
ensureFeedbackSchema($connection);

$action = $_GET['action'] ?? 'my-orders';
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? 'customer';

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    switch ($action) {
        case 'create':
            handleCreateOrder($database, $connection, (int) $userId);
            break;
        case 'submit-feedback':
            handleSubmitFeedback($connection, (int) $userId);
            break;
        case 'admin-dashboard':
            if ($userRole !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            handleAdminDashboard($database, $connection);
            break;
        case 'my-orders':
        default:
            handleUserOrders($connection, (int) $userId);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$database->close();

function ensureFeedbackSchema($connection) {
    $table = $connection->query("SHOW TABLES LIKE 'order_feedback'");
    if ($table && $table->num_rows === 0) {
        $connection->query("
            CREATE TABLE order_feedback (
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
        return;
    }

    $resolvedColumn = $connection->query("SHOW COLUMNS FROM order_feedback LIKE 'is_resolved'");
    if ($resolvedColumn && $resolvedColumn->num_rows === 0) {
        $connection->query("ALTER TABLE order_feedback ADD COLUMN is_resolved TINYINT(1) NOT NULL DEFAULT 0");
    }
}

function handleCreateOrder($database, $connection, $userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $items = $input['items'] ?? [];
    $paymentMethod = $input['paymentMethod'] ?? 'bank_transfer';

    if (!is_array($items) || count($items) === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        return;
    }

    $allowedPaymentMethods = ['credit_card', 'paypal', 'bank_transfer'];
    if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
        $paymentMethod = 'bank_transfer';
    }

    $database->beginTransaction();

    try {
        $totalAmount = 0;

        $orderStmt = $connection->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (?, 0, 'pending')");
        if (!$orderStmt) {
            throw new Exception('Failed to prepare order insert');
        }
        $orderStmt->bind_param('i', $userId);
        $orderStmt->execute();
        $orderId = $connection->insert_id;
        $orderStmt->close();

        $productStmt = $connection->prepare("SELECT product_id, name, price, wholesale_price, min_wholesale_qty, stock FROM products WHERE product_id = ? LIMIT 1");
        $itemStmt = $connection->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stockStmt = $connection->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
        $paymentStmt = $connection->prepare("INSERT INTO payments (order_id, amount, payment_method, status) VALUES (?, ?, ?, 'completed')");

        if (!$productStmt || !$itemStmt || !$stockStmt || !$paymentStmt) {
            throw new Exception('Failed to prepare order statements');
        }

        foreach ($items as $item) {
            $productId = (int) ($item['productId'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                throw new Exception('Invalid cart item');
            }

            $productStmt->bind_param('i', $productId);
            $productStmt->execute();
            $productResult = $productStmt->get_result();
            $product = $productResult ? $productResult->fetch_assoc() : null;

            if (!$product) {
                throw new Exception('Product not found');
            }

            if ((int) $product['stock'] < $quantity) {
                throw new Exception('Insufficient stock for ' . $product['name']);
            }

            $purchaseType = ($item['purchaseType'] ?? 'retail') === 'wholesale' ? 'wholesale' : 'retail';
            $minWholesaleQty = (int)($product['min_wholesale_qty'] ?? 1);

            if ($purchaseType === 'wholesale' && $quantity < $minWholesaleQty) {
                throw new Exception('Wholesale quantity for ' . $product['name'] . ' must be at least ' . $minWholesaleQty);
            }

            $price = $purchaseType === 'wholesale'
                ? (float) $product['wholesale_price']
                : (float) $product['price'];
            $lineTotal = $price * $quantity;
            $totalAmount += $lineTotal;

            $itemStmt->bind_param('iiid', $orderId, $productId, $quantity, $price);
            if (!$itemStmt->execute()) {
                throw new Exception('Failed to save order item');
            }

            $stockStmt->bind_param('ii', $quantity, $productId);
            if (!$stockStmt->execute()) {
                throw new Exception('Failed to update stock');
            }
        }

        $updateOrderStmt = $connection->prepare("UPDATE orders SET total_amount = ?, status = 'completed' WHERE order_id = ?");
        if (!$updateOrderStmt) {
            throw new Exception('Failed to prepare order update');
        }
        $updateOrderStmt->bind_param('di', $totalAmount, $orderId);
        $updateOrderStmt->execute();
        $updateOrderStmt->close();

        $paymentStmt->bind_param('ids', $orderId, $totalAmount, $paymentMethod);
        if (!$paymentStmt->execute()) {
            throw new Exception('Failed to save payment');
        }

        $productStmt->close();
        $itemStmt->close();
        $stockStmt->close();
        $paymentStmt->close();

        $database->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Order placed successfully',
            'order_id' => $orderId,
            'total_amount' => round($totalAmount, 2)
        ]);
    } catch (Exception $e) {
        $database->rollback();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleUserOrders($connection, $userId) {
    $orders = fetchOrdersForUser($connection, $userId);

    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
}

function handleSubmitFeedback($connection, $userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = (int)($input['order_id'] ?? 0);
    $type = $input['feedback_type'] ?? '';
    $rating = isset($input['rating']) ? (int)$input['rating'] : null;
    $message = trim($input['message'] ?? '');

    if ($orderId <= 0 || !in_array($type, ['review', 'complaint'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid feedback details']);
        return;
    }

    if ($message === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Feedback message is required']);
        return;
    }

    if ($type === 'review') {
        if ($rating === null || $rating < 1 || $rating > 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
            return;
        }
    } else {
        $rating = null;
    }

    $orderStmt = $connection->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1");
    if (!$orderStmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to verify order']);
        return;
    }

    $orderStmt->bind_param('ii', $orderId, $userId);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    $orderStmt->close();

    if (!$orderResult || $orderResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }

    $checkStmt = $connection->prepare("SELECT feedback_id FROM order_feedback WHERE order_id = ? AND user_id = ? AND feedback_type = ? LIMIT 1");
    if ($checkStmt) {
        $checkStmt->bind_param('iis', $orderId, $userId, $type);
        $checkStmt->execute();
        $existing = $checkStmt->get_result();
        $checkStmt->close();

        if ($existing && $existing->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Feedback already submitted for this order']);
            return;
        }
    }

    $stmt = $connection->prepare("INSERT INTO order_feedback (order_id, user_id, feedback_type, rating, message) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save feedback']);
        return;
    }

    if ($rating === null) {
        $nullValue = null;
        $stmt->bind_param('iisis', $orderId, $userId, $type, $nullValue, $message);
    } else {
        $stmt->bind_param('iisis', $orderId, $userId, $type, $rating, $message);
    }

    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Feedback submitted']);
}

function handleAdminDashboard($database, $connection) {
    $orders = fetchOrdersForUser($connection, null);
    $users = $database->getResults("SELECT id, full_name, email, role, created_at FROM users ORDER BY created_at DESC");

    $stats = [
        'totalOrders' => 0,
        'totalRevenue' => 0,
        'pendingOrders' => 0,
        'completedOrders' => 0,
        'totalUsers' => count($users)
    ];

    foreach ($orders as $order) {
        $stats['totalOrders'] += 1;
        $stats['totalRevenue'] += (float) $order['total_amount'];

        if ($order['status'] === 'pending') {
            $stats['pendingOrders'] += 1;
        }

        if ($order['status'] === 'completed') {
            $stats['completedOrders'] += 1;
        }
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'users' => $users,
        'orders' => $orders
    ]);
}

function fetchOrdersForUser($connection, $userId = null) {
    if ($userId === null) {
        $orderStmt = $connection->prepare("SELECT o.order_id, o.user_id, u.full_name AS user_name, u.email AS user_email, o.total_amount, o.status, o.created_at, o.updated_at FROM orders o INNER JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC");
    } else {
        $orderStmt = $connection->prepare("SELECT o.order_id, o.user_id, u.full_name AS user_name, u.email AS user_email, o.total_amount, o.status, o.created_at, o.updated_at FROM orders o INNER JOIN users u ON u.id = o.user_id WHERE o.user_id = ? ORDER BY o.created_at DESC");
    }

    if (!$orderStmt) {
        throw new Exception('Failed to prepare order query');
    }

    if ($userId !== null) {
        $orderStmt->bind_param('i', $userId);
    }

    $orderStmt->execute();
    $ordersResult = $orderStmt->get_result();
    $orders = [];

    $itemStmt = $connection->prepare("SELECT oi.order_item_id, oi.product_id, p.name AS product_name, oi.quantity, oi.price, (oi.quantity * oi.price) AS line_total FROM order_items oi INNER JOIN products p ON p.product_id = oi.product_id WHERE oi.order_id = ? ORDER BY oi.order_item_id ASC");

    if (!$itemStmt) {
        $orderStmt->close();
        throw new Exception('Failed to prepare order items query');
    }

    while ($order = $ordersResult->fetch_assoc()) {
        $orderId = (int) $order['order_id'];
        $itemStmt->bind_param('i', $orderId);
        $itemStmt->execute();
        $itemsResult = $itemStmt->get_result();

        $items = [];
        while ($item = $itemsResult->fetch_assoc()) {
            $items[] = $item;
        }

        $order['items'] = $items;
        $orders[] = $order;
    }

    $orderStmt->close();
    $itemStmt->close();

    return $orders;
}
