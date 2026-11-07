# Database setup

This is the first-pass implementation of the 11-table plan. It assumes MySQL 8.0 or newer and assigned seating. Run the files in this order against a fresh event_ticket_booking database:

1. schema.sql
2. procedures.sql
3. triggers.sql
4. seed.sql

The scripts select the project database themselves. They are intended for a fresh database, not as migrations. Do not rerun schema.sql, triggers.sql, or seed.sql over existing data.

For the PHP application, create a dedicated local MySQL account. Choose your own password and never commit it:

    CREATE USER IF NOT EXISTS 'event_app'@'127.0.0.1' IDENTIFIED BY 'choose-a-local-password';
    GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE
    ON event_ticket_booking.* TO 'event_app'@'127.0.0.1';

Use event_app and that same password in your ignored local .env file. Keep root for database administration.

Booking writes must go through the procedures in procedures.sql. The triggers in triggers.sql create show-seat inventory and record seat status changes. Procedures serialize updates per show. The application can read tables directly using parameterized PDO queries. See docs/database-contract.md for names and assumptions.

seed.sql inserts two initial venues, events, shows, and seat layouts. The show trigger creates each show's seat inventory. Add future venues as rows in venues and their seat layouts in seats. No user passwords or payment data are seeded.
