<?php
/**
 * Logout Script
 * Clears session and redirects to login page
 */
require_once 'includes/config.php';

// Clear all session data
$_SESSION = [];

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Redirect to login page
redirect('index.php');
