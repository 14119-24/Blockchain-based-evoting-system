# 🚀 XAMPP Quick Start - 3 Minutes to Live System

## Prerequisites Checklist
- ✅ XAMPP installed on your system
- ✅ Project at: `C:\xampp\htdocs\voting_system\`
- ✅ You're reading this guide

---

## IMMEDIATE SETUP (Do This Now)

### Step 1️⃣ - Start XAMPP (1 minute)
1. Open **XAMPP Control Panel**  
   Path: `C:\xampp\xampp-control.exe`
2. Click **Start** next to **Apache**
3. Click **Start** next to **MySQL**
4. Wait for both to show **Running** (green)

### Step 2️⃣ - Initialize Database (1 minute)
1. Open your web browser
2. Go to: **`http://localhost/voting_system/public/setup.html`**
3. Click **"Start Database Setup"**
4. Wait for success message
5. System redirects to home page automatically ✓

### Step 3️⃣ - Log In (1 minute)
1. Click **"Admin Login"** button
2. Enter credentials:
   - **Email:** `admin@votingsystem.local`
   - **Password:** `Admin123!`
3. You're in! 🎉

---

## URLs for All Functions

| Function | URL |
|----------|-----|
| 🏠 Home | `http://localhost/voting_system/public/` |
| 📝 Register Voter | `http://localhost/voting_system/public/register.html` |
| 🔐 Voter Login | `http://localhost/voting_system/public/login.html` |
| 🗳️ Vote | `http://localhost/voting_system/public/vote.html` |
| 👨‍💼 Admin Dashboard | `http://localhost/voting_system/public/admin.html` |
| ⚙️ Database Setup | `http://localhost/voting_system/public/setup.html` |
| 📊 phpMyAdmin | `http://localhost/phpmyadmin/` |

---

## Connection Details (Already Configured)

```
Host:     localhost
Database: voting_system
User:     root
Password: (empty)
```

All credentials are already set in `config/database.php` ✓

---

## What Just Got Created?

✅ Empty `voting_system` database  
✅ 5 database tables with proper structure  
✅ Admin user account  
✅ All API endpoints ready  
✅ Blockchain system initialized  

---

## Next Steps After Setup

1. **Add Elections:**
   - Go to Admin Dashboard
   - Create a new election
   - Set start/end dates

2. **Add Candidates:**
   - Admin Dashboard → Add Candidates
   - Assign to election

3. **Register Voters:**
   - Share registration link
   - Voters register and verify
   - You approve in admin panel

4. **Enable Voting:**
   - Activate election (change status to "ongoing")
   - Voters can now vote

5. **View Results:**
   - After voting ends, close election
   - See results in admin dashboard

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| "Failed to fetch" | Use `http://localhost/` not `file://` |
| "Can't connect to database" | Make sure MySQL is running in XAMPP |
| "Table not found" error | Run setup.html again |
| XAMPP won't start | Run as Administrator, close port conflicts |
| Forgot admin password | Manually reset in phpMyAdmin |

---

## Testing the System Works

### Quick Test (2 minutes)
1. Go to: `http://localhost/voting_system/public/register.html`
2. Register a test voter account
3. Login with those credentials
4. System should work smoothly ✓

### Database Test
1. Run this in your browser:  
   `http://localhost/voting_system/check-xampp.php`
2. Should see all green checkmarks ✓

---

## 📞 If Something Goes Wrong

1. **Check XAMPP Control Panel** - Are both Apache & MySQL running?
2. **Check browser console** - Press F12, look for red errors
3. **Check XAMPP logs** - Look in `C:\xampp\apache\logs\`
4. **Hard reset database** - Go to phpMyAdmin, delete `voting_system` database, re-run setup.html

---

## 🎯 Key Takeaway

**Your system is ready to go!** It's already configured for XAMPP. Just:
1. Start XAMPP ▶️
2. Run setup ⚙️
3. Login & use 🎉

**Questions?** See `XAMPP_INTEGRATION_GUIDE.md` for complete details.
