<?php
require_once __DIR__ . '/../config/auth.php';
require_role('student');

$userId = (int) $_SESSION['user_id'];
$statement = $conn->prepare('SELECT s.id, s.student_number, s.course, s.year_level, s.section, u.full_name FROM students s JOIN users u ON u.id = s.user_id WHERE s.user_id = ?');
$statement->bind_param('i', $userId);
$statement->execute();
$student = $statement->get_result()->fetch_assoc();
$error = '';
$today = date('Y-m-d');
$now = date('H:i:s');
$todayRecord = null;
$history = null;

if ($student) {
    $studentId = (int) $student['id'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        try {
            $insert = $conn->prepare("INSERT INTO attendance (student_id, attendance_date, time_in, status) VALUES (?, ?, ?, 'Present')");
            $insert->bind_param('iss', $studentId, $today, $now);
            $insert->execute();
            set_flash('success', 'Attendance recorded successfully. Have a great day!');
            header('Location: ' . app_url('student/attendance.php'));
            exit;
        } catch (mysqli_sql_exception $exception) {
            if ($exception->getCode() === 1062) {
                $error = 'Your attendance has already been recorded for today.';
            } else {
                error_log('Student attendance error: ' . $exception->getMessage());
                $error = 'Unable to record attendance right now. Please try again.';
            }
        }
    }
    $check = $conn->prepare('SELECT status, time_in FROM attendance WHERE student_id = ? AND attendance_date = ?');
    $check->bind_param('is', $studentId, $today);
    $check->execute();
    $todayRecord = $check->get_result()->fetch_assoc();
    $history = $conn->prepare('SELECT attendance_date, time_in, status FROM attendance WHERE student_id = ? ORDER BY attendance_date DESC LIMIT 12');
    $history->bind_param('i', $studentId);
    $history->execute();
    $history = $history->get_result();
}

render_header('Record attendance', 'attendance');
?>
<?php if (!$student): ?><div class="alert alert-warning">Your student profile is not set up yet. Please contact the school administrator.</div><?php else: ?>
<div class="section-heading first-heading"><div><span class="eyebrow">DAILY CHECK-IN</span><h2>Attendance</h2><p class="muted-copy">Check in once each school day to record your attendance.</p></div></div>
<?php if ($error !== ''): ?><div class="alert alert-warning" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="checkin-layout"><section class="checkin-card"><div class="checkin-top"><span class="checkin-icon">✓</span><span class="live-label"><i></i> TODAY’S CHECK-IN</span></div><p class="checkin-date"><?= e(date('l, F j, Y')) ?></p><div class="checkin-clock" id="clock"><?= e(date('g:i:s A')) ?></div><h2><?= e($student['full_name']) ?></h2><p class="checkin-subtitle"><?= e($student['student_number']) ?> <span>·</span> <?= e($student['course']) ?></p>
<?php if ($todayRecord): ?><div class="checked-in"><span>✓</span><div><strong>You're checked in!</strong><small>Recorded at <?= $todayRecord['time_in'] ? e(date('g:i A', strtotime($todayRecord['time_in']))) : '—' ?> · <?= e($todayRecord['status']) ?></small></div></div><?php else: ?><form method="post" action="<?= e(app_url('student/attendance.php')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="attendance-button" type="submit"><span class="button-check">✓</span><span>RECORD MY ATTENDANCE</span><span class="button-arrow">→</span></button></form><p class="checkin-hint">Your check-in is saved with the current date and time.</p><?php endif; ?></section>
<aside class="checkin-note"><span class="note-icon">✦</span><h3>Consistency matters</h3><p>Your attendance record helps you and your school keep track of each day. You can only check in once per day.</p><div class="note-rule"></div><span class="note-label">STUDENT</span><strong><?= e($student['full_name']) ?></strong><span class="note-label">CLASS</span><strong><?= e($student['year_level']) ?> · <?= e($student['section']) ?></strong></aside></div>
<div class="section-heading history-heading"><div><span class="eyebrow">YOUR RECENT ACTIVITY</span><h2>Attendance history</h2></div></div>
<div class="table-card"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date</th><th>Time in</th><th>Status</th></tr></thead><tbody><?php if ($history->num_rows === 0): ?><tr><td colspan="3" class="empty-state">No check-ins yet. Record today's attendance above.</td></tr><?php else: while ($record = $history->fetch_assoc()): ?><tr><td><strong><?= e(date('M j, Y', strtotime($record['attendance_date']))) ?></strong></td><td><?= $record['time_in'] ? e(date('g:i A', strtotime($record['time_in']))) : '—' ?></td><td><span class="status-pill <?= strtolower($record['status']) ?>"><?= e($record['status']) ?></span></td></tr><?php endwhile; endif; ?></tbody></table></div></div>
<script>
(() => {
    const clock = document.getElementById('clock');
    if (!clock) return;
    const updateClock = () => { clock.textContent = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit', second: '2-digit' }).format(new Date()); };
    updateClock();
    window.setInterval(updateClock, 1000);
})();
</script>
<?php endif; ?>
<?php render_footer(); ?>