/**
 * Authentication functions - login, register, logout, session management
 */

/**
 * Register a new user
 */
async function register(fullName, email, password, confirmPassword, phoneNumber) {
    try {
        const response = await apiPostForm('/auth/register', {
            fullName,
            email,
            password,
            confirmPassword,
            phoneNumber
        });
        
        showMessage('Registration successful! Please log in.', 'success');
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 1500);
    } catch (error) {
        showMessage('Registration failed: ' + error.message, 'error');
    }
}

/**
 * Login with email and password
 */
async function login(email, password) {
    try {
        const response = await apiPostForm('/auth/login', {
            email,
            password
        });
        
        if (response.success) {
            showMessage('Login successful!', 'success');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1000);
        }
    } catch (error) {
        showMessage('Login failed: ' + error.message, 'error');
    }
}

/**
 * Logout the current user
 */
async function logout() {
    try {
        await apiPost('/auth/logout', {});
        showMessage('Logged out successfully', 'success');
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 1000);
    } catch (error) {
        console.error('Logout failed:', error);
        // Force logout anyway
        window.location.href = 'login.html';
    }
}

/**
 * Get current user information
 */
async function getCurrentUser() {
    try {
        const response = await apiGet('/auth/me');
        if (response.success && response.data) {
            return response.data;
        }
        return null;
    } catch (error) {
        console.error('Failed to get current user:', error);
        return null;
    }
}

/**
 * Check if user is logged in
 */
async function isLoggedIn() {
    const user = await getCurrentUser();
    return user !== null;
}

/**
 * Check authentication and redirect if not logged in
 */
async function requireAuth(redirectUrl = 'login.html') {
    const loggedIn = await isLoggedIn();
    if (!loggedIn) {
        window.location.href = redirectUrl;
    }
}

/**
 * Display a message to the user
 */
function showMessage(message, type = 'info') {
    const messageDiv = document.getElementById('message') || document.querySelector('.message');
    if (messageDiv) {
        messageDiv.textContent = message;
        messageDiv.className = `message ${type}`;
        messageDiv.style.display = 'block';
        
        if (type !== 'error') {
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 3000);
        }
    }
}
