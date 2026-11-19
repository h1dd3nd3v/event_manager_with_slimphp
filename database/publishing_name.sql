ALTER TABLE publishing_entries
    ADD COLUMN name VARCHAR(255) NULL AFTER id;

UPDATE publishing_entries
SET name = filename
WHERE name IS NULL OR name = '';

ALTER TABLE publishing_entries
    MODIFY COLUMN name VARCHAR(255) NOT NULL;
