# Original submission materials

These files are the earlier Secure Research Data Management System material that was
supplied for this project. They are kept unchanged for provenance and comparison with the
current implementation in the repository root.

| File | What it is |
| --- | --- |
| `Secure_Research_Data_Management_System_Documentation.docx` | First text-only report (short, no screenshots) |
| `Secure_Research_Data_Management_System_Project.docx` | Illustrated project document containing the design mock-ups and diagrams reused in Chapter 2 |
| `Secure_RDMS_System_Implementation.docx` / `.pdf` | Implementation report with early UI mock-ups and partial code listings |
| `CHAPTER_THREE_SYSTEM_IMPLEMENTATION.md` | Markdown draft of the implementation chapter |
| `secure_rdms_project-original-sqlite.zip` | Original source bundle (PHP 8 + SQLite/PDO) |

## Relationship to the current system

- The current code in the repository root is the **MySQL/MariaDB conversion** of the ZIP
  above. All SQLite queries, `datetime('now')` expressions and `AUTOINCREMENT` definitions
  were replaced with MySQL equivalents, and `config/database.php` now creates the
  `secure_rdms` database and its InnoDB tables.
- The final, template-compliant documentation is
  `docs/Secure_RDMS_System_Documentation.docx` (63 pages). It supersedes the earlier
  reports here, which still refer to SQLite and contain mock-up screenshots rather than
  live application captures.
- The design images in `docs/screenshots/design/` were extracted from
  `Secure_Research_Data_Management_System_Project.docx`.

The earlier documents are not maintained and may contain statements that no longer match
the code (for example the database engine). Treat the repository root and
`docs/Secure_RDMS_System_Documentation.docx` as the authoritative version.
