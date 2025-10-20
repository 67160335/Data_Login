<?php
session_start();
require __DIR__ . '/config_mysqli.php';
require __DIR__ . '/csrf.php';

// ?????????? string/array
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$type = 'primary'; // ??????????? (???)
$msg  = '';
if (is_array($flash)) {
  $type = $flash['type'] ?? 'primary';
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
  <title>Sign in</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light" style="min-height:100vh;display:flex;align-items:center;">
  <main class="container d-flex justify-content-center">
    <div class="card shadow-sm p-3 p-md-4" style="max-width:420px;width:100%">
      <div class="card-body">
        <h1 class="h4 mb-3 text-center">Welcome</h1>

        <?php if ($msg): ?>
          <div class="alert alert-<?php echo htmlspecialchars($type); ?> py-2 mb-3">
            <?php echo htmlspecialchars($msg); ?>
          </div>
        <?php endif; ?>

        <form method="post" action="login_process.php" novalidate>
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" placeholder="you@example.com" required>
          </div>
          <div class="mb-2">
            <label class="form-label d-flex justify-content-between" for="password">
              <span>Password</span>
              <a href="#" class="small text-decoration-none" onclick="alert('Ask admin to reset');return false;">Forgot?</a>
            </label>
            <input class="form-control" type="password" id="password" name="password" placeholder="••••••••" required>
          </div>
          <div class="d-grid gap-2 mt-3">
            <button class="btn btn-primary" type="submit">Sign in</button>
            <a class="btn btn-outline-secondary" href="register.php">Create account</a>
          </div>
        </form>
      </div>
    </div>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
