<?php
session_start();
require __DIR__ . '/config_mysqli.php'; // ?????? $mysqli
require __DIR__ . '/csrf.php';          // ?? csrf_token() ??? csrf_check()

// helper: ??? flash ???????? register
function flash_and_back(string $msg): void {
  $_SESSION['flash'] = $msg; // ??????? string ???????????? login ?????????????
  header('Location: register.php');
  exit;
}

// 1) ???????? POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Invalid method');
}

// 2) ???? CSRF
if (!isset($_POST['csrf']) || !csrf_check($_POST['csrf'])) {
  flash_and_back('Invalid CSRF token.');
}

// 3) ??????????????
$display_name     = trim($_POST['display_name'] ?? '');
$email            = trim($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// 4) validate
if ($display_name === '')                        flash_and_back('Please enter your display name.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL))  flash_and_back('Invalid email address.');
if (strlen($password) < 8)                       flash_and_back('Password must be at least 8 characters.');
if ($password !== $password_confirm)             flash_and_back('Password confirmation does not match.');

// 5) ???? DB ready
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
  http_response_code(500);
  exit('DB is not ready.');
}
$mysqli->set_charset('utf8mb4');

// 6) ????????????
$stmt = $mysqli->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
if (!$stmt) { http_response_code(500); exit('Prepare SELECT failed: ' . $mysqli->error); }
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
  $stmt->close();
  flash_and_back('This email is already registered.');
}
$stmt->close();

// 7) ?? hash ???? INSERT
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare('INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)');
if (!$stmt) { http_response_code(500); exit('Prepare INSERT failed: ' . $mysqli->error); }
$stmt->bind_param('sss', $email, $display_name, $hash);
if (!$stmt->execute()) {
  $err = $stmt->error;
  $stmt->close();
  flash_and_back('Could not create account. (' . $err . ')'); // ??????????????? $err ???? column ??????/???????????
}
$stmt->close();

// 8) ??????????? ? ????? "???" ??????? login
$_SESSION['flash'] = ['msg' => 'Registration successful! Please sign in.', 'type' => 'primary'];
header('Location: login.php');
exit;
