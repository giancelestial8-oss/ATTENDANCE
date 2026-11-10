<?php
require_once __DIR__ . '/../config/auth.php';
require_role('admin');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$action = (string) ($_POST['action'] ?? '');
	$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
	if ($action === 'delete' && $id) {
		$statement = $conn->prepare('DELETE FROM attendance WHERE id = ?');
		$statement->bind_param('i', $id);
		$statement->execute();
		set_flash('success', 'Attendance record deleted.');
		header('Location: ' . app_url('admin/attendance.php'));
		exit;
	}
	if ($action === 'update' && $id) {
		$status = (string) ($_POST['status'] ?? '');
		$date = (string) ($_POST['attendance_date'] ?? '');
		$time = trim((string) ($_POST['time_in'] ?? ''));
		$validDate = DateTime::createFromFormat('Y-m-d', $date);
		$validTime = $time === '' || DateTime::createFromFormat('H:i', $time) !== false;
		if (!in_array($status, ['Present', 'Absent'], true) || !$validDate || $validDate->format('Y-m-d') !== $date || !$validTime) {
			$error = 'Enter a valid attendance date, time, and status.';
		} else {
			try {
				$timeValue = $status === 'Absent' || $time === '' ? null : $time . ':00';
				$statement = $conn->prepare('UPDATE attendance SET attendance_date = ?, time_in = ?, status = ? WHERE id = ?');
				$statement->bind_param('sssi', $date, $timeValue, $status, $id);
				$statement->execute();
				set_flash('success', 'Attendance record updated.');
				header('Location: ' . app_url('admin/attendance.php'));
				exit;
			} catch (mysqli_sql_exception $exception) {
				$error = $exception->getCode() === 1062 ? 'That student already has an attendance record on this date.' : 'Unable to update this record.';
			}
		}
	}
}

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$editRecord = null;
if ($editId) {
	$statement = $conn->prepare('SELECT a.id, a.attendance_date, a.time_in, a.status, u.full_name FROM attendance a JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id WHERE a.id = ?');
	$statement->bind_param('i', $editId);
	$statement->execute();
	$editRecord = $statement->get_result()->fetch_assoc();
}
$search = trim((string) ($_GET['q'] ?? ''));
$dateFilter = trim((string) ($_GET['date'] ?? ''));
$like = '%' . $search . '%';
$dateValid = $dateFilter === '' || (DateTime::createFromFormat('Y-m-d', $dateFilter) && DateTime::createFromFormat('Y-m-d', $dateFilter)->format('Y-m-d') === $dateFilter);
if (!$dateValid) {
	$dateFilter = '';
}
$statement = $conn->prepare('SELECT a.id, a.attendance_date, a.time_in, a.status, u.full_name, s.student_number FROM attendance a JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id WHERE u.full_name LIKE ? AND (? = \'\' OR a.attendance_date = ?) ORDER BY a.attendance_date DESC, u.full_name');
$statement->bind_param('sss', $like, $dateFilter, $dateFilter);
$statement->execute();
$records = $statement->get_result();

render_header('Attendance', 'attendance');
?>
<div class="section-heading first-heading"><div><span class="eyebrow">ATTENDANCE LOG</span><h2>Attendance records</h2><p class="muted-copy">Review and manage student attendance history.</p></div><span class="count-chip"><?= $records->num_rows ?> records</span></div>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($editRecord): ?><section class="panel-card edit-record-card"><div class="panel-heading"><div><h3>Edit attendance</h3><p><?= e($editRecord['full_name']) ?></p></div></div><form method="post" action="<?= e(app_url('admin/attendance.php')) ?>" class="filter-form edit-record-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $editRecord['id'] ?>"><div><label class="form-label">Date</label><input class="form-control" type="date" name="attendance_date" required value="<?= e($editRecord['attendance_date']) ?>"></div><div><label class="form-label">Time in</label><input class="form-control" type="time" name="time_in" value="<?= $editRecord['time_in'] ? e(substr($editRecord['time_in'], 0, 5)) : '' ?>"></div><div><label class="form-label">Status</label><select class="form-select" name="status"><option <?= $editRecord['status'] === 'Present' ? 'selected' : '' ?>>Present</option><option <?= $editRecord['status'] === 'Absent' ? 'selected' : '' ?>>Absent</option></select></div><button class="btn btn-primary" type="submit">Save record</button><a class="btn btn-light" href="<?= e(app_url('admin/attendance.php')) ?>">Cancel</a></form></section><?php endif; ?>
<section class="panel-card attendance-list-card"><form class="filter-form" method="get" action="<?= e(app_url('admin/attendance.php')) ?>"><div class="filter-search"><label class="form-label" for="search">Student name</label><input id="search" class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search student name"></div><div><label class="form-label" for="date">Attendance date</label><input id="date" class="form-control" type="date" name="date" value="<?= e($dateFilter) ?>"></div><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-light" href="<?= e(app_url('admin/attendance.php')) ?>">Clear</a></form>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Student</th><th>Student ID</th><th>Date</th><th>Time in</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php if ($records->num_rows === 0): ?><tr><td class="empty-state" colspan="6">No attendance records match these filters.</td></tr><?php else: while ($record = $records->fetch_assoc()): ?>
<tr><td><strong><?= e($record['full_name']) ?></strong></td><td><?= e($record['student_number']) ?></td><td><?= e(date('M j, Y', strtotime($record['attendance_date']))) ?></td><td><?= $record['time_in'] ? e(date('g:i A', strtotime($record['time_in']))) : '—' ?></td><td><span class="status-pill <?= strtolower($record['status']) ?>"><?= e($record['status']) ?></span></td><td class="text-end action-cell"><a class="icon-action" href="<?= e(app_url('admin/attendance.php?edit=' . (int) $record['id'])) ?>">Edit</a><form method="post" action="<?= e(app_url('admin/attendance.php')) ?>" onsubmit="return confirm('Delete this attendance record?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $record['id'] ?>"><button class="icon-action danger-action" type="submit">Delete</button></form></td></tr>
<?php endwhile; endif; ?></tbody></table></div></section>
<?php render_footer(); ?>
