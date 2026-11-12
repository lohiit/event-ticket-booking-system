# Database setup

This is the first-pass implementation of the 11-table plan. It assumes MySQL 8.0 or newer and assigned seating. Run the files in this order against a fresh event_ticket_booking database:

1. schema.sql
2. procedures.sql
3. triggers.sql
4. seed.sql

For multi-instance hosting, the PHP app can store sessions in MySQL. Fresh databases get the `app_sessions` table from `schema.sql`; for an existing database, apply `db/migrations/003_app_sessions.sql` once before setting `SESSION_DRIVER=database`.

The scripts select the project database themselves. They are intended for a fresh database, not as migrations. Do not rerun schema.sql, triggers.sql, or seed.sql over existing data.

For the PHP application, create a dedicated local MySQL account. Choose your own password and never commit it:

    CREATE USER IF NOT EXISTS 'event_app'@'127.0.0.1' IDENTIFIED BY 'choose-a-local-password';
    GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE
    ON event_ticket_booking.* TO 'event_app'@'127.0.0.1';

Use event_app and that same password in your ignored local .env file. For Razorpay, add Test Mode keys to the local .env only; never commit the key secret. Keep root for database administration.

For an existing database, apply db/migrations/001_razorpay_payment_refs.sql once, then rerun procedures.sql to install the payment-aware confirmation procedure. Do not rerun schema.sql, triggers.sql, or seed.sql.

For an existing database that predates event poster uploads, apply db/migrations/002_event_image_path.sql once. Fresh databases get this column from schema.sql.

The PHP server must create the Razorpay order and verify the returned signature and captured amount before calling sp_confirm_booking.

Booking writes must go through the procedures in procedures.sql. The triggers in triggers.sql create show-seat inventory and record seat status changes. Procedures serialize updates per show. The application can read tables directly using parameterized PDO queries. See docs/database-contract.md for names and assumptions.

seed.sql inserts two initial venues, events, shows, and seat layouts. The show trigger creates each show's seat inventory. Add future venues as rows in venues and their seat layouts in seats. No user passwords or payment data are seeded.
