<?php
require_once __DIR__ . '/../config/auth.php';
require_role('admin');

$totalStudents = (int) $conn->query('SELECT COUNT(*) AS total FROM students')->fetch_assoc()['total'];
$today = date('Y-m-d');
$statement = $conn->prepare("SELECT COUNT(*) AS total FROM attendance WHERE attendance_date = ? AND status = 'Present'");
$statement->bind_param('s', $today);
$statement->execute();
$presentToday = (int) $statement->get_result()->fetch_assoc()['total'];
$absentToday = max(0, $totalStudents - $presentToday);
$recent = $conn->query("SELECT a.attendance_date, a.time_in, a.status, u.full_name, s.student_number
	FROM attendance a JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id
	ORDER BY a.attendance_date DESC, a.time_in DESC, a.id DESC LIMIT 7");

render_header('Overview', 'dashboard');
?>
<div class="welcome-row"><div><h2>Good day, <?= e(explode(' ', trim($_SESSION['full_name']))[0]) ?> <span>✦</span></h2><p>Here’s what’s happening with attendance today.</p></div><a class="btn btn-primary" href="<?= e(app_url('admin/students.php')) ?>">＋ Add student</a></div>
<div class="stats-grid">
	<article class="stat-card"><span class="stat-icon blue">♙</span><span class="stat-label">Total students</span><strong><?= $totalStudents ?></strong><small>Active student profiles</small></article>
	<article class="stat-card"><span class="stat-icon green">✓</span><span class="stat-label">Present today</span><strong><?= $presentToday ?></strong><small><?= $totalStudents > 0 ? e(number_format($presentToday * 100 / $totalStudents, 0)) . '% of students' : 'No students enrolled yet' ?></small></article>
	<article class="stat-card"><span class="stat-icon orange">◷</span><span class="stat-label">Not checked in</span><strong><?= $absentToday ?></strong><small>Absent or not yet recorded</small></article>
</div>
<div class="section-heading"><div><span class="eyebrow">LATEST UPDATES</span><h2>Recent attendance</h2></div><a class="text-link" href="<?= e(app_url('admin/attendance.php')) ?>">View all records <span>→</span></a></div>
<div class="table-card"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Student</th><th>Student ID</th><th>Date</th><th>Time in</th><th>Status</th></tr></thead><tbody>
<?php if ($recent->num_rows === 0): ?><tr><td class="empty-state" colspan="5">No attendance records yet. Records will appear here when students check in.</td></tr><?php else: while ($row = $recent->fetch_assoc()): ?>
<tr><td><strong><?= e($row['full_name']) ?></strong></td><td><?= e($row['student_number']) ?></td><td><?= e(date('M j, Y', strtotime($row['attendance_date']))) ?></td><td><?= $row['time_in'] ? e(date('g:i A', strtotime($row['time_in']))) : '—' ?></td><td><span class="status-pill <?= strtolower($row['status']) ?>"><?= e($row['status']) ?></span></td></tr>
<?php endwhile; endif; ?></tbody></table></div></div>
<?php render_footer(); ?>
