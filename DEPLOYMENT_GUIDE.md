# Deployment Guide

## Local XAMPP deployment

1. Copy the project into XAMPP:
   `powershell -ExecutionPolicy Bypass -File .\deploy-xampp.ps1`
2. Start `Apache` and `MySQL` from the XAMPP Control Panel.
3. Open `http://localhost/voting_system/public/setup.html`
4. Click `Start Database Setup`
5. Sign in at `http://localhost/voting_system/public/admin.html`

Default admin credentials:

- Email: `admin@votingsystem.local`
- Password: `Admin123!`

## Before going beyond localhost

- Update `config/database.php` with your real database host, name, user, and password.
- Change the default admin password immediately after first login.
- Review `config/mpesa.php` before enabling live payments. Set a real HTTPS callback URL and replace any test credentials.
- Serve the app over HTTPS.
- Keep the project-level `.htaccess` file in place so Apache does not expose `config/`, `core/`, `database/`, `docs/`, or `tests/`.

## Notes

- The setup page must be opened over HTTP, not `file://`.
- The setup endpoint is `setup-api.php` in the project root, and `public/setup.html` now calls it correctly.
