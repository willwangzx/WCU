<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/admin.php';

start_admin_session();

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));
$loginError = '';
$configError = admin_credentials_configured()
    ? ''
    : 'Admin access is not configured yet. Add admin.username and admin.password_hash to server/config.php.';

if ($requestMethod === 'POST' && $action === 'login') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $loginError = 'Your session expired. Please try signing in again.';
    } elseif ($configError !== '') {
        $loginError = $configError;
    } elseif (attempt_admin_login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        header('Location: ' . admin_url());
        exit;
    } else {
        $loginError = 'Incorrect username or password.';
    }
}

if ($requestMethod === 'POST' && $action === 'logout' && is_admin_authenticated()) {
    if (validate_csrf_token($_POST['csrf_token'] ?? null)) {
        logout_admin();
    }

    header('Location: ' . admin_url());
    exit;
}

if ($requestMethod === 'POST' && $action === 'delete') {
    if (!is_admin_authenticated()) {
        header('Location: ' . admin_url());
        exit;
    }

    $filters = normalize_admin_filters($_POST);
    $query = array_filter($filters, static fn (string $value): bool => $value !== '');
    $applicationId = (int) ($_POST['application_id'] ?? 0);

    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        set_admin_flash('error', 'Delete request expired. Please try again.');
    } elseif ($applicationId < 1) {
        set_admin_flash('error', 'Invalid application selected for deletion.');
    } elseif (delete_application(get_database_connection(), $applicationId)) {
        set_admin_flash('success', 'Application #' . $applicationId . ' was deleted.');
    } else {
        set_admin_flash('error', 'Application #' . $applicationId . ' could not be deleted.');
    }

    header('Location: ' . admin_url($query));
    exit;
}

if ($action === 'export') {
    if (!is_admin_authenticated()) {
        header('Location: ' . admin_url());
        exit;
    }

    $filters = normalize_admin_filters($_GET);
    stream_applications_csv(get_database_connection(), $filters);
}

$csrfToken = generate_csrf_token();
$filters = normalize_admin_filters($_GET);
$flash = consume_admin_flash();

if (!is_admin_authenticated()) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admissions Admin Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #07111f;
      --card: rgba(10, 22, 39, 0.82);
      --line: rgba(148, 163, 184, 0.26);
      --text: #e5eefb;
      --muted: #9db0c8;
      --accent: #c89b3c;
      --danger: #ffb4b4;
      --shadow: 0 24px 80px rgba(0, 0, 0, 0.34);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      color: var(--text);
      font-family: "Inter", "Segoe UI", sans-serif;
      background:
        radial-gradient(circle at top left, rgba(31, 93, 255, 0.18), transparent 34%),
        radial-gradient(circle at right center, rgba(200, 155, 60, 0.14), transparent 28%),
        linear-gradient(135deg, #08101c, #10233d 58%, #162a47);
      display: grid;
      place-items: center;
      padding: 28px;
    }
    .shell {
      width: min(460px, 100%);
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 28px;
      box-shadow: var(--shadow);
      padding: 34px;
      backdrop-filter: blur(20px);
    }
    .eyebrow {
      margin: 0 0 10px;
      font-size: 0.78rem;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--accent);
    }
    h1 {
      margin: 0 0 12px;
      font-family: "Playfair Display", Georgia, serif;
      font-size: clamp(2rem, 6vw, 2.8rem);
      line-height: 1.05;
    }
    p {
      margin: 0;
      color: var(--muted);
      line-height: 1.7;
    }
    form {
      margin-top: 28px;
      display: grid;
      gap: 16px;
    }
    label {
      display: grid;
      gap: 8px;
      font-size: 0.92rem;
      color: #dce8f9;
    }
    input {
      width: 100%;
      padding: 14px 16px;
      border-radius: 14px;
      border: 1px solid rgba(148, 163, 184, 0.32);
      background: rgba(5, 13, 24, 0.82);
      color: var(--text);
      font: inherit;
    }
    button {
      margin-top: 8px;
      border: 0;
      border-radius: 999px;
      padding: 14px 18px;
      font: inherit;
      font-weight: 700;
      color: #08101c;
      background: linear-gradient(135deg, #e0bb67, #c7952e);
      cursor: pointer;
      box-shadow: 0 14px 36px rgba(200, 155, 60, 0.34);
    }
    .notice {
      margin-top: 18px;
      padding: 14px 16px;
      border-radius: 14px;
      border: 1px solid rgba(255, 180, 180, 0.24);
      background: rgba(94, 24, 24, 0.28);
      color: var(--danger);
    }
    .hint {
      margin-top: 16px;
      font-size: 0.88rem;
      color: #8ca1bb;
    }
  </style>
</head>
<body>
  <main class="shell">
    <p class="eyebrow">WCU Admissions</p>
    <h1>Admin dashboard</h1>
    <p>Review applications, open full statements, and export submissions without touching the database directly.</p>
    <?php if ($loginError !== ''): ?>
      <div class="notice"><?php echo escape_html($loginError); ?></div>
    <?php elseif ($configError !== ''): ?>
      <div class="notice"><?php echo escape_html($configError); ?></div>
    <?php endif; ?>
    <form method="post" action="<?php echo escape_html(admin_url()); ?>">
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrfToken); ?>">
      <label>
        Username
        <input type="text" name="username" autocomplete="username" required>
      </label>
      <label>
        Password
        <input type="password" name="password" autocomplete="current-password" required>
      </label>
      <button type="submit">Sign in</button>
    </form>
    <p class="hint">This page uses server-side sessions and a hashed password stored in <code>server/config.php</code>.</p>
  </main>
