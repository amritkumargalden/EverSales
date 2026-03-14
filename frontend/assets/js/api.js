/**
 * API utility functions - base fetch wrapper with auth header support
 */

// Detect the app context path (e.g. /eversales) so API calls work after WAR deployment.
const pathParts = window.location.pathname.split('/').filter(Boolean);
const STATIC_ROOT_SEGMENTS = new Set(['assets', 'frontend', 'static']);
const firstSegment = pathParts.length > 0 ? pathParts[0] : '';
const CONTEXT_PATH = firstSegment && !firstSegment.includes('.') && !firstSegment.includes(':') && !STATIC_ROOT_SEGMENTS.has(firstSegment.toLowerCase())
    ? `/${firstSegment}`
    : '';
const API_BASE_URL = `${CONTEXT_PATH}/api`;

async function parseApiResponse(response) {
    const rawText = await response.text();
    
    if (!rawText || rawText.trim().length === 0) {
        return {};
    }

    try {
        return JSON.parse(rawText);
    } catch (error) {
        if (!response.ok) {
            return { message: `API error: ${response.status}` };
        }

        throw new Error('Received invalid JSON response from server');
    }
}

/**
 * Make an API call with automatic error handling
 */
async function apiCall(endpoint, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    const finalOptions = { ...defaultOptions, ...options };
    
    try {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, finalOptions);
        const data = await parseApiResponse(response);
        
        if (!response.ok) {
            throw new Error(data.message || `API error: ${response.status}`);
        }
        
        return data;
    } catch (error) {
        console.error('API call failed:', error);
        throw error;
    }
}

/**
 * GET request
 */
function apiGet(endpoint) {
    return apiCall(endpoint, { method: 'GET' });
}

/**
 * POST request
 */
function apiPost(endpoint, data) {
    return apiCall(endpoint, {
        method: 'POST',
        body: JSON.stringify(data)
    });
}

/**
 * PUT request
 */
function apiPut(endpoint, data) {
    return apiCall(endpoint, {
        method: 'PUT',
        body: JSON.stringify(data)
    });
}

/**
 * DELETE request
 */
function apiDelete(endpoint) {
    return apiCall(endpoint, { method: 'DELETE' });
}

/**
 * Form data POST (for URL-encoded form submission)
 */
async function apiPostForm(endpoint, formData) {
    try {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, {
            method: 'POST',
            body: new URLSearchParams(formData)
        });
        
        const data = await parseApiResponse(response);
        
        if (!response.ok) {
            throw new Error(data.message || `API error: ${response.status}`);
        }
        
        return data;
    } catch (error) {
        console.error('Form API call failed:', error);
        throw error;
    }
}
