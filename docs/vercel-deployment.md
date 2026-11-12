# Vercel Test Mode demo

This project is a PHP and MySQL college demo. Vercel runs the PHP app from `Dockerfile.vercel` using FrankenPHP and Caddy. The database must be hosted separately; Vercel does not receive the laptop's database or `.env` file.

## Before importing the GitHub repository

1. Choose a hosted MySQL 8.0+ service that allows stored procedures, triggers, and remote TLS connections. The booking code relies on the procedures and triggers in `db/`.
2. Create a fresh database and import `db/schema.sql`, `db/procedures.sql`, `db/triggers.sql`, and `db/seed.sql`, in that order. These scripts use the database name `event_ticket_booking`; if the provider assigns another name, change the `USE event_ticket_booking;` line in each SQL file before importing.
3. Create an application database user with `SELECT`, `INSERT`, `UPDATE`, `DELETE`, and `EXECUTE` permissions on that database.
4. Add the session table using `db/migrations/003_app_sessions.sql` if the database was created before this session-storage change. Fresh databases include it in `schema.sql`.

The Vercel setup uses `Dockerfile.vercel` and `Caddyfile` at the repository root. The local project remains PHP/MySQL and keeps local file sessions and poster uploads by default.

## Vercel environment variables

Add these in the Vercel project's Settings → Environment Variables. Do not commit secrets or upload the local `.env` file.

- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`: values from the hosted MySQL service.
- `APP_ENV=test`: keeps Razorpay in Test Mode.
- `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`: Razorpay Test Mode keys.
- `SESSION_DRIVER=database`: shares login and checkout session data through MySQL.
- `SESSION_COOKIE_SECURE=true`: marks the login cookie as HTTPS-only.
- `EVENT_IMAGE_UPLOADS=disabled`: uses bundled event artwork; uploaded files would not persist on the Vercel container.

## Check the preview before sharing it

Import the repository only after the hosted database and environment variables are ready. Deploy a preview, then check the home page, event list and details, registration/login, seat selection, Razorpay Test Mode checkout, booking history, and admin pages. Keep this as a Test Mode college demo; it is not set up for real-money production use.
