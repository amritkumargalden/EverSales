/**
 * Authentication Utilities
 * Helper functions for authentication logic
 */

/**
 * Check if user is authenticated
 * Redirects to login if not
 */
async function requireAuth() {
    const loggedIn = await isLoggedIn();
    if (!loggedIn) {
        window.location.href = 'login.html';
    }
    return loggedIn;
}

/**
 * Check if user is admin
 * Redirects to admin login if not
 */
async function requireAdminAuth() {
    const user = getCurrentUser();
    if (user.role !== 'admin') {
        window.location.href = 'admin-login.html';
    }
    return user.role === 'admin';
}

/**
 * Handle admin login request
 */
async function adminLogin(email, password) {
    try {
        const response = await fetch(`${API_URL}?action=login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: email,
                password: password
            }),
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            if (data.user.role !== 'admin') {
                showMessage('Admin access required', 'error');
                return;
            }

            localStorage.setItem('user_id', data.user.id);
            localStorage.setItem('user_email', data.user.email);
            localStorage.setItem('user_name', data.user.full_name);
            localStorage.setItem('user_role', data.user.role);
            localStorage.setItem('auth_token', 'logged_in');

            showMessage('Admin login successful!', 'success');
            setTimeout(() => {
                window.location.href = 'admin-dashboard.html';
            }, 1500);
        } else {
            showMessage(data.message || 'Login failed', 'error');
        }
    } catch (error) {
        console.error('Admin login error:', error);
        showMessage('Network error. Please try again.', 'error');
    }
}

/**
 * Protect a page - redirect to login if not logged in
 */
document.addEventListener('DOMContentLoaded', async () => {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';

    if (currentPage === 'admin-dashboard.html') {
        await requireAdminAuth();
    } else if (currentPage === 'admin-login.html') {
        const user = getCurrentUser();
        if (user.role === 'admin') {
            window.location.href = 'admin-dashboard.html';
        } else if (user.role) {
            localStorage.removeItem('user_id');
            localStorage.removeItem('user_email');
            localStorage.removeItem('user_name');
            localStorage.removeItem('user_role');
            localStorage.removeItem('auth_token');
        }
    } else {
        const protectedPages = ['dashboard.html', 'index.html'];
        if (protectedPages.includes(currentPage)) {
            const loggedIn = await isLoggedIn();
            if (!loggedIn) {
                window.location.href = 'login.html';
            }
        }
    }
});
