# Candidate Portal - Visual Workflow & Architecture Guide

## 🏗️ System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     BLOCKVOTE CANDIDATE PORTAL                  │
└─────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│                         FRONTEND LAYER                           │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────────────────────┐                            │
│  │ candidate-register.html         │                            │
│  │ (Registration & Login Page)     │                            │
│  │                                 │                            │
│  │ ✓ Step 1: Personal Info        │                            │
│  │ ✓ Step 2: Eligibility Check    │                            │
│  │ ✓ Step 3: Payment & Terms      │                            │
│  └────────────┬────────────────────┘                            │
│               │                                                 │
│  ┌────────────▼──────────────────────┐                          │
│  │ candidate-dashboard.html          │                          │
│  │ (Campaign Management Dashboard)   │                          │
│  │                                   │                          │
│  │ ✓ Overview Panel                 │                          │
│  │ ✓ Campaign Info Panel            │                          │
│  │ ✓ Vote Results Panel             │                          │
│  │ ✓ Analytics Panel                │                          │
│  │ ✓ Profile Panel                  │                          │
│  └──────────────┬─────────────────────┘                         │
│                 │                                               │
└─────────────────┼───────────────────────────────────────────────┘
                  │
            API Calls
                  │
┌─────────────────┼───────────────────────────────────────────────┐
│                 ▼                                               │
│           API LAYER (PHP)                                      │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────────────────────┐                            │
│  │ candidate-auth.php              │                            │
│  │ (Authentication & Authorization)│                            │
│  │                                 │                            │
│  │ • candidate_register            │                            │
│  │ • candidate_login               │                            │
│  │ • check_candidate_session       │                            │
│  │ • candidate_logout              │                            │
│  └────────────┬────────────────────┘                            │
│               │                                                 │
│  ┌────────────▼──────────────────────┐                          │
│  │ candidates.php                    │                          │
│  │ (Dashboard & Statistics)          │                          │
│  │                                   │                          │
│  │ • get_stats                       │                          │
│  │ • get_activity                    │                          │
│  │ • get_vote_results                │                          │
│  │ • get_candidate_profile           │                          │
│  └────────────┬─────────────────────┘                           │
│               │                                                 │
│  ┌────────────▼──────────────────────┐                          │
│  │ admin-candidates.php              │                          │
│  │ (Admin Management)                │                          │
│  │                                   │                          │
│  │ • get_candidates                  │                          │
│  │ • get_candidate_details           │                          │
│  │ • verify_candidate                │                          │
│  │ • reject_candidate                │                          │
│  │ • process_payment                 │                          │
│  │ • get_candidates_stats            │                          │
│  └────────────┬─────────────────────┘                           │
│               │                                                 │
└───────────────┼──────────────────────────────────────────────────┘
                │
           Database Queries
                │
┌───────────────┼──────────────────────────────────────────────────┐
│               ▼                                                  │
│         DATABASE LAYER (MySQL)                                  │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  candidates                 candidate_payments                   │
│  ├─ id (PK)                 ├─ id (PK)                           │
│  ├─ candidate_id (U)        ├─ candidate_id (FK)                 │
│  ├─ full_name               ├─ amount                            │
│  ├─ email (U)               ├─ payment_method                    │
│  ├─ password_hash           ├─ transaction_id                    │
│  ├─ phone                   ├─ payment_status                    │
│  ├─ date_of_birth           ├─ payment_date                      │
│  ├─ party                   └─ created_at                        │
│  ├─ has_bsc_degree                                               │
│  ├─ good_conduct            candidate_activity_log               │
│  ├─ campaign_vision         ├─ id (PK)                           │
│  ├─ experience              ├─ candidate_id (FK)                 │
│  ├─ payment_status          ├─ activity_type                     │
│  ├─ verification_status     ├─ description                       │
│  ├─ rejected_reason         ├─ ip_address                        │
│  ├─ created_at              └─ created_at                        │
│  └─ updated_at                                                   │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

---

## 🔄 Registration Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│                   CANDIDATE REGISTRATION FLOW                   │
└─────────────────────────────────────────────────────────────────┘

START
  │
  ▼
