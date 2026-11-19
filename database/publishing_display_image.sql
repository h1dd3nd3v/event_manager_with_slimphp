ALTER TABLE publishing_entries
    ADD COLUMN display_image TEXT NULL AFTER bytes,
    ADD COLUMN display_image_public_id VARCHAR(255) NULL AFTER display_image;
