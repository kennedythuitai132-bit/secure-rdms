<?php require_once __DIR__ . "/includes/functions.php";
requireRole("admin");
$err = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrf();
    $action = $_POST["action"] ?? "";
    if ($action === "create") {
        $username = trim($_POST["username"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $name = trim($_POST["full_name"] ?? "");
        $role = $_POST["role"] ?? "viewer";
        $password = $_POST["password"] ?? "";
        if (
            !$username ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !$name ||
            strlen($password) < 10 ||
            !in_array($role, ["admin", "researcher", "viewer"], true)
        ) {
            $err = "Enter valid details and a password of at least 10 characters.";
        } else {
            try {
                $s = $pdo->prepare(
                    "INSERT INTO users(username,email,password_hash,full_name,role) VALUES(?,?,?,?,?)",
                );
                $s->execute([
                    $username,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    $name,
                    $role,
                ]);
                logAudit("USER_CREATED", "Created account " . $username);
                header("Location: users.php?message=" . urlencode("User account created."));
                exit();
            } catch (Throwable $e) {
                $err = "Could not create user. Username or email may already exist.";
            }
        }
    } elseif ($action === "toggle") {
        $id = (int) $_POST["id"];
        if ($id !== $_SESSION["user_id"]) {
            $pdo->prepare(
                "UPDATE users SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?",
            )->execute([$id]);
            logAudit("USER_STATUS_CHANGED", "Changed active status for user #" . $id);
        }
        header("Location: users.php");
        exit();
    }
}
pageStart("User management");
notice();
if ($err) {
    echo '<div class="alert danger">' . h($err) . "</div>";
}
?><div class="page-heading"><div><p class="eyebrow">ACCESS GOVERNANCE</p><h1>User management</h1><p class="muted">Create accounts and manage role assignments.</p></div></div><div class="panel"><h2>Create user</h2><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?= h(
    csrfToken(),
) ?>"><input type="hidden" name="action" value="create"><div><label>Full name</label><input name="full_name" required></div><div><label>Username</label><input name="username" required></div><div><label>Email</label><input type="email" name="email" required></div><div><label>Temporary password (10+ chars)</label><input type="password" name="password" minlength="10" required></div><div><label>Role</label><select name="role"><option value="researcher">Researcher</option><option value="viewer">Viewer</option><option value="admin">Administrator</option></select></div><div class="align-end"><button class="btn primary">Create account</button></div></form></div><div class="panel"><h2>Registered accounts</h2><div class="table-wrap"><table><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th>Action</th></tr></thead><tbody><?php foreach (
    $pdo->query("SELECT * FROM users ORDER BY id")
    as $r
) {
    echo "<tr><td>" .
        h($r["full_name"]) .
        "</td><td>" .
        h($r["username"]) .
        "</td><td>" .
        h($r["email"]) .
        "</td><td>" .
        h($r["role"]) .
        "</td><td>" .
        ($r["is_active"] ? "Active" : "Inactive") .
        "</td><td>" .
        h($r["last_login"] ?? "—") .
        "</td><td>";
    if ($r["id"] != $_SESSION["user_id"]) {
        echo '<form method="post"><input type="hidden" name="csrf" value="' .
            h(csrfToken()) .
            '"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' .
            (int) $r["id"] .
            '"><button class="btn small secondary">' .
            ($r["is_active"] ? "Deactivate" : "Activate") .
            "</button></form>";
    }
    echo "</td></tr>";
} ?></tbody></table></div></div><?php pageEnd(); ?>
