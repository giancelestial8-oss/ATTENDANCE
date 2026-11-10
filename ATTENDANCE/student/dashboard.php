<?php
require_once __DIR__ . '/../config/auth.php';
require_role('student');

$userId = (int) $_SESSION['user_id'];
$statement = $conn->prepare('SELECT s.id, s.student_number, s.course, s.year_level, s.section, u.full_name, u.email FROM students s JOIN users u ON u.id = s.user_id WHERE s.user_id = ?');
$statement->bind_param('i', $userId);
$statement->execute();
$student = $statement->get_result()->fetch_assoc();
if (!$student) {
    render_header('My dashboard', 'dashboard');
    echo '<div class="alert alert-warning">Your student profile is not set up yet. Please contact the school administrator.</div>';
    render_footer();
    exit;
}
$studentId = (int) $student['id'];
$summary = $conn->prepare("SELECT COUNT(*) AS total, SUM(status = 'Present') AS present FROM attendance WHERE student_id = ?");
$summary->bind_param('i', $studentId);
$summary->execute();
$attendanceSummary = $summary->get_result()->fetch_assoc();
$recordCount = (int) $attendanceSummary['total'];
$presentCount = (int) ($attendanceSummary['present'] ?? 0);
$presentRate = $recordCount > 0 ? (int) round($presentCount * 100 / $recordCount) : 0;
$today = date('Y-m-d');
$check = $conn->prepare('SELECT status, time_in FROM attendance WHERE student_id = ? AND attendance_date = ?');
$check->bind_param('is', $studentId, $today);
$check->execute();
$todayRecord = $check->get_result()->fetch_assoc();
$history = $conn->prepare('SELECT attendance_date, time_in, status FROM attendance WHERE student_id = ? ORDER BY attendance_date DESC LIMIT 8');
$history->bind_param('i', $studentId);
$history->execute();
$records = $history->get_result();

render_header('My dashboard', 'dashboard');
?>
<div class="welcome-row"><div><h2>Welcome back, <?= e(explode(' ', trim($student['full_name']))[0]) ?> <span>✦</span></h2><p>Here’s your attendance at a glance.</p></div><a class="btn btn-primary" href="<?= e(app_url('student/attendance.php')) ?>">Check in today <span>→</span></a></div>
<div class="student-hero"><div class="student-hero-copy"><span class="visual-kicker">YOUR STUDENT PROFILE</span><h2><?= e($student['full_name']) ?></h2><p><?= e($student['course']) ?> <span>·</span> <?= e($student['year_level']) ?> <span>·</span> Section <?= e($student['section']) ?></p><div class="student-meta"><span>Student ID <strong><?= e($student['student_number']) ?></strong></span><span>Email <strong><?= e($student['email']) ?></strong></span></div></div><div class="hero-orbit"><span><?= e(strtoupper(substr($student['full_name'], 0, 1))) ?></span></div></div>
<div class="stats-grid student-stats"><article class="stat-card"><span class="stat-icon blue">▦</span><span class="stat-label">Recorded days</span><strong><?= $recordCount ?></strong><small>Attendance records</small></article><article class="stat-card"><span class="stat-icon green">✓</span><span class="stat-label">Present days</span><strong><?= $presentCount ?></strong><small>Days marked present</small></article><article class="stat-card"><span class="stat-icon violet">↗</span><span class="stat-label">Attendance rate</span><strong><?= $presentRate ?>%</strong><small>Based on recorded days</small></article></div>
<div class="section-heading"><div><span class="eyebrow">YOUR LATEST ACTIVITY</span><h2>Attendance history</h2></div><a class="text-link" href="<?= e(app_url('student/attendance.php')) ?>">Record attendance <span>→</span></a></div>
<div class="table-card"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date</th><th>Time in</th><th>Status</th></tr></thead><tbody><?php if ($records->num_rows === 0): ?><tr><td class="empty-state" colspan="3">Your attendance history will show here after your first check-in.</td></tr><?php else: while ($record = $records->fetch_assoc()): ?><tr><td><strong><?= e(date('l, M j, Y', strtotime($record['attendance_date']))) ?></strong></td><td><?= $record['time_in'] ? e(date('g:i A', strtotime($record['time_in']))) : '—' ?></td><td><span class="status-pill <?= strtolower($record['status']) ?>"><?= e($record['status']) ?></span></td></tr><?php endwhile; endif; ?></tbody></table></div></div>
<?php render_footer(); ?>