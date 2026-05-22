<?php
/**
 * Add images for ALL remaining products
 */

require_once __DIR__ . '/util/Database.php';
require_once __DIR__ . '/config.php';

$db = new Database();
$db->connect();

// Get all products that don't have images
$products = $db->getResults("
    SELECT p.product_id, p.name 
    FROM products p 
    LEFT JOIN product_images pi ON p.product_id = pi.product_id 
    WHERE pi.image_id IS NULL
    ORDER BY p.product_id
");

$added = 0;

foreach ($products as $product) {
    $pid = $product['product_id'];
    $name = str_replace(' ', '_', strtolower($product['name']));
    
    // Create SVG image data
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300">
        <rect fill="#ecf0f1" width="400" height="300"/>
        <rect fill="#3498db" x="0" y="0" width="400" height="80"/>
        <text x="50%" y="40" text-anchor="middle" dy=".3em" fill="white" font-size="28" font-weight="bold" font-family="Arial">Product</text>
        <text x="50%" y="150" text-anchor="middle" dy=".3em" fill="#34495e" font-size="20" font-family="Arial">' . htmlspecialchars($product['name']) . '</text>
        <text x="50%" y="250" text-anchor="middle" dy=".3em" fill="#7f8c8d" font-size="14" font-family="Arial">ID: ' . $pid . '</text>
    </svg>';
    
    $dataUri = 'data:image/svg+xml;base64,' . base64_encode($svg);
    
    // Insert into database
    $sql = "INSERT INTO product_images (product_id, image_name, image_path, image_size, image_type) 
            VALUES ($pid, '" . $db->escape($product['name'] . '.svg') . "', '" . $db->escape($dataUri) . "', " . strlen($svg) . ", 'image/svg+xml')";
    
    if ($db->execute($sql)) {
        $added++;
        echo "✓ " . $product['name'] . "\n";
    }
}

echo "\n✅ Added $added images\n";

// Final count
$result = $db->getRow("SELECT COUNT(*) as cnt FROM product_images");
echo "Total images in database: " . $result['cnt'] . "\n";
?>
