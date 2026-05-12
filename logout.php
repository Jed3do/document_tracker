<?php
// logout.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

// Assuming logout() handles session_destroy() and unsetting variables
logout();

// Redirect to login with a logout success flag
header('Location: login.php?status=logged_out');
exit();
?>