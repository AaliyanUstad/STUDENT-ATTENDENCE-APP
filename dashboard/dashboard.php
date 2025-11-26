<?php
require_once '../config.php';
redirectIfNotLoggedIn();

if (isTeacher()) {
    header("Location: teacher-dashboard.php");
} elseif (isStudent()) {
    header("Location: student-dashboard.php");
} else {
    header("Location: ../auth/logout.php"); 
}
exit();
?>