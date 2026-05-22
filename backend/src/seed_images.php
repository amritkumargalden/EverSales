<?php
/**
 * Quick Image Seeder - Adds placeholder images to products
 * Run this once to populate product images
 */

require_once __DIR__ . '/util/Database.php';
require_once __DIR__ . '/config.php';

$db = new Database();
$db->connect();

// Create uploads directory if needed
$uploads_dir = __DIR__ . '/../uploads/products';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
    echo "Created uploads directory\n";
}

// Function to create a simple placeholder image
function createPlaceholderImage($text, $color = '#3498db') {
    $width = 400;
    $height = 300;
    $image = imagecreatetruecolor($width, $height);
    
    // Parse color
    $rgb = sscanf($color, "#%02x%02x%02x");
    $bgColor = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
    imagefill($image, 0, 0, $bgColor);
    
    // Add text
    $textColor = imagecolorallocate($image, 255, 255, 255);
    $fontSize = 5;
    $font = __DIR__ . '/../fonts/arial.ttf'; // fallback to default
    
    // Simple centered text
    $textX = $width / 2 - strlen($text) * 20;
    $textY = $height / 2;
    imagestring($image, $fontSize, $textX, $textY, $text, $textColor);
    
    // Return JPEG
    ob_start();
    imagejpeg($image, null, 85);
    $content = ob_get_clean();
    imagedestroy($image);
    
    return $content;
}

// Sample products and colors
$products = [
    1 => ['name' => 'Wireless Mouse', 'color' => '#e74c3c'],
    2 => ['name' => 'Mechanical Keyboard', 'color' => '#3498db'],
    3 => ['name' => 'USB-C Charger', 'color' => '#f39c12'],
    4 => ['name' => 'iPhone 18 Pro', 'color' => '#2c3e50'],
    5 => ['name' => 'Wireless Headphones', 'color' => '#9b59b6'],
    7 => ['name' => 'Yamaha Guitar', 'color' => '#d4a574'],
    8 => ['name' => 'Electric Guitar', 'color' => '#c0392b'],
    9 => ['name' => 'Classical Guitar', 'color' => '#8b7355'],
    10 => ['name' => 'Canon Camera', 'color' => '#34495e'],
    11 => ['name' => 'Sony Camera', 'color' => '#16a085'],
    12 => ['name' => 'Nikon DSLR', 'color' => '#27ae60'],
];

$count = 0;

foreach ($products as $product_id => $data) {
    // Check if image already exists
    $existing = $db->getRow("SELECT image_id FROM product_images WHERE product_id = $product_id LIMIT 1");
    if ($existing) {
        continue;
    }
    
    // Create placeholder image
    $image_data = createPlaceholderImage($data['name'], $data['color']);
    
    // Generate filename
    $filename = 'product_' . $product_id . '_placeholder_' . time() . '.jpg';
    $filepath = $uploads_dir . '/' . $filename;
    $relative_path = 'uploads/products/' . $filename;
    
    // Save image file
    if (file_put_contents($filepath, $image_data)) {
        // Insert into database
        $size = filesize($filepath);
        $query = "INSERT INTO product_images (product_id, image_name, image_path, image_size, image_type) 
                  VALUES ($product_id, '" . $db->escape($data['name'] . '.jpg') . "', 
                          '" . $db->escape($relative_path) . "', $size, 'image/jpeg')";
        
        if ($db->execute($query)) {
            $count++;
            echo "✓ Added image for product $product_id: " . $data['name'] . "\n";
        } else {
            echo "✗ Failed to insert image for product $product_id\n";
        }
    } else {
        echo "✗ Failed to save image file for product $product_id\n";
    }
}

echo "\n✅ Image seeding complete! Added $count product images.\n";
echo "Refresh your browser to see the images.\n";
?>
