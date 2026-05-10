<?php
/**
 * Kanzlei Rogalla Admin - Logout
 */
require_once __DIR__ . '/auth.php';

admin_session_start();
session_unset();
session_destroy();

// Altes Session-Cookie ungueltig machen
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

header('Location: login.php');
exit;
