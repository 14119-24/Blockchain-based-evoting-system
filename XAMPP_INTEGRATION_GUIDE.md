# XAMPP Integration Guide for Blockchain Voting System

**Date:** February 17, 2026  
**Status:** Ready for Integration with XAMPP

---

## ✅ What Has Been Verified

- **Database Configuration:** Already set to `localhost`, database name: `voting_system`
- **MySQL Credentials:** `root` user (default XAMPP setup)
- **Project Location:** `C:\xampp\htdocs\voting_system/`
- **XAMPP Status:** Running (Apache & MySQL active)
- **Schema:** Complete with all required tables (voters, elections, candidates, votes, blockchain_blocks)

---

## 🚀 Complete Setup Steps

### Step 1: Verify XAMPP is Running

**Windows - XAMPP Control Panel:**
1. Open **XAMPP Control Panel** (usually in `C:\xampp\xampp-control.exe`)
2. Ensure **Apache** has a green checkmark and shows "Running"
3. Ensure **MySQL** has a green checkmark and shows "Running"

If not running:
- Click **"Start"** button next to Apache
- Click **"Start"** button next to MySQL

### Step 2: Initialize the Database

Option A - **Using Web Interface (Recommended):**
1. Open your browser
2. Go to: `http://localhost/voting_system/public/setup.html`
3. Click **"Start Database Setup"**
4. Wait for confirmation message
5. System will automatically redirect to home page

Option B - **Using Command Line (Direct):**
```bash
cd C:\xampp\htdocs\voting_system
php setup.php
```

### Step 3: Access the Application

| Page | URL |
|------|-----|
| **Home** | `http://localhost/voting_system/public/index.html` |
| **Voter Registration** | `http://localhost/voting_system/public/register.html` |
| **Voter Login** | `http://localhost/voting_system/public/login.html` |
| **Voting Interface** | `http://localhost/voting_system/public/vote.html` |
| **Admin Dashboard** | `http://localhost/voting_system/public/admin.html` |
| **Database Setup** | `http://localhost/voting_system/public/setup.html` |

### Step 4: Admin Login

After database setup, login with default credentials:
- **Email:** `admin@votingsystem.local`
- **Password:** `Admin123!`

---

## 📋 Database Tables Created

| Table | Purpose |
|-------|---------|
| `voters` | User accounts and voting records |
| `elections` | Election events |
| `candidates` | Candidates per election |
| `votes` | Individual votes with encryption |
| `blockchain_blocks` | Blockchain ledger |
| `payments` | M-Pesa payment records (if configured) |

---

## ✨ Key Features Enabled

✅ Voter Registration & Verification  
✅ Secure Voting with Encryption  
✅ Blockchain Vote Recording  
✅ Admin Election Management  
✅ Vote Tallying & Results  
✅ M-Pesa Payment Integration (configured but optional)  

---

## 🔧 Configuration Files

### Database Connection
**Location:** `config/database.php`
```php
private $host = "localhost";
private $db_name = "voting_system";
private $username = "root";
private $password = "";  // Empty by default in XAMPP
```

### M-Pesa (Optional Payment Integration)
**Location:** `config/mpesa.php`  
Configure this if you need to enable payment features.

---

## 🐛 Troubleshooting

### "Failed to fetch" Error
**Cause:** Accessing via `file://` protocol  
**Fix:** Always use `http://localhost/voting_system/public/`

### "Database connection failed"
**Cause:** Database not initialized  
**Fix:** Run setup at `http://localhost/voting_system/public/setup.html`

### "Table not found" or "Unknown table"
**Cause:** Schema not properly created  
**Fix:** 
1. Drop the database: Go to phpMyAdmin (`http://localhost/phpmyadmin`)
2. Delete `voting_system` database
3. Re-run setup.html

### XAMPP Won't Start
**Troubleshooting:**
1. Close any other applications using ports 80/443 (Apache) or 3306 (MySQL)
2. Run XAMPP Control Panel as Administrator
3. Check XAMPP logs in `C:\xampp\apache\logs\`

---

## 📱 Testing Workflow

1. **Register** a voter at `/public/register.html`
2. **Verify** the voter account (admin can do this in admin.html)
3. **Login** with voter credentials at `/public/login.html`
4. **Vote** in an active election at `/public/vote.html`
5. **View Results** in admin dashboard

---

## 🔒 Security Notes

- All passwords are hashed using bcrypt
- Votes are encrypted before storage
- Each vote is recorded on blockchain
- Private keys are encrypted and stored separately
- Default admin password should be changed after first login

---

## 📞 Support

For issues or questions:
1. Check browser console (F12) for error messages
2. Review `STARTUP_GUIDE.md` for detailed instructions
3. Check XAMPP error logs in `/logs/` folder
4. Verify database connectivity with phpMyAdmin

---

**System is ready for deployment!** 🎉
