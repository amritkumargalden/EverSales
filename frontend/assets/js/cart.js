/**
 * Shopping Cart Management
 * Handle adding, removing, and managing cart items
 */

const CART_STORAGE_KEY = 'shopping_cart';
const CART_HISTORY_API_URL = `${BACKEND_ORIGIN}/api/products.php?action=cart-history`;
let cartHistorySyncTimer = null;

function getCart() {
    const cart = localStorage.getItem(CART_STORAGE_KEY);
    return cart ? JSON.parse(cart) : [];
}

function saveCart(cart) {
    localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
    scheduleCartHistorySync(cart);
}

function getCartKey(item) {
    return item.cartKey || `${item.productId}-${item.purchaseType || 'retail'}`;
}

function addToCart(productId, productName, price) {
    const cart = getCart();
    const existingItem = cart.find(item => item.productId === productId && (item.purchaseType || 'retail') === 'retail');

    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            productId: productId,
            name: productName,
            price: price,
            quantity: 1,
            purchaseType: 'retail'
        });
    }

    saveCart(cart);
    if (typeof showToast === 'function') {
        showToast(`${productName} added to cart - Qty: 1`, 'success');
    } else {
        showMessage(`${productName} added to cart!`, 'success');
    }
    updateCartBadge();
}

function removeFromCart(cartKey) {
    const nextCart = getCart().filter(item => getCartKey(item) !== String(cartKey));
    saveCart(nextCart);
    loadCart();
    updateCartBadge();
}

function updateCartQuantity(cartKey, quantity) {
    const nextQuantity = parseInt(quantity);

    if (nextQuantity <= 0) {
        removeFromCart(cartKey);
        return;
    }

    const cart = getCart();
    const item = cart.find(cartItem => getCartKey(cartItem) === String(cartKey));

    if (item) {
        if ((item.purchaseType || 'retail') === 'wholesale' && nextQuantity < Number(item.minWholesaleQty || 1)) {
            if (typeof showToast === 'function') showToast(`Wholesale quantity must be at least ${item.minWholesaleQty}`, 'info'); else showMessage(`Wholesale quantity must be at least ${item.minWholesaleQty}`, 'info');
            item.quantity = Number(item.minWholesaleQty || 1);
        } else {
            item.quantity = nextQuantity;
        }

        saveCart(cart);
        loadCart();
        updateCartBadge();
    }
}

function clearCart() {
    if (confirm('Are you sure you want to clear your cart?')) {
        localStorage.removeItem(CART_STORAGE_KEY);
        loadCart();
        updateCartBadge();
        scheduleCartHistorySync([]);
        if (typeof showToast === 'function') showToast('Cart cleared', 'info'); else showMessage('Cart cleared', 'info');
    }
}

function scheduleCartHistorySync(cart) {
    const user = getCurrentUser();
    if (!user || !user.id || user.role !== 'customer') {
        return;
    }

    const payload = Array.isArray(cart) ? cart : [];

    if (cartHistorySyncTimer) {
        clearTimeout(cartHistorySyncTimer);
    }

    cartHistorySyncTimer = setTimeout(() => {
        syncCartHistory(payload);
    }, 600);
}

async function syncCartHistory(cart) {
    const items = (cart || []).map(item => ({
        productId: Number(item.productId || 0),
        quantity: Number(item.quantity || 1),
        purchaseType: item.purchaseType || 'retail'
    })).filter(item => item.productId > 0);

    try {
        await fetch(CART_HISTORY_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ items })
        });
    } catch (error) {
        console.warn('Cart history sync error:', error);
    }
}

function loadCart() {
    const cart = getCart();
    const cartContainer = document.getElementById('cartContainer');

    if (!cartContainer) return;

    if (cart.length === 0) {
        cartContainer.innerHTML = '<p style="text-align: center; padding: 20px;">Your cart is empty</p>';
        document.getElementById('cartTotal').textContent = 'NPR 0.00';
        return;
    }

    let total = 0;
    let html = '';

    cart.forEach(item => {
        item.purchaseType = item.purchaseType || 'retail';
        item.cartKey = getCartKey(item);

        const itemTotal = item.price * item.quantity;
        const isWholesale = item.purchaseType === 'wholesale';
        const minQty = Number(item.minWholesaleQty || 1);
        const typeLabel = isWholesale ? `Wholesale min ${minQty}` : 'Retail';
        total += itemTotal;

        html += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <h4>${escapeHtml(item.name)}</h4>
                    <span class="cart-type">${typeLabel}</span>
                    <p>NPR ${formatPrice(item.price)} x <input type="number" min="${isWholesale ? minQty : 1}" value="${item.quantity}" onchange="updateCartQuantity('${item.cartKey}', this.value)" class="quantity-input"></p>
                </div>
                <div class="cart-item-total">
                    <p>NPR ${formatPrice(itemTotal)}</p>
                    <button onclick="removeFromCart('${item.cartKey}')" class="btn-remove">Remove</button>
                </div>
            </div>
        `;
    });

    saveCart(cart);
    cartContainer.innerHTML = html;
    document.getElementById('cartTotal').textContent = 'NPR ' + formatPrice(total);
}

function updateCartBadge() {
    const totalItems = getCart().reduce((sum, item) => sum + (Number(item.quantity) || 0), 0);
    let badge = document.getElementById('cartBadge')
        || document.querySelector('[data-cart-count]')
        || document.querySelector('.cart-count');

    if (!badge) {
        const cartLink = Array.from(document.querySelectorAll('a, button'))
            .find(element => /showCart\(\)/.test(element.getAttribute('onclick') || ''));

        if (!cartLink) return;

        badge = document.createElement('span');
        badge.id = 'cartBadge';
        badge.className = 'cart-count-badge';
        cartLink.appendChild(badge);
    }

    badge.textContent = totalItems;
    badge.style.display = totalItems > 0 ? 'inline-flex' : 'none';
}

async function checkout() {
    const cart = getCart();

    if (cart.length === 0) {
        if (typeof showToast === 'function') showToast('Your cart is empty', 'info'); else showMessage('Your cart is empty', 'info');
        return;
    }

    const user = getCurrentUser();

    if (!user.id) {
        if (typeof showToast === 'function') showToast('Please log in to checkout', 'error'); else showMessage('Please log in to checkout', 'error');
        return;
    }

    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

    const confirmMessage = `
Order Summary:
${cart.map(item => `- ${item.name} (${item.purchaseType || 'retail'}) x${item.quantity} = NPR ${formatPrice(item.price * item.quantity)}`).join('\n')}

Total: NPR ${formatPrice(total)}

Proceed to checkout?`;

    if (confirm(confirmMessage)) {
        if (typeof placeOrder === 'function') {
            await placeOrder(cart);
        } else {
            if (typeof showToast === 'function') showToast('Order system is not available right now.', 'error'); else showMessage('Order system is not available right now.', 'error');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    updateCartBadge();
});
