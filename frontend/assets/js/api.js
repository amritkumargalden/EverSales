/**
 * API Service
 * Handles all requests to the PHP backend
 */

const BACKEND_ORIGIN = 'http://localhost:8000';
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
    const token = localStorage.getItem('auth_token');
    return !!token;
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
            // Store user info in localStorage
            localStorage.setItem('user_id', data.user.id);
            localStorage.setItem('user_email', data.user.email);
            localStorage.setItem('user_name', data.user.full_name);
            localStorage.setItem('user_role', data.user.role);
            localStorage.setItem('auth_token', 'logged_in');

            showMessage('Login successful!', 'success');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1500);
        } else {
            showMessage(data.message || 'Login failed', 'error');
        }
    } catch (error) {
        console.error('Login error:', error);
        showMessage('Network error. Please try again.', 'error');
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
            showMessage('Registration successful! Redirecting to login...', 'success');
            setTimeout(() => {
                toggleForm('login');
                document.getElementById('loginEmail').value = email;
                document.getElementById('loginPassword').value = '';
            }, 1500);
        } else {
            showMessage(data.message || 'Registration failed', 'error');
        }
    } catch (error) {
        console.error('Registration error:', error);
        showMessage('Network error. Please try again.', 'error');
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

        // Clear localStorage
        localStorage.removeItem('user_id');
        localStorage.removeItem('user_email');
        localStorage.removeItem('user_name');
        localStorage.removeItem('user_role');
        localStorage.removeItem('auth_token');

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
