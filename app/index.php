<?php
// This file must be placed in the main folder (e.g. /app/index.php) and serves only as a redirect file to login.php or dashboard.php depending on
// whether the user is logged in or not.

session_start();

//Check if the user is logged in and has an active account
if (
    isset($_SESSION['user_id']) &&
    (int) ($_SESSION['user_status'] ?? 0) === 1
) {
    header('Location: /public/dashboard.php');
    exit;
}

//Otherwise send the user to the login page
header('Location: /login.php');
exit;
