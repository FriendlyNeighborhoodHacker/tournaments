# Debate Team Sign-ups (PHP + MySQL)

A minimal web app to manage debate tournaments, roster, and team sign-ups (with "Go Maverick" solo option). Roles: **Admin**, **Coach**, **Member**.

## Features
- Login with email + password (bcrypt).
- Roster CRUD (admins).
- Tournament CRUD (admins).
- Home page: upcoming tournaments, sign up with 1–2 partners or solo "Go Maverick".
- You see tournaments you’re on, even if someone else signed you up (shows who created it).
- Un-sign a team (any team member or admin).
- Coach view: all tournaments with all sign-ups, comments, and timestamps.
- Admins can edit sign-ups (team members, maverick flag, comment) and see coach view.

## Install
1. Create the database and tables:
   ```sql
   -- Optionally edit DB name inside schema first
   CREATE DATABASE debate_app DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE debate_app;
   SOURCE schema.sql;
   ```
   Optionally seed an admin (uncomment the INSERT at the bottom of `schema.sql`) then change the password after logging in.

2. Copy files to your PHP server (Apache/Nginx with PHP 8+). If you deploy in a subfolder, adjust asset paths (`/styles.css`, `/main.js`) or set a web root.

3. Edit `config.php` with your DB credentials.

4. Visit `/login.php`, sign in as admin, add users & tournaments.

## Notes
- A user cannot be in two sign-ups for the same tournament (enforced by `signup_members` unique key).
- Deleting a user who is part of any signup will fail; remove their signups first.
- CSRF protection for POSTs; PDO prepared statements; simple role checks.
- If you want email notifications, divisions, waitlists, or CSV export, extend easily from the existing endpoints.

## Default Admin (if seeded)
- Email: `admin@example.com`
- Password: `Admin123!` (please change immediately)

Enjoy!
