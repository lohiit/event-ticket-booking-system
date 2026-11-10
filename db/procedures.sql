-- All booking/hold state changes go through these procedures. Each procedure
-- locks the show row first to serialize changes for that show's seats.
USE event_ticket_booking
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_hold_seat$$
DROP PROCEDURE IF EXISTS sp_release_seat$$
DROP PROCEDURE IF EXISTS sp_confirm_booking$$
DROP PROCEDURE IF EXISTS sp_cancel_booking$$
DROP PROCEDURE IF EXISTS sp_release_expired_holds$$

CREATE PROCEDURE sp_hold_seat(
    IN p_user_id BIGINT UNSIGNED,
    IN p_show_id BIGINT UNSIGNED,
    IN p_seat_id BIGINT UNSIGNED
)
BEGIN
    DECLARE v_show_status VARCHAR(20) DEFAULT NULL;
    DECLARE v_show_seat_id BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_seat_status VARCHAR(20) DEFAULT NULL;
    DECLARE v_price DECIMAL(10,2) DEFAULT NULL;
    DECLARE v_count INT DEFAULT 0;
    DECLARE v_hold_id BIGINT UNSIGNED;
    DECLARE v_expires_at DATETIME;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET @etbs_actor_user_id = NULL;
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    SELECT status INTO v_show_status FROM shows
      WHERE show_id = p_show_id FOR UPDATE;
    IF v_show_status IS NULL OR v_show_status <> 'scheduled' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Show is unavailable';
    END IF;
    SELECT show_seat_id, seat_status, price INTO v_show_seat_id, v_seat_status, v_price
      FROM show_seats WHERE show_id = p_show_id AND seat_id = p_seat_id FOR UPDATE;
    IF v_show_seat_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seat is not part of this show';
    END IF;
    SELECT COUNT(*) INTO v_count FROM users WHERE user_id = p_user_id;
    IF v_count = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User does not exist';
    END IF;

    SELECT COUNT(*) INTO v_count FROM seat_holds
      WHERE show_seat_id = v_show_seat_id AND hold_status = 'active'
        AND expires_at <= UTC_TIMESTAMP();
    IF v_count > 0 THEN
        UPDATE seat_holds SET hold_status = 'expired'
          WHERE show_seat_id = v_show_seat_id AND hold_status = 'active'
            AND expires_at <= UTC_TIMESTAMP();
        UPDATE show_seats SET seat_status = 'available'
          WHERE show_seat_id = v_show_seat_id AND seat_status = 'held';
        SET v_seat_status = 'available';
    END IF;
    IF v_seat_status <> 'available' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seat is not available';
    END IF;

    SET v_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE);
    INSERT INTO seat_holds (show_seat_id, user_id, expires_at, hold_status)
      VALUES (v_show_seat_id, p_user_id, v_expires_at, 'active');
    SET v_hold_id = LAST_INSERT_ID();
    SET @etbs_actor_user_id = p_user_id;
    UPDATE show_seats SET seat_status = 'held' WHERE show_seat_id = v_show_seat_id;
    SET @etbs_actor_user_id = NULL;
    COMMIT;
    SELECT v_hold_id AS hold_id, v_expires_at AS expires_at, v_price AS price;
END$$

CREATE PROCEDURE sp_release_seat(
    IN p_user_id BIGINT UNSIGNED,
    IN p_show_id BIGINT UNSIGNED,
    IN p_seat_id BIGINT UNSIGNED
)
BEGIN
    DECLARE v_show_status VARCHAR(20) DEFAULT NULL;
    DECLARE v_show_seat_id BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_hold_id BIGINT UNSIGNED DEFAULT NULL;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET @etbs_actor_user_id = NULL;
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    SELECT status INTO v_show_status FROM shows
      WHERE show_id = p_show_id FOR UPDATE;
    IF v_show_status IS NULL OR v_show_status <> 'scheduled' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Show is unavailable';
    END IF;
    SELECT ss.show_seat_id, h.hold_id INTO v_show_seat_id, v_hold_id
      FROM show_seats ss
      JOIN seat_holds h ON h.show_seat_id = ss.show_seat_id
      WHERE ss.show_id = p_show_id AND ss.seat_id = p_seat_id
        AND h.user_id = p_user_id AND h.hold_status = 'active'
        AND h.expires_at > UTC_TIMESTAMP()
      FOR UPDATE;
    IF v_hold_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active seat hold not found for this user';
    END IF;

    UPDATE seat_holds SET hold_status = 'released' WHERE hold_id = v_hold_id;
    SET @etbs_actor_user_id = p_user_id;
    UPDATE show_seats SET seat_status = 'available'
      WHERE show_seat_id = v_show_seat_id AND seat_status = 'held';
    SET @etbs_actor_user_id = NULL;
    COMMIT;
    SELECT v_hold_id AS hold_id, 'released' AS status;
