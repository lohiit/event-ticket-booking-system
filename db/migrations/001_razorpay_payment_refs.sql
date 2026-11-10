-- Apply once to an existing event_ticket_booking database.
USE event_ticket_booking;

ALTER TABLE payments
    ADD COLUMN provider_order_id VARCHAR(160) NULL AFTER payment_provider,
    ADD COLUMN signature_verified_at DATETIME NULL AFTER provider_reference,
    ADD UNIQUE KEY uq_payment_provider_order (payment_provider, provider_order_id);
