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

The repository is intentionally shipped with placeholders instead of the Gmail credential that was present in the local development copy.
