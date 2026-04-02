# Candidate Approval Workflow - Setup Complete

## ✅ Workflow Implementation Summary

Your voting system now includes a complete candidate approval workflow where admins must approve candidates before they can access the candidate dashboard.

---

## 📋 How It Works

### Step 1: Candidate Registration
- Candidate fills out registration form with all required information
- System validates eligibility requirements:
  - Age 21+ ✓
  - BSc Degree ✓
  - Good Conduct ✓
  - Party Membership ✓
- Candidate is created with `verification_status = 'pending'`
- Candidate sees "Registration Submitted" message and is informed to wait for admin approval

### Step 2: Admin Review
- Admin logs into admin dashboard
- Admin navigates to **"Election Management" → "Candidate Approvals"**
- Admin sees list of all pending candidates with:
  - Candidate details
  - Registration date
  - Payment status
  - Current verification status

### Step 3: Admin Decision
**Option A - Approve:**
- Admin clicks ✓ (Approve) button on pending candidate
- Confirms approval action
- System updates `verification_status = 'verified'`
- Activity logged in `candidate_activity_log`
- Candidate can now log in and access dashboard

**Option B - Reject:**
- Admin clicks ✗ (Reject) button
- Prompted to provide rejection reason
- System updates `verification_status = 'rejected'`
- Activity logged
- Candidate receives rejection notification via email

### Step 4: Candidate Login
- If `verification_status = 'verified'`: ✅ Allowed → access dashboard
- If `verification_status = 'pending'`: ❌ Blocked → message: "Awaiting admin approval"
- If `verification_status = 'rejected'`: ❌ Blocked → message: "Your candidacy was rejected"

---

## 🎯 Admin Dashboard - Candidate Approvals Section

### Location
- **URL:** `http://localhost/voting_system/public/admin.html`
- **Menu:** Sidebar → Election Management → **Candidate Approvals**

### Features

#### Statistics Dashboard
Shows real-time counts:
- 📊 **Total Candidates** - All registered candidates
- ⏳ **Pending** - Awaiting approval (orange)
- ✅ **Approved** - Verified candidates (green)
- ❌ **Rejected** - Rejected candidates (red)

#### Candidate Table
| Column | Details |
|--------|---------|
| Candidate ID | Unique registration ID |
| Name | Full name of candidate |
| Email | Contact email |
| Party | Political party affiliation |
| Payment | Payment status (pending/completed) |
| Status | Verification status (pending/verified/rejected) |
| Registered | Registration date |
| Actions | View, Approve, Reject buttons |

#### Action Buttons
- **👁️ View** - Opens modal with full candidate details
- **✓ Approve** - Approves candidate (only for pending)
- **✗ Reject** - Rejects with reason (only for pending)

#### Filtering
- Dropdown filter: "All Candidates" / "Pending Approval" / "Approved" / "Rejected"

---

## 📊 Database Tables

### `candidate_registrations`
Stores all candidate registration data:
- `candidate_id` - Unique identifier (PK)
- `full_name` - Candidate name
- `email` - Email address
- `phone` - Contact number
- `date_of_birth` - DOB for age verification
- `party` - Political party
- `has_bsc_degree` - Eligibility flag
- `good_conduct` - Eligibility flag
- `campaign_vision` - Campaign description
- `experience` - Prior experience
- **`verification_status`** - 'pending' / 'verified' / 'rejected'
- **`payment_status`** - 'pending' / 'completed' / 'failed'
- `created_at` - Registration timestamp

### `candidate_activity_log`
Logs all candidate-related activities:
- `log_id` - Unique identifier (PK)
- `candidate_id` - FK to candidate_registrations
- `action` - Type of action: 'approval', 'rejection', etc.
- `description` - Details of the action
- `created_at` - When action occurred

---

## 🔧 Backend API Endpoints

### Admin Candidate Registration Management

**Base URL:** `/api/admin-candidate-registrations.php`

#### 1. Get All Pending Candidates
```
POST ?action=get_pending_candidates
Body: {}
Response: { success: true, data: [...], total: N }
```

#### 2. Get Candidate Details
```
POST ?action=get_candidate_registration
Body: { candidate_id: "CAN-XXXXXXXX" }
Response: { success: true, data: {...} }
```

