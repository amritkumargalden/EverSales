<?php
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

require_once __DIR__ . '/../util/Database.php';
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? 'get-all';

if ($action === 'get-all') {
    getAllProducts();
} elseif ($action === 'get-single') {
    getSingleProduct();
} elseif ($action === 'search') {
    searchProducts();
} elseif ($action === 'cart-history') {
    saveCartHistory();
} elseif ($action === 'recommendations') {
    getCustomerRecommendations();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get all products with images
 */
function getAllProducts() {
    $db = new Database();
    $db->connect();
    ensureProductModerationColumn($db);

    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $currentUserRole = $_SESSION['user_role'] ?? '';
    $visibilityClause = "p.product_status = 'approved'";

    if ($currentUserRole === 'seller' && $currentUserId > 0) {
        $visibilityClause = "(p.product_status = 'approved' OR (p.seller_id = $currentUserId AND p.product_status IN ('pending', 'approved')) )";
    }
    
    $result = $db->getResults("
        SELECT 
            p.product_id as id, 
            p.name, 
            p.description, 
            p.price, 
            p.wholesale_price,
            p.min_wholesale_qty,
            p.stock, 
            u.full_name as seller_name,
            COUNT(pi.image_id) as image_count,
            GROUP_CONCAT(pi.image_path SEPARATOR '|') as image_paths
        FROM products p 
        LEFT JOIN users u ON p.seller_id = u.id
        LEFT JOIN product_images pi ON p.product_id = pi.product_id
        WHERE $visibilityClause
        GROUP BY p.product_id
        LIMIT 50
    ");
    
    $products = formatProductRows(is_array($result) ? $result : []);
    
    echo json_encode(['success' => true, 'products' => $products, 'total' => count($products)]);
}

/**
 * Get single product with all images
 */
function getSingleProduct() {
    $product_id = $_GET['product_id'] ?? null;
    
    if (!$product_id || !is_numeric($product_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Product ID required']);
        return;
    }
    
    $db = new Database();
    $db->connect();
    ensureProductModerationColumn($db);
    $pid = (int)$product_id;
    
    $product = $db->getRow("
        SELECT 
            p.product_id as id,
            p.name,
            p.description,
            p.price,
            p.wholesale_price,
            p.min_wholesale_qty,
            p.stock,
            p.seller_id,
            u.full_name as seller_name,
            u.email as seller_email
        FROM products p
        LEFT JOIN users u ON p.seller_id = u.id
        WHERE p.product_id = $pid
    ");
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        return;
    }
    
    // Get images
    $images_result = $db->getResults("
        SELECT image_id, image_path, image_name, image_size, uploaded_at 
        FROM product_images 
        WHERE product_id = $pid 
        ORDER BY uploaded_at DESC
    ");
    
    $images = is_array($images_result) ? $images_result : [];
    $image_paths = array_column($images, 'image_path');
    
    echo json_encode([
        'success' => true,
        'product' => [
            'id' => (int)$product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => (float)$product['price'],
            'wholesale_price' => $product['wholesale_price'] !== null ? (float)$product['wholesale_price'] : null,
            'min_wholesale_qty' => $product['min_wholesale_qty'] !== null ? (int)$product['min_wholesale_qty'] : null,
            'stock' => (int)$product['stock'],
            'seller_id' => (int)$product['seller_id'],
            'seller_name' => $product['seller_name'] ?? 'Unknown',
            'seller_email' => $product['seller_email'] ?? '',
            'image_count' => count($images),
            'images' => $images,
            'image_paths' => $image_paths,
            'primary_image' => count($image_paths) > 0 ? $image_paths[0] : null
        ]
    ]);
}

/**
 * Search products by name, description, or seller name
 */
function searchProducts() {
    $query = trim($_GET['q'] ?? '');

    if (strlen($query) < 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Search query must be at least 2 characters']);
        return;
    }

    $db = new Database();
    $db->connect();
    ensureProductModerationColumn($db);

    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $currentUserRole = $_SESSION['user_role'] ?? '';
    $visibilityClause = "p.product_status = 'approved'";

    if ($currentUserRole === 'seller' && $currentUserId > 0) {
        $visibilityClause = "(p.product_status = 'approved' OR (p.seller_id = $currentUserId AND p.product_status IN ('pending', 'approved')) )";
    }

    $likeQuery = '%' . $query . '%';

    $stmt = $db->prepare("SELECT 
            p.product_id as id,
            p.name,
            p.description,
            p.price,
            p.wholesale_price,
            p.min_wholesale_qty,
            p.stock,
            u.full_name as seller_name,
            COUNT(pi.image_id) as image_count,
            GROUP_CONCAT(pi.image_path SEPARATOR '|') as image_paths
        FROM products p
        LEFT JOIN users u ON p.seller_id = u.id
        LEFT JOIN product_images pi ON p.product_id = pi.product_id
        WHERE $visibilityClause
        AND (p.name LIKE ? OR p.description LIKE ? OR u.full_name LIKE ?)
        GROUP BY p.product_id
        ORDER BY p.name ASC
        LIMIT 50");

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare search query']);
        return;
    }

    $stmt->bind_param('sss', $likeQuery, $likeQuery, $likeQuery);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($product = $result->fetch_assoc()) {
        $rows[] = $product;
    }

    $products = formatProductRows($rows);

    $stmt->close();

    echo json_encode([
        'success' => true,
        'products' => $products,
        'total' => count($products),
        'query' => $query
    ]);
}

/**
 * Customer-specific dynamic product sections based on previous checked-out carts.
 */
function getCustomerRecommendations() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        return;
    }

    $currentUserRole = $_SESSION['user_role'] ?? '';
    if ($currentUserRole !== 'customer') {
        echo json_encode([
            'success' => true,
            'has_history' => false,
            'buy_again' => [],
            'recommended' => [],
            'trending' => []
        ]);
        return;
    }

    $db = new Database();
    $connection = $db->connect();
    ensureProductModerationColumn($db);
    $userId = (int)$_SESSION['user_id'];

    $cartHistory = isset($_SESSION['cart_history']) && is_array($_SESSION['cart_history'])
        ? $_SESSION['cart_history']
        : [];
    $cartProductIds = extractCartProductIds($cartHistory);

    $buyAgain = [];
    $recommended = [];

    if (!empty($cartProductIds)) {
        $idList = buildIdList($cartProductIds);
        $buyAgain = fetchProductRows($connection, "
            SELECT
                p.product_id AS id,
                p.name,
                p.description,
                p.price,
                p.wholesale_price,
                p.min_wholesale_qty,
                p.stock,
                u.full_name AS seller_name,
                COUNT(pi.image_id) AS image_count,
                GROUP_CONCAT(pi.image_path SEPARATOR '|') AS image_paths
            FROM products p
            LEFT JOIN users u ON p.seller_id = u.id
            LEFT JOIN product_images pi ON pi.product_id = p.product_id
            WHERE p.product_status = 'approved'
              AND p.stock > 0
              AND p.product_id IN ($idList)
            GROUP BY p.product_id
            ORDER BY FIELD(p.product_id, $idList)
            LIMIT 6
        ");

        $historySellerIds = fetchSellerIdsForProducts($connection, $cartProductIds);
        if (!empty($historySellerIds)) {
            $sellerList = buildIdList($historySellerIds);
            $recommended = fetchProductRows($connection, "
                SELECT
                    p.product_id AS id,
                    p.name,
                    p.description,
                    p.price,
                    p.wholesale_price,
                    p.min_wholesale_qty,
                    p.stock,
                    u.full_name AS seller_name,
                    COUNT(pi.image_id) AS image_count,
                    GROUP_CONCAT(pi.image_path SEPARATOR '|') AS image_paths,
                    COUNT(DISTINCT oi2.order_item_id) AS popularity_score
                FROM products p
                LEFT JOIN users u ON p.seller_id = u.id
                LEFT JOIN product_images pi ON pi.product_id = p.product_id
                LEFT JOIN order_items oi2 ON oi2.product_id = p.product_id
                WHERE p.product_status = 'approved'
                  AND p.stock > 0
                  AND p.seller_id IN ($sellerList)
                  AND p.product_id NOT IN ($idList)
                GROUP BY p.product_id
                ORDER BY popularity_score DESC, p.created_at DESC
                LIMIT 8
            ");
        }
    } else {
        $buyAgain = fetchProductRows($connection, "
            SELECT
                p.product_id AS id,
                p.name,
                p.description,
                p.price,
                p.wholesale_price,
                p.min_wholesale_qty,
                p.stock,
                u.full_name AS seller_name,
                COUNT(pi.image_id) AS image_count,
                GROUP_CONCAT(pi.image_path SEPARATOR '|') AS image_paths,
                SUM(oi.quantity) AS purchased_quantity,
                COUNT(DISTINCT o.order_id) AS order_count,
                MAX(o.created_at) AS last_purchased_at
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.order_id
            INNER JOIN products p ON p.product_id = oi.product_id
            LEFT JOIN users u ON p.seller_id = u.id
            LEFT JOIN product_images pi ON pi.product_id = p.product_id
            WHERE o.user_id = ? AND p.product_status = 'approved' AND p.stock > 0
            GROUP BY p.product_id
            ORDER BY last_purchased_at DESC, purchased_quantity DESC
            LIMIT 6
        ", $userId);

        $recommended = fetchProductRows($connection, "
            SELECT
                p.product_id AS id,
                p.name,
                p.description,
                p.price,
                p.wholesale_price,
                p.min_wholesale_qty,
                p.stock,
                u.full_name AS seller_name,
                COUNT(pi.image_id) AS image_count,
                GROUP_CONCAT(pi.image_path SEPARATOR '|') AS image_paths,
                COUNT(DISTINCT oi2.order_item_id) AS popularity_score
            FROM products p
            LEFT JOIN users u ON p.seller_id = u.id
            LEFT JOIN product_images pi ON pi.product_id = p.product_id
            LEFT JOIN order_items oi2 ON oi2.product_id = p.product_id
            WHERE p.product_status = 'approved'
              AND p.stock > 0
              AND p.product_id NOT IN (
                  SELECT oi.product_id
                  FROM orders o
                  INNER JOIN order_items oi ON oi.order_id = o.order_id
                  WHERE o.user_id = ?
              )
              AND p.seller_id IN (
                  SELECT DISTINCT p2.seller_id
                  FROM orders o
                  INNER JOIN order_items oi ON oi.order_id = o.order_id
                  INNER JOIN products p2 ON p2.product_id = oi.product_id
                  WHERE o.user_id = ?
              )
            GROUP BY p.product_id
            ORDER BY popularity_score DESC, p.created_at DESC
            LIMIT 8
        ", $userId, $userId);
    }

    $trending = fetchProductRows($connection, "
        SELECT
            p.product_id AS id,
            p.name,
            p.description,
            p.price,
            p.wholesale_price,
            p.min_wholesale_qty,
            p.stock,
            u.full_name AS seller_name,
            COUNT(pi.image_id) AS image_count,
            GROUP_CONCAT(pi.image_path SEPARATOR '|') AS image_paths,
            COALESCE(SUM(oi.quantity), 0) AS sold_quantity
        FROM products p
        LEFT JOIN users u ON p.seller_id = u.id
        LEFT JOIN product_images pi ON pi.product_id = p.product_id
        LEFT JOIN order_items oi ON oi.product_id = p.product_id
        LEFT JOIN orders o ON o.order_id = oi.order_id AND o.status = 'completed'
        WHERE p.product_status = 'approved' AND p.stock > 0
        GROUP BY p.product_id
        ORDER BY sold_quantity DESC, p.created_at DESC
        LIMIT 8
    ");

    $db->close();

    echo json_encode([
        'success' => true,
        'has_history' => count($buyAgain) > 0 || !empty($cartProductIds),
        'buy_again' => $buyAgain,
        'recommended' => $recommended,
        'trending' => $trending
    ]);
}

/**
 * Save cart history in session for customer personalization.
 */
function saveCartHistory() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        return;
    }

    $currentUserRole = $_SESSION['user_role'] ?? '';
    if ($currentUserRole !== 'customer') {
        echo json_encode(['success' => true, 'message' => 'No cart history for non-customer users']);
        return;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    $items = $payload['items'] ?? [];
    if (!is_array($items)) {
        $items = [];
    }

    $_SESSION['cart_history'] = normalizeCartHistoryItems($items);

    echo json_encode([
        'success' => true,
        'count' => count($_SESSION['cart_history'])
    ]);
}

function fetchProductRows($connection, $sql, ...$params) {
    $stmt = $connection->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if (count($params) > 0) {
        $types = str_repeat('i', count($params));
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return formatProductRows($rows);
}

function formatProductRows($rows) {
    $products = [];

    foreach ($rows as $product) {
        $images = !empty($product['image_paths']) ? explode('|', $product['image_paths']) : [];
        $formatted = [
            'id' => (int)$product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => $product['price'],
            'wholesale_price' => $product['wholesale_price'] !== null ? (float)$product['wholesale_price'] : null,
            'min_wholesale_qty' => $product['min_wholesale_qty'] !== null ? (int)$product['min_wholesale_qty'] : null,
            'stock' => (int)$product['stock'],
            'seller_name' => $product['seller_name'] ?? 'Unknown',
            'image_count' => (int)($product['image_count'] ?? 0),
            'image_paths' => $images,
            'primary_image' => count($images) > 0 ? $images[0] : null
        ];

        foreach (['purchased_quantity', 'order_count', 'last_purchased_at', 'popularity_score', 'sold_quantity'] as $metaKey) {
            if (array_key_exists($metaKey, $product)) {
                $formatted[$metaKey] = is_numeric($product[$metaKey]) ? (float)$product[$metaKey] : $product[$metaKey];
            }
        }

        $products[] = $formatted;
    }

    return $products;
}

function normalizeCartHistoryItems($items) {
    $normalized = [];

    foreach ($items as $item) {
        $productId = (int)($item['productId'] ?? 0);
        if ($productId <= 0) {
            continue;
        }

        $quantity = (int)($item['quantity'] ?? 1);
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $purchaseType = ($item['purchaseType'] ?? 'retail') === 'wholesale' ? 'wholesale' : 'retail';
        $key = $productId . '-' . $purchaseType;

        if (!isset($normalized[$key])) {
            $normalized[$key] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'purchase_type' => $purchaseType,
                'updated_at' => time()
            ];
        } else {
            $normalized[$key]['quantity'] += $quantity;
            $normalized[$key]['updated_at'] = time();
        }
    }

    $values = array_values($normalized);
    usort($values, function ($a, $b) {
        return ($b['updated_at'] ?? 0) <=> ($a['updated_at'] ?? 0);
    });

    return array_slice($values, 0, 30);
}

function extractCartProductIds($cartHistory) {
    $ids = [];
    foreach ($cartHistory as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        if ($productId > 0) {
            $ids[] = $productId;
        }
    }
    return array_values(array_unique($ids));
}

function buildIdList($ids) {
    $clean = array_values(array_unique(array_map('intval', $ids)));
    if (empty($clean)) {
        return '0';
    }
    return implode(',', $clean);
}

function fetchSellerIdsForProducts($connection, $productIds) {
    $idList = buildIdList($productIds);
    if ($idList === '0') {
        return [];
    }

    $result = $connection->query("SELECT DISTINCT seller_id FROM products WHERE product_id IN ($idList)");
    if (!$result) {
        return [];
    }

    $sellerIds = [];
    while ($row = $result->fetch_assoc()) {
        $sellerId = (int)($row['seller_id'] ?? 0);
        if ($sellerId > 0) {
            $sellerIds[] = $sellerId;
        }
    }

    return array_values(array_unique($sellerIds));
}

function ensureProductModerationColumn($db) {
    $column = $db->query("SHOW COLUMNS FROM products LIKE 'product_status'");
    if ($column instanceof mysqli_result && $column->num_rows === 0) {
        $db->execute("ALTER TABLE products ADD COLUMN product_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved'");
    }
}
?>
