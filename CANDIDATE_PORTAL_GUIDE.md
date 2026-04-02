# Candidate Portal - BlockVote Voting System

## Overview

The Candidate Portal is a comprehensive system that allows candidates to register, complete eligibility verification, and manage their campaigns within the blockchain-based voting system.

## Features

### Candidate Registration
- **Multi-step registration form** with eligibility checks
- **Personal information collection**: Full name, email, phone, date of birth
- **Party membership selection**: Choose from registered political parties
- **Campaign platform details**: Vision, goals, and experience
- **Payment integration**: 1000 USD registration fee

### Candidate Authentication
- **Secure login** with email and password
- **Session-based authentication**
- **Real-time verification status check**
- **Payment status tracking**

### Candidate Dashboard
- **Real-time vote tracking** and statistics
- **Vote distribution analytics** with charts
- **Campaign performance insights**
- **Activity log** and audit trail
- **Blockchain integration** for vote verification
- **Vote results monitoring**

---

## Eligibility Requirements

Before a candidate can be verified, they must meet **ALL** of the following requirements:

### 1. **Age Requirement**
- Must be **21 years or older**
- Age is verified based on date of birth
- Automatic age validation during registration

### 2. **Education Requirement**
- Must hold a **Bachelor's degree (BSc) or equivalent**
- Candidate must confirm possession of BSc degree during registration
- Documentation will be verified by administrators

### 3. **Party Membership**
- Must be a **registered member of a political party**
- Available parties:
  - Progress Party
  - Unity Party
  - Future Party
  - Green Alliance
- Party affiliation must be confirmed at registration

### 4. **Good Conduct**
- Must have **good moral character**
- Must confirm **no criminal record**
- Candidate confirms via checkbox during registration
- Administrators verify during verification process

### 5. **Registration Payment**
- Must pay **1000 USD** (non-refundable) registration fee
- Payment must be completed before verification
- Payment can be processed through:
  - Online payment gateway
  - Manual payment by administrators
  - Bank transfer

---

## Registration Process

### Step 1: Personal Information
1. Navigate to [Candidate Portal](../public/candidate-register.html)
2. Click **"Register"** tab
3. Fill in personal details:
   - Full name
   - Email address
   - Phone number
   - Date of birth
4. Click **"Next"** to proceed

### Step 2: Eligibility Verification
1. Confirm **BSc degree** possession
2. Select **party membership**
3. Confirm **good conduct**
4. System auto-validates eligibility checklist
5. Click **"Next"** when all requirements are met

### Step 3: Campaign Platform & Payment
1. Describe your **campaign vision and goals**
2. Detail your **relevant experience and qualifications**
3. Review the **1000 USD registration fee**
4. Accept **terms and conditions**
5. Click **"Complete Registration"**

### Step 4: Payment Processing
- Payment confirmation will be sent to your email
- After payment is verified, registration is complete
- Wait for admin verification (typically 24-48 hours)

---

## Login & Dashboard

### Logging In
1. Go to [Candidate Portal](../public/candidate-register.html)
2. Click **"Login"** tab
3. Enter your **email** and **password**
4. Click **"Login as Candidate"**

### Dashboard Overview
After login, you'll see:

**Statistics Cards:**
- Total Votes: Number of votes received
- Registered Voters: Total eligible voters
- Votes Cast: Total votes in the election
- Blockchain Blocks: Secure vote records

**Vote Distribution Chart:**
- Visual representation of all candidates' votes
- Real-time updates as votes are cast

**Recent Activity:**
- Login/logout timestamps
- Vote updates
- System notifications

### Campaign Information Panel
View and manage:
- Your campaign vision
- Experience and qualifications
- Party affiliation
- Registration status
- Verification date

### Vote Results Panel
Monitor:
- Real-time vote counts
- Vote percentages by candidate
- Vote distribution charts
- Election statistics

### Analytics Panel
Access insights on:
- Vote trends over time
- Geographic vote distribution
- Peak voting times
- Voter demographics

### Profile Panel
Review:
- Eligibility status (Age, Degree, Conduct, Payment)
- Registration details
- Account status
- Payment confirmation

---

## Admin Management

### Accessing Candidate Management
1. Login as **Admin** at [Admin Portal](../public/admin.html)
2. Navigate to **Candidates Management**

### Admin Functions

#### 1. View Candidates
- **Filter by status**: Pending, Verified, Rejected
- **Search** by name, email, or party
- **Sort** by registration date
- **View** all candidate details

#### 2. Verify Candidates
**Before verifying**, admin must confirm:
- ✓ Age is 21+
- ✓ BSc degree is verified
- ✓ Party membership is confirmed
- ✓ Good conduct is confirmed
- ✓ Payment of 1000 USD is completed

**To verify:**
1. Select candidate from list
2. Review eligibility checklist
3. Add verification notes (optional)
4. Click **"Approve Candidate"**
5. Candidate receives verification notification

#### 3. Reject Candidates
- Provide detailed rejection reason
- Reasons include: insufficient documentation, failed eligibility, payment issues, etc.
- Candidate can reapply after addressing issues

