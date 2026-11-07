# Web-to-database contract (first-pass MVP)

This is the first implementation of the 11-table plan in the handover. It gives the web teammate stable names for the MVP while the remaining payment and cancellation details are agreed.

## Tables

1. `users` — customer/admin accounts; store PHP password hashes, never raw passwords.
2. `venues` — venue details.
3. `seats` — reusable seat labels within a venue.
4. `events` — event description and publication status.
5. `shows` — scheduled event at a venue with a base ticket price in INR.
6. `show_seats` — one seat's availability and price for a particular show. A trigger creates these rows when a show is created.
7. `seat_holds` — temporary user claims on show seats; each expires after 5 minutes.
8. `bookings` — one customer's booking for one show.
9. `booking_seats` — seats and price snapshots inside a booking.
10. `payments` — payment amount, INR currency, status, provider name, and provider reference. No card details are stored. The payment provider flow is not implemented yet.
11. `seat_status_audit` — history of changes to a show seat's state, recorded by a trigger.

All IDs are unsigned integer keys. Timestamps use MySQL `DATETIME`; PHP must set the MySQL session time zone to UTC. Prices and totals use `DECIMAL(10,2)` and INR.

## Procedure interfaces

All booking/hold state changes must go through these procedures. Call them without wrapping the call in a PHP-managed transaction because each procedure owns its transaction. Do not directly insert or update `show_seats`, `seat_holds`, `bookings`, or `booking_seats` from PHP.

| Procedure | Inputs | Result / behavior |
| --- | --- | --- |
| `sp_hold_seat` | `p_user_id`, `p_show_id`, `p_seat_id` | Holds an available seat for 5 minutes; returns `hold_id`, `expires_at`, and `price`. Errors when the show/seat/user is invalid or the seat is unavailable. |
| `sp_release_seat` | `p_user_id`, `p_show_id`, `p_seat_id` | Releases that user's active hold and makes the seat available; errors if no active hold exists. |
| `sp_confirm_booking` | `p_user_id`, `p_show_id` | Creates one confirmed booking from all active holds for that user/show; returns `booking_id`, `total_amount`, and `currency` (`INR`). Errors if no active holds remain. |
| `sp_cancel_booking` | `p_user_id`, `p_booking_id` | Marks the user's confirmed booking cancelled and releases its seats; returns booking ID and status. No refund is initiated. |
| `sp_release_expired_holds` | none | Expires holds past their expiry time and frees their seats; returns the number released. Safe to call repeatedly. |

Expected business-rule errors use SQLSTATE `45000`. PHP should catch `PDOException` and show a user-safe message; do not parse exact error text for control flow.

## Expected reads

- Event list: `events` where `status = 'published'`.
- Event schedule: `events` joined to `shows` and `venues`.
- Seat map: `seats` joined to `show_seats`, filtered by `show_id`. Availability is `show_seats.seat_status = 'available'`.
- Booking history/details: `bookings` joined to `booking_seats`, then `show_seats` and `seats`, filtered by `user_id`.
- Payment display: `payments` filtered by booking. Payment creation/update behavior must be coordinated before use.
- Admin reads/writes for venues, seats, events, and shows; creating a show automatically creates that show's seat inventory.

## Still to confirm

The user has confirmed INR, a 5-minute hold, and that bookings can be cancelled. The database cancellation operation marks the booking cancelled and frees its seats; refund timing/eligibility is not defined. Agree with the web teammate on payment-provider flow and when a booking is considered paid, cancellation/refund policy, required event/show fields, and admin account creation. No card number or security code should be stored here.