┌─────────────────────────────────┐
│ Visit Registration Page         │
│ candidate-register.html         │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Click Register Tab              │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ STEP 1: Personal Information    │
│                                 │
│ Enter:                          │
│ • Full Name                     │
│ • Email                         │
│ • Phone                         │
│ • Date of Birth                 │
│                                 │
│ System: Auto-calculates age     │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Age Valid (21+)?                │
├─────────────────────────────────┤
│ NO  ► Show Error ► Back to Step 1│
│ YES ► Continue                  │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ STEP 2: Eligibility Check       │
│                                 │
│ Confirm:                        │
│ • ✓ BSc Degree                  │
│ • Select Party                  │
│ • ✓ Good Conduct                │
│                                 │
│ Real-time Validation Display:   │
│ ✓/✗ Age: 21+                   │
│ ✓/✗ Degree                      │
│ ✓/✗ Party                       │
│ ✓/✗ Conduct                     │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ All Requirements Met?           │
├─────────────────────────────────┤
│ NO  ► Show Required Items       │
│ YES ► Continue                  │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ STEP 3: Campaign & Payment      │
│                                 │
│ Enter:                          │
│ • Campaign Vision               │
│ • Experience                    │
│                                 │
│ Review:                         │
│ • Registration Fee: 1000 USD    │
│                                 │
│ Confirm:                        │
│ • ✓ Agree to Fee                │
│ • ✓ Accept Terms                │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Submit Registration Form        │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ POST to API                     │
│ /api/candidate-auth.php         │
│ ?action=candidate_register      │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Server-Side Eligibility Check   │
│                                 │
│ Verify:                         │
│ ✓ Age >= 21                     │
│ ✓ BSc degree confirmed          │
│ ✓ Party selected                │
│ ✓ Good conduct confirmed        │
│ ✓ Email unique                  │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ All Checks Pass?                │
├─────────────────────────────────┤
│ NO  ► Return Error Message      │
│ YES ► Continue                  │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Generate Candidate ID           │
│ Format: CAN-XXXXXXXX            │
│                                 │
│ Hash Password (bcrypt)          │
│                                 │
│ Create Candidate Record:        │
│ • INSERT into candidates table  │
│ • payment_status = 'pending'    │
│ • verification_status = 'pending'
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Log Activity                    │
│ Type: 'registration'            │
│ Description: 'Candidate ...'    │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Return Success Response         │
│ • candidate_id                  │
│ • email                         │
│ • message                       │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Show Success Message            │
│ "Registration successful!"      │
│ "Proceed to payment"            │
│                                 │
│ Redirect to Payment Gateway     │
│ (or Admin Manual Payment)       │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Payment Processing              │
│ Amount: 1000 USD                │
│                                 │
│ Payment Status Updated:         │
│ 'completed'                     │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Admin Reviews Candidacy         │
│                                 │
│ Admin Panel:                    │
│ • Review eligibility            │
│ • Verify education              │
│ • Confirm party                 │
│ • Accept/Reject                 │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ verification_status Updated     │
│ 'verified' OR 'rejected'        │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ IF Verified:                    │
│ • Send Approval Email           │
│ • Candidate can now LOGIN       │
│ • Can access DASHBOARD          │
│                                 │
│ IF Rejected:                    │
│ • Send Rejection Email          │
│ • Show rejection reason         │
│ • Can reapply later             │
└────────────┬────────────────────┘
             │
             ▼
           END
