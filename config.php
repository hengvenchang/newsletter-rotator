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
define('DB_HOST', getenv('MYSQLHOST') ?: '');       // fallback empty string
define('DB_USER', getenv('MYSQLUSER') ?: '');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: '');// Database name

// Railway injects MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE
// For other providers, set DB_HOST, DB_USER, DB_PASS, DB_NAME in your environment
?>