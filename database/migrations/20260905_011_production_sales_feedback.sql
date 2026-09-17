ALTER TABLE rate_cards
    ADD COLUMN base_whole_bird_price DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER default_margin_percent;

ALTER TABLE production_stage_measurements
    ADD COLUMN defeathered_bird_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER stunned_bird_count;

ALTER TABLE customer_complaints
    ADD COLUMN submission_channel ENUM('staff','customer_portal') NOT NULL DEFAULT 'staff' AFTER details;

ALTER TABLE customer_complaints
    ADD COLUMN submitted_by_user_id BIGINT UNSIGNED NULL AFTER submission_channel;

ALTER TABLE customer_complaints
    ADD INDEX idx_complaint_submitter (submitted_by_user_id,submission_channel);

ALTER TABLE customer_complaints
    ADD CONSTRAINT fk_complaint_submitter FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

INSERT INTO settings (setting_key,setting_value,updated_by)
SELECT 'storage_door_alert_minutes','2',NULL
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='storage_door_alert_minutes');