#### 4. Process Payments
- Mark payments as completed when received
- Accept manual payments (cash, bank transfer)
- Record transaction IDs
- Generate payment receipts

#### 5. View Candidate Statistics
- **Total Candidates**: All registered candidates
- **Pending Verification**: Awaiting admin review
- **Verified Candidates**: Eligible candidates
- **Rejected Candidates**: Failed verification
- **Payments Completed**: Payment status
- **Revenue Collected**: Total registration fees

---

## Database Schema

### Candidates Table
```sql
candidates
├── candidate_id (unique identifier)
├── full_name
├── email
├── password_hash
├── phone
├── date_of_birth
├── party
├── has_bsc_degree (boolean)
├── good_conduct (boolean)
├── campaign_vision
├── experience
├── registration_fee
├── payment_status
├── verification_status
├── created_at
└── updated_at
```

### Candidate Payments Table
```sql
candidate_payments
├── id
├── candidate_id (FK)
├── amount
├── payment_method
├── transaction_id
├── payment_status
├── payment_date
└── created_at
```

### Candidate Activity Log
```sql
candidate_activity_log
├── id
├── candidate_id (FK)
├── activity_type
├── description
├── ip_address
└── created_at
```

---

## API Endpoints

### Candidate Authentication
- `POST /api/candidate-auth.php?action=candidate_register` - Register new candidate
- `POST /api/candidate-auth.php?action=candidate_login` - Login candidate
- `POST /api/candidate-auth.php?action=check_candidate_session` - Verify session
- `POST /api/candidate-auth.php?action=candidate_logout` - Logout candidate

### Candidate Dashboard
- `POST /api/candidates.php?action=get_stats` - Get dashboard statistics
- `POST /api/candidates.php?action=get_activity` - Get activity log
- `POST /api/candidates.php?action=get_vote_results` - Get vote results
- `POST /api/candidates.php?action=get_candidate_profile` - Get profile details

### Admin Candidate Management
- `POST /api/admin-candidates.php?action=get_candidates` - List candidates
- `POST /api/admin-candidates.php?action=get_candidate_details` - Get candidate details
- `POST /api/admin-candidates.php?action=verify_candidate` - Approve candidate
- `POST /api/admin-candidates.php?action=reject_candidate` - Reject candidate
- `POST /api/admin-candidates.php?action=process_payment` - Process payment
- `POST /api/admin-candidates.php?action=get_candidates_stats` - Get statistics

---

## Security Features

### Password Security
- Bcrypt hashing with secure salt
- Minimum password requirements enforced
- Session-based authentication

### Data Protection
- Personal information encrypted in transit (HTTPS)
- Secure database with prepared statements
- SQL injection prevention

### Audit Trail
- All candidate actions logged
- IP address tracking
- Modification timestamps
- Admin verification audit log

### Payment Security
- PCI compliant payment processing
- Transaction verification
- Duplicate payment prevention
- Refund tracking

---

## Setup Instructions

### 1. Initialize Database
Run the schema migration:
```bash
php setup-candidates.php
```

Or manually execute:
```sql
-- Import candidates-schema.sql into your MySQL database
source database/candidates-schema.sql;
```

### 2. Configure Payment Gateway (Optional)
- Update `config/payment-gateway.php` with payment provider credentials
- Supported providers: Stripe, PayPal, Square

### 3. Setup Candidates Portal
1. Ensure all PHP files are in `/api/` directory
2. Ensure HTML files are in `/public/` directory
3. Verify database connections in `/config/database.php`

### 4. Access Candidate Portal
- **Candidate Registration**: `http://localhost/voting_system/public/candidate-register.html`
- **Admin Candidate Management**: `http://localhost/voting_system/public/admin.html` (Candidates tab)

---

## Testing

### Test Candidate Registration
1. Visit candidate registration page
2. Fill form with valid data:
   - Age: 25+
   - BSc: Checked
   - Party: Select any party
   - Good Conduct: Checked
3. Complete payment

### Test Admin Verification
1. Login as admin
2. Navigate to candidates
3. Review pending candidates
4. Verify or reject with reason

### Test Candidate Dashboard
1. Login as verified candidate
2. Monitor vote statistics
3. View real-time results
4. Check activity log

---

## Troubleshooting

### Registration Issues
- **"Age must be 21+"**: Adjust date of birth
- **"BSc degree required"**: Check the checkbox
- **"Party selection required"**: Select a party

### Login Issues
- **"Payment pending"**: Complete the 1000 USD payment
- **"Verification pending"**: Wait for admin verification
- **"Invalid credentials"**: Verify email and password

### Dashboard Issues
- **"Not authenticated"**: Login first
- **"Session expired"**: Login again
- **"No data"**: Ensure votes have been cast

---

## Contact & Support

For technical support or issues with the Candidate Portal:
- Email: admin@blockvote.local
- Documentation: See [README.MD](../README.MD)

---

## Terms & Conditions

By registering as a candidate, you agree to:
- Provide accurate information
- Pay the 1000 USD registration fee (non-refundable)
- Comply with all election rules
- Accept the results of the election
- Maintain confidentiality of your login credentials

**Last Updated:** January 23, 2026
**Version:** 1.0
