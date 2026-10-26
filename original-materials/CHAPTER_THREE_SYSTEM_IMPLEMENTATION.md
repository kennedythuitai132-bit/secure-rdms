5# CHAPTER THREE: SYSTEM IMPLEMENTATION

## 3.1 Introduction

This chapter presents the implementation of the **Secure Research Data Management System (RDMS)** developed for protecting sensitive university research information. The implementation transforms the designs and models produced in Chapter Two into a fully functional web application. The system was built using **PHP**, **HTML**, **CSS**, **JavaScript** and **SQLite** (via PDO), following the project tools and resources defined in Chapter One.

The chapter covers:
- User Interface Development (frontend screens)
- Logic / Backend Development (authentication, MFA, RBAC, encryption, audit logging)
- System Testing
- Deployment notes

All security objectives stated in the Specific Objectives were implemented:
1. Secure storage, management and protection of research data through authentication, access control, encryption and audit logging.
2. User authentication combined with Role-Based Access Control (RBAC).
3. Generation of security audit reports, detection of suspicious login attempts and recording of file-access events.

---

## .2 User Interface Development

### 3.2.1 Login Page Development

The login page implements a two-step authentication flow:

1. Username + Password validation.
2. Multi-Factor Authentication (MFA) using a time-limited 6-digit One-Time Password (OTP).

**Key frontend features**
- Clean, centred card design with university-appropriate colour scheme (deep navy / blue).
- Clear error messages for invalid credentials or failed MFA.
- Demo accounts displayed for evaluation purposes.
- In production the OTP would be delivered by email or SMS; for demonstration the code is shown on-screen after successful credential verification.

**Figure 3.2.1 – Login page (credentials step)**

```html
<!-- Excerpt from login.php -->
<form method="POST" action="">
    <input type="hidden" name="step" value="credentials">
    <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>
    </div>
    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-primary">Continue →</button>
</form>
```

**Figure 3.2.2 – MFA challenge step**

After credentials are accepted the user is presented with an MFA input box. A 6-digit code is generated, stored with a 5-minute expiry, and (in demo mode) displayed to the user.

---

### 3.2.2 Dashboard

The dashboard is role-aware:

- **Administrators** see total research files, active users, today’s activity count and open security alerts, plus recent audit events.
- **Researchers / Viewers** see a personalised welcome and the research files they are authorised to access.

**Figure 3.2.3 – Dashboard (administrator view)**

Statistics cards, recent files table and recent audit activity are rendered dynamically from the database.

---

### 3.2.3 Navigation Bar

A persistent top navigation bar provides quick access to:

| Link            | Visible to          |
|-----------------|---------------------|
| Dashboard       | All authenticated   |
| Research Files  | All authenticated   |
| Audit Logs      | Admin only          |
| Security Alerts | Admin only          |
| Reports         | Admin only          |
| Users           | Admin only          |
| Logout          | All                 |

The user’s full name and role badge are displayed on the right-hand side.

---

### 3.2.4 Research Files Interface

Researchers and administrators can upload new research documents. Every uploaded file is immediately encrypted with **AES-256-CBC** before being stored on disk. The original filename, project code, description and metadata are kept in the database.

**Figure 3.2.4 – File upload form (encrypted)**

```html
<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="upload">
    <div class="form-group">
        <label>Title *</label>
        <input type="text" name="title" required>
    </div>
    <div class="form-group">
        <label>Project Code</label>
        <input type="text" name="project_code">
    </div>
    <div class="form-group">
        <label>Description</label>
        <textarea name="description" rows="3"></textarea>
    </div>
    <div class="form-group">
        <label>File * (max 10 MB)</label>
        <input type="file" name="research_file" required>
    </div>
    <button type="submit" class="btn btn-primary">Encrypt & Upload</button>
</form>
```

A table lists all files the current user is authorised to see, with a Download button that triggers on-the-fly decryption.

---

### 3.2.5 Audit Logging Interface

Administrators can view a chronological, filterable list of every significant system event (logins, MFA failures, uploads, downloads, unauthorised access attempts, user management actions). Filters by action keyword and status (success / failed) are provided.

---

### 3.2.6 Security Alerts Interface

Suspicious events (failed logins, MFA failures, brute-force indications) automatically generate security alerts. Administrators can mark alerts as resolved. Severity levels (high / medium / low) are colour-coded.

