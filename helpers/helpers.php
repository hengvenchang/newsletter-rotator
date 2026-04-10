<?php
/**
 * Newsletter Rotator Helper Functions
 *
 * This file contains utility functions for database operations and email domain
 * processing used throughout the newsletter rotator application.
 *
 * @author Newsletter Rotator Team
 * @version 1.0
 */

require_once __DIR__ . '/../config.php';

/**
 * Establishes and returns a database connection
 *
 * Creates a MySQLi connection using configuration constants.
 * Terminates execution on connection failure.
 *
 * @return mysqli Database connection object
 * @throws Exception If connection fails
 */
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        writeLog("Connection failed: " . $conn->connect_error, 'ERROR');
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

/**
 * Extracts the domain part from an email address
 *
 * @param string $email The email address to process
 * @return string The domain in lowercase
 */
function getDomain($email) {
    $parts = explode('@', $email);
    return strtolower($parts[1]);
}

/**
 * Normalizes email domains to group similar providers
 *
 * This function groups related email providers under common names to ensure
 * proper rotation across provider families (e.g., hotmail.com, outlook.com → 'hotmail',
 * gmail.de, gmail.co.uk → 'gmail')
 *
 * @param string $domain The domain to normalize
 * @return string The normalized domain name
 */
function normalizeDomain($domain) {
    // Normalize providers: hotmail.com, hotmail.co.uk, outlook.com are same
    $domain = strtolower($domain);
    if (preg_match('/hotmail\./', $domain) || preg_match('/outlook\./', $domain)) {
        return 'hotmail';
    }
    // Normalize all Gmail variants: gmail.com, gmail.de, gmail.co.uk, googlemail.com, etc.
    if (preg_match('/^gmail\./', $domain) || preg_match('/googlemail\./', $domain)) {
        return 'gmail';
    }
    return $domain;
}

/**
 * Appends a log message to logs/app.log
 *
 * @param string $message The message to log
 * @param string $level Log level (INFO, ERROR, etc.)
 */
function writeLog($message, $level = 'INFO') {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/app.log';
    $date = date('Y-m-d H:i:s');
    $entry = "[$date][$level] $message\n";
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

// Example usage:
// writeLog('Batch sent successfully');
// writeLog('Database connection failed', 'ERROR');
?>