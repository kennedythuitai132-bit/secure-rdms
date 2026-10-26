<?php require_once __DIR__ . "/includes/functions.php";
requireRole("admin");
pageStart("Security reports");
$count = function ($sql) {
    global $pdo;
    return (int) $pdo->query($sql)->fetchColumn();
};
?><div class="page-heading"><div><p class="eyebrow">SECURITY ANALYTICS</p><h1>Reports</h1><p class="muted">Summary metrics calculated from recorded system activity.</p></div></div><div class="stats-grid"><div class="stat-card"><span>Successful logins</span><strong><?= $count(
    "SELECT COUNT(*) FROM audit_logs WHERE action='LOGIN_SUCCESS'",
) ?></strong></div><div class="stat-card"><span>Failed logins</span><strong><?= $count(
    "SELECT COUNT(*) FROM audit_logs WHERE action='LOGIN_FAILED'",
) ?></strong></div><div class="stat-card"><span>File uploads</span><strong><?= $count(
    "SELECT COUNT(*) FROM audit_logs WHERE action='FILE_UPLOAD'",
) ?></strong></div><div class="stat-card"><span>File downloads</span><strong><?= $count(
    "SELECT COUNT(*) FROM audit_logs WHERE action='FILE_DOWNLOAD'",
) ?></strong></div><div class="stat-card"><span>MFA failures</span><strong><?= $count(
    "SELECT COUNT(*) FROM audit_logs WHERE action='MFA_FAILED'",
) ?></strong></div><div class="stat-card"><span>Open alerts</span><strong><?= $count(
    "SELECT COUNT(*) FROM security_alerts WHERE resolved=0",
) ?></strong></div></div><div class="panel"><h2>Most active users</h2><div class="table-wrap"><table><thead><tr><th>Username</th><th>Recorded actions</th></tr></thead><tbody><?php foreach (
    $pdo->query(
        "SELECT username,COUNT(*) AS total FROM audit_logs GROUP BY username ORDER BY total DESC LIMIT 10",
    )
    as $r
) {
    echo "<tr><td>" . h($r["username"]) . "</td><td>" . (int) $r["total"] . "</td></tr>";
} ?></tbody></table></div></div><div class="panel"><h2>Action breakdown</h2><div class="table-wrap"><table><thead><tr><th>Action</th><th>Count</th></tr></thead><tbody><?php foreach (
    $pdo->query(
        "SELECT action,COUNT(*) AS total FROM audit_logs GROUP BY action ORDER BY total DESC",
    )
    as $r
) {
    echo "<tr><td>" . h($r["action"]) . "</td><td>" . (int) $r["total"] . "</td></tr>";
} ?></tbody></table></div></div><?php pageEnd(); ?>
