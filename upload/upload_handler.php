<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['game_file'])) {

    // 1. Get user identity via auth module
    $user_id = getSafeUserId();

    if (empty($user_id) || $user_id === 'default_user') {
        // Optional: Enforce stricter check? 
        // For now, we allow default_user if that's the intention, but usually uploads require auth.
        if ($user_id === 'default_user' && !isset($_SESSION['lti_data'])) {
             // Decide policy: Allow anonymous uploads? 
             // Previous code allowed 'default_user'. I'll keep it but usually we want a real user.
        }
    }
    
    if (empty($user_id)) {
        die("Error: Could not determine a valid user identity.");
    }

    // 2. Process file and paths
    $uploaded_file = $_FILES['game_file'];
    
    if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
        die("File upload error: " . $uploaded_file['error']);
    }

    $original_filename = basename($uploaded_file['name']);
    $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);

    if (strtolower($file_extension) !== 'h5p') {
        die("Error: Only .h5p files are allowed.");
    }

    $game_prefix = pathinfo($original_filename, PATHINFO_FILENAME);
    $game_prefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $game_prefix); // Sanitize prefix

    // 2.5 Ensure base h5p-content directory exists (might be a symlink in prod)
    $base_content_dir = "h5p-content";
    if (!is_dir($base_content_dir)) {
        if (!mkdir($base_content_dir, 0755, true)) {
            die("Error: Could not create base content directory: " . $base_content_dir);
        }
    }

    $target_dir = $base_content_dir . "/" . $user_id . "/" . $game_prefix . "/";

    // 3. Create subdirectory for the game
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            die("Failed to create directory: " . $target_dir);
        }
    }

    // 4. Extract the H5P file (it's a ZIP archive)
    $temp_h5p_file = $uploaded_file['tmp_name'];

    $zip = new ZipArchive;
    if ($zip->open($temp_h5p_file) === TRUE) {
        $zip->extractTo($target_dir);
        $zip->close();

        // Validate H5P structure
        if (!file_exists($target_dir . 'h5p.json')) {
            die("Error: Invalid H5P package - missing h5p.json");
        }
        if (!file_exists($target_dir . 'content/content.json')) {
            die("Error: Invalid H5P package - missing content/content.json");
        }

        // Successfully extracted
        
        $saves_dir = $target_dir . 'saves/';
        if (!is_dir($saves_dir)) {
            if (!mkdir($saves_dir, 0755, true)) {
                die("Failed to create directory: " . $saves_dir);
            }
        }

        // 5. Provide access to the master gameState.php
        // Instead of a symlink (which often fails on Windows), we create a stub PHP file
        // that includes the master gameState.php using its absolute path.
        $master_game_state = getcwd() . '/gameState.php';
        $stub_file = $saves_dir . 'gameState.php';
        if (!file_exists($stub_file)) {
            $php_stub = "<?php require_once '" . addslashes($master_game_state) . "'; ?>";
            file_put_contents($stub_file, $php_stub);
        }

        // 6. Redirect to the new H5P content
        $content_url_path = htmlspecialchars("h5p-content/" . $user_id . "/" . $game_prefix);
        header("Location: index.php?game=" . $content_url_path);
        exit();

    } else {
        die("Failed to extract H5P package.");
    }

} else {
    header("Location: index.php");
    exit();
}
