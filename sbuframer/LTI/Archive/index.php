<?php

	$val = json_encode($_POST);
	
	setcookie("post", $val, time()+60, '/');
	$go = $_GET['go'];
	header("Location: $go");

?>