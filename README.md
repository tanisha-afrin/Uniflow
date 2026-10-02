# UniFlow

UniFlow is a university portal that brings student services, administration, proctorial, technical, and Lost & Found functions into one web application.

## Important: GitHub vs. hosting

GitHub stores and versions the source code. It does **not** execute PHP or provide the MySQL database for this application.

To make UniFlow publicly accessible, deploy this repository to a PHP/MySQL-compatible hosting provider and connect a domain such as `https://your-domain.example`.

## Before deployment

1. Create a MySQL/MariaDB database.
2. Import `sql/uniflow_db.sql` and any required migration SQL files in `sql/`.
3. Configure the environment variables listed in `.env.example` on the hosting server.
4. Set `UNIFLOW_BASE_URL` to the real HTTPS domain.
5. Configure the Gmail sender using a Google App Password in `UNIFLOW_SMTP_USERNAME` and `UNIFLOW_SMTP_PASSWORD`.
6. Make `lost_found/uploads/` writable by PHP if Lost & Found uploads are enabled.
7. Enable HTTPS/SSL.

## Local XAMPP setup

1. Copy this complete `Uniflow` folder to `C:\xampp\htdocs\Uniflow`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open phpMyAdmin at `http://localhost/phpmyadmin/`. Create a new database named `uniflow_db` using `utf8mb4` collation. Do not select or drop an existing database.
4. Select the new database and import `sql/uniflow_db.sql`.
5. Select the same database and import `lost_found/database.sql`. This creates the Lost & Found tables without dropping existing data.
6. Copy `.env.example` to `.env` in the project root. For the default local XAMPP account, set:

   ```dotenv
   UNIFLOW_DB_HOST=127.0.0.1
   UNIFLOW_DB_PORT=3306
   UNIFLOW_DB_NAME=uniflow_db
   UNIFLOW_DB_USER=root
   UNIFLOW_DB_PASSWORD=
   UNIFLOW_BASE_URL=http://localhost/Uniflow
   UNIFLOW_SMTP_USERNAME=
   UNIFLOW_SMTP_PASSWORD=
   ```

   If the local MariaDB root account has a password, enter it as `UNIFLOW_DB_PASSWORD`. Email features require a Gmail sender and Google App Password; set both SMTP values to enable them. Reset and invitation links use `UNIFLOW_BASE_URL`.
7. Create the first Administrative manager from PowerShell in the project folder. Set `UNIFLOW_BOOTSTRAP_ADMIN_NAME`, `UNIFLOW_BOOTSTRAP_ADMIN_EMAIL`, and `UNIFLOW_BOOTSTRAP_ADMIN_PASSWORD` (minimum 12 characters) in the private `.env` file or PowerShell session, then run `C:\xampp\php\php.exe setup_system_admin.php`. Clear temporary variables after setup. This CLI-only utility refuses to reset existing accounts.
8. Sign in at `http://localhost/Uniflow/login.php`. Use the Administrative dashboard's **Admin Management** to review staff signup requests, invite staff, and assign portal access.

The app does not ship with default administrator credentials. `setup_admins.php` is a CLI-only backward-compatible name for the same one-time Administrative manager setup; it does not seed demo credentials.

### Existing database upgrades

Back up the database first. Do not import the fresh-install SQL files over an existing installation as a substitute for migrations. Inspect the current schema and apply only the needed SQL migrations in `sql/`, followed where applicable by `lost_found/role_migration.sql` and `sql/lost_found_admin_interactions_migration.sql`. The migrations in `sql/` address specific older schemas; they are not a blanket script to run against every installation. `sql/single_login_migration.sql` preserves existing Lost & Found admins and their access when converting them to the shared permissions model.

If PHP shows a database connection error, check that MySQL is running, then verify `UNIFLOW_DB_*` in `.env`. Connection details are written to the PHP/Apache error log.

### URLs

- Home: `http://localhost/Uniflow/`
- Login: `http://localhost/Uniflow/login.php`
- Student services: `http://localhost/Uniflow/student/dashboard.php`
- Lost & Found: `http://localhost/Uniflow/lost_found/index.php`

## Google Search setup

After the site is live:

1. Replace `YOUR-DOMAIN.example` in `robots.txt` and `sitemap.xml` with the real domain.
2. Add the domain to Google Search Console.
3. Verify ownership.
4. Submit `https://your-domain.example/sitemap.xml`.
5. Use URL Inspection for the homepage and request indexing.

Google decides when and where an indexed page appears in search results; indexing does not guarantee a first-page or first-position result for the word “UniFlow”.

## Security

Never commit real database passwords, Gmail App Passwords, API keys, or other secrets. Use the hosting provider's environment-variable settings.

Set sender credentials only in `.env` or process environment variables. If a real sender credential was previously committed, revoke it and issue a replacement before enabling email.

Public System Admin registration is not available. Student self-registration creates an account that can sign in immediately. Staff self-registration creates an inactive request; only an active Administrative manager can approve it in Admin Management. The old student-management screens are no longer used; their legacy URLs redirect to signup or Admin Management.
