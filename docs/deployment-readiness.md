# Deployment readiness

The current application is a local/Test Mode demo. It is not ready for a public production launch yet.

## Before any production launch

- Choose a PHP host with the required PHP extensions (`pdo_mysql`, `curl`, and `openssl`) and set the web root to `public/` only.
- Provision MySQL with TLS, a dedicated least-privilege application account, automated backups, and a separate production database. Apply the schema, procedures, triggers, and the Razorpay-reference migration in the documented order. Do not copy the local database or demo accounts to production.
- Serve the site over HTTPS. Set `APP_ENV=production`; this enables secure session cookies and requires Razorpay Live Mode keys. Store the Live Mode key ID and secret in the host's secret settings, never in source control or browser code.
- Rotate the Test Mode secret used during development before production work begins. Use a new Live Mode key pair for the real account.
- Decide the refund/cancellation policy and implement the corresponding payment-provider workflow before enabling event/show cancellations or booking cancellation.
- Persist payment orders and add a signed Razorpay webhook with idempotent processing. The current success path relies on the customer's browser returning to the site; an interrupted return can leave a captured payment without a confirmed booking.
- Configure production error logging, monitoring, rate limiting, and recovery procedures. Keep detailed errors in server logs, not API responses.
- Review customer data retention, privacy notice, support contact, terms, and payment receipt requirements for the actual operating region.
- Run the full acceptance flow in a production-like staging environment using Razorpay Test Mode, including success, failure, expiry, duplicate callbacks, and refund recovery. Only then configure Live Mode.

## Current configuration behavior

- `APP_ENV=local` or `APP_ENV=test` accepts only `rzp_test_` credentials.
- `APP_ENV=production` accepts only `rzp_live_` credentials and marks session cookies secure. Production must terminate HTTPS correctly at the web server or trusted proxy.
- `.env` is ignored by Git. For a hosted deployment, prefer the host's secret/environment-variable manager over uploading a local `.env` file.

No deployment has been performed.
