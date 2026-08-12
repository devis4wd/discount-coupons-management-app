<?php

// Start session
session_start();

// Unset session variables
$_SESSION = [];

// Delete session
session_destroy();

// Redirect to index.php > if user is no logged in, it'll redirect to login.php page
header('location: public/index.php');
exit();

?>
