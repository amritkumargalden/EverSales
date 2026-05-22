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

        $productStmt = $connection->prepare("SELECT product_id, name, price, stock FROM products WHERE product_id = ? LIMIT 1");
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

            $price = (float) $product['price'];
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