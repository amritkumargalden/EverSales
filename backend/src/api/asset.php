<?php
/**
 * Asset proxy for product images and other backend-stored files.
 */

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}

$assetPath = $_GET['path'] ?? '';
if ($assetPath === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Asset path required';
    exit;
}

function normalizeAssetPath($path) {
    $path = trim(rawurldecode($path));
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path);
    return $path;
}

function getMimeTypeForFile($filePath) {
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            if ($mimeType) {
                return $mimeType;
            }
        }
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            return 'image/jpeg';
        case 'png':
            return 'image/png';
        case 'gif':
            return 'image/gif';
        case 'webp':
            return 'image/webp';
        case 'svg':
            return 'image/svg+xml';
        default:
            return 'application/octet-stream';
    }
}

function resolveAssetFilePath($assetPath) {
    $normalizedPath = normalizeAssetPath($assetPath);
    $candidates = [];

    if (preg_match('/^[A-Za-z]:\//', $normalizedPath) || str_starts_with($normalizedPath, '/')) {
        $candidates[] = $normalizedPath;
    } else {
        $candidates[] = __DIR__ . '/../../' . $normalizedPath;
        $candidates[] = __DIR__ . '/../' . $normalizedPath;

        if (str_starts_with($normalizedPath, 'uploads/')) {
            $suffix = substr($normalizedPath, strlen('uploads/'));
            $candidates[] = __DIR__ . '/../../uploads/' . $suffix;
            $candidates[] = __DIR__ . '/../uploads/' . $suffix;
        }
    }

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

$filePath = resolveAssetFilePath($assetPath);
if (!$filePath) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Asset not found';
    exit;
}

$mimeType = getMimeTypeForFile($filePath);
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;