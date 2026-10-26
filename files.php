<?php require_once __DIR__ . "/includes/functions.php";
requireLogin();
$msg = "";
$err = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrf();
    if (($_POST["action"] ?? "") === "upload") {
        if (!in_array($_SESSION["role"], ["admin", "researcher"], true)) {
            http_response_code(403);
            exit("Your role cannot upload files.");
        }
        $f = $_FILES["research_file"] ?? null;
        $title = trim($_POST["title"] ?? "");
        if (!$title || !$f || $f["error"] !== UPLOAD_ERR_OK || $f["size"] > 10 * 1024 * 1024) {
            $err = "Enter a title and select a file smaller than 10 MB.";
        } else {
            $dir = __DIR__ . "/uploads";
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            $stored = bin2hex(random_bytes(16)) . ".enc";
            try {
                encryptFile($f["tmp_name"], $dir . "/" . $stored);
                $s = $pdo->prepare(
                    "INSERT INTO research_data(title,description,filename,original_filename,file_path,file_size,mime_type,uploaded_by,project_code) VALUES(?,?,?,?,?,?,?,?,?)",
                );
                $s->execute([
                    $title,
                    trim($_POST["description"] ?? ""),
                    $stored,
                    basename($f["name"]),
                    $dir . "/" . $stored,
                    (int) $f["size"],
                    $f["type"] ?? "application/octet-stream",
                    $_SESSION["user_id"],
                    trim($_POST["project_code"] ?? ""),
                ]);
                logAudit("FILE_UPLOAD", "Encrypted upload: " . $title);
                header(
                    "Location: files.php?message=" .
                        urlencode("File encrypted and uploaded successfully."),
                );
                exit();
            } catch (Throwable $e) {
                $err = "Upload failed. Check the uploads folder permissions.";
            }
        }
    }
}
if (isset($_GET["download"])) {
    $s = in_array($_SESSION["role"], ["admin", "viewer"], true)
        ? $pdo->prepare("SELECT * FROM research_data WHERE id=?")
        : $pdo->prepare("SELECT * FROM research_data WHERE id=? AND uploaded_by=?");
    in_array($_SESSION["role"], ["admin", "viewer"], true)
        ? $s->execute([(int) $_GET["download"]])
        : $s->execute([(int) $_GET["download"], $_SESSION["user_id"]]);
    $r = $s->fetch();
    if (!$r) {
        logAudit("UNAUTHORIZED_ACCESS", "Attempted download of unavailable file", "failed");
        http_response_code(404);
        exit("File not found or access denied.");
    }
    $plain = decryptFile($r["file_path"]);
    if ($plain === false) {
        exit("Unable to decrypt file.");
    }
    logAudit("FILE_DOWNLOAD", "Decrypted download: " . $r["title"]);
    header("Content-Type: application/octet-stream");
    header(
        'Content-Disposition: attachment; filename="' .
            str_replace('"', "", basename($r["original_filename"])) .
            '"',
    );
    header("Content-Length: " . strlen($plain));
    echo $plain;
    exit();
}
pageStart("Research files");
notice();
?><div class="page-heading"><div><p class="eyebrow">DATA REPOSITORY</p><h1>Research files</h1><p class="muted">Uploads are encrypted before being written to disk.</p></div></div><?php if (
    $err
): ?><div class="alert danger"><?= h($err) ?></div><?php endif; ?>
<?php if (
    in_array($_SESSION["role"], ["admin", "researcher"], true)
): ?><div class="panel"><h2>Upload a research document</h2><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf" value="<?= h(
    csrfToken(),
) ?>"><input type="hidden" name="action" value="upload"><div><label>Document title *</label><input name="title" required></div><div><label>Project code</label><input name="project_code" placeholder="e.g. RES-2026-01"></div><div class="span-2"><label>Description</label><textarea name="description" rows="2"></textarea></div><div class="span-2"><label>Choose file (maximum 10 MB) *</label><input type="file" name="research_file" required></div><div class="span-2"><button class="btn primary">Encrypt and upload</button></div></form></div><?php endif; ?>
<div class="panel"><h2>Available documents</h2><div class="table-wrap"><table><thead><tr><th>Title</th><th>Original filename</th><th>Project</th><th>Uploaded</th><th>Action</th></tr></thead><tbody><?php
$s = in_array($_SESSION["role"], ["admin", "viewer"], true)
    ? $pdo->query("SELECT * FROM research_data ORDER BY id DESC")
    : $pdo->prepare("SELECT * FROM research_data WHERE uploaded_by=? ORDER BY id DESC");
if (!in_array($_SESSION["role"], ["admin", "viewer"], true)) {
    $s->execute([$_SESSION["user_id"]]);
}
$rows = $s->fetchAll();
if (!$rows) {
    echo '<tr><td colspan="5" class="empty">No files available.</td></tr>';
}
foreach ($rows as $r) {
    echo "<tr><td>" .
        h($r["title"]) .
        "</td><td>" .
        h($r["original_filename"]) .
        "</td><td>" .
        h($r["project_code"] ?: "—") .
        "</td><td>" .
        h($r["created_at"]) .
        '</td><td><a class="btn small secondary" href="files.php?download=' .
        (int) $r["id"] .
        '">Download</a></td></tr>';
}
?></tbody></table></div></div><?php pageEnd(); ?>
