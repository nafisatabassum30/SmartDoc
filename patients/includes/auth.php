<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function require_patient_login(){
  if (empty($_SESSION['patient_logged'])){
    header('Location: login.php'); 
    exit;
  }
}

function get_patient_id(){
  return (int)($_SESSION['patient_id'] ?? 0);
}

function get_patient_name(){
  return $_SESSION['patient_name'] ?? 'Patient';
}

function is_patient_logged_in(){ return !empty($_SESSION['patient_logged']); }

