/**
 * Session Security JavaScript
 * Handles cross-tab logout, back button prevention, and session management
 */

(function() {
    'use strict';
    
    // Session security configuration
    const SESSION_CONFIG = {
        logoutEventKey: 'logout-event',
        sessionTimeoutKey: 'session-timeout',
        loginUrl: '../login/',
        timeoutMinutes: 30, // Session timeout in minutes
        sessionCheckDelay: 3000 // Delay before first session check (3 seconds)
    };
    
    /**
     * Initialize session security features
     */
    function initSessionSecurity() {
        // Don't run session security on login page
        if (window.location.pathname.includes('/login/')) {
            return;
        }
        
        // Check for logout events
        checkLogoutEvent();
        
        // Set up event listeners
        setupEventListeners();
        
        // Initialize session timeout
        initSessionTimeout();
        
        // Prevent back button access
        preventBackButtonAccess();
        
        // Prevent form resubmission
        preventFormResubmission();
        
        // Delay session validity check to avoid interference with login process
        setTimeout(() => {
            checkSessionValidity();
        }, SESSION_CONFIG.sessionCheckDelay);
    }
    
    /**
     * Check if there's a logout event and redirect if necessary
     */
    function checkLogoutEvent() {
        const logoutEvent = localStorage.getItem(SESSION_CONFIG.logoutEventKey);
        if (logoutEvent) {
            // Only redirect if we're not on the login page
            if (!window.location.pathname.includes('/login/')) {
                // Clear the event and redirect
                localStorage.removeItem(SESSION_CONFIG.logoutEventKey);
                redirectToLogin('Logout detected');
            } else {
                // If we're on login page, just clear the event
                localStorage.removeItem(SESSION_CONFIG.logoutEventKey);
            }
        }
    }
    
    /**
     * Set up all event listeners
     */
    function setupEventListeners() {
        // Listen for storage events (cross-tab logout)
        window.addEventListener('storage', function(event) {
            if (event.key === SESSION_CONFIG.logoutEventKey) {
                redirectToLogin('Logout event detected');
            }
        });
        
        // Listen for page visibility changes
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                // Add a longer delay to prevent interference with login process
                setTimeout(() => {
                    checkSessionValidity();
                }, 2000);
            }
        });
        
        // Listen for beforeunload to clear sensitive data
        window.addEventListener('beforeunload', function() {
            // Clear any sensitive data from memory
            clearSensitiveData();
        });
    }
    
    /**
     * Initialize session timeout
     */
    function initSessionTimeout() {
        // Set session timeout
        const timeoutMs = SESSION_CONFIG.timeoutMinutes * 60 * 1000;
        const timeoutTime = Date.now() + timeoutMs;
        
        // Store timeout time
        sessionStorage.setItem(SESSION_CONFIG.sessionTimeoutKey, timeoutTime.toString());
        
        // Check timeout periodically with a delay to avoid interference
        setTimeout(() => {
            setInterval(checkSessionTimeout, 60000); // Check every minute
        }, 10000); // Start checking after 10 seconds instead of 5
    }
    
    /**
     * Check if session has timed out
     */
    function checkSessionTimeout() {
        const timeoutTime = sessionStorage.getItem(SESSION_CONFIG.sessionTimeoutKey);
        if (timeoutTime && Date.now() > parseInt(timeoutTime)) {
            redirectToLogin('Session expired');
        }
    }
    
    /**
     * Prevent back button access to cached pages
     */
    function preventBackButtonAccess() {
        // Listen for pageshow event (triggered when page is loaded from cache)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Page was loaded from cache (back button)
                redirectToLogin('Back button access prevented');
            }
        });
        
        // Additional check for popstate event
        window.addEventListener('popstate', function(event) {
            // Prevent navigation back to protected pages
            if (window.location.pathname.includes('/login/')) {
                return; // Allow navigation to login page
            }
            
            // Redirect to login if trying to go back to protected pages
            redirectToLogin('Back navigation prevented');
        });
    }
    
    /**
     * Prevent form resubmission on refresh
     */
    function preventFormResubmission() {
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    }
    
    /**
     * Check session validity
     */
    function checkSessionValidity() {
        // Skip session check if we're on login page
        if (window.location.pathname.includes('/login/')) {
            return;
        }
        
        // Make an AJAX request to check if session is still valid
        fetch('../login/session_check.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'check_session'
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (!data.valid) {
                console.warn('Session validation failed, but not redirecting immediately');
                // Don't redirect immediately, give the session a chance to establish
                setTimeout(() => {
                    // Check again after a delay
                    fetch('../login/session_check.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'check_session'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.valid) {
                            redirectToLogin('Session validation failed');
                        }
                    })
                    .catch(error => {
                        console.warn('Second session check failed:', error);
                        // Still don't redirect on network errors
                    });
                }, 2000);
            }
        })
        .catch(error => {
            console.warn('Session check failed:', error);
            // Don't redirect on network errors to avoid false positives
        });
    }
    
    /**
     * Redirect to login page
     */
    function redirectToLogin(reason) {
        console.log('Redirecting to login:', reason);
        
        // Clear any sensitive data
        clearSensitiveData();
        
        // Redirect to login page
        window.location.href = SESSION_CONFIG.loginUrl;
    }
    
    /**
     * Clear sensitive data from storage
     */
    function clearSensitiveData() {
        // Clear session storage
        sessionStorage.clear();
        
        // Clear specific localStorage items (keep logout event for cross-tab sync)
        const logoutEvent = localStorage.getItem(SESSION_CONFIG.logoutEventKey);
        localStorage.clear();
        if (logoutEvent) {
            localStorage.setItem(SESSION_CONFIG.logoutEventKey, logoutEvent);
        }
    }
    
    /**
     * Trigger logout event for cross-tab synchronization
     */
    function triggerLogout() {
        localStorage.setItem(SESSION_CONFIG.logoutEventKey, Date.now().toString());
    }
    
    /**
     * Extend session timeout
     */
    function extendSession() {
        const timeoutMs = SESSION_CONFIG.timeoutMinutes * 60 * 1000;
        const timeoutTime = Date.now() + timeoutMs;
        sessionStorage.setItem(SESSION_CONFIG.sessionTimeoutKey, timeoutTime.toString());
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSessionSecurity);
    } else {
        initSessionSecurity();
    }
    
    // Expose functions globally for use in other scripts
    window.SessionSecurity = {
        triggerLogout: triggerLogout,
        extendSession: extendSession,
        redirectToLogin: redirectToLogin
    };
    
})(); 