END$$

CREATE PROCEDURE sp_confirm_booking(
    IN p_user_id BIGINT UNSIGNED,
    IN p_show_id BIGINT UNSIGNED,
    IN p_provider_order_id VARCHAR(160),
    IN p_provider_payment_id VARCHAR(160)
)
BEGIN
    DECLARE v_show_status VARCHAR(20) DEFAULT NULL;
    DECLARE v_booking_id BIGINT UNSIGNED;
    DECLARE v_count INT DEFAULT 0;
    DECLARE v_total DECIMAL(10,2) DEFAULT 0.00;
    DECLARE v_payment_id BIGINT UNSIGNED;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET @etbs_actor_user_id = NULL;
        ROLLBACK;
        RESIGNAL;
    END;

    IF p_provider_order_id IS NULL OR p_provider_order_id = ''
       OR p_provider_payment_id IS NULL OR p_provider_payment_id = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Verified Razorpay order and payment IDs are required';
    END IF;

    START TRANSACTION;
    SELECT status INTO v_show_status FROM shows
      WHERE show_id = p_show_id FOR UPDATE;
    IF v_show_status IS NULL OR v_show_status <> 'scheduled' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Show is unavailable';
    END IF;
    SELECT COUNT(*) INTO v_count FROM seat_holds h
      JOIN show_seats ss ON ss.show_seat_id = h.show_seat_id
      WHERE h.user_id = p_user_id AND ss.show_id = p_show_id
        AND h.hold_status = 'active' AND h.expires_at > UTC_TIMESTAMP()
        AND ss.seat_status = 'held';
    IF v_count = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No active seat holds to confirm';
    END IF;
    SELECT COALESCE(SUM(ss.price), 0.00) INTO v_total
      FROM seat_holds h JOIN show_seats ss ON ss.show_seat_id = h.show_seat_id
      WHERE h.user_id = p_user_id AND ss.show_id = p_show_id
        AND h.hold_status = 'active' AND h.expires_at > UTC_TIMESTAMP()
        AND ss.seat_status = 'held';

    INSERT INTO bookings (user_id, show_id, status, total_amount)
      VALUES (p_user_id, p_show_id, 'confirmed', v_total);
    SET v_booking_id = LAST_INSERT_ID();
    INSERT INTO payments
        (booking_id, amount, currency, payment_status, payment_provider,
         provider_order_id, provider_reference, signature_verified_at)
      VALUES
        (v_booking_id, v_total, 'INR', 'succeeded', 'razorpay',
         p_provider_order_id, p_provider_payment_id, UTC_TIMESTAMP());
    SET v_payment_id = LAST_INSERT_ID();
    INSERT INTO booking_seats (booking_id, show_seat_id, unit_price)
      SELECT v_booking_id, ss.show_seat_id, ss.price
      FROM seat_holds h JOIN show_seats ss ON ss.show_seat_id = h.show_seat_id
      WHERE h.user_id = p_user_id AND ss.show_id = p_show_id
        AND h.hold_status = 'active' AND h.expires_at > UTC_TIMESTAMP()
        AND ss.seat_status = 'held';
    SET @etbs_actor_user_id = p_user_id;
    UPDATE show_seats ss JOIN seat_holds h ON h.show_seat_id = ss.show_seat_id
      SET ss.seat_status = 'booked'
      WHERE h.user_id = p_user_id AND ss.show_id = p_show_id
        AND h.hold_status = 'active' AND h.expires_at > UTC_TIMESTAMP()
        AND ss.seat_status = 'held';
    UPDATE seat_holds h JOIN show_seats ss ON ss.show_seat_id = h.show_seat_id
      SET h.hold_status = 'confirmed'
      WHERE h.user_id = p_user_id AND ss.show_id = p_show_id
        AND h.hold_status = 'active' AND h.expires_at > UTC_TIMESTAMP();
    SET @etbs_actor_user_id = NULL;
    COMMIT;
    SELECT v_booking_id AS booking_id, v_payment_id AS payment_id,
           v_total AS total_amount, 'INR' AS currency;
