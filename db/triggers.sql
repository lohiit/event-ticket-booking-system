USE event_ticket_booking;
DELIMITER $$

-- Create one inventory row for every venue seat when a show is added.
CREATE TRIGGER trg_shows_create_seat_inventory
AFTER INSERT ON shows
FOR EACH ROW
BEGIN
    INSERT INTO show_seats (show_id, seat_id, price, seat_status)
    SELECT NEW.show_id, s.seat_id, NEW.ticket_price, 'available'
    FROM seats s
    WHERE s.venue_id = NEW.venue_id;
END$$

-- Record each seat state transition. Procedures set this session variable to
-- the acting user; system expiry updates leave it NULL.
CREATE TRIGGER trg_show_seats_audit_status
AFTER UPDATE ON show_seats
FOR EACH ROW
BEGIN
    IF NOT (OLD.seat_status <=> NEW.seat_status) THEN
        INSERT INTO seat_status_audit
            (show_seat_id, old_status, new_status, changed_by_user_id)
        VALUES
            (NEW.show_seat_id, OLD.seat_status, NEW.seat_status, @etbs_actor_user_id);
    END IF;
END$$

DELIMITER ;
