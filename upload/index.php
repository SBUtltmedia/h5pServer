<?php

// print_r($_SERVER['REQUEST_URI']);
// exit;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
requireAuthentication();

$game_path = null;
$error_message = '';

// Check for the H5P content path in the query string.
if (isset($_GET['game'])) {
    // IMPORTANT: Security check to prevent directory traversal attacks.
    // We ensure the path is relative, starts with 'h5p-content/', and doesn't go "up" the directory tree.
    $unsafe_path = $_GET['game'];
    if (strpos($unsafe_path, '..') === false && strpos($unsafe_path, 'h5p-content/') === 0) {
        $game_path = $unsafe_path;
    } else {
        $error_message = "Invalid content path specified.";
    }
} else {
    $error_message = "No content specified.";
}

// Retrieve LTI data from the user context for the grading script.
$userContext = getUserContext();
$lti_data = $userContext['lti_data'];
$JSON_LTI_DATA = $lti_data ? json_encode($lti_data) : 'null';
// print_r($game_path);
// exit;

?>
<!DOCTYPE html>
<html>
<head>
    <title>H5P Content Viewer</title>
    <script src="js/grading.js"></script>
    <script>
        var ses = <?php echo $JSON_LTI_DATA; ?>;
        var contentPath = "<?php echo $game_path ? htmlspecialchars($game_path) : ''; ?>";
    </script>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: sans-serif;
        }
        #h5p-container {
            width: 100%;
            min-height: 95vh;
        }
    </style>
</head>
<body>

    <?php if ($game_path && !$error_message): ?>
        <div id="h5p-container"></div>

        <script src="h5p-player/main.bundle.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const el = document.getElementById('h5p-container');
                const userInfo = {
                    name: "<?php echo addslashes($userContext['name']); ?>",
                    email: "<?php echo addslashes($userContext['email']); ?>"
                };

                const options = {
                    id: contentPath.replace(/\//g, '-'),
                    h5pJsonPath: contentPath,
                    frameJs: 'h5p-player/frame.bundle.js',
                    frameCss: 'h5p-player/styles/h5p.css',
                    frame: true,
                    fullScreen: true,

                    // Enable state management
                    saveFreq: 30, // Auto-save every 30 seconds
                    ajax: {
                        contentUserDataUrl: contentPath + '/saves/gameState.php'
                    },
                    user: {
                        name: userInfo.name,
                        mail: userInfo.email
                    }
                };

                new H5PStandalone.H5P(el, options)
                    .then(() => {
                        console.log('H5P content loaded successfully');

                        // Listen for H5P xAPI events for grading
                        if (typeof H5P !== 'undefined' && H5P.externalDispatcher) {
                            H5P.externalDispatcher.on('xAPI', function(event) {
                                console.log('xAPI event:', event);

                                // Handle completed event for grading
                                if (event.getVerb() === 'completed' && ses) {
                                    const score = event.getScore();
                                    const maxScore = event.getMaxScore();

                                    if (score !== null && maxScore !== null) {
                                        const normalizedScore = (score / maxScore); // 0-1 scale
                                        console.log('Content completed with score:', normalizedScore);

                                        ses.grade = normalizedScore;
                                        postLTI(ses, "h5p-score");
                                    }
                                }
                            });
                        }
                    })
                    .catch(err => {
                        console.error('H5P error:', err);
                        el.innerHTML = '<div style="padding: 20px; color: red;">Error loading H5P content: ' + err.message + '</div>';
                    });
            });
        </script>
    <?php else: ?>
        <div style="padding: 20px; font-family: sans-serif;">
            <h2>Upload H5P Content</h2>
            <p>Please upload your H5P content package (.h5p file).</p>
            <form action="upload_handler.php" method="post" enctype="multipart/form-data">
                <input type="file" name="game_file" id="game_file" accept=".h5p" required>
                <br><br>
                <input type="submit" value="Upload H5P Content" name="submit">
            </form>
            <?php if ($error_message): ?>
                <h2 style="color: red; margin-top: 20px;">Error</h2>
                <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</body>
</html>
