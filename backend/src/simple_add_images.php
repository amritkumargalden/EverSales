<?php
/**
 * Simple Image Path Seeder - No GD required
 * Just adds image path records to database
 */

require_once __DIR__ . '/util/Database.php';
require_once __DIR__ . '/config.php';

$db = new Database();
$db->connect();

// Sample colors for each product
$products = [
    1 => 'electronics_mouse',
    2 => 'electronics_keyboard', 
    3 => 'electronics_charger',
    4 => 'electronics_iphone',
    5 => 'electronics_iphone2',
    7 => 'guitars_acoustic',
    8 => 'guitars_electric',
    9 => 'guitars_classical',
    10 => 'cameras_canon',
    11 => 'cameras_sony',
    12 => 'cameras_nikon',
];

$added = 0;

foreach ($products as $product_id => $name) {
    // Check if image exists
    $check = $db->getRow("SELECT image_id FROM product_images WHERE product_id = $product_id LIMIT 1");
    if ($check) {
        continue;
    }
    
    // Create a simple data URI SVG image
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><rect fill="#f0f0f0" width="400" height="300"/><text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#999" font-size="24" font-family="Arial">' . ucfirst(str_replace('_', ' ', $name)) . '</text></svg>';
    $dataUri = 'data:image/svg+xml;base64,' . base64_encode($svg);
    
    // Insert into database
    $sql = "INSERT INTO product_images (product_id, image_name, image_path, image_size, image_type) 
            VALUES ($product_id, '" . $db->escape($name . '.svg') . "', '" . $db->escape($dataUri) . "', " . strlen($svg) . ", 'image/svg+xml')";
    
    if ($db->execute($sql)) {
        $added++;
    }
}

echo "✅ Added $added product images to database.\n";

// Check result
$result = $db->getRow("SELECT COUNT(*) as cnt FROM product_images");
echo "Total images in DB: " . $result['cnt'] . "\n";
?>