</body>
</html>
    <?php
    exit;
}

$pdo = get_database_connection();
$applications = fetch_applications($pdo, $filters);
$totalMatching = count_applications($pdo, $filters);

$selectedId = (int) ($_GET['application'] ?? 0);
$selectedApplication = null;

if ($selectedId > 0) {
    $selectedApplication = fetch_application_by_id($pdo, $selectedId);
}

if ($selectedApplication === null && $applications !== []) {
    $selectedApplication = fetch_application_by_id($pdo, (int) $applications[0]['id']);
    $selectedId = (int) ($selectedApplication['id'] ?? 0);
}

$latestSubmission = $applications[0]['created_at'] ?? null;
$activeFilters = array_filter($filters, static fn (string $value): bool => $value !== '');

function filter_query_with_application(array $filters, int $applicationId): array
{
    $query = array_filter($filters, static fn (string $value): bool => $value !== '');
    $query['application'] = $applicationId;
    return $query;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WCU Admissions Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --surface: #ffffff;
      --ink: #10233d;
      --muted: #58708f;
      --line: #d5dfec;
      --accent: #184fdc;
      --gold: #b98a2c;
      --danger: #b42318;
      --danger-soft: rgba(180, 35, 24, 0.08);
      --success: #0a7a48;
      --success-soft: rgba(10, 122, 72, 0.08);
      --shadow: 0 20px 45px rgba(11, 31, 58, 0.08);
      --radius: 22px;
      --max: 1440px;
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body {
      min-height: 100vh;
      font-family: "Inter", "Segoe UI", sans-serif;
      color: var(--ink);
      background:
        radial-gradient(circle at top left, rgba(24, 79, 220, 0.09), transparent 26%),
        linear-gradient(180deg, #f8fbff 0%, #f3f6fb 48%, #eef3f9 100%);
    }
    a { color: inherit; text-decoration: none; }
    .topbar {
      position: sticky;
      top: 0;
      z-index: 10;
      border-bottom: 1px solid rgba(213, 223, 236, 0.82);
      background: rgba(248, 251, 255, 0.92);
      backdrop-filter: blur(16px);
    }
    .topbar-inner,
    .page {
      width: min(var(--max), calc(100vw - 40px));
      margin: 0 auto;
    }
    .topbar-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      padding: 18px 0;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .brand-mark {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background: linear-gradient(135deg, #173154, #23487a);
      color: #fff;
      font-family: "Playfair Display", Georgia, serif;
      font-size: 1.2rem;
      box-shadow: var(--shadow);
    }
    .brand h1 { margin: 0; font-size: 1.08rem; font-weight: 700; }
    .brand p { margin: 2px 0 0; font-size: 0.88rem; color: var(--muted); }
    .topbar-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; justify-content: flex-end; }
    .pill, .button, .ghost-button { border-radius: 999px; font: inherit; }
    .pill {
      padding: 10px 14px;
      background: var(--surface);
      border: 1px solid var(--line);
      color: var(--muted);
    }
    .button, .ghost-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 0;
      cursor: pointer;
      padding: 11px 16px;
      font-weight: 700;
    }
    .button {
      background: linear-gradient(135deg, #204bca, #133aa7);
      color: #fff;
      box-shadow: 0 14px 34px rgba(24, 79, 220, 0.2);
    }
    .danger-button {
      background: linear-gradient(135deg, #ca2b20, #991b1b);
      color: #fff;
      box-shadow: 0 14px 34px rgba(180, 35, 24, 0.22);
    }
    .ghost-button {
      background: transparent;
      border: 1px solid var(--line);
      color: var(--ink);
    }
    .page { padding: 28px 0 36px; display: grid; gap: 20px; }
    .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
    .stat, .panel {
      background: rgba(255, 255, 255, 0.9);
      border: 1px solid rgba(213, 223, 236, 0.82);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
    }
    .stat { padding: 20px 22px; }
    .stat-label {
      margin: 0 0 10px;
      font-size: 0.82rem;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      color: var(--muted);
    }
    .stat-value { margin: 0; font-size: 1.8rem; font-weight: 700; }
    .layout {
      display: grid;
      grid-template-columns: minmax(320px, 420px) minmax(0, 1fr);
      gap: 20px;
      align-items: start;
    }
    .panel { overflow: hidden; }
    .panel-header {
      padding: 18px 20px;
      border-bottom: 1px solid var(--line);
      background: linear-gradient(180deg, rgba(238, 244, 255, 0.82), rgba(255, 255, 255, 0.95));
    }
    .panel-header h2 { margin: 0; font-size: 1.12rem; }
    .panel-header p { margin: 6px 0 0; color: var(--muted); font-size: 0.92rem; }
    .filters {
      padding: 18px 20px;
      display: grid;
      gap: 12px;
      border-bottom: 1px solid var(--line);
      background: rgba(243, 246, 251, 0.64);
    }
    .filters-grid {
      display: grid;
      grid-template-columns: 1.4fr 1fr 1fr;
      gap: 12px;
    }
    label { display: grid; gap: 8px; font-size: 0.9rem; color: var(--muted); }
    input, select {
      width: 100%;
      padding: 12px 14px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: #fff;
      color: var(--ink);
      font: inherit;
    }
    .filter-actions, .detail-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .list { max-height: calc(100vh - 320px); overflow: auto; }
    .list-empty, .empty-state { padding: 28px 20px; color: var(--muted); }
    .item {
      display: block;
      padding: 18px 20px;
      border-top: 1px solid rgba(213, 223, 236, 0.72);
      transition: background-color 0.18s ease;
    }
    .item:hover { background: rgba(24, 79, 220, 0.04); }
    .item.active {
      background: linear-gradient(135deg, rgba(24, 79, 220, 0.08), rgba(24, 79, 220, 0.02));
      box-shadow: inset 4px 0 0 var(--accent);
    }
    .item-title { margin: 0; font-size: 1rem; font-weight: 700; }
    .item-meta, .detail-meta { margin: 8px 0 0; display: grid; gap: 4px; font-size: 0.9rem; color: var(--muted); }
    .detail-shell { padding: 22px; display: grid; gap: 20px; }
    .detail-head { display: flex; align-items: start; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
    .detail-head h2 { margin: 0; font-size: clamp(1.6rem, 3vw, 2.2rem); }
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(185, 138, 44, 0.12);
      color: var(--gold);
      font-weight: 700;
    }
    .detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .detail-card {
      padding: 16px 18px;
      border: 1px solid var(--line);
      border-radius: 18px;
      background: var(--surface);
    }
    .detail-card strong {
      display: block;
      margin-bottom: 6px;
      font-size: 0.86rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--muted);
    }
    .detail-card p {
      margin: 0;
      line-height: 1.75;
      white-space: pre-wrap;
      word-break: break-word;
    }
    .flash {
      padding: 16px 18px;
      border-radius: 18px;
      border: 1px solid var(--line);
      font-weight: 600;
      box-shadow: var(--shadow);
    }
    .flash.success {
      color: var(--success);
      background: var(--success-soft);
      border-color: rgba(10, 122, 72, 0.18);
    }
    .flash.error {
      color: var(--danger);
      background: var(--danger-soft);
      border-color: rgba(180, 35, 24, 0.18);
    }
    code { font-family: "SFMono-Regular", Consolas, monospace; font-size: 0.92em; }
    @media (max-width: 1080px) {
      .stats, .layout, .filters-grid, .detail-grid { grid-template-columns: 1fr; }
      .list { max-height: none; }
    }
    @media (max-width: 640px) {
      .topbar-inner, .page { width: min(var(--max), calc(100vw - 24px)); }
      .panel-header, .filters, .detail-shell, .stat { padding-left: 16px; padding-right: 16px; }
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="topbar-inner">
      <div class="brand">
        <div class="brand-mark">W</div>
        <div>
          <h1>WCU Admissions Admin</h1>
          <p>Secure review workspace for submitted applications</p>
        </div>
      </div>
      <div class="topbar-actions">
        <div class="pill">Signed in as <?php echo escape_html((string) ($_SESSION['admin_username'] ?? 'admin')); ?></div>
        <form method="post" action="<?php echo escape_html(admin_url()); ?>">
          <input type="hidden" name="action" value="logout">
          <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrfToken); ?>">
          <button class="ghost-button" type="submit">Sign out</button>
        </form>
      </div>
    </div>
  </header>

  <main class="page">
    <?php if (is_array($flash)): ?>
      <div class="flash <?php echo escape_html((string) ($flash['type'] ?? 'success')); ?>">
        <?php echo escape_html((string) ($flash['message'] ?? '')); ?>
      </div>
    <?php endif; ?>

    <section class="stats">
      <article class="stat">
        <p class="stat-label">Matching applications</p>
        <p class="stat-value"><?php echo escape_html((string) $totalMatching); ?></p>
      </article>
      <article class="stat">
        <p class="stat-label">Visible in dashboard</p>
        <p class="stat-value"><?php echo escape_html((string) count($applications)); ?></p>
      </article>
      <article class="stat">
        <p class="stat-label">Latest submission</p>
        <p class="stat-value" style="font-size:1.15rem;"><?php echo escape_html(format_datetime($latestSubmission)); ?></p>
      </article>
    </section>

    <section class="layout">
      <aside class="panel">
        <div class="panel-header">
          <h2>Applications</h2>
          <p>Search by applicant name, email, or phone. Filters also apply to CSV export.</p>
        </div>

        <form class="filters" method="get" action="<?php echo escape_html(admin_url()); ?>">
          <div class="filters-grid">
            <label>
              Search
              <input type="text" name="q" value="<?php echo escape_html($filters['q']); ?>" placeholder="Name, email, or phone">
            </label>
            <label>
              Entry term
              <select name="entry_term">
                <option value="">All terms</option>
                <?php foreach (get_valid_terms() as $term): ?>
                  <option value="<?php echo escape_html($term); ?>"<?php echo $filters['entry_term'] === $term ? ' selected' : ''; ?>><?php echo escape_html($term); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Program
              <select name="program">
                <option value="">All programs</option>
                <?php foreach (get_valid_programs() as $program): ?>
                  <option value="<?php echo escape_html($program); ?>"<?php echo $filters['program'] === $program ? ' selected' : ''; ?>><?php echo escape_html($program); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="filter-actions">
            <button class="button" type="submit">Apply filters</button>
            <a class="ghost-button" href="<?php echo escape_html(admin_url()); ?>">Reset</a>
            <a class="ghost-button" href="<?php echo escape_html(admin_url(array_merge(array_filter($filters, static fn (string $value): bool => $value !== ''), ['action' => 'export']))); ?>">Export CSV</a>
          </div>
        </form>

        <div class="list">
          <?php if ($applications === []): ?>
            <div class="list-empty">No applications match the current filters.</div>
          <?php else: ?>
            <?php foreach ($applications as $application): ?>
              <?php $applicationId = (int) $application['id']; ?>
              <a class="item<?php echo $selectedId === $applicationId ? ' active' : ''; ?>" href="<?php echo escape_html(admin_url(filter_query_with_application($filters, $applicationId))); ?>">
                <p class="item-title"><?php echo escape_html($application['first_name'] . ' ' . $application['last_name']); ?></p>
                <div class="item-meta">
                  <span><?php echo escape_html($application['email']); ?></span>
                  <span><?php echo escape_html($application['program']); ?></span>
                  <span><?php echo escape_html($application['entry_term']); ?> · <?php echo escape_html(format_datetime($application['created_at'])); ?></span>
                </div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </aside>

      <section class="panel">
        <div class="panel-header">
          <h2>Application detail</h2>
          <p><?php echo $activeFilters === [] ? 'Showing the latest application in the current dashboard view.' : 'Filters are active for this session.'; ?></p>
        </div>

        <?php if ($selectedApplication === null): ?>
          <div class="empty-state">Pick an application from the list to open the full submission.</div>
        <?php else: ?>
          <div class="detail-shell">
            <div class="detail-head">
              <div>
                <div class="badge">Application #<?php echo escape_html((string) $selectedApplication['id']); ?></div>
                <h2><?php echo escape_html($selectedApplication['first_name'] . ' ' . $selectedApplication['last_name']); ?></h2>
                <p class="detail-meta"><?php echo escape_html($selectedApplication['entry_term']); ?> · <?php echo escape_html($selectedApplication['program']); ?> · Submitted <?php echo escape_html(format_datetime($selectedApplication['created_at'])); ?></p>
              </div>
              <div class="detail-actions">
                <a class="button" href="mailto:<?php echo escape_html($selectedApplication['email']); ?>">Email applicant</a>
                <a class="ghost-button" href="<?php echo escape_html($selectedApplication['portfolio_url']); ?>" target="_blank" rel="noreferrer">Open portfolio</a>
                <form method="post" action="<?php echo escape_html(admin_url()); ?>" onsubmit="return confirm('Delete application #<?php echo escape_html((string) $selectedApplication['id']); ?>? This cannot be undone.');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrfToken); ?>">
                  <input type="hidden" name="application_id" value="<?php echo escape_html((string) $selectedApplication['id']); ?>">
                  <input type="hidden" name="q" value="<?php echo escape_html($filters['q']); ?>">
                  <input type="hidden" name="entry_term" value="<?php echo escape_html($filters['entry_term']); ?>">
                  <input type="hidden" name="program" value="<?php echo escape_html($filters['program']); ?>">
                  <button class="button danger-button" type="submit">Delete application</button>
                </form>
              </div>
            </div>

            <div class="detail-grid">
              <article class="detail-card">
                <strong>Contact</strong>
                <p><?php echo escape_html($selectedApplication['email']); ?><br><?php echo escape_html($selectedApplication['phone']); ?></p>
              </article>
              <article class="detail-card">
                <strong>Citizenship</strong>
                <p><?php echo escape_html($selectedApplication['citizenship']); ?></p>
              </article>
              <article class="detail-card">
                <strong>Date of birth</strong>
                <p><?php echo escape_html($selectedApplication['birth_month'] . ' ' . $selectedApplication['birth_day'] . ', ' . $selectedApplication['birth_year']); ?></p>
              </article>
              <article class="detail-card">
                <strong>Gender</strong>
                <p><?php echo escape_html($selectedApplication['gender']); ?></p>
              </article>
              <article class="detail-card">
                <strong>Current or most recent school</strong>
                <p><?php echo escape_html($selectedApplication['school_name']); ?></p>
              </article>
              <article class="detail-card">
                <strong>Origin metadata</strong>
                <p>IP: <?php echo escape_html($selectedApplication['ip_address'] ?: 'N/A'); ?><br>Origin: <?php echo escape_html($selectedApplication['origin_url'] ?: 'N/A'); ?></p>
              </article>
              <article class="detail-card" style="grid-column: 1 / -1;">
                <strong>Personal statement</strong>
                <p><?php echo escape_html($selectedApplication['personal_statement']); ?></p>
              </article>
              <article class="detail-card" style="grid-column: 1 / -1;">
                <strong>Additional notes</strong>
                <p><?php echo escape_html($selectedApplication['additional_notes'] ?: 'No additional notes submitted.'); ?></p>
              </article>
              <article class="detail-card" style="grid-column: 1 / -1;">
                <strong>User agent</strong>
                <p><code><?php echo escape_html($selectedApplication['user_agent'] ?: 'N/A'); ?></code></p>
              </article>
            </div>
          </div>
        <?php endif; ?>
      </section>
    </section>
  </main>
</body>
</html>
