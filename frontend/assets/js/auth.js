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
 * Protect a page - redirect to login if not logged in
 */
document.addEventListener('DOMContentLoaded', async () => {
    // Check if current page requires authentication
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    
    // List of protected pages
    const protectedPages = ['dashboard.html', 'index.html'];
    
    if (protectedPages.includes(currentPage)) {
        const loggedIn = await isLoggedIn();
        if (!loggedIn) {
            window.location.href = 'login.html';
        }
    }
});
