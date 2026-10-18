# JMRIS — verification notes

Checks performed in the preparation environment:

- All PHP files pass `php -l` (PHP 8.4 command-line interpreter).
- `php tests/check.php` passes seven module metadata and safe-query binding checks.
- Built-in PHP HTTP server returned HTTP 200 for the login page and styles, and redirected unauthenticated records access to sign in (HTTP 303).
- Source and schema were reviewed for table/field correspondence.

**Not tested here:** actual MySQL/MariaDB integration or end-to-end CRUD/report functions, because this environment has no MySQL service or PDO MySQL driver installed. Before using real records, run the XAMPP installation steps and complete the end-to-end workflow described in README.md, including role-permission checks and backup restore drills.
