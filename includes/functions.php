<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name("RDMSSESSID");
    session_start();
}
require_once __DIR__ . "/../config/database.php";
function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, "UTF-8");
}
function logAudit(
    string $action,
    string $details = "",
    string $status = "success",
    ?int $userId = null,
    ?string $username = null,
): void {
    global $pdo;
    $uid = $userId ?? ($_SESSION["user_id"] ?? null);
    $uname = $username ?? ($_SESSION["username"] ?? "guest");
    $s = $pdo->prepare(
        "INSERT INTO audit_logs(user_id,username,action,details,ip_address,user_agent,status) VALUES(?,?,?,?,?,?,?)",
    );
    $s->execute([
        $uid,
        $uname,
        $action,
        $details,
        $_SERVER["REMOTE_ADDR"] ?? "local",
        substr($_SERVER["HTTP_USER_AGENT"] ?? "unknown", 0, 250),
        $status,
    ]);
}
function createSecurityAlert(
    ?int $userId,
    string $type,
    string $message,
    string $severity = "medium",
): void {
    global $pdo;
    $s = $pdo->prepare(
        "INSERT INTO security_alerts(user_id,alert_type,message,severity) VALUES(?,?,?,?)",
    );
    $s->execute([$userId, $type, $message, $severity]);
}
function requireLogin(): void
{
    if (empty($_SESSION["user_id"])) {
        header("Location: login.php");
        exit();
    }
}
function requireRole($roles): void
{
    requireLogin();
    if (!in_array($_SESSION["role"], (array) $roles, true)) {
        logAudit("UNAUTHORIZED_ACCESS", "Attempted restricted page", "failed");
        header("Location: dashboard.php?error=access_denied");
        exit();
    }
}
function csrfToken(): string
{
    if (empty($_SESSION["csrf"])) {
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf"];
}
function verifyCsrf(): void
{
    if (
        $_SERVER["REQUEST_METHOD"] === "POST" &&
        !hash_equals($_SESSION["csrf"] ?? "", $_POST["csrf"] ?? "")
    ) {
        http_response_code(400);
        exit("Invalid request token. Return and try again.");
    }
}
function encryptFile(string $source, string $dest): void
{
    $data = file_get_contents($source);
    $key = hash(
        "sha256",
        getenv("RDMS_ENCRYPTION_KEY") ?: "local-demo-key-change-before-deployment",
        true,
    );
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($data, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false || file_put_contents($dest, $iv . $cipher) === false) {
        throw new RuntimeException("Could not encrypt file.");
    }
}
function decryptFile(string $path): string|false
{
    $data = file_get_contents($path);
    if ($data === false || strlen($data) < 17) {
        return false;
    }
    $key = hash(
        "sha256",
        getenv("RDMS_ENCRYPTION_KEY") ?: "local-demo-key-change-before-deployment",
        true,
    );
    return openssl_decrypt(
        substr($data, 16),
        "AES-256-CBC",
        $key,
        OPENSSL_RAW_DATA,
        substr($data, 0, 16),
    );
}
function pageStart(string $title): void
{
    $active = basename($_SERVER["PHP_SELF"]);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' .
        h($title) .
        ' | Secure RDMS</title><link rel="stylesheet" href="assets/css/style.css"></head><body><header class="topbar"><a class="brand" href="dashboard.php">Secure <span>RDMS</span></a>';
    if (!empty($_SESSION["user_id"])) {
        echo '<nav><a href="dashboard.php">Dashboard</a><a href="files.php">Research Files</a>';
        if ($_SESSION["role"] === "admin") {
            echo '<a href="audit.php">Audit Logs</a><a href="alerts.php">Security Alerts</a><a href="reports.php">Reports</a><a href="users.php">Users</a>';
        }
        echo '</nav><div class="identity">' .
            h($_SESSION["full_name"]) .
            "<small>" .
            h(ucfirst($_SESSION["role"])) .
            '</small> <a class="logout" href="logout.php">Logout</a></div>';
    }
    echo '</header><main class="container">';
}
function pageEnd(): void
{
    echo "</main><footer>Secure Research Data Management System · Academic demonstration</footer></body></html>";
}
function notice(): void
{
    if (isset($_GET["error"])) {
        echo '<div class="alert danger">' .
            h(
                $_GET["error"] === "access_denied"
                    ? "Access denied. Your role cannot open that page."
                    : $_GET["error"],
            ) .
            "</div>";
    }
    if (isset($_GET["message"])) {
        echo '<div class="alert success">' . h($_GET["message"]) . "</div>";
    }
}
