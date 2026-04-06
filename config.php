<?php
/**
 * Newsletter Rotator Configuration
 *
 * Reads database settings from environment variables if available.
 * Provides fallback for local testing.
 */

define('DB_HOST', getenv('MYSQLHOST') ?: '127.0.0.1');       // fallback to local MySQL
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'newsletter');

// Debug: uncomment to check if env vars are loaded correctly
// var_dump(DB_HOST, DB_USER, DB_PASS, DB_NAME); die();
?>