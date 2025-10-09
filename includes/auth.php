<?php
session_start();

function require_role($role) {
  if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== $role) {
    header('Location: login_' . $role . '.php');
    exit();
  }
}

function is_logged_in() {
  return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function logout() {
  session_unset();
  session_destroy();
  header('Location: index.php');
  exit();
}
?>
