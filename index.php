<?php
require_once __DIR__ . "/includes/functions.php";
header("Location: " . (!empty($_SESSION["user_id"]) ? "dashboard.php" : "login.php"));
exit();