END$$

CREATE PROCEDURE sp_cancel_booking(
    IN p_user_id BIGINT UNSIGNED,
    IN p_booking_id BIGINT UNSIGNED
)
BEGIN
    DECLARE v_show_id BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_booking_status VARCHAR(20) DEFAULT NULL;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET @etbs_actor_user_id = NULL;
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    SELECT show_id, status INTO v_show_id, v_booking_status FROM bookings
      WHERE booking_id = p_booking_id AND user_id = p_user_id FOR UPDATE;
    IF v_show_id IS NULL OR v_booking_status <> 'confirmed' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Confirmed booking not found for this user';
    END IF;
    SELECT status INTO v_booking_status FROM shows WHERE show_id = v_show_id FOR UPDATE;
    SET @etbs_actor_user_id = p_user_id;
    UPDATE show_seats ss JOIN booking_seats bs ON bs.show_seat_id = ss.show_seat_id
      SET ss.seat_status = 'available'
      WHERE bs.booking_id = p_booking_id AND ss.seat_status = 'booked';
    UPDATE bookings SET status = 'cancelled', cancelled_at = UTC_TIMESTAMP()
      WHERE booking_id = p_booking_id AND user_id = p_user_id AND status = 'confirmed';
    SET @etbs_actor_user_id = NULL;
    COMMIT;
    SELECT p_booking_id AS booking_id, 'cancelled' AS status;
END$$

CREATE PROCEDURE sp_release_expired_holds()
BEGIN
    DECLARE v_done BOOLEAN DEFAULT FALSE;
    DECLARE v_show_id BIGINT UNSIGNED;
    DECLARE v_show_status VARCHAR(20);
    DECLARE v_released_count INT DEFAULT 0;
    DECLARE shows_cursor CURSOR FOR
        SELECT DISTINCT ss.show_id FROM seat_holds h
        JOIN show_seats ss ON ss.show_seat_id = h.show_seat_id
        WHERE h.hold_status = 'active' AND h.expires_at <= UTC_TIMESTAMP()
        ORDER BY ss.show_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET @etbs_actor_user_id = NULL;
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    OPEN shows_cursor;
    release_loop: LOOP
        FETCH shows_cursor INTO v_show_id;
        IF v_done THEN
            LEAVE release_loop;
        END IF;
        SELECT status INTO v_show_status FROM shows WHERE show_id = v_show_id FOR UPDATE;
        UPDATE show_seats ss JOIN seat_holds h ON h.show_seat_id = ss.show_seat_id
          SET ss.seat_status = 'available'
          WHERE ss.show_id = v_show_id AND ss.seat_status = 'held'
            AND h.hold_status = 'active' AND h.expires_at <= UTC_TIMESTAMP();
        UPDATE seat_holds h JOIN show_seats ss ON ss.show_seat_id = h.show_seat_id
          SET h.hold_status = 'expired'
          WHERE ss.show_id = v_show_id AND h.hold_status = 'active'
            AND h.expires_at <= UTC_TIMESTAMP();
        SET v_released_count = v_released_count + ROW_COUNT();
    END LOOP;
    CLOSE shows_cursor;
    SET @etbs_actor_user_id = NULL;
    COMMIT;
    SELECT v_released_count AS released_count;
END$$

DELIMITER ;
