/**
 * Image Upload Manager
 * Handles product image uploads with compression feedback
 */

const IMAGE_UPLOAD_API = `${BACKEND_ORIGIN}/api/image-upload.php`;

/**
 * Upload product image
 */
async function uploadProductImage(productId, imageFile) {
    try {
        const formData = new FormData();
        formData.append('action', 'upload-product-image');
        formData.append('product_id', productId);
        formData.append('image', imageFile);

        const response = await fetch(IMAGE_UPLOAD_API, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            return {
                success: true,
                message: `${data.compression_ratio} smaller`,
                imagePath: data.image_path,
                originalSize: formatFileSize(data.original_size),
                compressedSize: formatFileSize(data.compressed_size)
            };
        } else {
            return {
                success: false,
                message: data.message || 'Upload failed'
            };
        }
    } catch (error) {
        console.error('Error uploading image:', error);
        return {
            success: false,
            message: 'Upload error: ' + error.message
        };
    }
}

/**
 * Delete product image
 */
async function deleteProductImage(imageId) {
    try {
        const formData = new FormData();
        formData.append('action', 'delete-image');
        formData.append('image_id', imageId);

        const response = await fetch(IMAGE_UPLOAD_API, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error deleting image:', error);
        return { success: false, message: error.message };
    }
}

/**
 * Format file size for display
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

/**
 * Escape HTML for safe display
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