```

---

## 🔐 Login & Authentication Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                   CANDIDATE LOGIN & AUTH FLOW                   │
└─────────────────────────────────────────────────────────────────┘

START
  │
  ▼
┌─────────────────────────────────┐
│ Visit Login Page                │
│ candidate-register.html         │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Click Login Tab                 │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Enter Credentials               │
│ • Email                         │
│ • Password                      │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Click "Login as Candidate"      │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ POST to API                     │
│ /api/candidate-auth.php         │
│ ?action=candidate_login         │
│                                 │
│ Payload: {email, password}      │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Query Database                  │
│ SELECT * FROM candidates        │
│ WHERE email = ?                 │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Candidate Found?                │
├─────────────────────────────────┤
│ NO  ► Return Error (401)        │
│ YES ► Continue                  │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Verify Password                 │
│ password_verify(input, hash)    │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Password Correct?               │
├─────────────────────────────────┤
│ NO  ► Return Error (401)        │
│ YES ► Continue                  │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Check Payment Status            │
│ payment_status == 'completed'?  │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Payment Required?               │
├─────────────────────────────────┤
│ NO  ► Return Error (403)        │
│ YES ► Continue                  │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Check Verification Status       │
│ verification_status == 'verified'?
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Verification Required?          │
├─────────────────────────────────┤
│ NO  ► Return Error (403)        │
│ YES ► Continue                  │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Create Session                  │
│ $_SESSION['candidate_id'] = ... │
│ $_SESSION['candidate_email'] = ..
│ $_SESSION['candidate_name'] = ...
│ $_SESSION['user_type'] = 'candidate'
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Log Activity                    │
│ Type: 'login'                   │
│ IP: $remote_addr                │
│ Timestamp: NOW()                │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Return Success Response         │
│ • candidate_id                  │
│ • full_name                     │
│ • email                         │
│ • party                         │
│ • message: "Login successful"   │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Redirect to Dashboard           │
│ candidate-dashboard.html        │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Dashboard Initialization        │
│                                 │
│ checkCandidateAuth()            │
│ • Verify session exists         │
│ • If not, redirect to login     │
│                                 │
│ loadCandidateData()             │
│ • GET /api/auth.php             │
│ • ?action=check_candidate_session
│                                 │
│ loadDashboardOverview()         │
│ • GET /api/candidates.php       │
│ • ?action=get_stats             │
│                                 │
│ Display:                        │
│ • Welcome message               │
│ • Statistics cards              │
│ • Vote chart                    │
│ • Recent activity               │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│ Candidate Can:                  │
│ • View campaign info            │
│ • Monitor votes                 │
│ • Check analytics               │
│ • Update profile                │
│ • Logout                        │
└────────────┬────────────────────┘
             │
             ▼
           END
```

---

## 📊 Eligibility Verification Checklist

