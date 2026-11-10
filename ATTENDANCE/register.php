<?php
require_once __DIR__ . '/config/auth.php';

if (!empty($_SESSION['user_id'])) {
	header('Location: ' . app_url(($_SESSION['role'] ?? '') === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
	exit;
}

$error = '';
$form = [
	'full_name' => '',
	'username' => '',
	'email' => '',
	'student_number' => '',
	'course' => '',
	'year_level' => '',
	'section' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$form = [
		'full_name' => trim((string) ($_POST['full_name'] ?? '')),
		'username' => trim((string) ($_POST['username'] ?? '')),
		'email' => trim((string) ($_POST['email'] ?? '')),
		'student_number' => trim((string) ($_POST['student_number'] ?? '')),
		'course' => trim((string) ($_POST['course'] ?? '')),
		'year_level' => trim((string) ($_POST['year_level'] ?? '')),
		'section' => trim((string) ($_POST['section'] ?? '')),
	];
	$password = (string) ($_POST['password'] ?? '');
	$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

	if (in_array('', array_values($form), true)
		|| !filter_var($form['email'], FILTER_VALIDATE_EMAIL)
		|| strlen($password) < 8
		|| $password !== $confirmPassword) {
		$error = 'Complete all fields with a valid email and make sure your password is at least 8 characters and matches the confirmation.';
	} else {
		$existingUser = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
		$existingUser->bind_param('ss', $form['username'], $form['email']);
		$existingUser->execute();
		if ($existingUser->get_result()->fetch_assoc()) {
			$error = 'That username or email is already registered.';
		} else {
			$existingStudent = $conn->prepare('SELECT id FROM students WHERE student_number = ? LIMIT 1');
			$existingStudent->bind_param('s', $form['student_number']);
			$existingStudent->execute();
			if ($existingStudent->get_result()->fetch_assoc()) {
				$error = 'A student with that ID already exists.';
			} else {
				try {
					$conn->begin_transaction();
					$hash = password_hash($password, PASSWORD_DEFAULT);
					$userInsert = $conn->prepare("INSERT INTO users (full_name, username, email, password, role) VALUES (?, ?, ?, ?, 'student')");
					$userInsert->bind_param('ssss', $form['full_name'], $form['username'], $form['email'], $hash);
					$userInsert->execute();
					$userId = $conn->insert_id;
					$studentInsert = $conn->prepare('INSERT INTO students (user_id, student_number, course, year_level, section) VALUES (?, ?, ?, ?, ?)');
					$studentInsert->bind_param('issss', $userId, $form['student_number'], $form['course'], $form['year_level'], $form['section']);
					$studentInsert->execute();
					$conn->commit();

					session_regenerate_id(true);
					$_SESSION['user_id'] = (int) $userId;
					$_SESSION['full_name'] = $form['full_name'];
					$_SESSION['role'] = 'student';
					header('Location: ' . app_url('student/dashboard.php'));
					exit;
				} catch (mysqli_sql_exception $exception) {
					$conn->rollback();
					error_log('Student registration error: ' . $exception->getMessage());
					$error = 'We could not create your account at the moment. Please try again.';
				}
			}
		}
	}
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Create account · Attendly</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="<?= e(app_url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="auth-page">
<main class="auth-layout">
	<section class="auth-visual">
		<a class="brand brand-light" href="<?= e(app_url('index.php')) ?>"><span class="brand-mark">A</span><span>attendly<small>STUDENT PORTAL</small></span></a>
		<div class="visual-copy">
			<span class="visual-kicker">YOUR SCHOOL, IN SYNC</span>
			<h1>One place<br>to check in.</h1>
			<p>Register your student profile and keep your attendance records organized in one secure portal.</p>
		</div>
		<div class="visual-footer">Made for the rhythm of school life.</div>
	</section>
	<section class="auth-form-side">
		<div class="auth-form-wrap">
			<span class="eyebrow">ACCOUNT ACCESS</span>
			<h2>Create your student account</h2>
			<p class="muted-copy">Fill in your details to set up your attendance portal access.</p>
			<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
			<form method="post" action="<?= e(app_url('register.php')) ?>" class="form-stack">
				<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
				<div><label class="form-label" for="full_name">Full name</label><input class="form-control" id="full_name" name="full_name" autocomplete="name" value="<?= e($form['full_name']) ?>" required placeholder="Enter your full name"></div>
				<div><label class="form-label" for="username">Username</label><input class="form-control" id="username" name="username" autocomplete="username" value="<?= e($form['username']) ?>" required placeholder="Choose a username"></div>
				<div><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" autocomplete="email" value="<?= e($form['email']) ?>" required placeholder="name@example.com"></div>
				<div><label class="form-label" for="student_number">Student ID</label><input class="form-control" id="student_number" name="student_number" value="<?= e($form['student_number']) ?>" required placeholder="e.g. 2024-001"></div>
				<div><label class="form-label" for="course">Course</label><input class="form-control" id="course" name="course" value="<?= e($form['course']) ?>" required placeholder="e.g. BS Information Technology"></div>
				<div class="row g-2">
					<div class="col-md-6"><label class="form-label" for="year_level">Year level</label><input class="form-control" id="year_level" name="year_level" value="<?= e($form['year_level']) ?>" required placeholder="e.g. Year 1"></div>
					<div class="col-md-6"><label class="form-label" for="section">Section</label><input class="form-control" id="section" name="section" value="<?= e($form['section']) ?>" required placeholder="e.g. A"></div>
				</div>
				<div><label class="form-label" for="password">Password</label><input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required placeholder="At least 8 characters"></div>
				<div><label class="form-label" for="confirm_password">Confirm password</label><input class="form-control" id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required placeholder="Re-enter your password"></div>
				<button class="btn btn-primary btn-lg w-100" type="submit">Create account <span aria-hidden="true">→</span></button>
			</form>
			<p class="auth-note">Already have an account? <a href="<?= e(app_url('login.php')) ?>">Sign in</a></p>
		</div>
		<div class="auth-copyright">© <?= date('Y') ?> Attendly School Attendance</div>
	</section>
</main>
</body>
</html>
