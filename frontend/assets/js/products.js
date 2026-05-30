/**
 * Products Management
 * Load and display products on the homepage
 */


const PRODUCTS_API_URL = `${BACKEND_ORIGIN}/api/products.php`;

/**
 * Load all products and display them
 */
async function loadProducts() {
    try {
        await loadPersonalizedContent();

        const response = await fetch(`${PRODUCTS_API_URL}?action=get-all`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success && data.products.length > 0) {
            displayProducts(data.products);
        } else {
            displayEmptyProducts();
        }
    } catch (error) {
        console.error('Error loading products:', error);
        showMessage('Error loading products', 'error');
        displayEmptyProducts();
    }
}

/**
 * Display products in grid
 */
function displayProducts(products) {
    const container = document.getElementById('productsContainer');
    
    if (!container) return;
    
    container.innerHTML = '';
    
    products.forEach(product => {
        const productCard = createProductCard(product);
        container.appendChild(productCard);
    });
}

async function loadPersonalizedContent() {
    const user = getCurrentUser();
    const container = document.getElementById('personalizedContent');

    if (!container || user.role !== 'customer') {
        if (container) container.classList.add('hidden');
        return;
    }

    try {
        const response = await fetch(`${PRODUCTS_API_URL}?action=recommendations`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (!data.success) {
            container.classList.add('hidden');
            return;
        }

        renderPersonalizedContent(data);
    } catch (error) {
        console.error('Recommendations error:', error);
        container.classList.add('hidden');
    }
}

function renderPersonalizedContent(data) {
    const container = document.getElementById('personalizedContent');
    if (!container) return;

    const sections = [];

    if (data.has_history && Array.isArray(data.buy_again) && data.buy_again.length > 0) {
        sections.push(createPersonalizedSection(
            'Buy Again',
            'Products from your previous carts, ready for quick reorder.',
            data.buy_again.slice(0, 4)
        ));
    }

    if (data.has_history && Array.isArray(data.recommended) && data.recommended.length > 0) {
        sections.push(createPersonalizedSection(
            'Recommended for You',
            'More products from sellers you have bought from before.',
            data.recommended.slice(0, 4)
        ));
    }

    if (!data.has_history && Array.isArray(data.trending) && data.trending.length > 0) {
        sections.push(createPersonalizedSection(
            'Popular Right Now',
            'Start your shopping history with products other customers are buying.',
            data.trending.slice(0, 4)
        ));
    }

    if (sections.length === 0) {
        container.classList.add('hidden');
        container.innerHTML = '';
        return;
    }

    container.innerHTML = '';
    sections.forEach(section => container.appendChild(section));
    container.classList.remove('hidden');
}

function createPersonalizedSection(title, subtitle, products) {
    const section = document.createElement('section');
    section.className = 'personalized-section';

    const header = document.createElement('div');
    header.className = 'personalized-header';
    header.innerHTML = `
        <div>
            <h2>${escapeHtml(title)}</h2>
            <p>${escapeHtml(subtitle)}</p>
        </div>
    `;

    const grid = document.createElement('div');
    grid.className = 'product-grid personalized-grid';
    products.forEach(product => grid.appendChild(createProductCard(product)));

    section.appendChild(header);
    section.appendChild(grid);
    return section;
}

/**
 * Create a product card element
 */
function createProductCard(product) {
    const card = document.createElement('div');
    card.className = 'product-card glass';

    const imageUrl = product.primary_image
        ? resolveBackendAssetUrl(product.primary_image)
        : 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500&q=80';

    const user = getCurrentUser();
    const retailPrice = product.price || 0;
    const wholesalePrice = product.wholesale_price ?? (retailPrice * 0.8);
    const minWholesaleQty = product.min_wholesale_qty ?? 5;

    let actionContent = '';
    if (user && user.role === 'seller') {
        actionContent = `<div class="seller-view">Seller View</div>`;
    } else {
        actionContent = `
            <form class="product-buy-form">
                <input type="hidden" name="product_id" value="${product.id}">
                <input type="number" name="quantity" value="1" min="1" class="form-control quantity-control" required>
                <div class="buy-actions">
                    <button type="button" class="btn btn-wholesale" data-purchase-type="retail">Buy Retail</button>
                    <button type="button" class="btn btn-wholesale" data-purchase-type="wholesale">Buy Wholesale</button>
                </div>
            </form>
        `;
    }

    card.innerHTML = `
        <img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(product.name)}" class="product-image">
        <div class="product-info">
            <h2 class="product-title">${escapeHtml(product.name)}</h2>
            <p class="product-desc">${escapeHtml(product.description || 'Quality product from trusted seller')}</p>

            <div class="pricing-container">
                <div class="price-row retail">
                    <span class="price-label">Retail Price</span>
                    <span class="price-value">NPR ${formatPrice(retailPrice)}</span>
                </div>

                <div class="price-row wholesale">
                    <div>
                        <span class="price-label">Wholesale Price</span>
                        <span class="wholesale-condition">Min. Qty: ${minWholesaleQty}</span>
                    </div>
                    <span class="price-value">NPR ${formatPrice(wholesalePrice)}</span>
                </div>
            </div>

            ${actionContent}
        </div>
    `;

    card.querySelectorAll('[data-purchase-type]').forEach(button => {
        button.addEventListener('click', event => {
            addToCartWithQuantity(
                product.id,
                product.name,
                retailPrice,
                wholesalePrice,
                minWholesaleQty,
                button.dataset.purchaseType,
                event
            );
        });
    });

    return card;
}

/**
 * Display empty state
 */
function displayEmptyProducts() {
    const container = document.getElementById('productsContainer');
    if (!container) return;
    
    container.innerHTML = `
        <div class="empty-state">
            <p>No products available. <a href="seller-dashboard.html">Become a seller and add products!</a></p>
        </div>
    `;
}

/**
 * Search products
 */
async function searchProducts() {
    const query = document.getElementById('searchInput').value.trim();
    
    if (query.length === 0) {
        await loadProducts();
        return;
    }

    const personalizedContainer = document.getElementById('personalizedContent');
    if (personalizedContainer) {
        personalizedContainer.classList.add('hidden');
    }

    if (query.length < 2) {
        showMessage('Search query must be at least 2 characters', 'info');
        return;
    }
    
    try {
        const response = await fetch(`${PRODUCTS_API_URL}?action=search&q=${encodeURIComponent(query)}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.products.length > 0) {
                displayProducts(data.products);
                showMessage(`Found ${data.products.length} product(s)`, 'success');
            } else {
                displayEmptyProducts();
                showMessage('No products found', 'info');
            }
        } else {
            showMessage(data.message || 'Error searching products', 'error');
        }
    } catch (error) {
        console.error('Search error:', error);
        showMessage('Error searching products', 'error');
    }
}

/**
 * Get product details
 */
async function getProductDetails(productId) {
    try {
        const response = await fetch(`${PRODUCTS_API_URL}?action=get-single&product_id=${productId}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        return data.success ? data.product : null;
    } catch (error) {
        console.error('Error fetching product details:', error);
        return null;
    }
}

/**
 * Format price for display
 */
function formatPrice(price) {
    return parseFloat(price).toFixed(2);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

/**
 * Add to cart with quantity from form
 */
function addToCartWithQuantity(productId, productName, retailPrice, wholesalePrice, minWholesaleQty, purchaseType, event) {
    event.preventDefault();

    const form = event.target.closest('form');
    const quantityInput = form.querySelector('input[name="quantity"]');
    const quantity = parseInt(quantityInput.value) || 1;
    const normalizedType = purchaseType === 'wholesale' ? 'wholesale' : 'retail';
    const effectivePrice = parseFloat(normalizedType === 'wholesale' ? wholesalePrice : retailPrice) || 0;

    if (normalizedType === 'wholesale' && quantity < minWholesaleQty) {
        quantityInput.value = minWholesaleQty;
        showMessage(`Wholesale orders require at least ${minWholesaleQty} units. Quantity updated for you.`, 'info');
        return;
    }

    const cart = getCart();
    const existingItem = cart.find(item => item.productId === productId && (item.purchaseType || 'retail') === normalizedType);

    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        cart.push({
            productId: productId,
            name: productName,
            price: effectivePrice,
            retailPrice: retailPrice,
            wholesalePrice: wholesalePrice,
            minWholesaleQty: minWholesaleQty,
            purchaseType: normalizedType,
            quantity: quantity
        });
    }

    saveCart(cart);
    if (typeof showToast === 'function') {
        showToast(`${productName} added to cart (${normalizedType}) - Qty: ${quantity}`, 'success');
    } else {
        showMessage(`${productName} added to cart as ${normalizedType}! (Qty: ${quantity})`, 'success');
    }
    updateCartBadge();
}

/**
 * Become a seller (from navbar button)
 */
async function becomeSeller() {
    const user = getCurrentUser();
    
    if (user.role === 'seller') {
        window.location.href = 'seller-dashboard.html';
        return;
    }
    
    if (confirm('Are you sure you want to become a seller? You can then add products and manage your store.')) {
        try {
            const response = await fetch(`${BACKEND_ORIGIN}/api/seller.php?action=become-seller`, {
                method: 'POST',
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                localStorage.setItem('user_role', 'seller');
                showMessage('Welcome to EverSales Seller! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = 'seller-dashboard.html';
                }, 1500);
            } else {
                showMessage(data.message || 'Error becoming seller', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('Network error', 'error');
        }
    }
}

/**
 * Update navbar based on user role
 */
function updateNavbar() {
    const user = getCurrentUser();
    const becomeSellBtn = document.getElementById('becomeSeller');
    const sellerLink = document.getElementById('sellerLink');
    
    if (!user.role) {
        // Not logged in
        if (becomeSellBtn) becomeSellBtn.style.display = 'none';
        if (sellerLink) sellerLink.style.display = 'none';
    } else if (user.role === 'seller') {
        // Is seller
        if (becomeSellBtn) becomeSellBtn.style.display = 'none';
        if (sellerLink) sellerLink.style.display = 'block';
    } else {
        // Is customer
        if (becomeSellBtn) becomeSellBtn.style.display = 'block';
        if (sellerLink) sellerLink.style.display = 'none';
    }
}

/**
 * Initialize page on load
 */
document.addEventListener('DOMContentLoaded', () => {
    updateNavbar();
});
