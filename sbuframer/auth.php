<?php
// auth.php
// Handles user authentication and identity resolution (Shibboleth > LTI > Session).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load .env file for local development (mock Shibboleth)
if (file_exists(__DIR__ . '/.env')) {
    $envVars = parse_ini_file(__DIR__ . '/.env');
    if (isset($envVars['ENVIRONMENT']) && $envVars['ENVIRONMENT'] === 'local') {
        if (isset($envVars['MOCK_SHIB_MAIL'])) {
            $_SERVER['mail'] = $envVars['MOCK_SHIB_MAIL'];
        }
        if (isset($envVars['MOCK_SHIB_CN'])) {
            $_SERVER['cn'] = $envVars['MOCK_SHIB_CN'];
        }
        if (isset($envVars['MOCK_SHIB_UID'])) {
            $_SERVER['uid'] = $envVars['MOCK_SHIB_UID'];
        }
    }
}

// 1. LTI Launch Handling: Capture POST data into session
// This allows the application to remember the LTI user even after navigation/redirects.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists("lis_person_name_given", $_POST)) {
    $_SESSION['lti_data'] = $_POST;
}

/**
 * Retrieves the current user's identity context.
 * Priority:
 * 1. Shibboleth Environment Variables ($_SERVER['cn'], $_SERVER['mail'])
 *    - Used when accessing directly via Shibboleth login (e.g. Faculty uploading games).
 * 2. LTI Session Data
 *    - Used when accessed via LMS (e.g. Students playing games).
 * 3. Default/Anonymous fallback
 *
 * @return array ['id' => string, 'email' => string, 'name' => string, 'source' => string, 'lti_data' => array|null]
 */
function getUserContext() {
    $context = [
        'id' => 'default_user',
        'email' => 'anonymous@example.com',
        'name' => 'Anonymous',
        'source' => 'none',
        'lti_data' => isset($_SESSION['lti_data']) ? $_SESSION['lti_data'] : null
    ];

    // Check Shibboleth (Server Vars) - Highest Priority
    // Priority: Mail > CN
    if (isset($_SERVER['mail']) && !empty($_SERVER['mail'])) {
        $context['id'] = $_SERVER['mail'];
        $context['email'] = $_SERVER['mail'];
        $context['source'] = 'shibboleth';
        
        // Populate name if available
        if (isset($_SERVER['givenName']) && isset($_SERVER['sn'])) {
            $context['name'] = $_SERVER['givenName'] . ' ' . $_SERVER['sn'];
        }
        return $context;
    }
    
    // Fallback to CN if mail is missing
    if (isset($_SERVER['cn']) && !empty($_SERVER['cn'])) {
        $context['id'] = $_SERVER['cn'];
        $context['source'] = 'shibboleth';
        
        if (isset($_SERVER['givenName']) && isset($_SERVER['sn'])) {
            $context['name'] = $_SERVER['givenName'] . ' ' . $_SERVER['sn'];
        }
        return $context;
    }

    // Check LTI Session - Secondary Priority
    if (isset($_SESSION['lti_data']) && !empty($_SESSION['lti_data'])) {
        $lti = $_SESSION['lti_data'];
        // LTI User ID
        if (isset($lti['user_id'])) {
            $context['id'] = $lti['user_id'];
            $context['source'] = 'lti';
        }
        // LTI Email
        if (isset($lti['lis_person_contact_email_primary'])) {
            $context['email'] = $lti['lis_person_contact_email_primary'];
        }
        // LTI Name
        if (isset($lti['lis_person_name_full'])) {
            $context['name'] = $lti['lis_person_name_full'];
        } elseif (isset($lti['lis_person_name_given'])) {
            $context['name'] = $lti['lis_person_name_given'];
        }
        return $context;
    }

    return $context;
}

/**
 * Helper to get just the User ID (safe for filesystem usage)
 */
function getSafeUserId() {
    $user = getUserContext();
    // Sanitize for filesystem
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $user['id']);
}

/**
 * Helper to get just the User Email (safe for filesystem usage if needed)
 */
function getSafeUserEmail() {
    $user = getUserContext();
    // Allow @ and . but sanitize others
    return preg_replace('/[^a-zA-Z0-9_.@-]/', '_', $user['email']);
}

/**
 * Enforces authentication.
 * If no valid LTI session and no Shibboleth identity are found,
 * redirects the user to the Shibboleth login page.
 */
function requireAuthentication() {
    // 1. Allow if LTI session is active
    if (isset($_SESSION['lti_data']) && !empty($_SESSION['lti_data'])) {
        return;
    }

    // 2. Allow if Shibboleth identity is present (covers DDEV if configured via .htaccess)
    if ((isset($_SERVER['cn']) && !empty($_SERVER['cn'])) || (isset($_SERVER['mail']) && !empty($_SERVER['mail']))) {
        return;
    }

    // 3. Redirect to Shibboleth Login
    $server = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
    // Force https for shib target typically
    $target = "https://{$server}{$request_uri}";
    
    header('Location: /shib/?shibtarget=' . rawurlencode($target));
    exit;
}
?>