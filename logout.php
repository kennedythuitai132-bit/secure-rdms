<?php require_once __DIR__ . "/includes/functions.php";
if (!empty($_SESSION["user_id"])) {
    logAudit("LOGOUT", "User signed out");
}
$_SESSION = [];
session_destroy();
header("Location: login.php?message=" . urlencode("You have signed out."));
exit();
