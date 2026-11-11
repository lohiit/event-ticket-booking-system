USE event_ticket_booking;

ALTER TABLE events
    ADD COLUMN image_path VARCHAR(255) NULL AFTER description;
