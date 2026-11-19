CREATE TABLE publishing_entries (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(255)    NOT NULL,
    filename        VARCHAR(255)    NOT NULL,
    url             TEXT            NOT NULL,
    public_id       VARCHAR(255)    NOT NULL,
    resource_type   ENUM('raw', 'video') NOT NULL,
    format          VARCHAR(32)     NOT NULL,
    bytes           BIGINT UNSIGNED NOT NULL,
    display_image   TEXT            NULL,
    display_image_public_id VARCHAR(255) NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_publishing_asset (resource_type, public_id),
    KEY idx_publishing_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
