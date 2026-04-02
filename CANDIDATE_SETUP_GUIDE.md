# Candidate Portal Setup Guide

## Quick Start

### 1. Initialize the Database

**Option A: Using PHP Setup Script**
```
Navigate to: http://localhost/voting_system/setup-candidates.php
```

**Option B: Manual SQL Execution**
1. Open your MySQL client (phpMyAdmin or MySQL CLI)
2. Select your `voting_system` database
3. Run the SQL from `database/candidates-schema.sql`

### 2. Verify Database Tables

After setup, verify these tables exist:
```sql
SHOW TABLES LIKE 'candidate%';
```

Expected tables:
- `candidates`
- `candidate_payments`
- `candidate_activity_log`
- `candidate_verification`

### 3. Access the Candidate Portal

**For Candidates:**
- Registration: http://localhost/voting_system/public/candidate-register.html
- Dashboard: http://localhost/voting_system/public/candidate-dashboard.html (after login)

**For Admins:**
- Candidate Management: http://localhost/voting_system/public/admin.html

---

## File Structure

```
voting_system/
├── api/
│   ├── candidate-auth.php          # Candidate authentication
│   ├── candidates.php              # Candidate dashboard data
│   └── admin-candidates.php        # Admin candidate management
├── public/
│   ├── candidate-register.html     # Registration & login page
│   └── candidate-dashboard.html    # Candidate dashboard
├── database/
│   └── candidates-schema.sql       # Database schema
├── setup-candidates.php            # Database setup script
└── CANDIDATE_PORTAL_GUIDE.md       # This guide
```

---

## Feature Verification Checklist

After setup, verify the following features:

### Candidate Registration
- [ ] Candidate can register with personal info
- [ ] Age validation (21+ required)
- [ ] BSc degree checkbox requirement
- [ ] Party selection from dropdown
- [ ] Good conduct confirmation
- [ ] Campaign vision and experience input
- [ ] 1000 USD fee acknowledgment
- [ ] Unique voter ID generation

### Candidate Login
- [ ] Email and password fields work
- [ ] Invalid credentials rejected
- [ ] Session created on successful login
- [ ] Redirect to dashboard

### Candidate Dashboard
- [ ] Statistics cards display correctly
- [ ] Vote chart renders
- [ ] Activity log shows entries
- [ ] Campaign information panel displays
- [ ] Vote results visible
- [ ] Profile shows eligibility status
- [ ] Logout function works

### Admin Management
- [ ] Admin can view all candidates
- [ ] Filter by status (pending/verified/rejected)
- [ ] Search by name/email/party works
- [ ] View candidate details
- [ ] Verify candidate (with eligibility check)
- [ ] Reject candidate (with reason)
- [ ] Process payment
- [ ] View statistics dashboard

---

## Testing Scenarios

### Test Scenario 1: Successful Registration & Verification

1. **Register as Candidate**
   - Name: John Candidate
   - Email: john@candidate.com
   - DOB: 1995-05-15 (Age: 28+)
   - Party: Progress Party
   - Confirm degree: ✓
   - Confirm conduct: ✓
   - Fee: Accept

2. **Expected Result**
   - Candidate created with ID: CAN-XXXXXXXX
   - Payment status: Pending
   - Verification status: Pending

3. **Admin Processing**
   - Login as admin
   - Navigate to Candidates
   - Process payment
   - Verify candidate
   - Candidate status: Verified

4. **Candidate Login**
   - Login with john@candidate.com
   - Should see dashboard
   - Can view stats and analytics

### Test Scenario 2: Eligibility Rejection

1. **Register with Invalid Age**
   - DOB: 2010-01-01 (Age: 14)
   - System should prevent registration

2. **Register Missing Degree**
   - Uncheck BSc degree
   - System should prevent progression to next step

### Test Scenario 3: Payment Processing

1. **Admin Payment Override**
   - Find candidate with payment_status: pending
   - Admin processes payment
   - Payment status updates to: completed
   - Candidate can now be verified

---

## Configuration

### Payment Gateway Integration (Optional)

To integrate actual payment processing, update `/api/candidate-auth.php`:

```php
// Example: Stripe integration
$stripe_key = 'sk_live_YOUR_KEY_HERE';

// In payment processing section:
if ($use_payment_gateway) {
    $charge = \Stripe\Charge::create([
        'amount' => 100000, // 1000 USD in cents
        'currency' => 'usd',
        'source' => $token,
        'description' => "Registration fee for candidate: {$email}"
    ]);
}
```

### Email Notifications (Optional)

Add email notifications in `/api/candidate-auth.php`:

