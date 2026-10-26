<?php require_once __DIR__ . "/includes/functions.php";
requireRole("admin");
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrf();
    $id = (int) ($_POST["id"] ?? 0);
    $pdo->prepare("UPDATE security_alerts SET resolved=1 WHERE id=?")->execute([$id]);
    logAudit("ALERT_RESOLVED", "Resolved alert #" . $id);
    header("Location: alerts.php?message=" . urlencode("Alert marked as resolved."));
    exit();
}
pageStart("Security alerts");
notice();
?><div class="page-heading"><div><p class="eyebrow">THREAT MONITORING</p><h1>Security alerts</h1><p class="muted">Review failed authentication and suspicious activity.</p></div></div><div class="panel"><div class="table-wrap"><table><thead><tr><th>Time</th><th>Type</th><th>Message</th><th>Severity</th><th>Status</th><th>Action</th></tr></thead><tbody><?php
$rows = $pdo->query("SELECT * FROM security_alerts ORDER BY resolved ASC,id DESC")->fetchAll();
if (!$rows) {
    echo '<tr><td colspan="6" class="empty">No security alerts have been generated.</td></tr>';
}
foreach ($rows as $r) {
    echo "<tr><td>" .
        h($r["created_at"]) .
        "</td><td>" .
        h($r["alert_type"]) .
        "</td><td>" .
        h($r["message"]) .
        '</td><td><span class="severity ' .
        h($r["severity"]) .
        '">' .
        h($r["severity"]) .
        "</span></td><td>" .
        ($r["resolved"] ? "Resolved" : "<strong>Open</strong>") .
        "</td><td>";
    if (!$r["resolved"]) {
        echo '<form method="post"><input type="hidden" name="csrf" value="' .
            h(csrfToken()) .
            '"><input type="hidden" name="id" value="' .
            (int) $r["id"] .
            '"><button class="btn small secondary">Resolve</button></form>';
    }
    echo "</td></tr>";
}
?></tbody></table></div></div><?php pageEnd(); ?>
