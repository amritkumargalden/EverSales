/**
 * Product functions - display, search, and manage products
 */

let allProducts = [];

/**
 * Load all products from API
 */
async function loadProducts(searchKeyword = '') {
    try {
        let url = '/products';
        if (searchKeyword) {
            url += `?search=${encodeURIComponent(searchKeyword)}`;
        }
        
        const response = await apiGet(url);
        if (response.success && Array.isArray(response.data)) {
            allProducts = response.data;
            displayProducts(allProducts);
        }
    } catch (error) {
        console.error('Failed to load products:', error);
        showMessage('Failed to load products', 'error');
    }
}

/**
 * Display products on the page
 */
function displayProducts(products) {
    const productsContainer = document.getElementById('productsContainer');
    if (!productsContainer) return;
    
    if (products.length === 0) {
        productsContainer.innerHTML = '<p>No products found.</p>';
        return;
    }
    
    productsContainer.innerHTML = products.map(product => `
        <div class="product-card">
            <h3>${product.name}</h3>
            <p class="description">${product.description || 'No description'}</p>
            <p class="price">₹${parseFloat(product.price).toFixed(2)}</p>
            <p class="stock">Stock: ${product.stock}</p>
            ${product.stock > 0 ? `
                <div class="quantity-control">
                    <input type="number" id="qty-${product.productId}" min="1" max="${product.stock}" value="1">
                    <button onclick="addToCart(${product.productId}, ${product.price}, '${product.name}')">Add to Cart</button>
                </div>
            ` : '<p class="out-of-stock">Out of Stock</p>'}
        </div>
    `).join('');
}

/**
 * Search products by keyword
 */
function searchProducts() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        loadProducts(searchInput.value);
    }
}

/**
 * Add item to cart
 */
async function addToCart(productId, price, productName) {
    try {
        const quantityInput = document.getElementById(`qty-${productId}`);
        const quantity = quantityInput ? parseInt(quantityInput.value) : 1;
        
        const response = await apiPostForm('/cart/add', {
            productId,
            quantity
        });
        
        if (response.success) {
            showMessage(`${productName} added to cart`, 'success');
        }
    } catch (error) {
        showMessage('Failed to add item to cart: ' + error.message, 'error');
    }
}

/**
 * Create a new product (admin only)
 */
async function createProduct() {
    try {
        const name = document.getElementById('productName').value;
        const description = document.getElementById('productDescription').value;
        const price = document.getElementById('productPrice').value;
        const stock = document.getElementById('productStock').value;
        
        if (!name || !price || !stock) {
            showMessage('Please fill in all required fields', 'error');
            return;
        }
        
        const response = await apiPostForm('/products', {
            name,
            description,
            price,
            stock
        });
        
        if (response.success) {
            showMessage('Product created successfully', 'success');
            loadProducts();
            // Clear form
            document.getElementById('productName').value = '';
            document.getElementById('productDescription').value = '';
            document.getElementById('productPrice').value = '';
            document.getElementById('productStock').value = '';
        }
    } catch (error) {
        showMessage('Failed to create product: ' + error.message, 'error');
    }
}

/**
 * Update an existing product (admin only)
 */
async function updateProduct(productId) {
    try {
        const name = prompt('Enter product name:');
        const description = prompt('Enter description:');
        const price = prompt('Enter price:');
        const stock = prompt('Enter stock quantity:');
        
        if (!name || !price || !stock) {
            showMessage('Update cancelled', 'info');
            return;
        }
        
        const response = await apiPostForm(`/products/${productId}`, {
            name,
            description,
            price,
            stock
        });
        
        if (response.success) {
            showMessage('Product updated successfully', 'success');
            loadProducts();
        }
    } catch (error) {
        showMessage('Failed to update product: ' + error.message, 'error');
    }
}

/**
 * Delete a product (admin only)
 */
async function deleteProduct(productId) {
    try {
        if (!confirm('Are you sure you want to delete this product?')) {
            return;
        }
        
        const response = await apiDelete(`/products/${productId}`);
        
        if (response.success) {
            showMessage('Product deleted successfully', 'success');
            loadProducts();
        }
    } catch (error) {
        showMessage('Failed to delete product: ' + error.message, 'error');
    }
}
