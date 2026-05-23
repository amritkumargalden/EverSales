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
    
    $products = [];
    if (is_array($result)) {
        foreach ($result as $product) {
            $images = !empty($product['image_paths']) ? explode('|', $product['image_paths']) : [];
            $products[] = [
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
        }
    }
    
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

    $products = [];
    while ($product = $result->fetch_assoc()) {
        $images = !empty($product['image_paths']) ? explode('|', $product['image_paths']) : [];
        $products[] = [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => $product['price'],
            'wholesale_price' => $product['wholesale_price'] !== null ? (float)$product['wholesale_price'] : null,
            'min_wholesale_qty' => $product['min_wholesale_qty'] !== null ? (int)$product['min_wholesale_qty'] : null,
            'stock' => (int) $product['stock'],
            'seller_name' => $product['seller_name'] ?? 'Unknown',
            'image_count' => (int) ($product['image_count'] ?? 0),
            'image_paths' => $images,
            'primary_image' => count($images) > 0 ? $images[0] : null
        ];
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'products' => $products,
        'total' => count($products),
        'query' => $query
    ]);
}

function ensureProductModerationColumn($db) {
    $column = $db->query("SHOW COLUMNS FROM products LIKE 'product_status'");
    if ($column instanceof mysqli_result && $column->num_rows === 0) {
        $db->execute("ALTER TABLE products ADD COLUMN product_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved'");
    }
}
?>
