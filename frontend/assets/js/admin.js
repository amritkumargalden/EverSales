const ADMIN_API_URL = `${BACKEND_ORIGIN}/api/admin.php`;
let adminState = {};

function adminEscape(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function adminMoney(value) {
    return Number(value || 0).toFixed(2);
}

function adminDate(value) {
    if (!value) return 'Unknown';
    return new Date(value).toLocaleString();
}

function switchAdminSection(section, button) {
    document.querySelectorAll('.admin-section').forEach(panel => panel.classList.add('hidden'));
    document.querySelectorAll('.admin-section').forEach(panel => panel.classList.remove('active'));
    document.querySelectorAll('.admin-nav').forEach(nav => nav.classList.remove('active'));

    const target = document.getElementById(`${section}Section`);
    if (target) {
        target.classList.remove('hidden');
        target.classList.add('active');
    }

    if (button) button.classList.add('active');
}

async function adminRequest(action, payload = null) {
    const options = {
        method: payload ? 'POST' : 'GET',
        credentials: 'include',
        headers: {}
    };

    if (payload) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(payload);
    }

    const response = await fetch(`${ADMIN_API_URL}?action=${encodeURIComponent(action)}`, options);
    const data = await response.json();

    if (!data.success) {
        throw new Error(data.message || 'Admin request failed');
    }

    return data;
}

async function loadAdminDashboard() {
    try {
        const data = await adminRequest('overview');
        adminState = data;
        renderAdminStats(data.stats);
        renderUsers(data.users || []);
        renderSellers(data.sellers || []);
        renderProducts(data.products || []);
        renderOrders(data.orders || []);
        renderFeedback(data.feedback || []);
        renderBanners(data.banners || []);
        renderReports(data.reports || {});
    } catch (error) {
        console.error('Admin dashboard error:', error);
        if (typeof showToast === 'function') showToast(error.message || 'Unable to load admin dashboard', 'error'); else showMessage(error.message || 'Unable to load admin dashboard', 'error');
    }
}

function renderAdminStats(stats = {}) {
    const container = document.getElementById('adminStats');
    if (!container) return;

    container.innerHTML = `
        <div class="stat-card"><h3>Total Users</h3><div class="stat-value">${stats.totalUsers || 0}</div></div>
        <div class="stat-card"><h3>Sellers</h3><div class="stat-value">${stats.totalSellers || 0}</div></div>
        <div class="stat-card"><h3>Products</h3><div class="stat-value">${stats.totalProducts || 0}</div></div>
        <div class="stat-card"><h3>Orders</h3><div class="stat-value">${stats.totalOrders || 0}</div></div>
        <div class="stat-card"><h3>Revenue</h3><div class="stat-value">Rs. ${adminMoney(stats.totalRevenue)}</div></div>
    `;
}

