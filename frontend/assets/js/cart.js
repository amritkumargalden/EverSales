/**
 * Shopping cart functions
 */

/**
 * Load and display shopping cart
 */
async function loadCart() {
    try {
        const response = await apiGet('/cart');
        if (response.success && response.data) {
            displayCart(response.data);
        }
    } catch (error) {
        console.error('Failed to load cart:', error);
        showMessage('Failed to load cart', 'error');
    }
}

/**
 * Display cart items
 */
function displayCart(cartData) {
    const cartContainer = document.getElementById('cartContainer');
    if (!cartContainer) return;
    
    if (!cartData.items || cartData.items.length === 0) {
        cartContainer.innerHTML = '<p>Your cart is empty</p>';
        updateCartTotal(0);
        return;
    }
    
    cartContainer.innerHTML = cartData.items.map(item => `
        <div class="cart-item">
            <h4>${item.productName}</h4>
            <p>Price: ₹${parseFloat(item.price).toFixed(2)}</p>
            <p>Quantity: 
                <input type="number" id="qty-${item.productId}" value="${item.quantity}" min="1">
                <button onclick="updateCartQuantity(${item.productId})">Update</button>
            </p>
            <p>Subtotal: ₹${parseFloat(item.total).toFixed(2)}</p>
            <button onclick="removeFromCart(${item.productId})">Remove</button>
        </div>
    `).join('');
    
    updateCartTotal(cartData.cartTotal);
}

/**
 * Update cart item quantity
 */
async function updateCartQuantity(productId) {
    try {
        const quantityInput = document.getElementById(`qty-${productId}`);
        const quantity = parseInt(quantityInput.value);
        
        if (quantity <= 0) {
            removeFromCart(productId);
            return;
        }
        
        const response = await apiPostForm('/cart/update', {
            productId,
            quantity
        });
        
        if (response.success) {
            loadCart();
        }
    } catch (error) {
        showMessage('Failed to update cart: ' + error.message, 'error');
    }
}

/**
 * Remove item from cart
 */
async function removeFromCart(productId) {
    try {
        const response = await apiPostForm('/cart/remove', {
            productId
        });
        
        if (response.success) {
            loadCart();
            showMessage('Item removed from cart', 'success');
        }
    } catch (error) {
        showMessage('Failed to remove item: ' + error.message, 'error');
    }
}

/**
 * Clear entire cart
 */
async function clearCart() {
    try {
        if (!confirm('Clear entire cart?')) {
            return;
        }
        
        const response = await apiPostForm('/cart/clear', {});
        
        if (response.success) {
            loadCart();
            showMessage('Cart cleared', 'success');
        }
    } catch (error) {
        showMessage('Failed to clear cart: ' + error.message, 'error');
    }
}

/**
 * Update cart total display
 */
function updateCartTotal(total) {
    const totalElement = document.getElementById('cartTotal');
    if (totalElement) {
        totalElement.textContent = `₹${parseFloat(total).toFixed(2)}`;
    }
}

/**
 * Proceed to checkout
 */
async function checkout() {
    try {
        // Load cart to check if empty
        const response = await apiGet('/cart');
        if (!response.data.items || response.data.items.length === 0) {
            showMessage('Cart is empty', 'error');
            return;
        }
        
        // Create order from cart
        const orderResponse = await apiPost('/orders', {});
        if (orderResponse.success) {
            showMessage('Order created! Proceeding to payment...', 'success');
            // Redirect to payment or order confirmation
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 1500);
        }
    } catch (error) {
        showMessage('Checkout failed: ' + error.message, 'error');
    }
}
