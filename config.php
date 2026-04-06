<?php
/**
 * Newsletter Rotator Configuration
 *
 * This file contains database configuration constants used throughout the application.
 * Modify these values to match your database setup.
 *
 * @author Newsletter Rotator Team
 * @version 1.0
 */

// Database connection settings
define('DB_HOST', getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '127.0.0.1');        // Database server hostname/IP
define('DB_USER', getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'newsletter-rotator'); // Database username
define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '8np3r52cr8u');     // Database password
define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'newsletter');      // Database name
define('DB_SOCKET', getenv('DB_SOCKET') ?: null);            // Unix socket path (null for TCP connection)

// Railway injects MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE
// For other providers, set DB_HOST, DB_USER, DB_PASS, DB_NAME in your environment
?>