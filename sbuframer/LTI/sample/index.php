<?php

// Necessary b/c of server PHP config
date_default_timezone_set("America/New_York");

// Require the LTI common code
require $_SERVER["DOCUMENT_ROOT"] . "/LTI/LTI.php";

// Given that we post the data to this page from blackboard, have LTI parse and send it
// var_dump(json_encode($_POST));

// Create LTI obj
$lti = new LTI();

// Get the necessary data fields from the raw post obj... Basically parser.
$dataFromPost = $lti->getDataFromPost($_POST);

// Check for any error when trying to get data from raw post
if ($dataFromPost->isError) {
    echo "there was an error when trying to get data from post";
}

// Try to send the grade
$gradeResult = $lti->sendGrade($dataFromPost->url, $dataFromPost->id, .85);

// Determine if the send grade was successful
$isSuccessful = $lti->isSuccessful($gradeResult);
if (!$isSuccessful) {
    echo "sendGrade() failed! <br>\n";
} else {
    echo "sendGrade() was successful! <br>\n";
}

// NOTE: Typically remove this in production
// Print out grade result for debugging purposes
echo $gradeResult;
