# Secure Research Data Management System (Secure RDMS)

A PHP 8 + MySQL web application that protects sensitive university research data with
multi-factor authentication, role-based access control, encrypted file storage, audit
logging, security alerts and security reporting.

This repository contains the complete, runnable source code, the MySQL schema, the
system documentation and every screenshot used in that documentation.

**Published at:** <https://github.com/kennedythuitai132-bit/secure-rdms>

---

## 1. Features

| Area | What the system does |
| --- | --- |
| Authentication | Password verification with `password_hash()` / `password_verify()` plus a six-digit, five-minute one-time MFA code |
| Brute-force defence | Failed attempts are recorded; five failures within 15 minutes block further attempts and raise a high-severity alert |
| Role-based access control | `admin`, `researcher` and `viewer` roles enforced on every protected page and on every file download |
| File protection | Uploads are encrypted with AES-256-CBC before being written to disk; decryption happens only after authorisation checks |
| Audit logging | Logins, MFA events, uploads, downloads, user changes and denials are written to `audit_logs` with user, IP address, user agent and status |
| Security alerts | Brute-force and MFA-failure events create reviewable alerts with severity levels |
| Reporting | Aggregated login, upload, download, MFA-failure and open-alert metrics, most-active users and activity by action |
| Web hardening | CSRF tokens on every POST form, output escaping, session cookie settings, upload size/type checks |

## 2. Technology stack

- **PHP 8.0+** (tested on PHP 8.0.30) with `pdo_mysql` and `openssl` extensions
- **MySQL 8.0+ / MariaDB 10.4+** (tested on MariaDB 10.4.32)
- **HTML5, CSS3** (responsive interface, no front-end framework)
- **Apache** via XAMPP for local development, or PHP's built-in server
- **PDO** with prepared statements and exceptions

## 3. Repository structure

```
secure-rdms/
├── index.php                  # Entry point: redirects to dashboard or login
├── login.php                  # Credentials + MFA challenge
├── logout.php                 # Session destruction + audit event
├── dashboard.php              # Role-aware overview
├── files.php                  # Encrypted upload, listing, authorised download
├── audit.php                  # Audit log viewer with filters (admin)
├── alerts.php                 # Security alert centre (admin)
├── reports.php                # Security metrics and reports (admin)
├── users.php                  # User creation and activation (admin)
├── config/
│   └── database.php           # MySQL connection, schema creation, demo seed
├── includes/
│   └── functions.php          # Sessions, CSRF, RBAC, encryption, audit helpers, layout
├── assets/
│   └── css/
│       └── style.css          # Application styling
├── database/
│   └── schema.sql             # Standalone MySQL schema + demo accounts
├── uploads/                   # Encrypted files (contents excluded from git)
├── docs/
    ├── Secure_RDMS_System_Documentation.docx
    └── screenshots/
        ├── ui/                # Live implementation screenshots (Chapter 3)
        ├── design/            # Design mock-ups and diagrams (Chapter 2)
        └── code/              # Syntax-highlighted source-code screenshots
└── original-materials/        # Earlier submission material kept for provenance
    ├── Secure_Research_Data_Management_System_Documentation.docx
    ├── Secure_Research_Data_Management_System_Project.docx
    ├── Secure_RDMS_System_Implementation.docx / .pdf
    ├── CHAPTER_THREE_SYSTEM_IMPLEMENTATION.md
    └── secure_rdms_project-original-sqlite.zip
```

## 4. Prerequisites

- PHP 8.0 or newer with `pdo_mysql` and `openssl` enabled
- MySQL 8.0+ or MariaDB 10.4+ running on `127.0.0.1:3306`
- A MySQL account that can create databases (the default `root` account in XAMPP works)

Verify the PHP extensions:

```bash
php -m | findstr "pdo_mysql openssl"
```

## 5. Setup

### Option A - XAMPP (recommended for Windows)

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Copy this folder into `C:\xampp\htdocs\secure-rdms`.
3. Import the schema (optional - the app also creates it on first request):
   - Open <http://localhost/phpmyadmin>, choose **Import**, select `database/schema.sql`, or
   - run `C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql`
4. Open <http://localhost/secure-rdms/>.

### Option B - PHP built-in server

```bash
cd secure-rdms
php -S 127.0.0.1:8080
```

Then open <http://127.0.0.1:8080>.

On first request `config/database.php` creates the `secure_rdms` database, the six
tables and three demonstration accounts if the `users` table is empty.

## 6. Configuration

Configuration is read from environment variables (see `.env.example`):

