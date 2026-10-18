<?php
require_once __DIR__ . '/../lib/lib.php';

// Security headers + no-cache
send_security_headers(['admin' => true, 'no_store' => true]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo 'Method Not Allowed';
  exit;
}

$csrf = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
if (!csrf_verify($csrf)) {
  http_response_code(400);
  echo 'Bad Request';
  exit;
}

admin_logout();
header('Location: /admin/login.php');
exit;
