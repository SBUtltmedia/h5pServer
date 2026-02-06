<?php
session_start();

if (isset($_SESSION['lti_data'])) {
    echo "<pre>";
    print_r($_SESSION['lti_data']);
    echo "</pre>";
} else {
    echo "No LTI data found.";
}
?>