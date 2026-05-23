<?php
/**
 * Seller Management API
 * Handles seller registration, product creation, and image uploads
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
$database->connect();

$request = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($request) {
        case 'become-seller':
            handleBecomeSeller($database);
            break;
        case 'add-product':
            handleAddProduct($database);
            break;
        case 'update-product':
            handleUpdateProduct($database);
            break;
        case 'get-seller-products':
            handleGetSellerProducts($database);
            break;
        case 'delete-product':
            handleDeleteProduct($database);
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

/**
 * Handle user becoming a seller
 */
function handleBecomeSeller($database) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $user_id = $_SESSION['user_id'];

    // Update user role to seller
    $stmt = $database->prepare("UPDATE users SET role = 'seller' WHERE id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
        return;
    }

    $stmt->bind_param('i', $user_id);
    if ($stmt->execute()) {
        $_SESSION['user_role'] = 'seller';
        echo json_encode(['success' => true, 'message' => 'You are now a seller!']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
}

/**
 * Handle adding a new product with images
 */
function handleAddProduct($database) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    if ($_SESSION['user_role'] !== 'seller') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only sellers can add products']);
        return;
    }

    $seller_id = $_SESSION['user_id'];
    ensureProductModerationColumn($database);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $wholesale_price_input = trim($_POST['wholesale_price'] ?? '');
    $min_wholesale_qty_input = trim($_POST['min_wholesale_qty'] ?? '');
    $stock = intval($_POST['stock'] ?? 0);
    $wholesale_price = $wholesale_price_input !== '' ? floatval($wholesale_price_input) : round($price * 0.8, 2);
    $min_wholesale_qty = $min_wholesale_qty_input !== '' ? intval($min_wholesale_qty_input) : 5;

    if (!$name || $price <= 0 || $wholesale_price <= 0 || $min_wholesale_qty < 1 || $stock < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Name, price (>0), wholesale price (>0), wholesale qty (>=1), and stock (>=0) are required']);
        return;
    }

    // Insert product
    $stmt = $database->prepare("INSERT INTO products (seller_id, name, description, price, wholesale_price, min_wholesale_qty, stock, product_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare product statement']);
        return;
    }

    $stmt->bind_param('issddii', $seller_id, $name, $description, $price, $wholesale_price, $min_wholesale_qty, $stock);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add product: ' . $stmt->error]);
        $stmt->close();
        return;
    }

    $product_id = $stmt->insert_id;
    $stmt->close();

    // Handle image uploads (max 5)
    $uploadedImages = [];
    $uploads_dir = __DIR__ . '/../../uploads/products/';

    if (!is_dir($uploads_dir)) {
        if (!mkdir($uploads_dir, 0755, true)) {
            error_log("Failed to create uploads directory: $uploads_dir");
        }
    }

    $imageFiles = normalizeUploadedImages($_FILES['images'] ?? null);

    if (!empty($imageFiles)) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $max_images = 5;
        $image_count = 0;

        foreach ($imageFiles as $imageFile) {
            if ($image_count >= $max_images) {
                break;
            }

            if (empty($imageFile['name'])) {
                continue;
            }

            if ($imageFile['error'] !== UPLOAD_ERR_OK) {
                error_log("Upload error for file " . $imageFile['name'] . ": " . $imageFile['error']);
                continue;
            }

            $file_type = detectImageMimeType($imageFile);

            if (!in_array($file_type, $allowed_types)) {
                error_log("Invalid file type: $file_type for file " . $imageFile['name']);
                continue;
            }

            $file_size = $imageFile['size'];
            if ($file_size > 5242880) { // 5MB limit per file
                error_log("File too large: " . $imageFile['name'] . " (" . $file_size . " bytes)");
                continue;
            }

            // Generate unique filename
            $extension = strtolower(pathinfo($imageFile['name'], PATHINFO_EXTENSION));
            $file_name = 'product_' . $product_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $file_path = $uploads_dir . $file_name;

            // Move uploaded file
            if (move_uploaded_file($imageFile['tmp_name'], $file_path)) {
                // Store image metadata in database
                $img_stmt = $database->prepare("INSERT INTO product_images (product_id, image_name, image_path, image_size, image_type) VALUES (?, ?, ?, ?, ?)");
                if ($img_stmt) {
                    $relative_path = 'uploads/products/' . $file_name;
                    $img_stmt->bind_param('issis', $product_id, $imageFile['name'], $relative_path, $file_size, $file_type);
                    if ($img_stmt->execute()) {
                        $uploadedImages[] = $relative_path;
                        $image_count++;
                    } else {
                        error_log("Database insert error: " . $img_stmt->error);
                    }
                    $img_stmt->close();
                }
            } else {
                error_log("Failed to move uploaded file: " . $imageFile['tmp_name'] . " to " . $file_path);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Product added successfully',
        'product_id' => $product_id,
        'images_uploaded' => count($uploadedImages),
        'images' => $uploadedImages
    ]);
}

