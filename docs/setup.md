# Local setup checklist

1. Create the event_ticket_booking database and run the SQL files in the order documented in db/README.md.
2. Create the local event_app MySQL account as documented in db/README.md.
3. Copy .env.example to .env and enter the local event_app password. The .env file is ignored by Git.
4. Configure a local PHP server with public/ as its document root.