| Variable | Default | Purpose |
| --- | --- | --- |
| `RDMS_DB_HOST` | `127.0.0.1` | Database host |
| `RDMS_DB_PORT` | `3306` | Database port |
| `RDMS_DB_NAME` | `secure_rdms` | Database name |
| `RDMS_DB_USER` | `root` | Database user |
| `RDMS_DB_PASS` | *(empty)* | Database password |
| `RDMS_ENCRYPTION_KEY` | demo fallback | Key material for AES-256-CBC file encryption |

Set them in the shell before starting the server:

```powershell
$env:RDMS_DB_USER  = "root"
$env:RDMS_DB_PASS  = "your-password"
$env:RDMS_ENCRYPTION_KEY = "a-long-random-secret"
php -S 127.0.0.1:8080
```

With Apache, use `SetEnv` in the virtual-host configuration. The application calls
`getenv()`, so a `.env` file is not parsed automatically - `.env.example` documents the
names only.

## 7. Demonstration accounts

| Role | Username | Password | Can do |
| --- | --- | --- | --- |
| Administrator | `admin` | `Admin@123` | Everything: users, audit logs, alerts, reports, files |
| Researcher | `researcher1` | `Researcher@123` | Upload, view and download permitted files |
| Viewer | `viewer1` | `Viewer@123` | View and download permitted files only |

After the password step, the MFA code is displayed on the verification page because this
is a local demonstrator. It expires after five minutes and can be used once.

> Change or remove these accounts and set a strong `RDMS_ENCRYPTION_KEY` before any
> shared or production deployment.

## 8. Documentation

- **Full system documentation:** [`docs/Secure_RDMS_System_Documentation.docx`](docs/Secure_RDMS_System_Documentation.docx)
  - Cover, declaration, dedication, abstract, key terms, abbreviations
  - Table of contents, list of figures, list of tables
  - Chapter 1: problem statement, justification, objectives, requirements, tools, schedule, ethics
  - Chapter 2: interface models, flowcharts, data flow diagrams, ERD, use case and class diagrams
  - Chapter 3: implementation screenshots, complete source code as 33 numbered code screenshots, testing, deployment
  - Chapter 4: conclusion and recommendations, references, appendix
- **UI screenshots:** `docs/screenshots/ui/` (captured from the running system)
- **Design diagrams:** `docs/screenshots/design/`
- **Code screenshots:** `docs/screenshots/code/` (13 files, 33 figures, 50 lines per figure)
- **Earlier submission material:** `original-materials/` (previous reports and the original SQLite source bundle)

## 9. Testing summary

| Test | Expected result | Result |
| --- | --- | --- |
| Valid credentials + valid MFA | Dashboard opens, `LOGIN_SUCCESS` recorded | Pass |
| Wrong password | Error shown, `LOGIN_FAILED` recorded | Pass |
| Wrong MFA code | Error shown, `MFA_FAILURE` alert created | Pass |
| Five failed logins | High-severity `BRUTE_FORCE` alert, login blocked | Pass |
| Researcher opening `users.php` | 403/redirect and denial logged | Pass |
| Upload | File stored as ciphertext, `FILE_UPLOAD` recorded | Pass |
| Download by unauthorised user | Denied before decryption | Pass |
| Alert resolution | Alert marked resolved, `ALERT_RESOLVED` recorded | Pass |

## 10. Security limitations

This is an academic demonstrator. Before production use:

- Replace the on-screen MFA code with a TOTP authenticator app or hardware token.
- Use authenticated encryption (AES-GCM) and a managed key service instead of AES-CBC
  with a key derived from an environment variable.
- Enforce HTTPS, `Secure` + `HttpOnly` + `SameSite` cookies and HSTS.
- Move the encryption key and database credentials out of the web root.
- Add centralised logging/SIEM shipping, retention rules and independent penetration testing.
- Apply least-privilege database credentials instead of `root`.

## 11. Repository and updates

The project is published at
<https://github.com/kennedythuitai132-bit/secure-rdms>. Clone it with:

```bash
git clone https://github.com/kennedythuitai132-bit/secure-rdms.git
```

To publish later changes:

```bash
git add -A
git commit -m "Describe the change"
git push origin main
```

If Git asks for a password, use a **personal access token** (GitHub → Settings →
Developer settings → Personal access tokens) as the password, not your account password.

## 12. Author

**Kennedy Mwangi** - Reg. No. DCF-01-0107/2026
Diploma in Information Technology, Zetech University
System documentation submitted in partial fulfilment of the award, August 2026.