#### 3. Approve Candidate
```
POST ?action=approve_candidate
Body: { 
  candidate_id: "CAN-XXXXXXXX",
  notes: "Approved by admin" (optional)
}
Response: { success: true, message: "...", candidate_name: "..." }
```

#### 4. Reject Candidate
```
POST ?action=reject_candidate
Body: { 
  candidate_id: "CAN-XXXXXXXX",
  reason: "Reason for rejection"
}
Response: { success: true, message: "...", candidate_name: "..." }
```

#### 5. Get Statistics
```
POST ?action=get_candidate_stats
Body: {}
Response: { 
  success: true, 
  data: { 
    total: N, 
    approved: N, 
    pending: N, 
    rejected: N,
    paid: N
  } 
}
```

---

## 🔐 Authentication & Authorization

- Only logged-in admins can access the Candidate Approvals section
- Authentication check in `admin-candidate-registrations.php`:
  ```php
  if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
      http_response_code(401);
      echo json_encode(['success' => false, 'error' => 'Not authorized']);
  }
  ```

---

## 📝 User-Facing Messages

### Registration Success (New!)
After candidate submits registration form, they see:
- ✅ Registration confirmation
- 📧 Notification: Email will be sent when approved
- ⏱️ Timeline: "Typically approved within 24-48 hours"
- 📋 Candidate ID: Unique reference number
- 🏠 Options to go home or go to login page

### Login - Pending Approval
If candidate tries to login before approval:
```
❌ Your candidacy is pending verification. 
   Please wait for admin approval.
```

### Login - Rejected
If candidate's application was rejected:
```
❌ Your candidacy application was not approved.
   Contact admin for more information.
```

---

## 🚀 Testing the Workflow

### Test Case 1: Full Approval Flow
```
1. Register as new candidate
2. See "pending approval" message
3. Login as admin
4. Go to Candidate Approvals
5. Click Approve on the candidate
6. Candidate logs in successfully → access dashboard
```

### Test Case 2: Rejection Flow
```
1. Register as new candidate
2. Login as admin
3. Go to Candidate Approvals
4. Click Reject → provide reason
5. Candidate tries to login → sees rejection message
```

### Test Case 3: Multiple Candidates
```
1. Register 3-4 candidates
2. Admin sees all in Candidate Approvals
3. Approve some, reject others
4. Verify statistics update correctly
```

---

## 📱 Candidate Portal Changes

### Registration Form
✅ Added eligibility checklist
✅ Added verification requirements
✅ Cleaner multi-step form

### After Registration (New!)
✅ Shows "Awaiting Admin Approval" modal
✅ Displays candidate ID for reference
✅ Clear instructions on next steps
✅ Email address for notifications
✅ Direct links to login or home page

### Login Page
✅ Clear error messages for pending/rejected candidates
✅ Time estimate for approval
✅ Contact instructions for help

---

## 🔄 Admin Dashboard Updates

### New Navigation Item
- Location: Sidebar → Election Management
- Label: "Candidate Approvals"
- Icon: 👤✓
- Function: Manages candidate registration approvals

### New Admin Functions
- `loadCandidateRegistrations()` - Load pending candidates
- `filterRegistrations(status)` - Filter by status
- `viewCandidateDetails(id)` - View full details
- `approveCandidateReg(id)` - Approve candidate
- `rejectCandidateReg(id)` - Reject with reason
- `updateRegistrationStats()` - Update dashboard stats

---

## ✨ Security Features

✅ Admin authentication required
✅ Verification status checked on login
✅ Activity logging for all approvals/rejections
✅ Email notifications for candidates
✅ CORS protection
✅ Input validation

---

## 🎓 Quick Reference

| Action | URL | Permission |
|--------|-----|-----------|
| View Approvals | `/admin.html` → Candidate Approvals | Admin only |
| Register | `/candidate-register.html` | Any user |
| Login | `/candidate-register.html` | Registered candidates |
| Approve | Admin API | Admin only |
| Reject | Admin API | Admin only |

---

## 📞 Support

For issues or questions about the approval workflow:
1. Check admin dashboard for candidate details
2. Review activity logs for action history
3. Verify database status with verification scripts
4. Check error logs in browser console

---

**Workflow Implementation Date:** March 31, 2026
**Status:** ✅ Complete and Tested
