# Web-to-database contract (MVP)

This contract covers the 11-table assigned-seat booking model and Razorpay Test Mode. The PHP teammate owns the checkout and payment callback code; this document defines the database boundary.

## Tables

1. users — customer/admin accounts; store PHP password hashes, never raw passwords.
2. venues — venue details.
3. seats — reusable seat labels within a venue.
4. events — event description and publication status.
5. shows — scheduled event at a venue with a base ticket price in INR.
6. show_seats — each seat's availability and price for one show; a trigger creates these rows when a show is created.
7. seat_holds — temporary user claims on show seats; each expires after 5 minutes.
8. bookings — one customer's booking for one show.
9. booking_seats — seats and price snapshots inside a booking.
10. payments — INR amount, provider, Razorpay order ID, Razorpay payment ID, status, and time PHP verified the signature. No card details, API secrets, or signatures are stored.
11. seat_status_audit — history of show-seat state changes, recorded by a trigger.

All IDs are unsigned integers. Timestamps use MySQL DATETIME; PHP must set the connection time zone to UTC. Prices and totals use DECIMAL(10,2) in INR.

## Procedure interfaces

All hold and booking state changes must go through these procedures. Each owns its transaction; do not wrap a call in a PHP-managed transaction. PHP must not write show_seats, seat_holds, bookings, or booking_seats directly.

| Procedure | Inputs | Result / behavior |
| --- | --- | --- |
| sp_hold_seat | p_user_id, p_show_id, p_seat_id | Holds an available seat for 5 minutes; returns hold_id, expires_at, and price. |
| sp_release_seat | p_user_id, p_show_id, p_seat_id | Releases that user's active hold and makes the seat available. |
| sp_confirm_booking | p_user_id, p_show_id, p_provider_order_id, p_provider_payment_id | Atomically creates the confirmed booking, booking seats, and successful Razorpay payment record from active holds. Returns booking_id, payment_id, total_amount, and INR. Errors if IDs are missing or no active holds remain. |
| sp_cancel_booking | p_user_id, p_booking_id | Marks the booking cancelled and releases its seats. It does not issue a refund. |
| sp_release_expired_holds | none | Expires holds past expiry and frees seats; safe to call repeatedly. |

Important: PHP must create the Razorpay order from the database-calculated hold total, verify the returned signature with the server-only Test Mode secret, and confirm that the payment is captured for the exact INR amount before calling sp_confirm_booking. The procedure records the verified identifiers but does not verify Razorpay's HMAC itself. If payment succeeds after the 5-minute holds expire, confirmation fails and the PHP side must handle the payment/refund case. Failed and abandoned attempts are not yet recorded in payments.

## Razorpay Test Mode flow

1. User holds one or more seats.
2. PHP calculates the total from active holds and creates a Razorpay order in INR (convert rupees to paise for the API).
3. The browser opens Razorpay Checkout using the server-created order ID and public key ID.
4. PHP verifies the callback signature and captured payment status/amount using the secret kept in local .env.
5. PHP calls sp_confirm_booking with the authenticated user, show, Razorpay order ID, and payment ID.

Use Test Mode only for this college demo; test transactions are simulated and do not move real money. Never place the secret key in browser code or GitHub.

## Expected reads

- Event list: events where status = published.
- Event schedule: events joined to shows and venues.
- Seat map: seats joined to show_seats, filtered by show_id; available seats have seat_status = available.
- Booking history: bookings joined to booking_seats, show_seats, and seats, filtered by user_id.
- Payment receipt: payments filtered by booking_id.
- Admin screens can manage venues, seats, events, and shows. Creating a show automatically creates that show's seat inventory.

## Still to confirm

INR, 5-minute holds, and booking cancellation are confirmed. Cancellation frees seats but does not refund. Agree on refund policy, payment-failure handling, exact event/show fields, and admin account creation. Never store card numbers or security codes.
