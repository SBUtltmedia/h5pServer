<?php

session_start();

// If LTI POST or running locally, capture identity fields
if (array_key_exists('lis_person_name_given', $_POST))  {
  $_SESSION['mail'] = $_POST['lis_person_contact_email_primary'] ?? '';
  $_SESSION['givenName'] = $_POST['lis_person_name_given'] ?? '';
  $_SESSION['nickname'] = $_POST['lis_person_name_given'] ?? '';
  $_SESSION['sn'] = $_POST['lis_person_name_family'] ?? '';

  $JSON_POST = json_encode($_POST);

  // Provide a local sample payload if running in local env



} elseif (!array_key_exists('mail', $_SESSION) && array_key_exists('mail', $_SERVER) ) {
  // session already has mail value; nothing to do

  $_SESSION['mail'] = $_SERVER['mail'];
  $_SESSION['givenName'] = $_SERVER['givenName'] ?? '';
  $_SESSION['nickname'] = $_SERVER['nickname'] ?? '';
  $_SESSION['sn'] = $_SERVER['sn'] ?? '';
} else {
  if (!isset($_SERVER['cn'])) {
    $server = $_SERVER['SERVER_NAME'] ?? '';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $target = "https://{$server}{$request_uri}";
    header('Location: /shib/?shibtarget=' . rawurlencode($target));
    exit;
  }
}


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$message = '';
$message_type = 'error';

$server = $_ENV['VIRTUAL_HOST'] ?? 'apps.tlt.stonybrook.edu';


// Check for and display a flash message from the session
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']); // Clear it after displaying
}

// Step 1: Check for Shibboleth authentication
if (!isset($_SERVER['cn'])) {
    header("HTTP/1.1 403 Forbidden");
    echo "Access Denied. You must be authenticated via Shibboleth to upload a game.";
    exit;
}

$user_cn = $_SERVER['cn'];

