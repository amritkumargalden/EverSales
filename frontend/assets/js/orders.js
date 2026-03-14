/**
 * Order functions - view order history and order details
 */

/**
 * Load user's order history
 */
async function loadOrders() {
    try {
        const response = await apiGet('/orders');
        if (response.success && Array.isArray(response.data)) {
            displayOrders(response.data);
        }
    } catch (error) {
        console.error('Failed to load orders:', error);
        showMessage('Failed to load orders', 'error');
    }
}

/**
 * Display orders in a table
 */
function displayOrders(orders) {
    const ordersContainer = document.getElementById('ordersContainer');
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
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                ${orders.map(order => `
                    <tr>
                        <td>${order.orderId}</td>
                        <td>₹${parseFloat(order.totalAmount).toFixed(2)}</td>
                        <td><span class="status ${order.status}">${order.status}</span></td>
                        <td>${new Date(order.createdAt).toLocaleDateString()}</td>
                        <td>
                            <button onclick="viewOrderDetails(${order.orderId})">View</button>
                            ${order.status === 'pending' ? `<button onclick="cancelOrder(${order.orderId})">Cancel</button>` : ''}
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    ordersContainer.innerHTML = table;
}

/**
 * View details of a specific order
 */
async function viewOrderDetails(orderId) {
    try {
        const response = await apiGet(`/orders/${orderId}`);
        if (response.success && response.data) {
            const order = response.data;
            const details = `
                <h3>Order Details</h3>
                <p><strong>Order ID:</strong> ${order.orderId}</p>
                <p><strong>Total Amount:</strong> ₹${parseFloat(order.totalAmount).toFixed(2)}</p>
                <p><strong>Status:</strong> ${order.status}</p>
                <h4>Items:</h4>
                <ul>
                    ${order.items.map(item => `
                        <li>Product ${item.productId} - Qty: ${item.quantity} - Price: ₹${parseFloat(item.price).toFixed(2)}</li>
                    `).join('')}
                </ul>
            `;
            
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    ${details}
                    <button onclick="this.parentElement.parentElement.remove()">Close</button>
                </div>
            `;
            document.body.appendChild(modal);
        }
    } catch (error) {
        showMessage('Failed to load order details: ' + error.message, 'error');
    }
}

/**
 * Cancel an order
 */
async function cancelOrder(orderId) {
    try {
        if (!confirm('Are you sure you want to cancel this order?')) {
            return;
        }
        
        const response = await apiPost(`/orders/${orderId}/cancel`, {});
        
        if (response.success) {
            showMessage('Order cancelled successfully', 'success');
            loadOrders();
        }
    } catch (error) {
        showMessage('Failed to cancel order: ' + error.message, 'error');
    }
}
