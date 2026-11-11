# Local setup checklist

1. Create the event_ticket_booking database and run SQL files in the order documented in db/README.md.
2. For an existing database, apply the payment-reference migration documented in db/README.md, then update procedures.sql.
3. Create the local event_app MySQL account as documented in db/README.md.
4. Copy .env.example to .env and set the local event_app password plus Razorpay Test Mode keys. Keep .env private; Git ignores it.
5. Configure a local PHP server with public/ as its document root.

To grant admin access to a registered account, run `php tools/make-admin.php account@example.com` from the project folder and type `PROMOTE` at the confirmation prompt. This script works only from the command line. The account owner must sign out and back in before the Admin link appears.

Admin users can create and edit events, create venues with seat layouts, schedule shows, and review bookings. Show times in the admin form use India Standard Time. Booking cancellation and refunds remain unavailable until a refund policy and payment-provider workflow are defined.