// Step 2: Handle the file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['game_file'])) {
    $upload_message = '';
    $upload_type = 'error';

    // Sanitize the original filename for security
    $original_filename = basename($_FILES['game_file']['name']);
    if (!preg_match('/^[a-zA-Z0-9_\-]+\.html$/', $original_filename)) {
        $upload_message = "Error: Invalid filename. Please use only letters, numbers, underscores, and hyphens. The file must end with .html.";
    } else {
        $upload_dir = '../games/' . $user_cn . '/';
        $upload_file = $upload_dir . $original_filename;

        $file_type = mime_content_type($_FILES['game_file']['tmp_name']);
        if ($file_type !== 'text/html') {
            $upload_message = "Error: Only .html files are allowed.";
        } else {
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            if (move_uploaded_file($_FILES['game_file']['tmp_name'], $upload_file)) {
                $game_path_for_player = '?game=games/' . $user_cn . '/' . $original_filename;

                $extraPath='';
                $location = explode("/",$_SERVER['HTTP_REFERER']);
                if (count($location)>5){ #if it does have SBUFramer in the webiste path then add it to the new link
                    $extraPath = "/{$location[3]}";
                }
                $full_game_url = "https://{$server}{$extraPath}/{$game_path_for_player}";

                // print_r($full_game_url);
                // exit;

                $escaped_path = htmlspecialchars($game_path_for_player, ENT_QUOTES);
                $upload_message = "<strong>Success!</strong> Your game's path for the LTI tool is: <code>" . $escaped_path . "</code> " .
                                  "<button type='button' onclick='copyTextToClipboard(\"" . htmlspecialchars($full_game_url) . "\"); this.innerText=\"Copied!\";' style='margin-left: 8px; padding: 4px 8px; cursor: pointer;'>&#x2398;</button>" .
                                  "<br>You can test the game at: <a href=\"" . htmlspecialchars($full_game_url) . "\" target=\"_blank\">" . htmlspecialchars($full_game_url) . "</a>";
                $upload_type = 'success';
            } else {
                $upload_message = "Sorry, there was an error uploading your file.";
            }
        }
    }

    // Store message in session and redirect
    $_SESSION['flash_message'] = ['text' => $upload_message, 'type' => $upload_type];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Game Upload</title>
    <script>
    function copyTextToClipboard(text) {
        // 1. Try modern Async Clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text)
            .then(() => {})
            .catch(err => {});
            return;
        }

        // 2. Fallback using document.execCommand('copy')
        const textArea = document.createElement("textarea");
        textArea.value = text;
        
        // Make the textarea invisible and outside the viewport
        textArea.style.position = "fixed";
        textArea.style.opacity = 0;
        
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            const successful = document.execCommand('copy');
            console.log('Text copied (Fallback): ' + (successful ? 'successful' : 'unsuccessful'));
        } catch (err) {
            console.error('Fallback: Oops, unable to copy', err);
        }

        document.body.removeChild(textArea);
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4285F4;
            --success-color: #34A853;
            --error-color: #EA4335;
            --background-color: #f5f5f5;
            --text-color: #333;
            --light-gray: #e0e0e0;
            --white: #ffffff;
        }
        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            margin: 0;
            padding: 2em;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background-color: var(--white);
            padding: 2.5em;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        h1 {
            color: var(--primary-color);
            margin-bottom: 0.5em;
        }
        p {
            line-height: 1.6;
            margin-bottom: 1.5em;
        }
        .message {
            margin: 1.5em 0;
            padding: 1em;
            border-radius: 4px;
            text-align: left;
            border-left: 5px solid;
        }
        .message.success {
            background-color: #e6f4ea;
            border-color: var(--success-color);
        }
        .message.error {
            background-color: #fce8e6;
            border-color: var(--error-color);
        }
        code {
            background-color: #eee;
            padding: 0.2em 0.4em;
            border-radius: 3px;
            font-family: monospace;
        }
        #drop-area {
            border: 2px dashed var(--light-gray);
            border-radius: 8px;
            padding: 2em;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.3s, background-color 0.3s;
        }
        #drop-area.highlight {
            border-color: var(--primary-color);
            background-color: #e8f0fe;
        }
        #drop-area p {
            margin: 0;
            font-size: 1.1em;
            color: #666;
        }
        #file-elem {
            display: none;
        }
        #file-name {
            margin-top: 1em;
            font-weight: 500;
            color: var(--primary-color);
        }
        .submit-btn {
            background-color: #000000 !important;
            color: var(--white);
            border: none;
            padding: 0.8em 1.5em;
            font-size: 1em;
            font-weight: 500;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s, box-shadow 0.3s;
            margin-top: 1.5em;
        }
        .submit-btn:hover {
            background-color: #3367D6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .submit-btn:disabled {
            background-color: #a0a0a0;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Faculty Game Upload</h1>
    <p>Upload your single file HTML game</p>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form id="upload-form" action="" method="post" enctype="multipart/form-data">
        <div id="drop-area">
            <input type="file" name="game_file" id="file-elem" accept=".html" onchange="handleFiles(this.files)">
            <label for="file-elem">
                <p>Drag & Drop your .html file here</p>
                <p>or <strong>click to select a file</strong></p>
            </label>
            <div id="file-name"></div>
        </div>
        <button type="submit" class="submit-btn" id="submit-button" disabled>Upload Game</button>
    </form>
</div>

<script>
    let dropArea = document.getElementById('drop-area');
    let fileElem = document.getElementById('file-elem');
    let fileNameDisplay = document.getElementById('file-name');
    let submitButton = document.getElementById('submit-button');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, () => dropArea.classList.add('highlight'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, () => dropArea.classList.remove('highlight'), false);
    });

    dropArea.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        let dt = e.dataTransfer;
        let files = dt.files;
        handleFiles(files);
    }

    function handleFiles(files) {
        if (files.length > 0) {
            const file = files[0];
            if (file.type === 'text/html') {
                fileElem.files = files; // Important for form submission
                fileNameDisplay.textContent = `Selected file: ${file.name}`;
                submitButton.disabled = false;
            } else {
                fileNameDisplay.textContent = 'Error: Please select an .html file.';
                submitButton.disabled = true;
            }
        }
    }
</script>

</body>
</html>