/**
 * Seller API Service
 * Handles seller-related requests to the PHP backend
 */

const SELLER_API_URL = 'http://localhost:8000/api/seller.php';

/**
 * Upgrade current user to seller
 */
async function becomeSeller() {
    try {
        const response = await fetch(`${SELLER_API_URL}?action=become-seller`, {
            method: 'POST',
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            // Update user role in localStorage
            localStorage.setItem('user_role', 'seller');
            showMessage('Welcome to seller mode! You can now add products.', 'success');
            setTimeout(() => {
                window.location.href = 'seller-dashboard.html';
            }, 1500);
        } else {
            showMessage(data.message || 'Failed to become seller', 'error');
        }
    } catch (error) {
        console.error('Seller upgrade error:', error);
        showMessage('Network error. Please try again.', 'error');
    }
}

/**
 * Add a new product with images
 */
async function addProduct(formData) {
    try {
        // Log for debugging
        console.log('Adding product with data:');
        for (let [key, value] of formData.entries()) {
            if (key === 'images') {
                console.log(`${key}: File object`);
            } else {
                console.log(`${key}: ${value}`);
            }
        }

        const response = await fetch(`${SELLER_API_URL}?action=add-product`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        const data = await response.json();
        console.log('Add product response:', data);

        if (data.success) {
            const productName = formData.get('name');
            const imagesCount = data.images_uploaded || 0;
            showMessage(`Product "${productName}" added successfully with ${imagesCount} image(s)!`, 'success');

            // Clear form
            const productForm = document.getElementById('productForm');
            if (productForm) {
                productForm.reset();
                // Clear image preview
                const imagePreview = document.getElementById('imagePreview');
                if (imagePreview) {
                    imagePreview.innerHTML = '';
                }
            }

            // Refresh product list
            loadSellerProducts();
            return data;
        } else {
            showMessage(data.message || 'Failed to add product', 'error');
            return null;
        }
    } catch (error) {
        console.error('Add product error:', error);
        showMessage('Network error: ' + error.message, 'error');
        return null;
    }
}

/**
 * Get all products for current seller
 */
async function getSellerProducts() {
    try {
        const response = await fetch(`${SELLER_API_URL}?action=get-seller-products`, {
            method: 'GET',
            credentials: 'include'
        });

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Get products error:', error);
        return { success: false, products: [] };
    }
}

/**
 * Delete a product
 */
async function deleteProduct(productId) {
    try {
        const response = await fetch(`${SELLER_API_URL}?action=delete-product`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product_id: productId   
            }),
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            showMessage('Product deleted successfully', 'success');
            loadSellerProducts();
        } else {
            showMessage(data.message || 'Failed to delete product', 'error');
        }
    } catch (error) {
        console.error('Delete product error:', error);
        showMessage('Network error. Please try again.', 'error');
    }
}
