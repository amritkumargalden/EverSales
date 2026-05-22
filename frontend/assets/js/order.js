/**
 * Order Management
 * Handles checkout order creation and order history rendering
 */

const ORDER_API_URL = `${BACKEND_ORIGIN}/api/orders.php`;

function formatOrderMoney(value) {
    if (typeof formatPrice === 'function') {
        return formatPrice(value);
    }

    return Number(value || 0).toFixed(2);
}

function escapeOrderHtml(value) {
    if (typeof escapeHtml === 'function') {
        return escapeHtml(value);
    }

    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatOrderDate(value) {
    if (!value) return 'Unknown date';
    return new Date(value).toLocaleString();
}

function renderOrderList(containerId, orders, emptyMessage) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!orders || orders.length === 0) {
        container.innerHTML = `<p style="padding: 1rem; text-align: center;">${emptyMessage}</p>`;
        return;
    }

    container.innerHTML = orders.map(order => {
        const itemsHtml = (order.items || []).map(item => `
            <tr>
                <td>${escapeOrderHtml(item.product_name)}</td>
                <td>${item.quantity}</td>
                <td>₹${formatOrderMoney(item.price)}</td>
                <td>₹${formatOrderMoney(item.line_total)}</td>
            </tr>
        `).join('');

        return `
            <div class="order-card" style="margin-bottom: 1.5rem; padding: 1rem; border: 1px solid #ecf0f1; border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; align-items: center; margin-bottom: 1rem;">
                    <div>
                        <h3 style="margin-bottom: 0.25rem;">Order #${order.order_id}</h3>
                        <p style="margin: 0; color: #7f8c8d;">${escapeOrderHtml(order.user_name || 'Customer')} · ${formatOrderDate(order.created_at)}</p>
                    </div>
                    <span class="status ${escapeOrderHtml(order.status)}">${escapeOrderHtml(order.status)}</span>
                </div>
                <p><strong>Total:</strong> ₹${formatOrderMoney(order.total_amount)}</p>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                </table>
            </div>
        `;
    }).join('');
}

async function placeOrder(cart) {
    if (!Array.isArray(cart) || cart.length === 0) {
        showMessage('Your cart is empty', 'info');
        return false;
    }

    try {
        const response = await fetch(`${ORDER_API_URL}?action=create`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                items: cart,
                paymentMethod: 'bank_transfer'
            })
        });

        const data = await response.json();

        if (!data.success) {
            showMessage(data.message || 'Checkout failed', 'error');
            return false;
        }

        localStorage.removeItem('shopping_cart');

        if (typeof loadCart === 'function') {
            loadCart();
        }

        if (typeof closeCart === 'function') {
            closeCart();
        }

        if (typeof loadOrders === 'function') {
            loadOrders();
        }

        showMessage(`Order #${data.order_id} placed successfully!`, 'success');
        return true;
    } catch (error) {
        console.error('Place order error:', error);
        showMessage('Checkout failed. Please try again.', 'error');
        return false;
    }
}

async function loadOrders() {
    const container = document.getElementById('ordersContainer');
    if (!container) return;

    try {
        const response = await fetch(`${ORDER_API_URL}?action=my-orders`, {
            credentials: 'include'
        });

        const data = await response.json();

        if (!data.success) {
            renderOrderList('ordersContainer', [], data.message || 'Unable to load orders');
            return;
        }

        renderOrderList('ordersContainer', data.orders || [], 'No orders found');
    } catch (error) {
        console.error('Load orders error:', error);
        renderOrderList('ordersContainer', [], 'Unable to load orders');
    }
}

async function loadAdminDashboard() {
    const statsContainer = document.getElementById('statsContainer');
    const usersContainer = document.getElementById('usersContainer');
    const allOrdersContainer = document.getElementById('allOrdersContainer');

    if (!statsContainer && !usersContainer && !allOrdersContainer) return;

    try {
        const response = await fetch(`${ORDER_API_URL}?action=admin-dashboard`, {
            credentials: 'include'
        });

        const data = await response.json();

        if (!data.success) {
            if (statsContainer) statsContainer.innerHTML = `<p>${escapeOrderHtml(data.message || 'Unable to load admin dashboard')}</p>`;
            return;
        }

        if (statsContainer && data.stats) {
            statsContainer.innerHTML = `
                <div class="stat-card"><h3>Total Orders</h3><div class="stat-value">${data.stats.totalOrders}</div></div>
                <div class="stat-card"><h3>Total Revenue</h3><div class="stat-value">₹${formatOrderMoney(data.stats.totalRevenue)}</div></div>
                <div class="stat-card"><h3>Pending Orders</h3><div class="stat-value">${data.stats.pendingOrders}</div></div>
                <div class="stat-card"><h3>Completed Orders</h3><div class="stat-value">${data.stats.completedOrders}</div></div>
                <div class="stat-card"><h3>Total Users</h3><div class="stat-value">${data.stats.totalUsers}</div></div>
            `;
        }

        if (usersContainer) {
            const users = data.users || [];
            if (users.length === 0) {
                usersContainer.innerHTML = '<p style="padding: 1rem; text-align: center;">No users found</p>';
            } else {
                usersContainer.innerHTML = `
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${users.map(user => `
                                <tr>
                                    <td>${escapeOrderHtml(user.full_name)}</td>
                                    <td>${escapeOrderHtml(user.email)}</td>
                                    <td>${escapeOrderHtml(user.role)}</td>
                                    <td>${formatOrderDate(user.created_at)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            }
        }

        if (allOrdersContainer) {
            renderOrderList('allOrdersContainer', data.orders || [], 'No orders found');
        }
    } catch (error) {
        console.error('Load admin dashboard error:', error);
        if (statsContainer) statsContainer.innerHTML = '<p>Unable to load admin dashboard</p>';
    }
}