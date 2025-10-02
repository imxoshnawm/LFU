<?php
require_once 'config.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
} else {
    // If not logged in, redirect to login
    header('Location: login.php');
    exit;
}
?>