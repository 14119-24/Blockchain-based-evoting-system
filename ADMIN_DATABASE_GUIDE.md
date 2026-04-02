# Admin Database Guide

This project now includes a dedicated MySQL database named `admin` for admin-owned data.

## Files

- `config/admin_database.php`
- `database/admin_schema.sql`
- `setup-admin.php`

## What the `admin` database contains

- `admins`
- `elections`
- `election_candidates`
- `candidate_approvals`
- `voter_verifications`
- `admin_audit_logs`
- `admin_sessions`

## Default admin account

- Email: `admin@votingsystem.local`
- Password: `Admin123!`

## Setup

Run:

```powershell
php setup-admin.php
```

## Note

This creates a dedicated admin database package in the repo. Existing app APIs may still use the main `voting_system` database unless they are explicitly migrated to `config/admin_database.php`.
