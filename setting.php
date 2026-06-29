<?php
/**
 * SQL Assist - Session and Configuration Settings
 *
 * Shares sessions with DocInfo Manager via database-backed sessions
 * and .cloudbox9.com cookie domain.
 *
 * @author Cloud Box 9
 * @date 2026-01-28
 */

// Include database configuration
require_once __DIR__ . '/db.php';

// Session lifetime: 8 hours (28800 seconds)
$lifetime = 2851200;

// Enable session garbage collection
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// Set the cookie lifetime for the session cookie
ini_set('session.cookie_lifetime', $lifetime);
ini_set('session.gc_maxlifetime', $lifetime);

// Set session cookie parameters before starting session
// Domain set to .cloudbox9.com to allow session sharing across subdomains (only on production)
$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
$cookieDomain = '';
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'cloudbox9.com') !== false) {
    $cookieDomain = '.cloudbox9.com';
}
session_set_cookie_params($lifetime, '/', $cookieDomain, $secure, true);

// Use shared session name across cloudbox9.com subdomains
// Must match DocInfo's session name for session sharing
session_name('CB9SID');

// Database-backed session handler (shared with DocInfo Manager)
// Sessions are stored in the docInfo database
require_once __DIR__ . '/model/sessionHandler.php';

try {
    // Create database connection for the session handler.
    // Credentials come from db.php ($sessionDb_dsn / $sessionDb_user /
    // $sessionDb_pass). If the connection fails, the catch block below
    // falls back to standard file-based PHP sessions.
    $sessionDb = new \PDO(
        $sessionDb_dsn,
        $sessionDb_user,
        $sessionDb_pass
    );
    $sessionDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    // Initialize database session handler
    $sessionHandler = new DatabaseSessionHandler($sessionDb, $lifetime);

    // Register the session handler
    session_set_save_handler($sessionHandler, true);
} catch (\PDOException $e) {
    // Fallback to file-based sessions if database fails
    error_log("SQL Assist session handler initialization failed: " . $e->getMessage());
}

session_start();

// Set timezone
date_default_timezone_set('America/New_York');
?>