```
┌─────────────────────────────────────────────────────────────────┐
│              CANDIDATE ELIGIBILITY VERIFICATION                 │
└─────────────────────────────────────────────────────────────────┘

REQUIREMENT 1: AGE
┌─────────────────────────────────────────────────────────────┐
│ Criteria: Must be 21 years or older                        │
│ Verification: Check date_of_birth < NOW() - 21 years      │
│ Location: Registration Step 1, Admin Panel                 │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐
│ │ Age Calculation:                                        │
│ │ NOW() = 2026-01-23                                      │
│ │ DOB = 1995-05-15                                        │
│ │ Age = 30 years ✓ PASS                                  │
│ │                                                         │
│ │ DOB = 2010-01-01                                        │
│ │ Age = 15 years ✗ FAIL                                  │
│ └─────────────────────────────────────────────────────────┘
└─────────────────────────────────────────────────────────────┘

REQUIREMENT 2: EDUCATION
┌─────────────────────────────────────────────────────────────┐
│ Criteria: Bachelor's Degree (BSc) or equivalent            │
│ Verification: has_bsc_degree = 1 (checkbox confirmed)      │
│ Location: Registration Step 2 - Checkbox required          │
│                                                             │
│ ✓ Checkbox marked        = ELIGIBLE                        │
│ ✗ Checkbox not marked    = NOT ELIGIBLE                    │
│                                                             │
│ Note: Admin may request documentation for verification     │
└─────────────────────────────────────────────────────────────┘

REQUIREMENT 3: PARTY MEMBERSHIP
┌─────────────────────────────────────────────────────────────┐
│ Criteria: Member of registered political party             │
│ Verification: Select from approved party list              │
│ Location: Registration Step 2 - Dropdown selection         │
│                                                             │
│ Approved Parties:                                          │
│ ✓ Progress Party 🌲                                        │
│ ✓ Unity Party 🤝                                           │
│ ✓ Future Party 🚀                                          │
│ ✓ Green Alliance 🌍                                        │
│                                                             │
│ ✗ Not selected OR other party = NOT ELIGIBLE               │
└─────────────────────────────────────────────────────────────┘

REQUIREMENT 4: GOOD CONDUCT
┌─────────────────────────────────────────────────────────────┐
│ Criteria: Good moral character, no criminal record         │
│ Verification: good_conduct = 1 (checkbox confirmed)        │
│ Location: Registration Step 2 - Checkbox required          │
│                                                             │
│ ✓ Checkbox marked        = ELIGIBLE                        │
│ ✗ Checkbox not marked    = NOT ELIGIBLE                    │
│                                                             │
│ Admin verifies through background check                    │
└─────────────────────────────────────────────────────────────┘

REQUIREMENT 5: PAYMENT
┌─────────────────────────────────────────────────────────────┐
│ Criteria: 1000 USD registration fee paid                   │
│ Verification: payment_status = 'completed'                 │
│ Location: Registration Step 3 + Payment Gateway            │
│                                                             │
│ Payment Status Progression:                                │
│ pending ────────────┐                                      │
│                     │ (Payment processed)                  │
│                     ▼                                      │
│                  completed ✓ ELIGIBLE                      │
│                     │                                      │
│                     ▼ (Payment failed)                     │
│                    failed ✗ NOT ELIGIBLE                   │
│                                                             │
│ Amount: 1000 USD (Non-refundable)                         │
│ Methods: Online, Bank Transfer, Manual (Admin)            │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                   FINAL ELIGIBILITY STATUS                  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ IF (Age ✓ AND Degree ✓ AND Party ✓ AND Conduct ✓ AND Payment ✓)
│                                                             │
│    THEN: ELIGIBLE FOR VERIFICATION                         │
│          ✓ Admin can approve candidacy                     │
│          ✓ Candidate can login to dashboard                │
│          ✓ Can view and monitor votes                      │
│                                                             │
│    ELSE: NOT ELIGIBLE                                      │
│          ✗ Show failed requirements                        │
│          ✗ Cannot login                                    │
│          ✗ Admin cannot verify                             │
│          → Candidate must resolve issues and reapply       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 🛡️ Security & Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                   SECURITY & DATA PROTECTION                    │
└─────────────────────────────────────────────────────────────────┘

PASSWORD SECURITY
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│ User Input Password: "MyPassword123!"                       │
│         │                                                   │
│         ▼ (Bcrypt Hashing)                                 │
│         │                                                   │
│ Hash: $2y$10$abcdefghijklmnopqrstuvwxyz123456             │
│         │                                                   │
│         ▼ (Store in Database)                              │
│         │                                                   │
│ Database → password_hash column                            │
│                                                             │
│ On Login:                                                   │
│ 1. User enters password                                    │
│ 2. Fetch hash from database                                │
│ 3. password_verify(input, hash)                            │
│ 4. Return boolean (true/false)                             │
│                                                             │
│ Bcrypt Advantages:                                         │
│ ✓ Automatic salting                                        │
│ ✓ Computationally expensive                                │
│ ✓ Resistant to rainbow tables                              │
│ ✓ Adaptive work factor                                     │
│                                                             │
└─────────────────────────────────────────────────────────────┘

DATA TRANSMISSION SECURITY
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│ Client: Candidate Registration Form                        │
│         │                                                   │
│         ▼ (POST request with JSON)                         │
│         │                                                   │
│ HTTPS → Encrypted transmission                             │
│ (SSL/TLS certificate required)                             │
│         │                                                   │
│         ▼ (Server receives)                                │
│         │                                                   │
│ Server: Validate & Process                                 │
│ ✓ Input validation                                         │
│ ✓ Type checking                                            │
│ ✓ Sanitization                                             │
│         │                                                   │
│         ▼ (Prepare Statement)                              │
│         │                                                   │
│ Database: SQL Injection Prevention                         │
│ ✓ Parameterized queries                                    │
│ ✓ Prepared statements                                      │
│ ✓ Bound parameters                                         │
│                                                             │
└─────────────────────────────────────────────────────────────┘

SESSION SECURITY
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│ Successful Login                                            │
│         │                                                   │
│         ▼                                                   │
│ ┌─────────────────────────────────┐                        │
│ │ session_start()                 │                        │
│ │ $_SESSION['candidate_id'] = 'CAN-X'                      │
│ │ $_SESSION['candidate_email'] = 'x@x.com'                 │
│ │ $_SESSION['user_type'] = 'candidate'                     │
│ └─────────────────────────────────┘                        │
│         │                                                   │
│         ▼                                                   │
│ Session Cookie Created (HTTPS only, HttpOnly)              │
│ PHPSESSID=abc123def456...                                  │
│         │                                                   │
│         ▼                                                   │
│ Browser stores in secure cookie                            │
│ (Not accessible to JavaScript)                             │
│         │                                                   │
│         ▼                                                   │
│ Each Request: Validate Session                             │
│ if (empty($_SESSION['candidate_id'])) {                    │
│     Redirect to login                                      │
│ }                                                           │
│                                                             │
│ Session Timeout: 30 minutes                                │
│ Logout: session_destroy()                                  │
│                                                             │
└─────────────────────────────────────────────────────────────┘

AUDIT LOGGING
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│ Every Action Logged:                                        │
│                                                             │
│ INSERT INTO candidate_activity_log (                        │
│    candidate_id,                                            │
│    activity_type,      // 'login', 'registration', etc.    │
│    description,        // "Candidate logged in"            │
│    ip_address,         // $_SERVER['REMOTE_ADDR']          │
│    created_at          // Timestamp                        │
│ )                                                           │
│                                                             │
│ Logged Actions:                                             │
│ ✓ Registration                                              │
│ ✓ Login                                                     │
│ ✓ Logout                                                    │
│ ✓ Dashboard access                                          │
│ ✓ Payment processing                                        │
│ ✓ Verification                                              │
│ ✓ Profile updates                                           │
│                                                             │
│ Admin can audit:                                            │
│ SELECT * FROM candidate_activity_log                       │
│ WHERE candidate_id = ? AND created_at > ?                 │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 📈 Database Relationships

```
candidates
┌────────────────────────────────────────┐
│ id (PK)                                │◄─────┐
│ candidate_id (UNIQUE)                  │      │
│ full_name                              │      │
│ email (UNIQUE)                         │      │
│ password_hash                          │      │
│ date_of_birth                          │      │ 1-to-1
│ party                                  │      │
│ has_bsc_degree                         │      │
│ good_conduct                           │      │
│ payment_status                         │      │
│ verification_status                    │      │
│ created_at                             │      │
└────────────────────────────────────────┘      │
                                                 │
                    ┌────────────────────────────┘
                    │
                    ▼
    candidate_payments
    ┌────────────────────────────────────────┐
    │ id (PK)                                │
    │ candidate_id (FK) ─► candidates.id     │
    │ amount (1000 USD)                      │
    │ payment_method                         │
    │ transaction_id                         │
    │ payment_status                         │
    │ payment_date                           │
    │ created_at                             │
    └────────────────────────────────────────┘

        ┌────────────────────────────────────────┐
        │ candidate_activity_log                 │
        │                                        │
        │ id (PK)                                │
        │ candidate_id (FK) ─► candidates.id     │
        │ activity_type                          │
        │ description                            │
        │ ip_address                             │
        │ created_at                             │
        └────────────────────────────────────────┘

Query Examples:
────────────────

1. Get candidate with all info:
   SELECT c.*, cp.amount, cp.payment_status, cp.payment_date
   FROM candidates c
   LEFT JOIN candidate_payments cp ON c.id = cp.candidate_id
   WHERE c.candidate_id = ?

2. Get activity log for candidate:
   SELECT * FROM candidate_activity_log
   WHERE candidate_id = ?
   ORDER BY created_at DESC

3. Get payment info:
   SELECT * FROM candidate_payments
   WHERE candidate_id = ? AND payment_status = 'completed'
```

---

**End of Visual Architecture Guide**

This guide provides a complete visual overview of the Candidate Portal system architecture, workflows, security measures, and data relationships.

For detailed information, refer to the comprehensive documentation files.
