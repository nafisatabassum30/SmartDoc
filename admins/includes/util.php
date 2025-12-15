<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/**
 * Flash messaging helper
 * Usage:
 *  flash('Saved'); // success
 *  flash('Email exists','warning');
 *  flash(); // render if exists
 */
function flash($msg=null, $type='success'){
  if ($msg!==null){
    $_SESSION['flash']=$msg;
    $_SESSION['flash_type']=$type;
    return;
  }
  if (!empty($_SESSION['flash'])){
    $type = $_SESSION['flash_type'] ?? 'success';
    $map = ['success'=>'alert-success','warning'=>'alert-warning','danger'=>'alert-danger','info'=>'alert-info'];
    $cls = $map[$type] ?? 'alert-success';
    echo '<div class="alert '.$cls.' alert-dismissible fade show" role="alert">'.h($_SESSION['flash']).'<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['flash'], $_SESSION['flash_type']);
  }
}

// Request helpers
function is_post(){ return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function redirect($path){ header('Location: '.$path); exit; }

// CSRF helpers
function csrf_token(){
  if (empty($_SESSION['csrf_token'])){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['csrf_token'];
}
function csrf_field(){
  echo '<input type="hidden" name="csrf" value="'.h(csrf_token()).'">';
}
function verify_csrf(){
  $ok = !empty($_POST['csrf']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf']);
  if (!$ok){ http_response_code(400); echo '<div class="container p-5"><div class="alert alert-danger">Invalid request token.</div></div>'; exit; }
}