<?php require_once __DIR__ . "/includes/functions.php";
requireRole("admin");
$action = trim($_GET["action"] ?? "");
$status = trim($_GET["status"] ?? "");
$sql = "SELECT * FROM audit_logs WHERE 1=1";
$args = [];
if ($action !== "") {
    $sql .= " AND action LIKE ?";
    $args[] = "%" . $action . "%";
}
if (in_array($status, ["success", "failed"], true)) {
    $sql .= " AND status=?";
    $args[] = $status;
}
$sql .= " ORDER BY id DESC LIMIT 250";
$s = $pdo->prepare($sql);
$s->execute($args);
pageStart("Audit logs");
?><div class="page-heading"><div><p class="eyebrow">ACCOUNTABILITY</p><h1>Audit logs</h1><p class="muted">Chronological record of significant system actions.</p></div></div><div class="panel"><form class="filter-row"><input name="action" placeholder="Filter action keyword" value="<?= h(
    $action,
) ?>"><select name="status"><option value="">All statuses</option><option value="success" <?= $status ===
"success"
    ? "selected"
    : "" ?>>Success</option><option value="failed" <?= $status === "failed"
    ? "selected"
    : "" ?>>Failed</option></select><button class="btn primary">Filter</button></form><div class="table-wrap"><table><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th><th>Status</th></tr></thead><tbody><?php foreach (
    $s
    as $r
) {
    echo "<tr><td>" .
        h($r["created_at"]) .
        "</td><td>" .
        h($r["username"]) .
        "</td><td>" .
        h($r["action"]) .
        "</td><td>" .
        h($r["details"]) .
        "</td><td>" .
        h($r["ip_address"]) .
        '</td><td><span class="status ' .
        ($r["status"] === "failed" ? "failed" : "") .
        '">' .
        h($r["status"]) .
        "</span></td></tr>";
} ?></tbody></table></div></div><?php pageEnd(); ?>
