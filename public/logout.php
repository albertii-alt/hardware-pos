<?php
require_once __DIR__ . '/../app/bootstrap.php';

session_unset();
session_destroy();
header('Location: login.php');
exit;
