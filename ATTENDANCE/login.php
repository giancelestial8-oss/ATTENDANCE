<?php
require_once __DIR__ . '/config/auth.php';

if (!empty($_SESSION['user_id'])) {
	header('Location: ' . app_url(($_SESSION['role'] ?? '') === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
	exit;
}

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$username = trim((string) ($_POST['username'] ?? ''));
	$password = (string) ($_POST['password'] ?? '');

	if ($username === '' || $password === '') {
		$error = 'Enter your username and password.';
	} else {
		$statement = $conn->prepare('SELECT id, full_name, username, password, role FROM users WHERE username = ? LIMIT 1');
		$statement->bind_param('s', $username);
		$statement->execute();
		$user = $statement->get_result()->fetch_assoc();
		if ($user && password_verify($password, $user['password'])) {
			session_regenerate_id(true);
			$_SESSION['user_id'] = (int) $user['id'];
			$_SESSION['full_name'] = $user['full_name'];
			$_SESSION['role'] = $user['role'];
			header('Location: ' . app_url($user['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
			exit;
		}
		$error = 'The username or password you entered is incorrect.';
	}
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Sign in · Attendly</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="<?= e(app_url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="auth-page">
<main class="auth-layout">
	<section class="auth-visual"><a class="brand brand-light" href="<?= e(app_url('index.php')) ?>"><span class="brand-mark">A</span><span>attendly<small>STUDENT PORTAL</small></span></a><div class="visual-copy"><span class="visual-kicker">EVERY DAY COUNTS</span><h1>Show up.<br>Move forward.</h1><p>A simpler way to keep your school days organized and your progress in view.</p><div class="visual-decoration"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div></div><div class="visual-footer">A little consistency goes a long way.</div></section>
	<section class="auth-form-side"><div class="auth-form-wrap"><span class="eyebrow">WELCOME BACK</span><h2>Sign in to Attendly</h2><p class="muted-copy">Use your account to continue to your portal.</p>
		<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
		<form method="post" action="<?= e(app_url('login.php')) ?>" class="form-stack">
			<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
			<div><label class="form-label" for="username">Username</label><input class="form-control" id="username" name="username" autocomplete="username" value="<?= e($username) ?>" required autofocus placeholder="Enter your username"></div>
			<div><label class="form-label" for="password">Password</label><input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password"></div>
			<button class="btn btn-primary btn-lg w-100" type="submit">Sign in <span aria-hidden="true">→</span></button>
		</form>
		<p class="auth-note">Need a student account? <a href="<?= e(app_url('register.php')) ?>">Create one here</a>.</p>
	</div><div class="auth-copyright">© <?= date('Y') ?> Attendly School Attendance</div></section>
</main>
</body>
</html>