```php
// Send registration confirmation
mail($email, 'Registration Confirmation', $message);

// Send verification confirmation
mail($email, 'Candidacy Verified', $message);
```

---

## Troubleshooting

### Database Tables Not Created

**Problem**: Tables don't exist after running setup
**Solution**:
1. Check MySQL error logs
2. Verify user has CREATE TABLE permission
3. Manually run SQL from `candidates-schema.sql`

### Registration Form Stuck

**Problem**: Form won't progress after filling section 1
**Solution**:
1. Check browser console (F12) for JavaScript errors
2. Verify all required fields are filled
3. Clear browser cache and reload

### Candidate Login Fails

**Problem**: "Invalid email or password" even with correct credentials
**Solution**:
1. Verify candidate exists in database:
   ```sql
   SELECT * FROM candidates WHERE email = 'email@example.com';
   ```
2. Check payment_status is 'completed'
3. Check verification_status is 'verified'
4. Test password hash:
   ```php
   password_verify('password', $hash_from_db)
   ```

### Admin Can't See Candidates

**Problem**: Admin page shows no candidates
**Solution**:
1. Verify admin_verified = 1 in voters table for admin user
2. Check session has user_type = 'admin'
3. Verify candidates table has data:
   ```sql
   SELECT COUNT(*) FROM candidates;
   ```

### Dashboard Stats Not Updating

**Problem**: Vote counts and statistics don't update
**Solution**:
1. Verify votes are being cast (check votes table)
2. Check votes are linked to candidates
3. Test API endpoint directly:
   ```
   POST /api/candidates.php?action=get_stats
   ```

---

## Performance Optimization

### Database Indexes

Verify indexes are created:
```sql
SHOW INDEXES FROM candidates;
SHOW INDEXES FROM candidate_payments;
SHOW INDEXES FROM candidate_activity_log;
```

Expected indexes:
- `candidates.email`
- `candidates.candidate_id`
- `candidates.verification_status`
- `candidate_payments.candidate_id`
- `candidate_activity_log.candidate_id`

### Query Optimization

For large numbers of candidates, add pagination:

```php
$limit = 20;
$offset = ($page - 1) * $limit;
$query .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
```

---

## Security Hardening

### Recommendations

1. **HTTPS Only**
   - Enforce SSL/TLS for all candidate portal pages
   - Update config to require HTTPS

2. **Rate Limiting**
   - Limit registration attempts to 3 per hour
   - Limit login attempts to 5 per hour

3. **Password Policy**
   - Enforce minimum 8 characters
   - Require uppercase, lowercase, numbers, symbols
   - Implement password reset functionality

4. **Two-Factor Authentication**
   - Add email/SMS verification
   - Require verification after registration

5. **Session Security**
   - Set session timeout to 30 minutes
   - Regenerate session ID on login
   - Use secure cookie flags

Example hardening in PHP:
```php
// Session security
session_set_cookie_params([
    'lifetime' => 1800,
    'path' => '/',
    'domain' => 'yourdomain.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Password validation
function validatePassword($password) {
    return strlen($password) >= 8 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/[0-9]/', $password);
}
```

---

## Database Backup

### Regular Backups

Create daily backups:
```bash
# Backup candidates tables
mysqldump -u username -p voting_system candidates candidate_payments candidate_activity_log > backup_candidates_$(date +%Y%m%d).sql

# Backup entire database
mysqldump -u username -p voting_system > backup_voting_system_$(date +%Y%m%d).sql
```

### Restore from Backup

```bash
mysql -u username -p voting_system < backup_candidates_20260123.sql
```

---

## Monitoring & Logging

### Enable Candidate Activity Logging

All candidate actions are logged in `candidate_activity_log`:
- Registration
- Login/Logout
- Payment processing
- Verification
- Profile updates

Query recent activity:
```sql
SELECT * FROM candidate_activity_log ORDER BY created_at DESC LIMIT 50;
```

### Monitor Payment Processing

```sql
SELECT 
    candidate_id,
    COUNT(*) as payment_attempts,
    SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed
FROM candidate_payments
GROUP BY candidate_id;
```

---

## Support & Documentation

- **Candidate Portal Guide**: [CANDIDATE_PORTAL_GUIDE.md](CANDIDATE_PORTAL_GUIDE.md)
- **Main README**: [README.MD](README.MD)
- **API Documentation**: [api/](api/) (inline comments)
- **Database Schema**: [database/candidates-schema.sql](database/candidates-schema.sql)

---

**Created**: January 23, 2026
**Last Updated**: January 23, 2026
**Version**: 1.0
