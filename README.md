<<<<<<< HEAD
# BJMP-Final-Project
dodong final project
=======
# JMRIS — Jail Management & Records Information System

A full PHP 8.1+ / MySQL (or MariaDB) web application for XAMPP. Includes inmate, personnel, visitor and visit, incident, document and routing sheet record management; search, filtering, printable reports, CSV exports, audit history and role-based user accounts.

> **Important**: This is a functional local deployment starter, **not a security-certified correctional records platform**. It has not been audited for legal/regulatory compliance, production penetration testing, or the facility's operational policies. Use fictitious sample records until your organization has approved access control, retention, confidentiality and security procedures. Do not expose a default XAMPP installation to the public Internet.

## Requirements

- XAMPP with **Apache + MySQL/MariaDB + PHP 8.1 or newer** (PHP PDO MySQL and mbstring enabled; both are usually available in XAMPP)
- Modern browser (Chrome, Edge, Firefox)
- No Composer packages or third-party services

## Windows / XAMPP installation

1. Start Apache and MySQL using the XAMPP Control Panel.
2. Extract `JMRIS` to `C:\xampp\htdocs\JMRIS` (the folder should contain `index.php`).
3. Open `http://localhost/phpmyadmin`, select **Import**, and import `C:\xampp\htdocs\JMRIS\database\schema.sql`. It creates a database called **jmris** and the required tables. Alternatively create the database first and run the statements inside `schema.sql`.
4. Default XAMPP MySQL credentials are `root` with an empty password. Those development defaults are in `app/config.php`. If yours differ, copy `app/config.local.example.php` to `app/config.local.php` and update the credentials. Do **not** commit `config.local.php` to source control. For multiuser usage, make a dedicated MySQL account with access only to the `jmris` database.
5. **Create the first admin** by opening Command Prompt and running:

   ```bat
   cd C:\xampp\htdocs\JMRIS
   C:\xampp\php\php.exe bin\create_admin.php adminofficer@gmail.com "System Administrator"
   ```

   The capstone schema already seeds the demo administrator account `adminofficer@gmail.com` with the presentation password. Change or remove that demo account before real deployment. This step does not create a public installation endpoint.
6. Open `http://localhost/JMRIS/` and sign in with the admin credentials you created.
7. In **User Accounts**, create individual `records_officer` and `viewer` accounts as necessary. Avoid sharing admin accounts.

**macOS / Linux:** Use the corresponding XAMPP web root, run `php bin/create_admin.php admin "System Administrator"` from the project folder, then visit `/JMRIS/`.

## Navigation / workflows

| Module | What you can do |
| --- | --- |
| Dashboard | Counts, quick links, activity feed |
| Inmates | Add, edit, search, filter statuses; archive records |
| Personnel | Employee number, rank, position, contact and status |
| Visitors | Register unique visitors and contact details |
| Visitor Visits | Select registered visitor and inmate; schedule time, relationship, purpose and visit status |
| Incidents | Track incident number, inmate, type, date, description, action and status |
| Cases & Court | Track multiple cases per inmate, court, offense, status and next hearing date |
| Custody Timeline | Record admission, housing assignment, transfer and release events as a traceable custody timeline |
| Documents | Register metadata, classification, subject and date |
| Routing Sheets | Link document, set from/to units, add timestamped routing events |
| Reports & Print | Choose module; filter status, dates and free text; print or download CSV |
| Global Search | Search across supported modules, respecting document permissions |
| Recent Activity | User sign-ins, changes and routing events (latest 200) |
| User Accounts | Admin-created users, roles, activation/deactivation, password change |

### Roles

- `admin`: all records, account administration and archiving.
- `records_officer`: read, add and edit records; cannot archive or manage accounts; may see `Public`, `Internal` and `Confidential` document metadata.
- `viewer`: read, search and report only; may see `Public` and `Internal` document metadata.
- `Restricted` document metadata is accessible only to `admin`. Linked routing records follow the document's classification.

### Deletion / retention

"Delete" is implemented as **archive** for admins; records are soft-deleted (`archived_at`) so linked visit / incident / routing history remains available in the database. Do not use SQL hard deletes to bypass your organization's retention requirements. Restore and archival searches should be subject to a formal administrator procedure.

### Reports

