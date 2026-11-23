ALTER TABLE orders
    MODIFY product_format ENUM('ebook', 'hardcover', 'paperback') NOT NULL;
