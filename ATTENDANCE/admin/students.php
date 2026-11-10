<?php
require_once __DIR__ . '/../config/auth.php';
require_role('admin');

$error = '';
$form = ['student_id' => '', 'full_name' => '', 'username' => '', 'email' => '', 'student_number' => '', 'course' => '', 'year_level' => '', 'section' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$action = (string) ($_POST['action'] ?? '');
	if ($action === 'delete') {
		$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
		if ($id) {
			$statement = $conn->prepare("DELETE u FROM users u JOIN students s ON s.user_id = u.id WHERE s.id = ? AND u.role = 'student'");
			$statement->bind_param('i', $id);
			$statement->execute();
			set_flash('success', $statement->affected_rows > 0 ? 'Student account deleted.' : 'Student account was not found.');
		}
		header('Location: ' . app_url('admin/students.php'));
		exit;
	}

	$form = [
		'student_id' => trim((string) ($_POST['student_id'] ?? '')),
		'full_name' => trim((string) ($_POST['full_name'] ?? '')),
		'username' => trim((string) ($_POST['username'] ?? '')),
		'email' => trim((string) ($_POST['email'] ?? '')),
		'student_number' => trim((string) ($_POST['student_number'] ?? '')),
		'course' => trim((string) ($_POST['course'] ?? '')),
		'year_level' => trim((string) ($_POST['year_level'] ?? '')),
		'section' => trim((string) ($_POST['section'] ?? '')),
	];
	$password = (string) ($_POST['password'] ?? '');
	$isEdit = $action === 'update';
	if (in_array('', [$form['full_name'], $form['username'], $form['email'], $form['student_number'], $form['course'], $form['year_level'], $form['section']], true)
		|| !filter_var($form['email'], FILTER_VALIDATE_EMAIL)
		|| (!$isEdit && strlen($password) < 8)
		|| ($password !== '' && strlen($password) < 8)) {
		$error = 'Complete all fields with a valid email. Passwords must be at least 8 characters.';
	} else {
		try {
			$conn->begin_transaction();
			if ($isEdit) {
				$id = (int) $form['student_id'];
				$lookup = $conn->prepare('SELECT user_id FROM students WHERE id = ?');
				$lookup->bind_param('i', $id);
				$lookup->execute();
				$student = $lookup->get_result()->fetch_assoc();
				if (!$student) {
					throw new RuntimeException('Student record was not found.');
				}
				$userId = (int) $student['user_id'];
				if ($password !== '') {
					$hash = password_hash($password, PASSWORD_DEFAULT);
					$userUpdate = $conn->prepare('UPDATE users SET full_name = ?, username = ?, email = ?, password = ? WHERE id = ? AND role = \'student\'');
					$userUpdate->bind_param('ssssi', $form['full_name'], $form['username'], $form['email'], $hash, $userId);
				} else {
					$userUpdate = $conn->prepare('UPDATE users SET full_name = ?, username = ?, email = ? WHERE id = ? AND role = \'student\'');
					$userUpdate->bind_param('sssi', $form['full_name'], $form['username'], $form['email'], $userId);
				}
				$userUpdate->execute();
				$studentUpdate = $conn->prepare('UPDATE students SET student_number = ?, course = ?, year_level = ?, section = ? WHERE id = ?');
				$studentUpdate->bind_param('ssssi', $form['student_number'], $form['course'], $form['year_level'], $form['section'], $id);
				$studentUpdate->execute();
				$message = 'Student details updated.';
			} else {
				$hash = password_hash($password, PASSWORD_DEFAULT);
				$userInsert = $conn->prepare("INSERT INTO users (full_name, username, email, password, role) VALUES (?, ?, ?, ?, 'student')");
				$userInsert->bind_param('ssss', $form['full_name'], $form['username'], $form['email'], $hash);
				$userInsert->execute();
				$userId = $conn->insert_id;
				$studentInsert = $conn->prepare('INSERT INTO students (user_id, student_number, course, year_level, section) VALUES (?, ?, ?, ?, ?)');
				$studentInsert->bind_param('issss', $userId, $form['student_number'], $form['course'], $form['year_level'], $form['section']);
				$studentInsert->execute();
				$message = 'Student account created.';
			}
			$conn->commit();
			set_flash('success', $message);
			header('Location: ' . app_url('admin/students.php'));
			exit;
		} catch (mysqli_sql_exception $exception) {
			$conn->rollback();
			error_log('Student save error: ' . $exception->getMessage());
			$error = $exception->getCode() === 1062 ? 'Username, email, or student ID is already in use.' : 'Unable to save the student. Please check the details and try again.';
		} catch (RuntimeException $exception) {
			$conn->rollback();
			$error = $exception->getMessage();
		}
	}
}

