// Global API URL Helper for Blockchain Voting System
// This file handles proper API URL resolution for both file:// and http:// protocols

(function() {
    'use strict';
    
    // Store the original fetch function
    const originalFetch = window.fetch;
    
    // Helper function to convert absolute paths to relative paths
    window.getApiUrl = function(endpoint) {
        if (window.location.protocol === 'file:') {
            // Running locally as a file, use relative path
            return '../' + endpoint;
        }
        // Check if running in subdirectory
        if (window.location.pathname.includes('/voting_system/')) {
            return '../' + endpoint;
        }
        // Otherwise use absolute path from root
        return '/api/' + endpoint;
    };
    
    // Override fetch to automatically handle API URLs and include credentials
    window.fetch = function(resource, config = {}) {
        let url = resource;

        // If it's a string URL starting with /api/, convert it
        if (typeof resource === 'string' && resource.startsWith('/api/')) {
            // Extract the endpoint path after /api/
            const endpoint = resource.substring(5); // Remove '/api/' prefix
            url = getApiUrl(endpoint);
        }

        // Include cookies for authenticated admin API calls (session propagation)
        if (!config.credentials) {
            config.credentials = 'include';
        }

        // Call original fetch with modified URL
        return originalFetch.call(this, url, config);
    };
    
    // Copy over any properties from original fetch
    Object.setPrototypeOf(window.fetch, originalFetch);
    
    console.log('API URL helper initialized - Protocol: ' + window.location.protocol);
})();