function renderUsers(users) {
    const container = document.getElementById('adminUsers');
    if (!container) return;

    if (users.length === 0) {
        container.innerHTML = '<p class="empty-admin">No users found.</p>';
        return;
    }

    container.innerHTML = `
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Joined</th><th>Action</th></tr></thead>
            <tbody>
                ${users.map(user => `
                    <tr>
                        <td>${adminEscape(user.full_name)}</td>
                        <td>${adminEscape(user.email)}</td>
                        <td>${adminEscape(user.phone_number || '-')}</td>
                        <td><span class="status ${adminEscape(user.role)}">${adminEscape(user.role)}</span></td>
                        <td>${adminDate(user.created_at)}</td>
                        <td>
                            <select onchange="updateUserRole(${user.id}, this.value)">
                                <option value="customer" ${user.role === 'customer' ? 'selected' : ''}>Customer</option>
                                <option value="seller" ${user.role === 'seller' ? 'selected' : ''}>Seller</option>
                                <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                            </select>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function renderSellers(sellers) {
    const container = document.getElementById('adminSellers');
    if (!container) return;

    if (sellers.length === 0) {
        container.innerHTML = '<p class="empty-admin">No sellers found.</p>';
        return;
    }

    container.innerHTML = `
        <table>
            <thead><tr><th>Seller</th><th>Email</th><th>Status</th><th>Products</th><th>Total Stock</th><th>Orders</th><th>Revenue</th><th>Action</th></tr></thead>
            <tbody>
                ${sellers.map(seller => `
                    <tr>
                        <td>${adminEscape(seller.full_name)}</td>
                        <td>${adminEscape(seller.email)}</td>
                        <td><span class="status ${Number(seller.is_blocked) === 1 ? 'rejected' : 'approved'}">${Number(seller.is_blocked) === 1 ? 'Blocked' : 'Active'}</span></td>
                        <td>${seller.product_count || 0}</td>
                        <td>${seller.total_stock || 0}</td>
                        <td>${seller.order_count || 0}</td>
                        <td>Rs. ${adminMoney(seller.revenue)}</td>
                        <td class="action-row">
                            <button class="mini-btn ${Number(seller.is_blocked) === 1 ? 'approve' : 'reject'}" onclick="toggleSellerBlock(${seller.id}, ${Number(seller.is_blocked) === 1 ? 0 : 1})">
                                ${Number(seller.is_blocked) === 1 ? 'Unblock' : 'Block'}
                            </button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function renderProducts(products) {
    const container = document.getElementById('adminProducts');
    if (!container) return;

    if (products.length === 0) {
        container.innerHTML = '<p class="empty-admin">No products found.</p>';
        return;
    }

    container.innerHTML = `
        <table>
            <thead><tr><th>Product</th><th>Seller</th><th>Price</th><th>Stock</th><th>Status</th><th>Moderation</th></tr></thead>
            <tbody>
                ${products.map(product => `
                    <tr>
                        <td>
                            <strong>${adminEscape(product.name)}</strong>
                            <small>${adminEscape(product.description || 'No description')}</small>
                        </td>
                        <td>${adminEscape(product.seller_name || 'Unknown')}</td>
                        <td>Rs. ${adminMoney(product.price)}</td>
                        <td>${product.stock}</td>
                        <td><span class="status ${adminEscape(product.product_status || 'approved')}">${adminEscape(product.product_status || 'approved')}</span></td>
                        <td class="action-row">
                            <button class="mini-btn approve" onclick="updateProductStatus(${product.product_id}, 'approved')">Approve</button>
                            <button class="mini-btn reject" onclick="updateProductStatus(${product.product_id}, 'rejected')">Reject</button>
                            <button class="mini-btn pending" onclick="updateProductStatus(${product.product_id}, 'pending')">Review</button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function renderOrders(orders) {
    const container = document.getElementById('adminOrders');
    if (!container) return;

    if (orders.length === 0) {
        container.innerHTML = '<p class="empty-admin">No orders found.</p>';
        return;
    }

    container.innerHTML = `
        <table>
            <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Placed</th><th>Update</th></tr></thead>
            <tbody>
                ${orders.map(order => `
                    <tr>
                        <td>#${order.order_id}</td>
                        <td>${adminEscape(order.user_name)}<small>${adminEscape(order.user_email)}</small></td>
                        <td>Rs. ${adminMoney(order.total_amount)}</td>
                        <td><span class="status ${adminEscape(order.status)}">${adminEscape(order.status)}</span></td>
                        <td>${adminDate(order.created_at)}</td>
                        <td>
                            <select onchange="updateOrderStatus(${order.order_id}, this.value)">
                                <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="completed" ${order.status === 'completed' ? 'selected' : ''}>Completed</option>
                                <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                            </select>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function renderFeedback(feedbackItems) {
    const container = document.getElementById('adminFeedback');
    if (!container) return;

    if (!feedbackItems || feedbackItems.length === 0) {
        container.innerHTML = '<p class="empty-admin">No feedback submitted yet.</p>';
        return;
    }

    container.innerHTML = `
        <table>
            <thead><tr><th>Order</th><th>Customer</th><th>Type</th><th>Rating</th><th>Message</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead>
            <tbody>
                ${feedbackItems.map(item => `
                    <tr>
                        <td>#${item.order_id}</td>
                        <td>${adminEscape(item.user_name)}<small>${adminEscape(item.user_email || '')}</small></td>
                        <td><span class="status ${item.feedback_type === 'complaint' ? 'rejected' : 'approved'}">${adminEscape(item.feedback_type)}</span></td>
                        <td>${item.feedback_type === 'review' ? (item.rating || '-') : '-'}</td>
                        <td>${adminEscape(item.message)}</td>
                        <td><span class="status ${Number(item.is_resolved) === 1 ? 'approved' : 'pending'}">${Number(item.is_resolved) === 1 ? 'Resolved' : 'Open'}</span></td>
                        <td>${adminDate(item.created_at)}</td>
                        <td class="action-row">
                            ${item.feedback_type === 'complaint' ? `
                                <button class="mini-btn ${Number(item.is_resolved) === 1 ? 'pending' : 'approve'}" onclick="toggleFeedbackResolve(${item.feedback_id}, ${Number(item.is_resolved) === 1 ? 0 : 1})">
                                    ${Number(item.is_resolved) === 1 ? 'Reopen' : 'Mark Resolved'}
                                </button>
                            ` : '-'}
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

async function toggleFeedbackResolve(feedbackId, isResolved) {
    const confirmText = isResolved ? 'Mark this complaint as resolved?' : 'Reopen this complaint?';
    if (!confirm(confirmText)) return;

    try {
        await adminRequest('update-feedback-status', { feedback_id: feedbackId, is_resolved: isResolved });
        if (typeof showToast === 'function') showToast('Feedback status updated', 'success'); else showMessage('Feedback status updated', 'success');
        loadAdminDashboard();
    } catch (error) {
        if (typeof showToast === 'function') showToast(error.message, 'error'); else showMessage(error.message, 'error');
    }
}

function renderBanners(banners) {
    const container = document.getElementById('adminBanners');
    if (!container) return;

    if (banners.length === 0) {
        container.innerHTML = '<p class="empty-admin">No banners created yet.</p>';
        return;
    }

    container.innerHTML = banners.map(banner => `
        <div class="banner-admin-card">
            <div>
                <strong>${adminEscape(banner.title)}</strong>
                <p>${adminEscape(banner.subtitle || '')}</p>
                <small>${banner.is_active == 1 ? 'Active' : 'Hidden'} - Sort ${banner.sort_order || 0}</small>
            </div>
            <div class="action-row">
                <button class="mini-btn" onclick='editBanner(${JSON.stringify(banner).replace(/'/g, '&apos;')})'>Edit</button>
                <button class="mini-btn reject" onclick="deleteBanner(${banner.banner_id})">Delete</button>
            </div>
        </div>
    `).join('');
}

function renderReports(reports) {
    const container = document.getElementById('adminReports');
    if (!container) return;

    const topProducts = reports.topProducts || [];
    const sellerRevenue = reports.sellerRevenue || [];

    container.innerHTML = `
        <div class="report-grid">
            <div class="report-card">
                <h4>Revenue Snapshot</h4>
                <p><strong>Gross Revenue:</strong> Rs. ${adminMoney(reports.totalRevenue)}</p>
                <p><strong>Average Order:</strong> Rs. ${adminMoney(reports.averageOrderValue)}</p>
                <p><strong>Completed Orders:</strong> ${reports.completedOrders || 0}</p>
            </div>
            <div class="report-card">
                <h4>Top Products</h4>
                ${topProducts.length ? topProducts.map(product => `
                    <p>${adminEscape(product.product_name)} <strong>Rs. ${adminMoney(product.revenue)}</strong></p>
                `).join('') : '<p>No product sales yet.</p>'}
            </div>
            <div class="report-card">
                <h4>Seller Revenue</h4>
                ${sellerRevenue.length ? sellerRevenue.map(seller => `
                    <p>${adminEscape(seller.seller_name)} <strong>Rs. ${adminMoney(seller.revenue)}</strong></p>
                `).join('') : '<p>No seller revenue yet.</p>'}
            </div>
        </div>
    `;
}

async function updateUserRole(userId, role) {
    try {
        await adminRequest('update-user-role', { user_id: userId, role });
        if (typeof showToast === 'function') showToast('User role updated', 'success'); else showMessage('User role updated', 'success');
        loadAdminDashboard();
    } catch (error) {
        if (typeof showToast === 'function') showToast(error.message, 'error'); else showMessage(error.message, 'error');
    }
}

async function updateProductStatus(productId, status) {
    try {
        await adminRequest('update-product-status', { product_id: productId, status });
        if (typeof showToast === 'function') showToast('Product moderation status updated', 'success'); else showMessage('Product moderation status updated', 'success');
        loadAdminDashboard();
    } catch (error) {
        if (typeof showToast === 'function') showToast(error.message, 'error'); else showMessage(error.message, 'error');
    }
}

async function updateOrderStatus(orderId, status) {
    try {
        await adminRequest('update-order-status', { order_id: orderId, status });
        if (typeof showToast === 'function') showToast('Order status updated', 'success'); else showMessage('Order status updated', 'success');
        loadAdminDashboard();
    } catch (error) {
        if (typeof showToast === 'function') showToast(error.message, 'error'); else showMessage(error.message, 'error');
    }
}

async function toggleSellerBlock(sellerId, isBlocked) {
    const confirmText = isBlocked
        ? 'Block this seller? They will not be able to manage products.'
        : 'Unblock this seller? They will regain access to seller tools.';

    if (!confirm(confirmText)) return;

    try {
        await adminRequest('update-seller-status', { seller_id: sellerId, is_blocked: isBlocked });
        if (typeof showToast === 'function') showToast('Seller status updated', 'success'); else showMessage('Seller status updated', 'success');
        loadAdminDashboard();
    } catch (error) {
        if (typeof showToast === 'function') showToast(error.message, 'error'); else showMessage(error.message, 'error');
    }
}

async function saveBanner(event) {
    event.preventDefault();

    const payload = {
        banner_id: document.getElementById('bannerId').value || null,
        title: document.getElementById('bannerTitle').value,
        subtitle: document.getElementById('bannerSubtitle').value,
        image_url: document.getElementById('bannerImage').value,
        target_url: document.getElementById('bannerTarget').value,
        sort_order: document.getElementById('bannerOrder').value,
        is_active: document.getElementById('bannerActive').checked ? 1 : 0
    };

    try {
        await adminRequest('save-banner', payload);
        if (typeof showToast === 'function') showToast('Banner saved', 'success'); else showMessage('Banner saved', 'success');
        resetBannerForm();
        loadAdminDashboard();
    } catch (error) {
        if (typeof showToast === 'function') showToast(error.message, 'error'); else showMessage(error.message, 'error');
    }
}

function editBanner(banner) {
    document.getElementById('bannerId').value = banner.banner_id;
    document.getElementById('bannerTitle').value = banner.title || '';
    document.getElementById('bannerSubtitle').value = banner.subtitle || '';
    document.getElementById('bannerImage').value = banner.image_url || '';
    document.getElementById('bannerTarget').value = banner.target_url || '';
    document.getElementById('bannerOrder').value = banner.sort_order || 0;
    document.getElementById('bannerActive').checked = Number(banner.is_active) === 1;
}

function resetBannerForm() {
    document.getElementById('bannerForm').reset();
    document.getElementById('bannerId').value = '';
    document.getElementById('bannerActive').checked = true;
}

async function deleteBanner(bannerId) {
    if (!confirm('Delete this banner?')) return;

    try {
        await adminRequest('delete-banner', { banner_id: bannerId });
        if (typeof showToast === 'function') showToast('Banner deleted', 'success'); else showMessage('Banner deleted', 'success');
        loadAdminDashboard();
    } catch (error) {
        if (typeof showToast === 'function') showToast(error.message, 'error'); else showMessage(error.message, 'error');
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    const loggedIn = await isLoggedIn();
    if (!loggedIn) {
        window.location.href = 'login.html';
        return;
    }

    const user = getCurrentUser();
    if (user.role !== 'admin') {
        window.location.href = 'index.html';
        return;
    }

    loadAdminDashboard();
});
