# Web-to-database contract

Before implementation, document each table/view/procedure the PHP app will call here.

## Planned stored procedures

| Procedure | Purpose | Status |
| --- | --- | --- |
| `sp_hold_seat` | Temporarily reserve a selected seat safely | To define |
| `sp_confirm_booking` | Create a confirmed booking from active holds | To define |
| `sp_cancel_booking` | Cancel a booking and release seats | To define |
| `sp_release_expired_holds` | Release holds past their expiry time | To define |

For every procedure, record its parameters, returned result, errors, and transaction behavior before the PHP endpoint uses it.