---

### 3.2.7 Security Reports Interface

A summary dashboard shows:
- Successful vs failed logins
- File upload / download counts
- MFA failure count
- Open alerts
- Most active users
- Breakdown of all audited actions

This directly satisfies Specific Objective III.

---

### 3.2.8 User Management (RBAC)

Administrators can:
- Create new users and assign one of three roles: **admin**, **researcher**, **viewer**
- Activate / deactivate accounts
- View last-login timestamps

Role permissions are enforced on every protected page.

---

## 3.3 Logic Development (Backend)

### 3.3.1 Database Schema

Five main tables were created:

```sql
-- users (RBAC)
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('admin','researcher','viewer')),
    mfa_secret TEXT,
    mfa_enabled INTEGER DEFAULT 1,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
);

-- research_data (encrypted files)
CREATE TABLE research_data (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    filename TEXT NOT NULL,
    original_filename TEXT NOT NULL,
    file_path TEXT NOT NULL,
    file_size INTEGER,
    mime_type TEXT,
    encryption_key_hash TEXT,
    uploaded_by INTEGER NOT NULL,
    project_code TEXT,
    is_encrypted INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- audit_logs
CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    username TEXT,
    action TEXT NOT NULL,
    details TEXT,
    ip_address TEXT,
    user_agent TEXT,
    status TEXT DEFAULT 'success',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- mfa_codes (temporary OTPs)
CREATE TABLE mfa_codes (...);

-- security_alerts
CREATE TABLE security_alerts (...);
```

Default seeded accounts:
- `admin` / `Admin@123`
- `researcher1` / `Researcher@123`
- `viewer1` / `Viewer@123`

---

### 3.3.2 Authentication & Multi-Factor Authentication

```php
// Password verification (login.php)
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    // Issue MFA challenge
    $code = generateMFACode($user['id']);   // 6-digit, 5-min expiry
    $_SESSION['mfa_pending'] = true;
    // ...
}

// MFA verification
function verifyMFACode($userId, $code) {
    $stmt = $pdo->prepare(
        "SELECT id FROM mfa_codes 
         WHERE user_id = ? AND code = ? AND used = 0 
           AND expires_at > datetime('now')"
    );
    $stmt->execute([$userId, $code]);
    // mark used and return true/false
}
```

Failed login attempts are counted; after five failures within 15 minutes a high-severity security alert is raised and further attempts are blocked.

---

### 3.3.3 Role-Based Access Control

```php
function requireRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], (array)$roles)) {
        logAudit('UNAUTHORIZED_ACCESS', 'Attempted access to restricted page', 'failed');
        header('Location: dashboard.php?error=access_denied');
        exit;
    }
}

// Usage examples
requireRole('admin');                 // audit, alerts, reports, users
requireRole(['admin', 'researcher']); // file upload
```

Viewers may download files but cannot upload. Researchers may upload and manage their own files. Only administrators see the full audit trail and user management screens.

---

### 3.3.4 Data Encryption

Every research file is encrypted with **AES-256-CBC** before storage:

```php
function encryptFile($sourcePath, $destPath, $key) {
    $data = file_get_contents($sourcePath);
    $iv   = random_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    file_put_contents($destPath, $iv . $encrypted);   // IV prepended
    return true;
}

function decryptFile($sourcePath, $key) {
    $data = file_get_contents($sourcePath);
    $iv   = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
}
```

A unique random key is generated per upload. In a production environment the key would be stored in a Hardware Security Module (HSM) or Key Management Service; for the academic demonstration the key is held in the user session after upload so that the same session can download and decrypt the file.

---

### 3.3.5 Audit Logging

Every significant action is recorded:

```php
function logAudit($action, $details = '', $status = 'success') {
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs 
         (user_id, username, action, details, ip_address, user_agent, status) 
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $_SESSION['username'] ?? 'guest',
        $action, $details,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        $status
    ]);
}
```

Typical actions logged: `LOGIN_SUCCESS`, `LOGIN_FAILED`, `MFA_FAILED`, `FILE_UPLOAD`, `FILE_DOWNLOAD`, `UNAUTHORIZED_ACCESS`, `USER_CREATED`, `ALERT_RESOLVED`, etc.

---

### 3.3.6 Security Alerts

