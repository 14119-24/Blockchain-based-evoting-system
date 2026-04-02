# Blockchain Voting System - Setup and Access Instructions

## Important: Must Use Web Server

The Blockchain Voting System requires access through a web server (Apache/XAMPP), NOT by opening files directly in the browser.

### ❌ Do NOT do this:
- Opening `file:///C:/xampp/htdocs/voting_system/public/register.html` directly in the browser
- Double-clicking HTML files

### ✅ DO this instead:

#### For XAMPP Users:
1. Ensure XAMPP is running (Apache should be started)
2. Access the application at:
   ```
   http://localhost/voting_system/public/
   ```
3. Or access directly to register:
   ```
   http://localhost/voting_system/public/register.html
   ```

#### First Time Setup:
1. Go to: `http://localhost/voting_system/public/setup.html`
2. Click "Start Database Setup" to initialize the database
3. Once setup is complete, you'll be redirected to the home page

#### Admin Credentials (after setup):
- Email: `admin@votingsystem.local`
- Password: `Admin123!`

## Troubleshooting

### "Failed to fetch" Error
This means the file is being opened with `file://` protocol instead of `http://`.
**Solution:** Access through XAMPP using `http://localhost/voting_system/public/`

### "Database connection failed"
The database might not be initialized.
**Solution:** Run the setup page at `http://localhost/voting_system/public/setup.html`

### XAMPP Not Running
Start Apache and MySQL from the XAMPP Control Panel:
1. Open XAMPP Control Panel
2. Click "Start" next to Apache
3. Click "Start" next to MySQL

## File Structure
```
voting_system/
├── public/              # Web accessible files
│   ├── index.html      # Home page
│   ├── register.html   # Voter registration
│   ├── login.html      # Voter login
│   ├── vote.html       # Voting interface
│   ├── admin.html      # Admin dashboard
│   ├── setup.html      # Database setup
│   ├── js/             # JavaScript files
│   └── css/            # Stylesheets
├── api/                # API endpoints (not web accessible directly)
│   ├── auth.php        # Authentication API
│   ├── vote.php        # Voting API
│   └── admin.php       # Admin API
├── config/             # Configuration files
│   └── database.php    # Database connection
├── core/               # Core classes
│   ├── Cryptography.php
│   ├── Validator.php
│   └── Blockchain.php
├── database/
│   └── schema.sql      # Database schema
└── setup-api.php       # Backend setup script
```

## Quick Start Checklist
- [ ] XAMPP is running (Apache and MySQL started)
- [ ] Access application via `http://localhost/voting_system/public/`
- [ ] Run database setup at `/public/setup.html`
- [ ] Login with admin credentials
- [ ] Create elections and manage voters

---
If issues persist, check the browser console (F12) for specific error messages.
