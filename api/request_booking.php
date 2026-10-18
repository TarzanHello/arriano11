<?php
// DISABLED (Oct 2026): legacy endpoint, no longer used by the site.
// The booking form uses /api/create_checkout.php. This old endpoint accepted requests
// without guest count / min-stay messages and could be abused to block dates with spam.
require_once __DIR__ . '/../lib/lib.php';
send_security_headers(['no_store' => true]);
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode(['ok' => false, 'error' => 'Endpoint non più disponibile.'], JSON_UNESCAPED_UNICODE);