```php
function createSecurityAlert($userId, $type, $message, $severity = 'medium') {
    $stmt = $pdo->prepare(
        "INSERT INTO security_alerts (user_id, alert_type, message, severity) 
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $type, $message, $severity]);
}
```

Alerts are automatically created for failed logins, MFA failures and potential brute-force activity.

---

## 3.4 Testing

| Test Case                        | Action Performed                                      | Expected Result                          | Actual Result / Correction          |
|----------------------------------|-------------------------------------------------------|------------------------------------------|-------------------------------------|
| Valid login + MFA                | admin / Admin@123 + correct OTP                       | Redirect to dashboard                    | Pass                                |
| Invalid password                 | Wrong password                                        | Error message + audit log + alert        | Pass                                |
| Invalid / expired MFA            | Wrong 6-digit code                                    | Error + MFA_FAILED audit entry           | Pass                                |
| Brute-force protection           | ≥5 failed logins in 15 min                            | High-severity alert + temporary block    | Pass                                |
| File upload (researcher)         | Upload .txt file                                      | File encrypted on disk, audit entry      | Pass                                |
| File download                    | Click Download after upload                           | Decrypted original file returned         | Pass                                |
| Unauthorised page access         | Researcher tries /audit.php                           | Redirect + UNAUTHORIZED_ACCESS log       | Pass                                |
| Role-based file visibility       | Researcher sees only own files; Admin sees all        | Correct filtering                        | Pass                                |
| User creation (admin)            | Create new researcher                                 | User appears in list, can log in         | Pass                                |
| Deactivate user                  | Toggle active status                                  | User cannot log in                       | Pass                                |
| Security report generation       | Open Reports page                                     | Correct counts of logins, uploads, etc.  | Pass                                |
| Audit log filtering              | Filter by action / status                             | Filtered rows displayed                  | Pass                                |

All functional requirements mapped from the Specific Objectives were verified successfully.

---

## 3.5 Deployment

The system is designed to run on any environment that supports PHP 8+ with the OpenSSL and PDO_SQLite (or PDO_MySQL) extensions.

**Local development / demonstration**

```bash
cd secure_rdms
php -S localhost:8000
```

Open `http://localhost:8000` in a browser.

**Production recommendations**
- Place the application behind HTTPS.
- Move encryption keys to a proper Key Management Service.
- Replace the on-screen MFA demo with email/SMS delivery (e.g. PHPMailer + Twilio).
- Switch SQLite to MySQL/MariaDB for concurrent multi-user load.
- Configure proper file-system permissions on the `uploads/` directory.
- Enable PHP session security settings (`session.cookie_secure`, `session.cookie_httponly`, etc.).

---

## 3.6 System File Structure

```
secure_rdms/
├── index.php                 # Redirects to login
├── login.php                 # Two-step login + MFA
├── logout.php
├── dashboard.php
├── files.php                 # Upload / list / download (encrypted)
├── audit.php                 # Audit log viewer (admin)
├── alerts.php                # Security alerts (admin)
├── reports.php               # Security & access reports (admin)
├── users.php                 # User & role management (admin)
├── config/
│   └── database.php          # PDO connection + schema + seed data
├── includes/
│   ├── functions.php         # Auth, encryption, audit helpers
│   ├── header.php
│   └── footer.php
├── assets/
│   └── css/
│       └── style.css
├── uploads/                  # Encrypted research files (.enc)
└── README.md
```

---

## Summary of Implemented Security Controls

| Control                    | Implementation                                      | Objective Covered |
|----------------------------|-----------------------------------------------------|-------------------|
| Authentication             | Username + password (bcrypt)                        | SO-i, SO-ii       |
| Multi-Factor Authentication| Time-limited 6-digit OTP                            | SO-i, SO-ii       |
| Role-Based Access Control  | admin / researcher / viewer with page-level checks  | SO-ii             |
| Data Encryption            | AES-256-CBC on every research file                  | SO-i              |
| Audit Logging              | Every login, file event, privilege use recorded     | SO-iii            |
| Suspicious activity detection | Failed-login counters + automatic security alerts | SO-iii            |
| Security Reports           | Aggregated login, upload, download & alert metrics  | SO-iii            |

The implemented system fully realises the three Specific Objectives defined in Chapter One and provides a practical, working demonstration of a Secure Research Data Management System suitable for a university research environment.
