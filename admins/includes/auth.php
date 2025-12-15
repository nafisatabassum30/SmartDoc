<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function admins_count($con){
  $r = $con->query("SELECT COUNT(*) c FROM `admins`");
  return (int)($r->fetch_assoc()['c'] ?? 0);
}

function require_login(){
  if (empty($_SESSION['admin_logged'])){
    header('Location: login.php'); exit;
  }
}

// Legacy helper kept for compatibility; all logged-in admins are treated the same now.
function require_super(){
  require_login();
}

function is_admin_logged_in(){ return !empty($_SESSION['admin_logged']); }
function current_admin_name(){ return $_SESSION['admin_name'] ?? 'Admin'; }
function current_admin_role(){ return $_SESSION['admin_role'] ?? 'admin'; }
