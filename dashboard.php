<?php require_once __DIR__ . "/includes/functions.php";
requireLogin();
pageStart("Dashboard");
notice();
$admin = $_SESSION["role"] === "admin";
$files = (int) $pdo->query("SELECT COUNT(*) FROM research_data")->fetchColumn();
$users = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$alerts = (int) $pdo->query("SELECT COUNT(*) FROM security_alerts WHERE resolved=0")->fetchColumn();
$events = (int) $pdo
    ->query("SELECT COUNT(*) FROM audit_logs WHERE date(created_at)=CURDATE()")
    ->fetchColumn();
?>
<div class="page-heading"><div><p class="eyebrow">OVERVIEW</p><h1>Dashboard</h1><p class="muted">Welcome, <?= h(
    $_SESSION["full_name"],
) ?>. Here is your secure workspace.</p></div><span class="role-chip"><?= h(
    strtoupper($_SESSION["role"]),
) ?></span></div>
<div class="stats-grid"><div class="stat-card"><span>Research files</span><strong><?= $files ?></strong><small>Encrypted records</small></div><?php if (
    $admin
): ?><div class="stat-card"><span>Active users</span><strong><?= $users ?></strong><small>Accounts enabled</small></div><div class="stat-card"><span>Today's events</span><strong><?= $events ?></strong><small>Audit records</small></div><div class="stat-card"><span>Open alerts</span><strong><?= $alerts ?></strong><small>Need review</small></div><?php endif; ?></div>
<div class="panel"><div class="panel-head"><div><h2>Recent research files</h2><p class="muted">Files visible to your account.</p></div><a class="btn secondary" href="files.php">Open file manager</a></div><div class="table-wrap"><table><thead><tr><th>Title</th><th>Project</th><th>Uploaded by</th><th>Date</th><th>Status</th></tr></thead><tbody><?php
$q = in_array($_SESSION["role"], ["admin", "viewer"], true)
    ? $pdo->query(
        "SELECT r.*,u.username FROM research_data r JOIN users u ON u.id=r.uploaded_by ORDER BY r.id DESC LIMIT 6",
    )
    : $pdo->prepare(
        "SELECT r.*,u.username FROM research_data r JOIN users u ON u.id=r.uploaded_by WHERE r.uploaded_by=? ORDER BY r.id DESC LIMIT 6",
    );
if (!in_array($_SESSION["role"], ["admin", "viewer"], true)) {
    $q->execute([$_SESSION["user_id"]]);
}
$rows = $q->fetchAll();
if (!$rows) {
    echo '<tr><td colspan="5" class="empty">No research files yet. Upload a sample document to begin.</td></tr>';
}
foreach ($rows as $r) {
    echo "<tr><td>" .
        h($r["title"]) .
        "</td><td>" .
        h($r["project_code"] ?: "—") .
        "</td><td>" .
        h($r["username"]) .
        "</td><td>" .
        h($r["created_at"]) .
        '</td><td><span class="status">Encrypted</span></td></tr>';
}
?></tbody></table></div></div>
<?php if (
    $admin
): ?><div class="panel"><div class="panel-head"><div><h2>Recent audit activity</h2><p class="muted">Latest recorded security events.</p></div><a href="audit.php">View audit logs →</a></div><?php foreach (
    $pdo->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 4")
    as $e
) {
    echo '<div class="event-row"><span class="event-dot"></span><div><strong>' .
        h($e["action"]) .
        "</strong><p>" .
        h($e["details"]) .
        "</p></div><time>" .
        h($e["created_at"]) .
        "</time></div>";
} ?></div><?php endif; ?>
<?php pageEnd(); ?>
