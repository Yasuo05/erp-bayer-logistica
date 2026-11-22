<?php
// logout.php
require_once __DIR__ . '/core/db.php';
session_start();
session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;
