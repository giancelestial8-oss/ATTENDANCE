<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';

function app_url(string $path = ''): string
{
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (preg_match('~/(admin|student|students)$~', $directory)) {
        $directory = dirname($directory);
    }
    $base = rtrim($directory, '/');
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function require_role(string $role): void
{
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== $role) {
        header('Location: ' . app_url('login.php'));
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page, and try again.');
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function render_header(string $title, string $active = ''): void
{
    $user = $_SESSION['full_name'] ?? 'Guest';
    $role = $_SESSION['role'] ?? '';
    $isAdmin = $role === 'admin';
    $dashboard = $isAdmin ? 'admin/dashboard.php' : 'student/dashboard.php';
    $links = $isAdmin
        ? [['dashboard', 'Overview', 'admin/dashboard.php'], ['students', 'Students', 'admin/students.php'], ['attendance', 'Attendance', 'admin/attendance.php']]
        : [['dashboard', 'My dashboard', 'student/dashboard.php'], ['attendance', 'Record attendance', 'student/attendance.php']];
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · Attendly</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="<?= e(app_url('assets/css/style.css')) ?>" rel="stylesheet">
    </head>
    <body>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="<?= e(app_url($dashboard)) ?>"><span class="brand-mark">A</span><span>attendly<small>STUDENT PORTAL</small></span></a>
            <div class="nav-caption">WORKSPACE</div>
            <nav class="side-nav">
                <?php foreach ($links as [$key, $label, $href]): ?>
                    <a class="side-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(app_url($href)) ?>">
                        <span class="nav-dot"></span><?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-bottom"><span class="avatar"><?= e(strtoupper(substr($user, 0, 1))) ?></span><div class="profile-label"><strong><?= e($user) ?></strong><small><?= e(ucfirst($role)) ?> account</small></div><a class="signout" href="<?= e(app_url('logout.php')) ?>" title="Sign out" aria-label="Sign out">↗</a></div>
        </aside>
        <main class="main-content">
            <header class="topbar"><div><span class="eyebrow"><?= $isAdmin ? 'ADMINISTRATION' : 'STUDENT PORTAL' ?></span><h1><?= e($title) ?></h1></div><div class="topbar-date"><?= date('l, F j, Y') ?></div></header>
            <section class="page-content">
                <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert"><?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php
}

function render_footer(): void
{
    ?>
            </section>
            <footer class="page-footer">Attendly <span>·</span> A simple way to stay on track.</footer>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}