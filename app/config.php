<?php

// Database connection values are provided to the PHP container by compose.yaml
// through the service's environment variables.
//
// compose.yaml resolves MYSQL_DATABASE, MYSQL_USER and MYSQL_PASSWORD from the
// project's .env file when available, while also defining local demo fallback
// values. MYSQL_HOST is set to the Compose service name "db".
//
// getenv() reads those values from the PHP container environment at runtime.
// The fallback values below mirror the defaults used in compose.yaml so this
// configuration remains usable even if the variables are not defined.
define('DB_SERVER', getenv('MYSQL_HOST') ?: 'db');
define('DB_USERNAME', getenv('MYSQL_USER') ?: 'app_user');
define('DB_PASSWORD', getenv('MYSQL_PASSWORD') ?: 'secret');
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'app_db');

// Company email domain used by backend user-form validation.
// This is application configuration rather than a database/Docker credential,
// so it remains defined directly here.
define('COMPANY_DOMAIN', 'companydomain.com');

// Create the MySQL connection using the resolved configuration values.
$db_connection = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($db_connection === false) {
    die("ERROR: failed connection" . mysqli_connect_error());
} else {
    error_log("Connection to db completed successfully.");
}

?>
