<?php
require_once 'config.php';

if (isLoggedIn()) {
    header("Location: dashboard/dashboard.php");
} else {
    header("Location: auth/login.php");
}
exit();
?>