In Reports & Print: choose a report, enter an optional search term, status and date range, press **Apply**, then **Print report** (browser print dialog, can save PDF) or **Export CSV**. CSV exports are limited to 25,000 matching rows per request for resource safety; narrow the filters for larger datasets. CSV formula-like content is escaped.

### Database overview

- `users`: login, hashed passwords, roles, active status and lockouts
- `inmates`: inmate master file and status
- `personnel`: employee master records
- `visitors`: visitor identities
- `visits`: scheduled/completed visits referencing `visitors` and `inmates`
- `incidents`: incidents referencing `inmates`
- `documents`: document metadata and classification
- `routing_sheets`: routing records referencing `documents`
- `routing_history`: append-only routing activity referencing `routing_sheets` and the actor
- `activity_logs`: actor, module, event, timestamp and IP address

## Security provisions and known limitations

Implemented: password hashing, per-account permissions, server-side validation, prepared PDO queries, CSRF protection, HTML escaping, secure session flags (HTTPS-only Secure flag when accessed via HTTPS), randomized session ID on login, lockouts after five bad attempts, protected app/database/CLI directories through Apache `.htaccess`, classification-aware document and routing queries, event history, archived records and CSV formula-injection protection.

Before **any real correctional data** is entered, an IT/security team should additionally require:

1. HTTPS/TLS, trusted internal/VPN-only network access, secure server hardening and strict backup permissions.
2. A non-root MySQL service account, a unique password, backups encrypted at rest, tested restores, retention policy and breach procedures.
3. Identity integration/MFA if policy requires it, independent access logging, automated monitoring and periodic permission review.
4. A site-specific access review (especially for inmates, visitors, incident narratives), privacy assessment, legal review and documented operating procedure.
5. Independent security testing, disaster recovery testing and performance evaluation at the actual data volume.
6. Facility-approved data migration and verification before any production use.

**Current limitations**: no file attachments (document *metadata* only), no biometric features, no multi-factor authentication, no automated backups, no rich-text editor, no archive-restore UI, no external integrations and no PDF generator (use the browser's Print / Save as PDF). Admin password prompt input is visible in the command terminal, so use a private session. Audit events capture operations, not field-level old/new values. Browser caching is disabled for authenticated pages; network and database backups remain the operator's responsibility.

## Development and quick checks

```sh
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

For a quick functional test, import the schema, create an admin, register one inmate, one visitor, a visit, an incident, a document and a routing sheet; route it to another unit; verify print/CSV output. Then test using `records_officer` and `viewer` accounts to confirm permissions. These end-to-end steps require a working MySQL service and are not exercised by PHP linting.

## License and ownership

You may modify this generated starter project for your organization. Review it and conduct appropriate security, legal and QA validation before institutional deployment.

## Capstone demonstration workflow

Use fictional records only for the proposal presentation:

1. Create an admin account and sign in.
2. Add one personnel record for an approving officer.
3. Register an inmate with an initial housing unit.
4. Add a custody event for **Admission**, then a **Housing Assignment** or **Transfer** event.
5. Add two cases for the inmate and enter a next hearing date.
6. Register a visitor, create a pending visit, and show the visit status in the inmate workflow.
7. Record an incident and link it to the same inmate.
8. Register a document and route it between units using the routing history.
9. Open Reports & Print and export a filtered report.
10. Sign in as a viewer to demonstrate read-only access; sign in as an admin to demonstrate archival and user-account controls.

The proposal workflow is: **Admission → Housing → Case/Court monitoring → Visits and incidents → Transfer or release → Reports and audit history**. This build is for research demonstration and requires facility policy, privacy review, security testing, database integration testing and approval before operational use.
### Account request workflow

The sign-in page includes Request an account. Applicants submit their full name, employee/staff number, department, official work email, requested role and justification. Requests remain pending and do not create login access. An administrator reviews the request in User Accounts, then approves it by assigning a username and temporary password or rejects it with a note. Only approved requests create active viewer or records_officer accounts; public requests cannot create administrator accounts.
### First-run browser setup

After importing the schema, open JMRIS with an empty users table. The supplied capstone schema includes a demo administrator account so you can sign in immediately. The Initial Setup page remains available for a clean database without the demo seed. Additional accounts must be approved or created by an administrator.
>>>>>>> 6a6f7c7 (complete)
