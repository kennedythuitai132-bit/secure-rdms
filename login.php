<?php
require_once __DIR__ . "/includes/functions.php";
if (!empty($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit();
}
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrf();
    if (($_POST["step"] ?? "credentials") === "credentials") {
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";
        $s = $pdo->prepare("SELECT * FROM users WHERE username=? AND is_active=1");
        $s->execute([$username]);
        $user = $s->fetch();
        $a = $pdo->prepare("INSERT INTO login_attempts(username,success) VALUES(?,?)");
        $a->execute([
            $username,
            $user && password_verify($password, $user["password_hash"]) ? 1 : 0,
        ]);
        $fail = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE username=? AND success=0 AND attempted_at >= (NOW() - INTERVAL 15 MINUTE)",
        );
        $fail->execute([$username]);
        $failCount = (int) $fail->fetchColumn();
        if ($user && password_verify($password, $user["password_hash"]) && $failCount < 5) {
            $code = (string) random_int(100000, 999999);
            $ins = $pdo->prepare(
                "INSERT INTO mfa_codes(user_id,code_hash,expires_at) VALUES(?,?,(NOW() + INTERVAL 5 MINUTE))",
            );
            $ins->execute([$user["id"], password_hash($code, PASSWORD_DEFAULT)]);
            $_SESSION["mfa_user"] = $user["id"];
            $_SESSION["mfa_code_demo"] = $code;
            logAudit(
                "CREDENTIALS_ACCEPTED",
                "Password verified; MFA issued",
                "success",
                $user["id"],
                $username,
            );
            header("Location: login.php?mfa=1");
            exit();
        }
        logAudit(
            "LOGIN_FAILED",
            "Invalid credentials or temporary lockout",
            "failed",
            null,
            $username,
        );
        if ($failCount >= 5) {
            createSecurityAlert(
                $user["id"] ?? null,
                "BRUTE_FORCE",
                "Five or more failed login attempts for " . $username,
                "high",
            );
        }
        $error = "Invalid credentials or too many failed attempts.";
    } else {
        $id = (int) ($_SESSION["mfa_user"] ?? 0);
        $code = trim($_POST["code"] ?? "");
        $s = $pdo->prepare(
            "SELECT * FROM mfa_codes WHERE user_id=? AND used=0 AND expires_at>NOW() ORDER BY id DESC",
        );
        $s->execute([$id]);
        $row = $s->fetch();
        if ($row && password_verify($code, $row["code_hash"])) {
            $pdo->prepare("UPDATE mfa_codes SET used=1 WHERE id=?")->execute([$row["id"]]);
            $s = $pdo->prepare("SELECT * FROM users WHERE id=? AND is_active=1");
            $s->execute([$id]);
            $u = $s->fetch();
            if ($u) {
                session_regenerate_id(true);
                $_SESSION["user_id"] = $u["id"];
                $_SESSION["username"] = $u["username"];
                $_SESSION["full_name"] = $u["full_name"];
                $_SESSION["role"] = $u["role"];
                unset($_SESSION["mfa_user"], $_SESSION["mfa_code_demo"]);
                $pdo->prepare("UPDATE users SET last_login=CURRENT_TIMESTAMP WHERE id=?")->execute([
                    $u["id"],
                ]);
                logAudit("LOGIN_SUCCESS", "Password and MFA verified");
                header("Location: dashboard.php");
                exit();
            }
        }
        logAudit("MFA_FAILED", "Invalid or expired MFA code", "failed", $id ?: null);
        createSecurityAlert($id ?: null, "MFA_FAILURE", "Invalid or expired MFA code", "medium");
        $error = "The code is invalid or has expired. Start again.";
        unset($_SESSION["mfa_user"], $_SESSION["mfa_code_demo"]);
    }
}
pageStart("Sign in");
?>
<section class="auth-wrap"><div class="auth-card"><div class="brand-mark">SR</div><p class="eyebrow">UNIVERSITY RESEARCH SECURITY</p><h1><?= isset(
    $_GET["mfa"],
)
    ? "Verify your identity"
    : "Welcome back" ?></h1><p class="muted"><?= isset($_GET["mfa"])
    ? "Enter the six-digit code to complete sign-in."
    : "Sign in to access protected research records." ?></p>
<?php if ($error): ?><div class="alert danger"><?= h($error) ?></div><?php endif; ?>
<?php if (
    isset($_GET["mfa"]) &&
    !empty($_SESSION["mfa_user"])
): ?><form method="post"><input type="hidden" name="csrf" value="<?= h(
    csrfToken(),
) ?>"><input type="hidden" name="step" value="mfa"><label for="code">6-digit verification code</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus><button class="btn primary full">Verify and continue</button></form><div class="demo-code">Demo OTP: <strong><?= h(
    $_SESSION["mfa_code_demo"] ?? "",
) ?></strong><small>Displayed only for local demonstration; expires in 5 minutes.</small></div><?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?= h(
    csrfToken(),
) ?>"><input type="hidden" name="step" value="credentials"><label for="username">Username</label><input id="username" name="username" autocomplete="username" required autofocus><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required><button class="btn primary full">Continue to MFA <span>→</span></button></form><div class="demo-accounts"><strong>Demo accounts</strong><p>admin / Admin@123</p><p>researcher1 / Researcher@123</p><p>viewer1 / Viewer@123</p></div><?php endif; ?></div></section>
<?php pageEnd(); ?>
