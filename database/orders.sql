CREATE TABLE orders (
    order_id            CHAR(32)       NOT NULL,
    product_name        VARCHAR(255)   NOT NULL,
    product_format      ENUM('ebook', 'hardcover') NOT NULL,
    amount_total        INT UNSIGNED   NULL COMMENT 'Smallest currency unit',
    currency            CHAR(3)        NULL,
    customer_email      VARCHAR(255)   NULL,
    checkout_session_id VARCHAR(255)  NULL,
    payment_intent_id   VARCHAR(255)  NULL,
    payment_status      ENUM('pending', 'paid', 'failed', 'expired') NOT NULL DEFAULT 'pending',
    fulfillment_status  ENUM('pending', 'processing', 'shipped', 'fulfilled') NOT NULL DEFAULT 'pending',
    shipping_address    JSON           NULL,
    created_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (order_id),
    UNIQUE KEY uq_orders_checkout_session_id (checkout_session_id),
    KEY idx_orders_payment_status (payment_status),
    KEY idx_orders_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
