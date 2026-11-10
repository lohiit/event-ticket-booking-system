-- Initial catalogue records for the project. Values are in INR.
-- This script is for a fresh database and should be run once.
USE event_ticket_booking;

START TRANSACTION;

INSERT INTO venues (name, address)
VALUES
    ('Aarambh Performing Arts Hall', 'Andheri East, Mumbai, Maharashtra'),
    ('Rangmanch Open Air Theatre', 'Bandra West, Mumbai, Maharashtra');
SELECT venue_id INTO @venue_hall_id FROM venues WHERE name = 'Aarambh Performing Arts Hall';
SELECT venue_id INTO @venue_open_air_id FROM venues WHERE name = 'Rangmanch Open Air Theatre';

INSERT INTO seats (venue_id, section_name, row_label, seat_number)
VALUES
    (@venue_hall_id, 'Stalls', 'A', '1'),
    (@venue_hall_id, 'Stalls', 'A', '2'),
    (@venue_hall_id, 'Stalls', 'A', '3'),
    (@venue_hall_id, 'Stalls', 'B', '1'),
    (@venue_hall_id, 'Stalls', 'B', '2'),
    (@venue_hall_id, 'Stalls', 'B', '3'),
    (@venue_open_air_id, 'Front', 'A', '1'),
    (@venue_open_air_id, 'Front', 'A', '2'),
    (@venue_open_air_id, 'Front', 'A', '3'),
    (@venue_open_air_id, 'Front', 'B', '1'),
    (@venue_open_air_id, 'Front', 'B', '2'),
    (@venue_open_air_id, 'Front', 'B', '3');

INSERT INTO events (title, description, category, status)
VALUES
    ('City Beats Live', 'An evening of live music and local performers.', 'Music', 'published'),
    ('Satrang: Classical Fusion', 'A contemporary evening of Indian classical music and dance.', 'Arts', 'published');
SELECT event_id INTO @music_event_id FROM events WHERE title = 'City Beats Live';
SELECT event_id INTO @fusion_event_id FROM events WHERE title = 'Satrang: Classical Fusion';

INSERT INTO shows (event_id, venue_id, starts_at, ends_at, ticket_price, status)
VALUES
    (@music_event_id, @venue_hall_id, '2026-12-19 18:00:00', '2026-12-19 21:00:00', 799.00, 'scheduled'),
    (@fusion_event_id, @venue_open_air_id, '2026-12-20 18:30:00', '2026-12-20 21:00:00', 599.00, 'scheduled');

COMMIT;