$editingId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editingId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
	$statement = $conn->prepare('SELECT s.id AS student_id, s.student_number, s.course, s.year_level, s.section, u.full_name, u.username, u.email FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
	$statement->bind_param('i', $editingId);
	$statement->execute();
	$savedForm = $statement->get_result()->fetch_assoc();
	if ($savedForm) {
		$form = $savedForm;
	} else {
		$editingId = null;
	}
}
$search = trim((string) ($_GET['q'] ?? ''));
$like = '%' . $search . '%';
$list = $conn->prepare('SELECT s.id AS student_id, s.student_number, s.course, s.year_level, s.section, u.full_name, u.username, u.email FROM students s JOIN users u ON u.id = s.user_id WHERE u.full_name LIKE ? OR s.student_number LIKE ? OR u.username LIKE ? ORDER BY u.full_name');
$list->bind_param('sss', $like, $like, $like);
$list->execute();
$students = $list->get_result();

render_header('Students', 'students');
?>
<div class="section-heading first-heading"><div><span class="eyebrow">STUDENT DIRECTORY</span><h2>Manage student accounts</h2><p class="muted-copy">Create and update student profiles and login details.</p></div><span class="count-chip"><?= $students->num_rows ?> students</span></div>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="content-grid students-grid">
	<section class="panel-card"><div class="panel-heading"><div><h3><?= $editingId ? 'Edit student' : 'Add a student' ?></h3><p><?= $editingId ? 'Update profile and account information.' : 'Create a student profile and sign-in.' ?></p></div></div>
		<form method="post" action="<?= e(app_url('admin/students.php' . ($editingId ? '?edit=' . $editingId : ''))) ?>" class="form-grid">
			<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= $editingId ? 'update' : 'create' ?>"><input type="hidden" name="student_id" value="<?= e($form['student_id']) ?>">
			<div class="field-span"><label class="form-label">Full name</label><input class="form-control" name="full_name" maxlength="120" required value="<?= e($form['full_name']) ?>" placeholder="e.g. Alex Rivera"></div>
			<div><label class="form-label">Username</label><input class="form-control" name="username" maxlength="50" required value="<?= e($form['username']) ?>"></div>
			<div><label class="form-label">Email</label><input class="form-control" type="email" name="email" maxlength="160" required value="<?= e($form['email']) ?>"></div>
			<div><label class="form-label">Student ID</label><input class="form-control" name="student_number" maxlength="40" required value="<?= e($form['student_number']) ?>"></div>
			<div><label class="form-label">Course</label><input class="form-control" name="course" maxlength="120" required value="<?= e($form['course']) ?>"></div>
			<div><label class="form-label">Year level</label><input class="form-control" name="year_level" maxlength="30" required value="<?= e($form['year_level']) ?>" placeholder="e.g. Year 1"></div>
			<div><label class="form-label">Section</label><input class="form-control" name="section" maxlength="60" required value="<?= e($form['section']) ?>"></div>
			<div class="field-span"><label class="form-label">Password <?= $editingId ? '<span class="optional">(leave blank to keep current)</span>' : '' ?></label><input class="form-control" type="password" name="password" minlength="8" <?= $editingId ? '' : 'required' ?> autocomplete="new-password" placeholder="At least 8 characters"></div>
			<div class="field-span form-actions"><button class="btn btn-primary" type="submit"><?= $editingId ? 'Save changes' : 'Create student' ?></button><?php if ($editingId): ?><a class="btn btn-light" href="<?= e(app_url('admin/students.php')) ?>">Cancel</a><?php endif; ?></div>
		</form>
	</section>
	<section class="panel-card directory-card"><div class="panel-heading"><div><h3>All students</h3><p>Search by name, username, or student ID.</p></div></div>
		<form class="search-form" method="get" action="<?= e(app_url('admin/students.php')) ?>"><input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search students..."><button class="btn btn-outline-primary" type="submit">Search</button></form>
		<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Student</th><th>Student ID</th><th>Course / year</th><th>Section</th><th class="text-end">Actions</th></tr></thead><tbody>
		<?php if ($students->num_rows === 0): ?><tr><td colspan="5" class="empty-state">No students found.</td></tr><?php else: while ($student = $students->fetch_assoc()): ?>
		<tr><td><strong><?= e($student['full_name']) ?></strong><small class="table-subline"><?= e($student['username']) ?></small></td><td><?= e($student['student_number']) ?></td><td><?= e($student['course']) ?><small class="table-subline"><?= e($student['year_level']) ?></small></td><td><?= e($student['section']) ?></td><td class="text-end action-cell"><a class="icon-action" href="<?= e(app_url('admin/students.php?edit=' . (int) $student['student_id'])) ?>" aria-label="Edit student">Edit</a><form method="post" action="<?= e(app_url('admin/students.php')) ?>" onsubmit="return confirm('Delete this student and their attendance history?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $student['student_id'] ?>"><button class="icon-action danger-action" type="submit">Delete</button></form></td></tr>
		<?php endwhile; endif; ?></tbody></table></div>
	</section>
</div>
<?php render_footer(); ?>
