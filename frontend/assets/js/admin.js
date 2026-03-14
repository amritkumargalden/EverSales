/**
 * Admin functions - admin dashboard operations
 */

let currentUser = null;

/**
 * Load admin dashboard
 */
async function loadAdminDashboard() {
    try {
        const user = await getCurrentUser();
        if (!user || user.role !== 'admin') {
            showMessage('Admin access required', 'error');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 2000);
            return;
        }
        
        currentUser = user;
        await loadAdminStats();
        await loadAllUsers();
        await loadAllOrders();
    } catch (error) {
        console.error('Failed to load admin dashboard:', error);
    }
}

/**
 * Load dashboard statistics
 */
async function loadAdminStats() {
    try {
        const response = await apiGet('/admin/stats');
        if (response.success && response.data) {
            const stats = response.data;
            const statsContainer = document.getElementById('statsContainer');
            if (statsContainer) {
                statsContainer.innerHTML = `
                    <div class="stat-card">
                        <h3>Total Users</h3>
                        <p class="stat-value">${stats.userCount}</p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Orders</h3>
                        <p class="stat-value">${stats.orderCount}</p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Products</h3>
                        <p class="stat-value">${stats.productCount}</p>
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

/**
 * Load all users
 */
async function loadAllUsers() {
    try {
        const response = await apiGet('/admin/users');
        if (response.success && Array.isArray(response.data)) {
            displayUsersList(response.data);
        }
    } catch (error) {
        console.error('Failed to load users:', error);
        showMessage('Failed to load users list', 'error');
    }
}

/**
 * Display users list
 */
function displayUsersList(users) {
    const usersContainer = document.getElementById('usersContainer');
    if (!usersContainer) return;
    
    const table = `
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Phone</th>
                </tr>
            </thead>
            <tbody>
                ${users.map(user => `
                    <tr>
                        <td>${user.id}</td>
                        <td>${user.fullName}</td>
                        <td>${user.email}</td>
                        <td>${user.role}</td>
                        <td>${user.phoneNumber || '-'}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    usersContainer.innerHTML = table;
}

/**
 * Load all orders
 */
async function loadAllOrders() {
    try {
        const response = await apiGet('/admin/orders');
        if (response.success && Array.isArray(response.data)) {
            displayOrdersList(response.data);
        }
    } catch (error) {
        console.error('Failed to load orders:', error);
        showMessage('Failed to load orders list', 'error');
    }
}

/**
 * Display orders list
 */
function displayOrdersList(orders) {
    const ordersContainer = document.getElementById('allOrdersContainer');
    if (!ordersContainer) return;
    
    if (orders.length === 0) {
        ordersContainer.innerHTML = '<p>No orders found</p>';
        return;
    }
    
    const table = `
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>User ID</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                ${orders.map(order => `
                    <tr>
                        <td>${order.orderId}</td>
                        <td>${order.userId}</td>
                        <td>₹${parseFloat(order.totalAmount).toFixed(2)}</td>
                        <td><span class="status ${order.status}">${order.status}</span></td>
                        <td>
                            <button onclick="updateOrderStatus(${order.orderId}, '${order.status}')">Update Status</button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    ordersContainer.innerHTML = table;
}

/**
 * Update order status
 */
async function updateOrderStatus(orderId, currentStatus) {
    try {
        const newStatus = prompt(`Enter new status (current: ${currentStatus}):`);
        if (!newStatus) return;
        
        const validStatuses = ['pending', 'completed', 'cancelled'];
        if (!validStatuses.includes(newStatus)) {
            showMessage('Invalid status. Valid options: ' + validStatuses.join(', '), 'error');
            return;
        }
        
        // Note: This would require an endpoint to update status directly
        showMessage('Status update feature would be implemented in full version', 'info');
    } catch (error) {
        showMessage('Failed to update status: ' + error.message, 'error');
    }
}

/**
 * Load admin dashboard on page load
 */
document.addEventListener('DOMContentLoaded', async () => {
    if (document.getElementById('statsContainer')) {
        await loadAdminDashboard();
    }
});
