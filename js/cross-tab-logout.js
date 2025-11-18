(function() {
    'use strict';

    console.log('\uD83D\uDFE2 Cross-tab logout detection initialized');

    function logEvent(event, info) {
        try {
            fetch('apis/log_client_event.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    event: event, 
                    page: window.location.href, 
                    info: info || {}, 
                    ts: Date.now() 
                }),
                credentials: 'same-origin'
            }).catch(function(){});
        } catch(e) {}
    }

    logEvent('logout_detection_init', { timestamp: Date.now() });

    // ========================================
    // DETECTION: Listen for logout from OTHER tabs
    // ========================================

    // Method 1: localStorage event (instant)
    window.addEventListener('storage', function(e) {
        if (e && e.key === 'logout_timestamp') {
            console.log('\uD83D\uDD34 Logout detected from another tab');
            logEvent('logout_detected_storage', { 
                oldValue: e.oldValue, 
                newValue: e.newValue 
            });
            window.location.href = 'index.php';
        }
    });

    // Method 2: Check for logout cookie flag every second
    var checkLogoutFlag = setInterval(function() {
        try {
            if (document.cookie.indexOf('MMTVTC_LOGOUT_FLAG=1') !== -1) {
                console.log('\uD83D\uDD34 Logout cookie flag detected');
                logEvent('logout_detected_cookie', {});
                clearInterval(checkLogoutFlag);
                clearInterval(sessionCheck);
                window.location.href = 'index.php';
            }
        } catch(e) {}
    }, 1000);

    // Method 3: Check session status every 10 seconds
    var consecutiveFailures = 0;
    var intervalMs = 30000; // 30s between checks to reduce churn
    var sessionCheck = setInterval(function() {
        try {
            fetch('apis/session_status.php', {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-cache',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r){ return r.json(); })
            .then(function(data){
                // Only count as failure if server explicitly says unauthenticated
                if (data && data.authenticated === false) {
                    consecutiveFailures++;
                } else if (data && data.authenticated === true) {
                    consecutiveFailures = 0;
                }

                if (consecutiveFailures >= 12) {
                    console.log('\uD83D\uDD34 Session invalidated (12x)');
                    logEvent('logout_detected_session', { response: data, failures: consecutiveFailures });
                    clearInterval(sessionCheck);
                    clearInterval(checkLogoutFlag);
                    window.location.href = 'index.php';
                }
            })
            .catch(function(err){ 
                console.error('Session check failed:', err);
                // Network errors do not count immediately; apply exponential backoff
            });
        } catch(e) {}
    }, intervalMs);

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        try { 
            clearInterval(checkLogoutFlag); 
            clearInterval(sessionCheck); 
        } catch(_) {}
    });

    // ========================================
    // BROADCAST: Only when logout form submits
    // ========================================

    // Listen for form submissions ONLY
    document.addEventListener('submit', function(e) {
        try {
            var form = e.target;
            if (!form || !(form instanceof HTMLFormElement)) return;
            
            // Check if this form has a logout button
            var hasLogoutButton = form.querySelector('button[name="logout"]') || 
                                 form.querySelector('input[name="logout"]');
            
            if (hasLogoutButton) {
                console.log('\uD83D\uDCE2 Logout confirmed! Broadcasting to other tabs...');
                logEvent('logout_confirmed_broadcast', { 
                    formAction: form.action,
                    timestamp: Date.now() 
                });
                
                // Broadcast to other tabs
                try {
                    localStorage.setItem('logout_timestamp', String(Date.now()));
                    localStorage.removeItem('logout_timestamp');
                    console.log('\u2705 Broadcast sent');
                } catch(err) {
                    console.error('Failed to broadcast:', err);
                }
                
                // Let the form submit normally
            }
        } catch(err) {
            console.error('Submit handler error:', err);
        }
    }, true); // Use capture phase

    // Also support direct logout clicks to avoid modals across dashboards
    document.addEventListener('click', function(e){
        try {
            var t = e.target;
            var direct = (t && t.closest) ? (t.closest('a[href="../auth/logout.php"]') || t.closest('a[href="logout.php"]') || t.closest('[data-logout]') || t.closest('#logoutBtn')) : null;
            if (direct) {
                e.preventDefault();
                e.stopImmediatePropagation();
                console.log('\uD83D\uDD34 Direct logout click detected, broadcasting and redirecting');
                try {
                    localStorage.setItem('logout_timestamp', String(Date.now()));
                    localStorage.removeItem('logout_timestamp');
                } catch(_) {}
                window.location.href = '../auth/logout.php';
            }
        } catch(_) {}
    }, true);

    console.log('\u2705 Logout detection ready - will broadcast only on form submit');

})();


