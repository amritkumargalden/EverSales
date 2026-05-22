/**
 * Shopping Cart Management
 * Handle adding, removing, and managing cart items
 */

const CART_STORAGE_KEY = 'shopping_cart';

/**
 * Initialize cart from localStorage
 */
function getCart() {
    const cart = localStorage.getItem(CART_STORAGE_KEY);
    return cart ? JSON.parse(cart) : [];
}

/**
 * Save cart to localStorage
 */
function saveCart(cart) {
    localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
}

/**
 * Add product to cart
 */
function addToCart(productId, productName, price) {
    const cart = getCart();
    const existingItem = cart.find(item => item.productId === productId);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            productId: productId,
            name: productName,
            price: price,
            quantity: 1
        });
    }
    
    saveCart(cart);
    showMessage(`${productName} added to cart!`, 'success');
    updateCartBadge();
}

/**
 * Remove item from cart
 */
function removeFromCart(productId) {
    let cart = getCart();
    cart = cart.filter(item => item.productId !== productId);
    saveCart(cart);
    loadCart();
    updateCartBadge();
}

/**
 * Update cart item quantity
 */
function updateCartQuantity(productId, quantity) {
    if (quantity <= 0) {
        removeFromCart(productId);
        return;
    }
    
    const cart = getCart();
    const item = cart.find(item => item.productId === productId);
    
    if (item) {
        item.quantity = parseInt(quantity);
        saveCart(cart);
        loadCart();
        updateCartBadge();
    }
}

/**
 * Clear entire cart
 */
function clearCart() {
    if (confirm('Are you sure you want to clear your cart?')) {
        localStorage.removeItem(CART_STORAGE_KEY);
        loadCart();
        updateCartBadge();
        showMessage('Cart cleared', 'info');
    }
}

/**
 * Load and display cart items
 */
function loadCart() {
    const cart = getCart();
    const cartContainer = document.getElementById('cartContainer');
    
    if (!cartContainer) return;
    
    if (cart.length === 0) {
        cartContainer.innerHTML = '<p style="text-align: center; padding: 20px;">Your cart is empty</p>';
        document.getElementById('cartTotal').textContent = '₹0.00';
        return;
    }
    
    let total = 0;
    let html = '';
    
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        
        html += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <h4>${escapeHtml(item.name)}</h4>
                    <p>₹${formatPrice(item.price)} x <input type="number" min="1" value="${item.quantity}" onchange="updateCartQuantity(${item.productId}, this.value)" class="quantity-input"></p>
                </div>
                <div class="cart-item-total">
                    <p>₹${formatPrice(itemTotal)}</p>
                    <button onclick="removeFromCart(${item.productId})" class="btn-remove">Remove</button>
                </div>
            </div>
        `;
    });
    
    cartContainer.innerHTML = html;
    document.getElementById('cartTotal').textContent = '₹' + formatPrice(total);
}

/**
 * Update cart badge count
 */
function updateCartBadge() {
    return;
}

/**
 * Checkout function
 */
async function checkout() {
    const cart = getCart();
    
    if (cart.length === 0) {
        showMessage('Your cart is empty', 'info');
        return;
    }
    
    const user = getCurrentUser();
    
    if (!user.id) {
        showMessage('Please log in to checkout', 'error');
        return;
    }
    
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    
    const confirmMessage = `
Order Summary:
${cart.map(item => `- ${item.name} x${item.quantity} = ₹${formatPrice(item.price * item.quantity)}`).join('\n')}

Total: ₹${formatPrice(total)}

Proceed to checkout?`;
    
    if (confirm(confirmMessage)) {
        if (typeof placeOrder === 'function') {
            await placeOrder(cart);
        } else {
            showMessage('Order system is not available right now.', 'error');
        }
    }
}

/**
 * Initialize cart on page load
 */
document.addEventListener('DOMContentLoaded', () => {
    updateCartBadge();
});
