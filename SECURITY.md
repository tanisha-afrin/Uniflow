# Security notes

- Keep production credentials outside Git.
- Use environment variables for database and SMTP credentials.
- Use HTTPS in production.
- Do not publish real `.env` files.
- Do not commit Gmail App Passwords or database passwords.
- Restrict access to administrative endpoints through authentication and authorization.
