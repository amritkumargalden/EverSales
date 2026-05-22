<?php
/**
 * Quick Image Generator and Database Seeder
 */

require_once __DIR__ . '/util/Database.php';
require_once __DIR__ . '/config.php';

$db = new Database();
$db->connect();

// Create uploads directory
$uploads_dir = __DIR__ . '/../uploads/products';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
}

// Function to create simple colored placeholder image
function createImage($width = 400, $height = 300, $bgColor = 'RGB(52, 152, 219)') {
    $image = imagecreatetruecolor($width, $height);
    
    // Parse RGB color (simple colors)
    preg_match('/RGB\((\d+),\s*(\d+),\s*(\d+)\)/', $bgColor, $matches);
    if (count($matches) === 4) {
        $bg = imagecolorallocate($image, $matches[1], $matches[2], $matches[3]);
    } else {
        $bg = imagecolorallocate($image, 52, 152, 219);
    }
    
    imagefill($image, 0, 0, $bg);
    
    ob_start();
    imagejpeg($image, null, 75);
    $data = ob_get_clean();
    imagedestroy($image);
    
    return $data;
}

// Product image configurations
$imageConfigs = [
    1 => 'RGB(231, 76, 60)',    // Wireless Mouse - Red
    2 => 'RGB(52, 152, 219)',   // Keyboard - Blue
    3 => 'RGB(243, 156, 18)',   // Charger - Orange
    4 => 'RGB(44, 62, 80)',     // iPhone - Dark
    5 => 'RGB(155, 89, 182)',   // Headphones - Purple
    7 => 'RGB(212, 165, 116)',  // Acoustic Guitar - Brown
    8 => 'RGB(192, 57, 43)',    // Electric Guitar - Dark Red
    9 => 'RGB(139, 115, 85)',   // Classical Guitar - Wood
    10 => 'RGB(52, 73, 94)',    // Canon Camera - Slate
    11 => 'RGB(22, 160, 133)',  // Sony Camera - Teal
    12 => 'RGB(39, 174, 96)',   // Nikon Camera - Green
];

$addedCount = 0;

foreach ($imageConfigs as $product_id => $color) {
    // Check if images already exist
    $check = $db->getRow("SELECT COUNT(*) as cnt FROM product_images WHERE product_id = $product_id");
    if ($check['cnt'] > 0) {
        echo "⊘ Product $product_id already has images, skipping...\n";
        continue;
    }
    
    // Create image
    $imageData = createImage(400, 300, $color);
    
    // Save file
    $filename = 'product_' . $product_id . '_img_' . time() . '.jpg';
    $filepath = $uploads_dir . '/' . $filename;
    $relativePath = 'uploads/products/' . $filename;
    
    if (file_put_contents($filepath, $imageData)) {
        $size = filesize($filepath);
        
        // Insert into DB
        $sql = "INSERT INTO product_images (product_id, image_name, image_path, image_size, image_type) 
                VALUES ($product_id, 'placeholder.jpg', '" . $db->escape($relativePath) . "', $size, 'image/jpeg')";
        
        if ($db->execute($sql)) {
            $addedCount++;
            echo "✓ Added image for product $product_id\n";
        }
    }
}

echo "\n✅ Done! Added $addedCount images\n";

// Verify
$count = $db->getRow("SELECT COUNT(*) as cnt FROM product_images");
echo "Total images in database: " . $count['cnt'] . "\n";
?>
