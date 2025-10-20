<?php
session_start();
require __DIR__ . '/config_mysqli.php';
require __DIR__ . '/csrf.php';

// ==== Flash (?????? string/array) ====
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$type = 'danger'; // default ??????? (????????? error)
$msg  = '';
if (is_array($flash)) {
  $type = $flash['type'] ?? 'danger';
  $msg  = $flash['msg']  ?? '';
} else {
  $msg = (string)$flash;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { min-height: 100vh; display:flex; align-items:center; }
    .card-auth { max-width: 460px; width: 100%; }
  </style>
</head>
<body class="bg-light">
  <main class="container d-flex justify-content-center">
    <div class="card shadow-sm card-auth p-3 p-md-4">
      <div class="card-body">
        <h1 class="h4 mb-3 text-center">Create your account</h1>

        <?php if ($msg !== ''): ?>
          <div class="alert alert-<?php echo htmlspecialchars($type); ?> py-2 mb-3">
            <?php echo htmlspecialchars($msg); ?>
          </div>
        <?php endif; ?>

        <form method="post" action="register_process.php" novalidate>
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">

          <div class="mb-3">
            <label class="form-label" for="display_name">Display name</label>
            <input class="form-control" type="text" id="display_name" name="display_name" placeholder="Your name" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" placeholder="you@example.com" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" type="password" id="password" name="password" placeholder="password" minlength="8" required>
            <div class="form-text">At least 8 characters.</div>
          </div>

          <div class="mb-2">
            <label class="form-label" for="password_confirm">Confirm password</label>
            <input class="form-control" type="password" id="password_confirm" name="password_confirm" placeholder="Confirm password" minlength="8" required>
          </div>

          <div class="d-grid gap-2 mt-3">
            <button class="btn btn-primary" type="submit">Create account</button>
            <a class="btn btn-outline-secondary" href="login.php">Back to Sign in</a>
          </div>
        </form>
      </div>
    </div>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
