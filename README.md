# Event Ticket Booking System

A database-driven ticket-booking application built with PHP, MySQL, HTML, CSS, and JavaScript.

## Project layout

- `public/` — pages, API endpoints, static assets, and administrator screens.
- `app/` — shared PHP configuration, layout includes, and helper functions.
- `db/` — schema, stored procedures, triggers, and sample data maintained by the database teammate.
- `docs/` — the agreement between the web and database parts of the project.
- `storage/logs/` — runtime logs (not committed, except for the directory placeholder).

## Team workflow

Database work belongs primarily in `db/`. Web work belongs primarily in `public/` and `app/`.
Before changing a database procedure or its inputs/outputs, update `docs/database-contract.md` so both teammates implement against the same interface.

For local setup, admin access, and launch blockers, see [`docs/setup.md`](docs/setup.md) and [`docs/deployment-readiness.md`](docs/deployment-readiness.md). For a Vercel Test Mode demo, see [`docs/vercel-deployment.md`](docs/vercel-deployment.md). The application is not configured for real-money production use.
