<?php
session_start();
session_destroy();

require __DIR__ . '/config/database.php';

header("Location: " . BASE_URL . "login.php");
exit;