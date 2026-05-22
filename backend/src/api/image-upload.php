<?php
/**
 * Image Upload and Compression API
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

require_once __DIR__ . '/../util/Database.php';
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? null;

if ($action === 'upload-product-image') {
    handleProductImageUpload();
} elseif ($action === 'delete-image') {
    handleImageDelete();
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Handle product image upload with compression
 */
function handleProductImageUpload() {
    if (!isset($_FILES['image']) || !isset($_POST['product_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing image or product_id']);
        return;
    }

    $product_id = (int)$_POST['product_id'];
    $user_id = $_SESSION['user_id'];
    $file = $_FILES['image'];

    // Validate file
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid image format']);
        return;
    }

    // Check file size (max 5MB before compression)
    if ($file['size'] > 5242880) { // 5MB
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Image too large (max 5MB)']);
        return;
    }

    // Verify user owns the product
    $db = new Database();
    $db->connect();
    
    $product = $db->getRow("SELECT seller_id FROM products WHERE product_id = $product_id");
    if (!$product || (int)$product['seller_id'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized to upload for this product']);
        return;
    }

    // Create uploads directory if it doesn't exist
    $uploads_dir = __DIR__ . '/../../uploads/products';
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }

    // Load and compress image
    $compressed = compressImage($file['tmp_name'], $file['type']);
    if (!$compressed) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Failed to process image']);
        return;
    }

    // Generate unique filename
    $filename = 'product_' . $product_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $filepath = $uploads_dir . '/' . $filename;
    $relative_path = 'uploads/products/' . $filename;

    // Save compressed image
    if (!file_put_contents($filepath, $compressed)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save image']);
        return;
    }

    $original_size = $file['size'];
    $compressed_size = filesize($filepath);
    $compression_ratio = round((1 - ($compressed_size / $original_size)) * 100, 2);

    // Save to database
    $query = "INSERT INTO product_images (product_id, image_name, image_path, image_size, image_type) 
              VALUES ($product_id, '" . $db->escape(basename($file['name'])) . "', 
                      '" . $db->escape($relative_path) . "', $compressed_size, 'image/jpeg')";
    
    if (!$db->execute($query)) {
        unlink($filepath); // Delete file if DB save fails
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save image metadata']);
        return;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded and compressed',
        'image_path' => $relative_path,
        'original_size' => $original_size,
        'compressed_size' => $compressed_size,
        'compression_ratio' => $compression_ratio . '%'
    ]);
}

/**
 * Compress image using GD library
 */
function compressImage($source_path, $mime_type) {
    if (!extension_loaded('gd')) {
        return null;
    }

    // Load image based on type
    switch ($mime_type) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source_path);
            // Preserve transparency for PNG
            imagesavealpha($image, true);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source_path);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source_path);
            break;
        default:
            return null;
    }

    if (!$image) {
        return null;
    }

    // Get image dimensions
    $width = imagesx($image);
    $height = imagesy($image);

    // Calculate new dimensions (max 1200x1200)
    $max_width = 1200;
    $max_height = 1200;

    if ($width > $max_width || $height > $max_height) {
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = (int)($width * $ratio);
        $new_height = (int)($height * $ratio);

        $resized = imagecreatetruecolor($new_width, $new_height);
        
        // Handle transparency
        if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefill($resized, 0, 0, $transparent);
            imagesavealpha($resized, true);
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($image);
        $image = $resized;
    }

    // Output as JPEG with compression
    ob_start();
    imagejpeg($image, null, 75); // 75% quality for good balance
    $compressed = ob_get_clean();
    imagedestroy($image);

    return $compressed;
}

/**
 * Delete product image
 */
function handleImageDelete() {
    if (!isset($_POST['image_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Image ID required']);
        return;
    }

    $image_id = (int)$_POST['image_id'];
    $user_id = $_SESSION['user_id'];

    $db = new Database();
    $db->connect();

    // Get image and verify user owns the product
    $image = $db->getRow("SELECT pi.image_id, pi.image_path, p.seller_id 
                          FROM product_images pi 
                          JOIN products p ON pi.product_id = p.product_id 
                          WHERE pi.image_id = $image_id");

    if (!$image || (int)$image['seller_id'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    // Delete file from either the current or legacy upload location.
    $imagePath = str_replace('\\', '/', trim($image['image_path']));
    $candidatePaths = [];

    if (preg_match('/^[A-Za-z]:\//', $imagePath) || str_starts_with($imagePath, '/')) {
        $candidatePaths[] = $imagePath;
    } else {
        $candidatePaths[] = __DIR__ . '/../../' . $imagePath;
        $candidatePaths[] = __DIR__ . '/../' . $imagePath;
        if (str_starts_with($imagePath, 'uploads/')) {
            $suffix = substr($imagePath, strlen('uploads/'));
            $candidatePaths[] = __DIR__ . '/../../uploads/' . $suffix;
            $candidatePaths[] = __DIR__ . '/../uploads/' . $suffix;
        }
    }

    foreach ($candidatePaths as $filepath) {
        if (file_exists($filepath)) {
            unlink($filepath);
            break;
        }
    }

    // Delete from database
    if ($db->execute("DELETE FROM product_images WHERE image_id = $image_id")) {
        echo json_encode(['success' => true, 'message' => 'Image deleted']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete image']);
    }
}
?>
