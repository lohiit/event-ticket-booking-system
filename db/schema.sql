-- Event Ticket Booking System: planned 11-table MVP schema.
-- Monetary values are INR.
USE event_ticket_booking;

CREATE TABLE users (
    user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE venues (
    venue_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    address VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (venue_id),
    KEY idx_venues_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seats (
    seat_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    venue_id BIGINT UNSIGNED NOT NULL,
    section_name VARCHAR(80) NOT NULL DEFAULT 'General',
    row_label VARCHAR(20) NOT NULL,
    seat_number VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (seat_id),
    UNIQUE KEY uq_seat_label (venue_id, section_name, row_label, seat_number),
    KEY idx_seats_venue (venue_id),
    CONSTRAINT fk_seats_venue FOREIGN KEY (venue_id) REFERENCES venues (venue_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    category VARCHAR(80) NULL,
    status ENUM('draft', 'published', 'cancelled') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY idx_events_status_title (status, title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shows (
    show_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    venue_id BIGINT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    ticket_price DECIMAL(10,2) NOT NULL,
    status ENUM('scheduled', 'cancelled', 'completed') NOT NULL DEFAULT 'scheduled',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (show_id),
    KEY idx_shows_event_start (event_id, starts_at),
    KEY idx_shows_venue_start (venue_id, starts_at),
    CONSTRAINT fk_shows_event FOREIGN KEY (event_id) REFERENCES events (event_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_shows_venue FOREIGN KEY (venue_id) REFERENCES venues (venue_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE show_seats (
    show_seat_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    show_id BIGINT UNSIGNED NOT NULL,
    seat_id BIGINT UNSIGNED NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    seat_status ENUM('available', 'held', 'booked', 'unavailable') NOT NULL DEFAULT 'available',
    PRIMARY KEY (show_seat_id),
    UNIQUE KEY uq_show_seat (show_id, seat_id),
    KEY idx_show_seats_availability (show_id, seat_status),
    CONSTRAINT fk_show_seats_show FOREIGN KEY (show_id) REFERENCES shows (show_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_show_seats_seat FOREIGN KEY (seat_id) REFERENCES seats (seat_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seat_holds (
    hold_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    show_seat_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    hold_status ENUM('active', 'confirmed', 'released', 'expired') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (hold_id),
    KEY idx_holds_seat_status (show_seat_id, hold_status),
    KEY idx_holds_expiry (hold_status, expires_at),
    KEY idx_holds_user_status (user_id, hold_status),
    CONSTRAINT fk_holds_show_seat FOREIGN KEY (show_seat_id) REFERENCES show_seats (show_seat_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_holds_user FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
    booking_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    show_id BIGINT UNSIGNED NOT NULL,
    status ENUM('confirmed', 'cancelled') NOT NULL DEFAULT 'confirmed',
    total_amount DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at DATETIME NULL,
    PRIMARY KEY (booking_id),
    KEY idx_bookings_user_created (user_id, created_at),
    KEY idx_bookings_show_status (show_id, status),
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_show FOREIGN KEY (show_id) REFERENCES shows (show_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_seats (
    booking_seat_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT UNSIGNED NOT NULL,
    show_seat_id BIGINT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (booking_seat_id),
    UNIQUE KEY uq_booking_show_seat (booking_id, show_seat_id),
    KEY idx_booking_seats_show_seat (show_seat_id),
    CONSTRAINT fk_booking_seats_booking FOREIGN KEY (booking_id) REFERENCES bookings (booking_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_booking_seats_show_seat FOREIGN KEY (show_seat_id) REFERENCES show_seats (show_seat_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    payment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'INR',
    payment_status ENUM('pending', 'succeeded', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    payment_provider VARCHAR(80) NULL,
    provider_reference VARCHAR(160) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    KEY idx_payments_booking (booking_id, created_at),
    UNIQUE KEY uq_payment_provider_reference (payment_provider, provider_reference),
    CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings (booking_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seat_status_audit (
    audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    show_seat_id BIGINT UNSIGNED NOT NULL,
    old_status ENUM('available', 'held', 'booked', 'unavailable') NOT NULL,
    new_status ENUM('available', 'held', 'booked', 'unavailable') NOT NULL,
    changed_by_user_id BIGINT UNSIGNED NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (audit_id),
    KEY idx_seat_audit_seat_time (show_seat_id, changed_at),
    KEY idx_seat_audit_user_time (changed_by_user_id, changed_at),
    CONSTRAINT fk_seat_audit_show_seat FOREIGN KEY (show_seat_id) REFERENCES show_seats (show_seat_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_seat_audit_user FOREIGN KEY (changed_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
