CANDIDATE DASHBOARD SETUP GUIDE
=============================

This candidate dashboard is now fully connected to the database with the following features:

1. REAL-TIME VOTE TRACKING & ANALYTICS
   - View total votes received
   - Track vote percentage of total system votes
   - Real-time vote distribution charts (Doughnut chart)
   - Blockchain blocks verification counter
   - Recent activity log

2. CAMPAIGN MANAGEMENT TOOLS
   - Create and manage campaign goals with progress tracking
   - Create campaign posts (announcements, policies, events, achievements)
   - Track goal progress with visual progress bars
   - Store posts with engagement metrics
   - Access: Sidebar → "Campaign Tools"

3. VOTER DEMOGRAPHICS & INSIGHTS
   - View age group distribution of voters
   - Geographic voting distribution
   - Gender distribution analysis
   - Voter insights and trends
   - Visual charts for each demographic section
   - Access: Sidebar → "Demographics"

4. SECURE BLOCKCHAIN VERIFICATION
   - Display current block hash
   - Show Merkle root for vote verification
   - Display verified votes count
   - Blockchain verification timestamp
   - Historical blockchain records showing all verified blocks
   - Access: Sidebar → "Blockchain Verification"

5. VOTE DISPUTE RESOLUTION
   - Report vote disputes (illegitimate votes, duplicates, system errors, fraud)
   - Submit evidence URLs with dispute reports
   - Track dispute status (pending, investigating, resolved, rejected)
   - View resolution notes from election commission
   - Dispute guidelines provided
   - Access: Sidebar → "Vote Disputes"

SETUP INSTRUCTIONS
==================

1. Run the campaign management setup script:
   - Navigate to the voting system folder
   - Open http://localhost/voting_system/setup-campaign-management.php
   - This will create all necessary database tables

2. Tables Created:
   - campaign_posts: Store candidate posts and announcements
   - campaign_goals: Track campaign targets and goals
   - vote_disputes: Record vote dispute reports
   - blockchain_verification: Store blockchain verification records
   - voter_demographics: Store voter demographic data

3. API ENDPOINTS:
   - /api/campaign-management.php?action=get_campaign_goals
   - /api/campaign-management.php?action=create_campaign_goal
   - /api/campaign-management.php?action=get_campaign_posts
   - /api/campaign-management.php?action=create_campaign_post
   - /api/campaign-management.php?action=get_voter_demographics
   - /api/campaign-management.php?action=get_blockchain_verification
   - /api/campaign-management.php?action=report_vote_dispute
   - /api/campaign-management.php?action=get_vote_disputes

DATABASE TABLES
===============

campaign_posts:
- post_id (INT, Primary Key)
- candidate_id (VARCHAR 50)
- title (VARCHAR 255)
- content (LONGTEXT)
- post_type (ENUM: announcement, policy, event, achievement)
- image_url (VARCHAR 255)
- views (INT)
- engagement_score (INT)
- created_at, updated_at (TIMESTAMP)

campaign_goals:
- goal_id (INT, Primary Key)
- candidate_id (VARCHAR 50)
- goal_name (VARCHAR 255)
- goal_description (TEXT)
- target_votes (INT)
- current_progress (INT)
- status (ENUM: pending, active, completed, failed)
- target_date (DATE)
- created_at, updated_at (TIMESTAMP)

vote_disputes:
- dispute_id (INT, Primary Key)
- candidate_id (VARCHAR 50)
- vote_id (INT)
- dispute_type (ENUM: illegitimate_vote, duplicate_vote, system_error, fraud_allegation)
- description (TEXT)
- evidence_url (VARCHAR 255)
- status (ENUM: pending, investigating, resolved, rejected)
- resolution_notes (TEXT)
- created_at, resolved_at (TIMESTAMP)

blockchain_verification:
- verification_id (INT, Primary Key)
- candidate_id (VARCHAR 50)
- vote_count (INT)
- block_hash (VARCHAR 255)
- verification_timestamp (TIMESTAMP)
- merkle_root (VARCHAR 255)
- is_verified (BOOLEAN)
- created_at (TIMESTAMP)

voter_demographics:
- demographic_id (INT, Primary Key)
- candidate_id (VARCHAR 50)
- age_group (VARCHAR 50)
- gender (VARCHAR 20)
- region (VARCHAR 100)
- vote_count (INT)
- percentage (DECIMAL 5,2)
- updated_at (TIMESTAMP)

FEATURES BREAKDOWN
==================

Dashboard Overview Tab:
✓ Total votes stat card
✓ Blockchain blocks verification card
✓ Vote distribution pie chart
✓ Recent activity log
✓ Live election tracker with vote results

Campaign Info Tab:
✓ Candidate profile information
✓ Campaign vision and goals
✓ Experience and qualifications
✓ Verification status display

Vote Results Tab:
✓ All candidates with vote counts
✓ Vote percentages
✓ Results sorted by vote count
✓ Total votes in election

Elections Tab:
✓ All elections (ongoing, upcoming, completed)
✓ Election details (start/end dates, candidates, votes)
✓ Vote results display on each election card
✓ Turnout percentage estimates
✓ Search and filter elections

Analytics Tab:
✓ Vote trend chart (30-day history)
✓ Peak voting times analysis
✓ Geographic distribution
✓ Voter demographics breakdown

Profile Tab:
✓ Eligibility status (degree, age, conduct, party)
✓ Registration details
✓ Payment status
✓ Account status

Campaign Tools Tab (NEW):
✓ Campaign goals management
✓ Progress tracking with visual bars
✓ Campaign posts creation
✓ Post type categorization
✓ Engagement metrics

Demographics Tab (NEW):
✓ Age group distribution with percentages
✓ Geographic distribution with percentages
✓ Voter insights summary
✓ Total votes counter
✓ Strongest voter group identification

Blockchain Verification Tab (NEW):
✓ Current block hash display
✓ Merkle root verification
✓ Verified votes count
✓ Verification timestamp
✓ Blockchain history records
✓ Block verification status badges

Vote Disputes Tab (NEW):
✓ Report new disputes
✓ Dispute type selection
✓ Evidence upload/URL
✓ Dispute history tracking
✓ Resolution status display
✓ Dispute guidelines

USAGE NOTES
===========

1. All data loads asynchronously - no page refresh needed
2. Campaign goals show progress percentage automatically calculated
3. Demographics data generated from votes if no records exist
4. Blockchain verification shows latest records by default
5. Disputes can be filtered by status
6. Modal dialogs for creating goals, posts, and reporting disputes
7. All timestamps converted to local timezone
8. Real-time vote data updates when switching panels

TESTING
=======

To test the full functionality:
1. Register a candidate
2. Wait for admin approval
3. Log into candidate dashboard
4. View each tab to see data
5. Create a campaign goal
6. Create a campaign post
7. Report a test dispute
8. View blockchain verification
9. Check voter demographics

All data persists in the database and updates in real-time.
