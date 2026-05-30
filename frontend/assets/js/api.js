/**
 * API Service
 * Handles all requests to the PHP backend
 */

const APP_BASE_PATH = window.location.pathname.includes('/frontend/')
    ? window.location.pathname.split('/frontend/')[0]
    : '';
const BACKEND_ORIGIN = `${window.location.origin}${APP_BASE_PATH}/backend/src`;
const API_URL = `${BACKEND_ORIGIN}/api/auth.php`;
const ASSET_API_URL = `${BACKEND_ORIGIN}/api/asset.php`;

/**
 * Resolve a backend-stored asset path to a browser-accessible URL.
 */
function resolveBackendAssetUrl(assetPath) {
    if (!assetPath) return '';
    if (/^(https?:)?\/\//i.test(assetPath) || assetPath.startsWith('data:')) {
        return assetPath;
    }

    const normalizedPath = String(assetPath).replace(/\\/g, '/').replace(/^\/+/, '');
    return `${ASSET_API_URL}?path=${encodeURIComponent(normalizedPath)}`;
}

/**
 * Check if user is logged in
 */
async function isLoggedIn() {
    try {
        const response = await fetch(`${API_URL}?action=me`, {
            credentials: 'include'
        });

        const data = await response.json();

        if (!response.ok || !data.success || !data.user) {
            clearStoredUser();
            return false;
        }

        storeUser(data.user);
        return true;
    } catch (error) {
        console.error('Session check error:', error);
        clearStoredUser();
        return false;
    }
}

function storeUser(user) {
    localStorage.setItem('user_id', user.id);
    localStorage.setItem('user_email', user.email || '');
    localStorage.setItem('user_name', user.full_name || user.name || '');
    localStorage.setItem('user_role', user.role || 'customer');
    localStorage.setItem('auth_token', 'session_active');
}

function clearStoredUser() {
    localStorage.removeItem('user_id');
    localStorage.removeItem('user_email');
    localStorage.removeItem('user_name');
    localStorage.removeItem('user_role');
    localStorage.removeItem('auth_token');
}

/**
 * Handle login request
 */
async function login(email, password) {
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
            storeUser(data.user);

            if (typeof showToast === 'function') showToast('Login successful!', 'success'); else showMessage('Login successful!', 'success');
            setTimeout(() => {
                window.location.href = data.user.role === 'admin' ? 'admin-dashboard.html' : 'index.html';
            }, 1500);
        } else {
            if (typeof showToast === 'function') showToast(data.message || 'Login failed', 'error'); else showMessage(data.message || 'Login failed', 'error');
        }
    } catch (error) {
        console.error('Login error:', error);
            if (typeof showToast === 'function') showToast('Network error. Please try again.', 'error'); else showMessage('Network error. Please try again.', 'error');
    }
}

/**
 * Handle registration request
 */
async function register(fullName, email, password, confirmPassword, phoneNumber) {
    try {
        const response = await fetch(`${API_URL}?action=register`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                fullName: fullName,
                email: email,
                password: password,
                confirmPassword: confirmPassword,
                phoneNumber: phoneNumber
            }),
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            if (typeof showToast === 'function') showToast('Registration successful! Redirecting to login...', 'success'); else showMessage('Registration successful! Redirecting to login...', 'success');
            setTimeout(() => {
                toggleForm('login');
                document.getElementById('loginEmail').value = email;
                document.getElementById('loginPassword').value = '';
            }, 1500);
        } else {
            if (typeof showToast === 'function') showToast(data.message || 'Registration failed', 'error'); else showMessage(data.message || 'Registration failed', 'error');
        }
    } catch (error) {
        console.error('Registration error:', error);
        if (typeof showToast === 'function') showToast('Network error. Please try again.', 'error'); else showMessage('Network error. Please try again.', 'error');
    }
}

/**
 * Handle logout request
 */
async function logout() {
    try {
        await fetch(`${API_URL}?action=logout`, {
            method: 'POST',
            credentials: 'include'
        });

        clearStoredUser();

        window.location.href = 'login.html';
    } catch (error) {
        console.error('Logout error:', error);
    }
}

/**
 * Show message to user
 */
function showMessage(message, type = 'info') {
    const messageDiv = document.getElementById('message');
    if (!messageDiv) return;

    messageDiv.textContent = message;
    messageDiv.className = 'message ' + type;
    messageDiv.style.display = 'block';

    // Auto-hide after 5 seconds for non-error messages
    if (type !== 'error') {
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }
}

/**
 * Show a small toast notification (floating) using DOM.
 */
function showToast(message, type = 'info', timeout = 4000) {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span class="toast-message">${message}</span>`;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'close-btn';
    closeBtn.innerHTML = '&times;';
    closeBtn.onclick = () => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 220);
    };

    toast.appendChild(closeBtn);
    container.appendChild(toast);

    // Trigger enter animation
    requestAnimationFrame(() => toast.classList.add('show'));

    // Auto remove
    if (timeout > 0) {
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 220);
        }, timeout);
    }
}

/**
 * Get current user info
 */
function getCurrentUser() {
    return {
        id: localStorage.getItem('user_id'),
        email: localStorage.getItem('user_email'),
        name: localStorage.getItem('user_name'),
        role: localStorage.getItem('user_role')
    };
}
