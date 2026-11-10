# Local setup checklist

1. Create the event_ticket_booking database and run SQL files in the order documented in db/README.md.
2. For an existing database, apply the payment-reference migration documented in db/README.md, then update procedures.sql.
3. Create the local event_app MySQL account as documented in db/README.md.
4. Copy .env.example to .env and set the local event_app password plus Razorpay Test Mode keys. Keep .env private; Git ignores it.
5. Configure a local PHP server with public/ as its document root.