function ensureProductModerationColumn($database) {
    $column = $database->query("SHOW COLUMNS FROM products LIKE 'product_status'");
    if ($column instanceof mysqli_result && $column->num_rows === 0) {
        $database->execute("ALTER TABLE products ADD COLUMN product_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved'");
    }
}

function normalizeUploadedImages($files) {
    if (!$files || !isset($files['name'])) {
        return [];
    }

    if (is_array($files['name'])) {
        $normalized = [];
        foreach ($files['name'] as $index => $name) {
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0
            ];
        }
        return $normalized;
    }

    return [$files];
}

function detectImageMimeType($imageFile) {
    if (function_exists('finfo_open') && !empty($imageFile['tmp_name'])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $imageFile['tmp_name']);
            finfo_close($finfo);
            if ($detected) {
                return $detected;
            }
        }
    }

    if (!empty($imageFile['type'])) {
        return $imageFile['type'];
    }

    $extension = strtolower(pathinfo($imageFile['name'] ?? '', PATHINFO_EXTENSION));
    return match ($extension) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        default => ''
    };
}

/**
 * Handle updating an existing product
 */
function handleUpdateProduct($database) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $seller_id = $_SESSION['user_id'];
    $product_id = intval($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $wholesale_price_input = trim($_POST['wholesale_price'] ?? '');
    $min_wholesale_qty_input = trim($_POST['min_wholesale_qty'] ?? '');
    $stock = intval($_POST['stock'] ?? 0);
    $wholesale_price = $wholesale_price_input !== '' ? floatval($wholesale_price_input) : round($price * 0.8, 2);
    $min_wholesale_qty = $min_wholesale_qty_input !== '' ? intval($min_wholesale_qty_input) : 5;

    if (!$product_id || !$name || $price <= 0 || $wholesale_price <= 0 || $min_wholesale_qty < 1 || $stock < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid product data']);
        return;
    }

    // Verify ownership
    $verify_stmt = $database->prepare("SELECT product_id FROM products WHERE product_id = ? AND seller_id = ?");
    if (!$verify_stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to verify ownership']);
        return;
    }

    $verify_stmt->bind_param('ii', $product_id, $seller_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only edit your own products']);
        $verify_stmt->close();
        return;
    }

    $verify_stmt->close();

    // Update product
    $stmt = $database->prepare("UPDATE products SET name = ?, description = ?, price = ?, wholesale_price = ?, min_wholesale_qty = ?, stock = ? WHERE product_id = ? AND seller_id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare update statement']);
        return;
    }

    $stmt->bind_param('ssdddiii', $name, $description, $price, $wholesale_price, $min_wholesale_qty, $stock, $product_id, $seller_id);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update product: ' . $stmt->error]);
        $stmt->close();
        return;
    }

    $stmt->close();

    // Handle optional image uploads for existing products (max 5 total)
    $uploadedImages = [];
    $uploads_dir = __DIR__ . '/../../uploads/products/';

    if (!is_dir($uploads_dir)) {
        if (!mkdir($uploads_dir, 0755, true)) {
            error_log("Failed to create uploads directory: $uploads_dir");
        }
    }

    $count_stmt = $database->prepare("SELECT COUNT(*) AS image_count FROM product_images WHERE product_id = ?");
    $existing_image_count = 0;
    if ($count_stmt) {
        $count_stmt->bind_param('i', $product_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        if ($count_row = $count_result->fetch_assoc()) {
            $existing_image_count = (int)$count_row['image_count'];
        }
        $count_stmt->close();
    }

    $max_new_images = max(0, 5 - $existing_image_count);

    $imageFiles = normalizeUploadedImages($_FILES['images'] ?? null);

    if ($max_new_images > 0 && !empty($imageFiles)) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $image_count = 0;

        foreach ($imageFiles as $imageFile) {
            if ($image_count >= $max_new_images) {
                break;
            }

            if (empty($imageFile['name'])) {
                continue;
            }

            if ($imageFile['error'] !== UPLOAD_ERR_OK) {
                error_log("Upload error for file " . $imageFile['name'] . ": " . $imageFile['error']);
                continue;
            }

            $file_type = detectImageMimeType($imageFile);

            if (!in_array($file_type, $allowed_types)) {
                error_log("Invalid file type: $file_type for file " . $imageFile['name']);
                continue;
            }

            $file_size = $imageFile['size'];
            if ($file_size > 5242880) {
                error_log("File too large: " . $imageFile['name'] . " (" . $file_size . " bytes)");
                continue;
            }

            $extension = strtolower(pathinfo($imageFile['name'], PATHINFO_EXTENSION));
            $file_name = 'product_' . $product_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $file_path = $uploads_dir . $file_name;

            if (move_uploaded_file($imageFile['tmp_name'], $file_path)) {
                $img_stmt = $database->prepare("INSERT INTO product_images (product_id, image_name, image_path, image_size, image_type) VALUES (?, ?, ?, ?, ?)");
                if ($img_stmt) {
                    $relative_path = 'uploads/products/' . $file_name;
                    $img_stmt->bind_param('issis', $product_id, $imageFile['name'], $relative_path, $file_size, $file_type);
                    if ($img_stmt->execute()) {
                        $uploadedImages[] = $relative_path;
                        $image_count++;
                    } else {
                        error_log("Database insert error: " . $img_stmt->error);
                    }
                    $img_stmt->close();
                }
            } else {
                error_log("Failed to move uploaded file: " . $imageFile['tmp_name'] . " to " . $file_path);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Product updated successfully',
        'images_uploaded' => count($uploadedImages),
        'images' => $uploadedImages
    ]);
}

