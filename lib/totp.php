<?php
// Pure PHP TOTP (Google Authenticator compatible)
// RFC 4226 (HOTP) + RFC 6238 (TOTP)

function totp_base32_encode($data) {
  $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  $bits = '';
  for ($i = 0; $i < strlen($data); $i++) {
    $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
  }
  $chunks = str_split($bits, 5);
  $out = '';
  foreach ($chunks as $chunk) {
    if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
    $out .= $alphabet[bindec($chunk)];
  }
  return $out;
}

function totp_base32_decode($b32) {
  $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
  $bits = '';
  for ($i = 0; $i < strlen($b32); $i++) {
    $v = strpos($alphabet, $b32[$i]);
    if ($v === false) continue;
    $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
  }
  $bytes = str_split($bits, 8);
  $out = '';
  foreach ($bytes as $byte) {
    if (strlen($byte) < 8) continue;
    $out .= chr(bindec($byte));
  }
  return $out;
}

function totp_generate_secret($length = 20) {
  $raw = random_bytes($length);
  return totp_base32_encode($raw);
}

function totp_get_code($secret_b32, $timeSlice = null, $digits = 6) {
  if ($timeSlice === null) $timeSlice = (int) floor(time() / 30);
  $secret = totp_base32_decode($secret_b32);

  // 8-byte big-endian counter
  $counter = pack('N*', 0) . pack('N*', $timeSlice);

  $hash = hash_hmac('sha1', $counter, $secret, true);
  $offset = ord($hash[19]) & 0x0F;

  $binary =
    ((ord($hash[$offset]) & 0x7F) << 24) |
    ((ord($hash[$offset + 1]) & 0xFF) << 16) |
    ((ord($hash[$offset + 2]) & 0xFF) << 8) |
    (ord($hash[$offset + 3]) & 0xFF);

  $otp = $binary % (10 ** $digits);
  return str_pad((string)$otp, $digits, '0', STR_PAD_LEFT);
}

function totp_verify_code($secret_b32, $code, $window = 1, $digits = 6) {
  $code = preg_replace('/\D/', '', (string)$code);
  if (strlen($code) !== $digits) return false;

  $timeSlice = (int) floor(time() / 30);

  // allow +/- window steps
  for ($i = -$window; $i <= $window; $i++) {
    if (hash_equals(totp_get_code($secret_b32, $timeSlice + $i, $digits), $code)) {
      return true;
    }
  }
  return false;
}
