<?php
// gameState.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Use __DIR__ to locate auth.php in the root, resolving symlinks correctly
require_once __DIR__ . '/auth.php';

/**
 * Get the current user's identifier (email or unique ID).
 * This will be used to name save files.
 * @return string|null User identifier or null if not found.
 */
function getUserIdentifier() {
    $user = getUserContext();
    
    // Priority: Email > ID > Anonymous
    // Note: auth.php now prioritizes mail as 'id' for Shibboleth users too.
    if (!empty($user['email']) && $user['email'] !== 'anonymous@example.com') {
        return $user['email'];
    }
    if (!empty($user['id']) && $user['id'] !== 'default_user') {
        return $user['id'];
    }
    return 'anonymous_user';
}

/**
 * Get the path to the current game's saves directory.
 * This function assumes it's being called from within a symlinked context like:
 * /games/<user_id>/<game_name>/saves/gameState.php
 * @return string The absolute path to the saves directory.
 */
function getSaveDirectory() {
    // getcwd() returns the directory where the script is being executed from (the symlink location)
    // e.g., /path/to/project/games/<user_id>/<game_name>/saves/
    return getcwd() . '/';
}

/**
 * Get the full path for a user's save file.
 * @return string The full path to the user's JSON save file.
 */
function getSaveFilePath() {
    $user_identifier = getUserIdentifier();
    $save_dir = getSaveDirectory();
    // Sanitize user identifier for filename, allowing '@' for emails
    $filename = preg_replace('/[^a-zA-Z0-9_.-@]/', '_', $user_identifier) . '.json';
    return $save_dir . $filename;
}

/**
 * Save game state data for the current user.
 * @param array $data The game state data to save.
 * @return bool True on success, false on failure.
 */
function saveGameState($data) {
    $filepath = getSaveFilePath();
    $json_data = json_encode($data, JSON_PRETTY_PRINT);
    if ($json_data === false) {
        error_log("Failed to encode game state data to JSON.");
        return false;
    }
    if (file_put_contents($filepath, $json_data) === false) {
        error_log("Failed to write game state to file: " . $filepath);
        return false;
    }
    return true;
}

/**
 * Load game state data for the current user.
 * @return array|null The loaded game state data, or null if no save file exists or on error.
 */
function loadGameState() {
    $filepath = getSaveFilePath();
    if (!file_exists($filepath)) {
        return null; // No save file found
    }
    $json_data = file_get_contents($filepath);
    if ($json_data === false) {
        error_log("Failed to read game state from file: " . $filepath);
        return null;
    }
    $data = json_decode($json_data, true);
    if ($data === null) {
        error_log("Failed to decode game state JSON from file: " . $filepath);
        return null;
    }
    return $data;
}

// Set headers for CORS if needed
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle API requests - support both legacy action-based and H5P format
if (isset($_GET['action'])) {
    // Legacy action-based API for backward compatibility
    $action = $_GET['action'];
    switch ($action) {
        case 'save':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if ($data !== null) {
                if (saveGameState($data)) {
                    echo json_encode(['status' => 'success', 'message' => 'Game state saved.']);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to save game state.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data for saving.']);
            }
            break;
        case 'load':
            $data = loadGameState();
            if ($data !== null) {
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'No game state found.']);
            }
            break;
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
            break;
    }
    exit;
}

// H5P-compatible API (no action parameter)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Load state for H5P
    $filepath = getSaveFilePath();

    if (file_exists($filepath)) {
        $state_json = file_get_contents($filepath);
        // H5P expects an array with this structure
        echo json_encode([[
            'dataType' => 'state',
            'previousState' => $state_json,
            'subContentId' => '0'
        ]]);
    } else {
        // No saved state - return empty array
        echo json_encode([]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save state for H5P
    $raw_data = file_get_contents('php://input');
    $filepath = getSaveFilePath();

    // H5P sends state as a JSON string, we save it directly
    if (file_put_contents($filepath, $raw_data) !== false) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save state']);
    }
    exit;
}

?>