/**
 * Get all products for the logged-in seller
 */
function handleGetSellerProducts($database) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $seller_id = $_SESSION['user_id'];

    $stmt = $database->prepare("SELECT p.*, COUNT(pi.image_id) as image_count, GROUP_CONCAT(pi.image_path SEPARATOR '|') as image_paths FROM products p LEFT JOIN product_images pi ON p.product_id = pi.product_id WHERE p.seller_id = ? GROUP BY p.product_id ORDER BY p.created_at DESC");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
        return;
    }

    $stmt->bind_param('i', $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $row['image_paths'] = !empty($row['image_paths']) ? explode('|', $row['image_paths']) : [];
        $row['primary_image'] = count($row['image_paths']) > 0 ? $row['image_paths'][0] : null;
        $products[] = $row;
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
}

/**
 * Delete a product (only by seller who created it)
 */
function handleDeleteProduct($database) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $product_id = intval($input['product_id'] ?? 0);
    $seller_id = $_SESSION['user_id'];

    if (!$product_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Product ID required']);
        return;
    }

    // Verify ownership
    $stmt = $database->prepare("SELECT product_id FROM products WHERE product_id = ? AND seller_id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
        return;
    }

    $stmt->bind_param('ii', $product_id, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only delete your own products']);
        $stmt->close();
        return;
    }

    $stmt->close();

    // Delete product (cascade deletes images)
    $del_stmt = $database->prepare("DELETE FROM products WHERE product_id = ?");
    if (!$del_stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare delete statement']);
        return;
    }

    $del_stmt->bind_param('i', $product_id);
    if ($del_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $del_stmt->error]);
    }
    $del_stmt->close();
}
