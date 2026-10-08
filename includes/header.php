<?php
// includes/header.php - Global Header
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// If no user is logged in, redirect visitors to register.php to sign up
if (!is_logged_in()) {
    header("Location: register.php");
    exit;
}

$currentUser = current_user();
if (!$currentUser) {
    header("Location: register.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? e($pageTitle) . ' | CampusForum' : 'CampusForum - College Discussion Board' ?></title>
  <meta name="description" content="CampusForum is the modern discussion board for college students to share questions, images, setups, and campus moments.">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2338bdf8'><path d='M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z'/></svg>">
  <script>
    (function() {
      const savedTheme = localStorage.getItem('campus_theme') || 'dark';
      document.documentElement.setAttribute('data-theme', savedTheme);
    })();
  </script>
</head>
<body>
<div class="app-container">
