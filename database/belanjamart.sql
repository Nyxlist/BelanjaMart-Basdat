-- =====================================================================
-- BelanjaMart - E-Commerce Marketplace
-- Full consolidated schema with sample seed data
--
-- HOW TO INSTALL
-- 1. Open phpMyAdmin and DROP database `belanjamart` if it exists
-- 2. Import this file (it will create everything from scratch)
-- 3. Default test accounts (password = "password123" for all):
--    - buyer1@mail.com   (Indonesian buyer)
--    - buyer2@mail.com   (US buyer, multi-currency demo)
--    - seller1@mail.com  (Top Rated Seller demo)
--    - seller2@mail.com  (New Seller demo)
--    - admin@mail.com    (Admin / moderator)
-- =====================================================================

DROP DATABASE IF EXISTS belanjamart;
CREATE DATABASE belanjamart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE belanjamart;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- =====================================================================
-- 1. REFERENCE TABLES (countries, currencies, taxes)
-- =====================================================================

CREATE TABLE countries (
    country_code   CHAR(2) PRIMARY KEY,           -- ISO-3166 alpha-2 (ID, US, ...)
    country_name   VARCHAR(80)  NOT NULL,
    currency_code  CHAR(3)      NOT NULL,         -- default currency for that country
    tax_rate       DECIMAL(5,4) NOT NULL DEFAULT 0,    -- e.g. 0.1100 = 11%
    base_shipping  DECIMAL(10,2) NOT NULL DEFAULT 0    -- baseline shipping in local currency
) ENGINE=InnoDB;

CREATE TABLE currencies (
    currency_code  CHAR(3) PRIMARY KEY,            -- ISO-4217
    symbol         VARCHAR(8)   NOT NULL,
    name           VARCHAR(60)  NOT NULL,
    -- exchange rate against USD: 1 USD = rate_to_usd of this currency
    rate_to_usd    DECIMAL(18,6) NOT NULL DEFAULT 1,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================================
-- 2. CATEGORIES
-- =====================================================================

CREATE TABLE categories (
    category_id    INT AUTO_INCREMENT PRIMARY KEY,
    category_name  VARCHAR(100) NOT NULL,
    icon           VARCHAR(20)  DEFAULT '📦',
    parent_id      INT NULL,
    FOREIGN KEY (parent_id) REFERENCES categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 3. UNIFIED ACCOUNTS  (single login table for all roles)
--    role-specific data lives in `buyer_profiles` and `seller_profiles`
-- =====================================================================

CREATE TABLE users (
    user_id        INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password       VARCHAR(255) NOT NULL,
    role           ENUM('buyer','seller','admin') NOT NULL DEFAULT 'buyer',
    avatar         VARCHAR(255) DEFAULT NULL,
    phone          VARCHAR(30)  DEFAULT NULL,
    country_code   CHAR(2)      DEFAULT 'ID',
    preferred_currency CHAR(3)  DEFAULT 'IDR',
    is_verified    TINYINT(1)   NOT NULL DEFAULT 0,    -- email/document verification
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at  TIMESTAMP    NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (country_code)       REFERENCES countries(country_code),
    FOREIGN KEY (preferred_currency) REFERENCES currencies(currency_code)
) ENGINE=InnoDB;

-- =====================================================================
-- 4. PROFILES (1:1 with users)
-- =====================================================================

CREATE TABLE buyer_profiles (
    user_id        INT PRIMARY KEY,
    bio            TEXT,
    total_orders   INT NOT NULL DEFAULT 0,
    total_spent    DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE seller_profiles (
    user_id        INT PRIMARY KEY,
    shop_name      VARCHAR(120) NOT NULL,
    description    TEXT,
    banner         VARCHAR(255) DEFAULT NULL,
    -- Reputation metrics (kept up to date by triggers / services)
    avg_rating         DECIMAL(3,2)  NOT NULL DEFAULT 0,
    total_reviews      INT NOT NULL DEFAULT 0,
    total_sales        INT NOT NULL DEFAULT 0,        -- successfully delivered orders
    total_cancellations INT NOT NULL DEFAULT 0,
    response_time_min  INT NOT NULL DEFAULT 0,        -- avg minutes to first chat reply
    cancellation_rate  DECIMAL(5,2) NOT NULL DEFAULT 0,   -- percent
    delivery_success_rate DECIMAL(5,2) NOT NULL DEFAULT 100,
    is_verified        TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 5. ADDRESSES (multiple per user, used for shipping)
-- =====================================================================

CREATE TABLE addresses (
    address_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    label         VARCHAR(60) DEFAULT 'Home',
    recipient     VARCHAR(120) NOT NULL,
    phone         VARCHAR(30)  NOT NULL,
    line1         VARCHAR(255) NOT NULL,
    line2         VARCHAR(255) DEFAULT NULL,
    city          VARCHAR(100) NOT NULL,
    state         VARCHAR(100) DEFAULT NULL,
    postal_code   VARCHAR(20)  DEFAULT NULL,
    country_code  CHAR(2)      NOT NULL,
    latitude      DECIMAL(10,7) DEFAULT NULL,
    longitude     DECIMAL(10,7) DEFAULT NULL,
    is_default    TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)      REFERENCES users(user_id)        ON DELETE CASCADE,
    FOREIGN KEY (country_code) REFERENCES countries(country_code)
) ENGINE=InnoDB;

-- =====================================================================
-- 6. PRODUCTS
-- =====================================================================

CREATE TABLE products (
    product_id     INT AUTO_INCREMENT PRIMARY KEY,
    seller_id      INT NOT NULL,                    -- references users.user_id (role=seller)
    category_id    INT,
    product_name   VARCHAR(180) NOT NULL,
    description    TEXT,
    price          DECIMAL(12,2) NOT NULL,          -- price in seller's currency
    currency_code  CHAR(3) NOT NULL DEFAULT 'IDR',
    stock          INT NOT NULL DEFAULT 0,
    image          VARCHAR(255) DEFAULT NULL,
    weight_grams   INT NOT NULL DEFAULT 500,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    average_rating DECIMAL(3,2) NOT NULL DEFAULT 0,
    total_reviews  INT NOT NULL DEFAULT 0,
    total_sold     INT NOT NULL DEFAULT 0,
    views          INT NOT NULL DEFAULT 0,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id)     REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id)   REFERENCES categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (currency_code) REFERENCES currencies(currency_code),
    INDEX idx_seller   (seller_id),
    INDEX idx_category (category_id),
    INDEX idx_active   (is_active),
    FULLTEXT KEY ft_name_desc (product_name, description)
) ENGINE=InnoDB;

CREATE TABLE product_images (
    image_id    INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT NOT NULL,
    image_url   VARCHAR(255) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 7. WISHLIST
-- =====================================================================

CREATE TABLE wishlist (
    wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    product_id  INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_wish (user_id, product_id),
    FOREIGN KEY (user_id)    REFERENCES users(user_id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 8. ORDERS
--    Statuses requested by user:
--      pending   - buyer has not paid OR seller has not prepared shipment
--      shipped   - item is being delivered
--      delivered - item has arrived to buyer
--      cancelled - cancelled by buyer or seller
--    Plus internal helpers: paid, processing (still belong to "pending" UX bucket)
-- =====================================================================

CREATE TABLE orders (
    order_id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,                       -- buyer
    address_id      INT NULL,
    order_date      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status          ENUM('pending','paid','processing','shipped','delivered','cancelled')
                       NOT NULL DEFAULT 'pending',
    payment_status  ENUM('unpaid','held','released','refunded') NOT NULL DEFAULT 'unpaid',
    payment_method  VARCHAR(40) DEFAULT 'simulation',
    subtotal        DECIMAL(14,2) NOT NULL DEFAULT 0,
    shipping_fee    DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_amount      DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount    DECIMAL(14,2) NOT NULL DEFAULT 0,
    currency_code   CHAR(3) NOT NULL DEFAULT 'IDR',
    exchange_rate   DECIMAL(18,6) NOT NULL DEFAULT 1,    -- vs USD at time of purchase
    tracking_number VARCHAR(100) DEFAULT NULL,
    courier         VARCHAR(60)  DEFAULT NULL,
    shipped_at      TIMESTAMP NULL,
    delivered_at    TIMESTAMP NULL,
    cancelled_at    TIMESTAMP NULL,
    notes           TEXT,
    FOREIGN KEY (user_id)       REFERENCES users(user_id),
    FOREIGN KEY (address_id)    REFERENCES addresses(address_id) ON DELETE SET NULL,
    FOREIGN KEY (currency_code) REFERENCES currencies(currency_code),
    INDEX idx_status (status),
    INDEX idx_user_date (user_id, order_date)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id      INT NOT NULL,
    product_id    INT NOT NULL,
    seller_id     INT NOT NULL,
    quantity      INT NOT NULL DEFAULT 1,
    price         DECIMAL(12,2) NOT NULL,        -- snapshot at purchase time
    FOREIGN KEY (order_id)   REFERENCES orders(order_id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (seller_id)  REFERENCES users(user_id)
) ENGINE=InnoDB;

-- Live delivery tracking simulation - seller/courier inserts events
CREATE TABLE order_tracking (
    tracking_id  INT AUTO_INCREMENT PRIMARY KEY,
    order_id     INT NOT NULL,
    event_label  VARCHAR(120) NOT NULL,
    location     VARCHAR(120) DEFAULT NULL,
    note         TEXT,
    happened_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    INDEX idx_order (order_id, happened_at)
) ENGINE=InnoDB;

-- =====================================================================
-- 9. CANCELLATIONS  (dynamic, customizable forms)
-- =====================================================================

CREATE TABLE cancellation_reasons (
    reason_id    INT AUTO_INCREMENT PRIMARY KEY,
    audience     ENUM('buyer','seller') NOT NULL,
    label        VARCHAR(160) NOT NULL,
    requires_note TINYINT(1) NOT NULL DEFAULT 0,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    sort_order   INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE cancellations (
    cancellation_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT NOT NULL,
    cancelled_by    ENUM('buyer','seller','admin') NOT NULL,
    actor_user_id   INT NOT NULL,
    reason_id       INT NULL,
    reason_text     VARCHAR(255),         -- denormalized snapshot
    note            TEXT,
    attachment      VARCHAR(255) DEFAULT NULL,   -- optional file upload
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)      REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (actor_user_id) REFERENCES users(user_id),
    FOREIGN KEY (reason_id)     REFERENCES cancellation_reasons(reason_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 10. REVIEWS
-- =====================================================================

CREATE TABLE reviews (
    review_id     INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT NOT NULL UNIQUE,
    user_id       INT NOT NULL,
    rating        INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    seller_rating INT NULL CHECK (seller_rating BETWEEN 1 AND 5),
    comment       TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)       REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE review_images (
    image_id   INT AUTO_INCREMENT PRIMARY KEY,
    review_id  INT NOT NULL,
    image_url  VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES reviews(review_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 11. SELLER BADGES
-- =====================================================================

CREATE TABLE badges (
    badge_id    INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(40) UNIQUE NOT NULL,
    label       VARCHAR(80) NOT NULL,
    description VARCHAR(255),
    icon        VARCHAR(20) DEFAULT '🏅',
    color       VARCHAR(20) DEFAULT '#f1c40f'
) ENGINE=InnoDB;

CREATE TABLE seller_badges (
    user_id     INT NOT NULL,
    badge_id    INT NOT NULL,
    awarded_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, badge_id),
    FOREIGN KEY (user_id)  REFERENCES users(user_id)  ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(badge_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 12. CHAT (order-based chat rooms with polling-based "real-time")
-- =====================================================================

CREATE TABLE chats (
    chat_id     INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT NULL,                    -- null = generic pre-purchase chat
    buyer_id    INT NOT NULL,
    seller_id   INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_message_at TIMESTAMP NULL,
    UNIQUE KEY uniq_chat_room (order_id, buyer_id, seller_id),
    FOREIGN KEY (order_id)  REFERENCES orders(order_id) ON DELETE SET NULL,
    FOREIGN KEY (buyer_id)  REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE chat_messages (
    message_id   INT AUTO_INCREMENT PRIMARY KEY,
    chat_id      INT NOT NULL,
    sender_id    INT NOT NULL,
    body         TEXT,
    attachment   VARCHAR(255) DEFAULT NULL,   -- image / file path under storage/chat
    attachment_type VARCHAR(40) DEFAULT NULL,
    is_read      TINYINT(1) NOT NULL DEFAULT 0,
    read_at      TIMESTAMP NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id)   REFERENCES chats(chat_id)   ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id),
    INDEX idx_chat_time (chat_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE chat_typing (
    chat_id    INT NOT NULL,
    user_id    INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (chat_id, user_id),
    FOREIGN KEY (chat_id) REFERENCES chats(chat_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 13. NOTIFICATIONS
-- =====================================================================

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    title      VARCHAR(150) NOT NULL,
    body       TEXT,
    icon       VARCHAR(20)  DEFAULT '🔔',
    link       VARCHAR(255) DEFAULT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- =====================================================================
-- 14. PRICING SUGGESTIONS  (dynamic pricing)
-- =====================================================================

CREATE TABLE price_suggestions (
    suggestion_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id    INT NOT NULL,
    source        VARCHAR(80) NOT NULL,            -- e.g. "Tokopedia", "Amazon", "manual"
    source_price  DECIMAL(14,2) NOT NULL,
    source_currency CHAR(3) NOT NULL DEFAULT 'USD',
    converted_price DECIMAL(14,2) NOT NULL,        -- in product's currency
    note          VARCHAR(255),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (source_currency) REFERENCES currencies(currency_code)
) ENGINE=InnoDB;

-- =====================================================================
-- 15. ADMIN MODERATION & FRAUD INDICATORS
-- =====================================================================

CREATE TABLE admin_actions (
    action_id   INT AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT NOT NULL,
    target_type ENUM('user','product','order','review','chat') NOT NULL,
    target_id   INT NOT NULL,
    action      VARCHAR(60)  NOT NULL,        -- ban, hide, verify, warn, ...
    note        TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE fraud_flags (
    flag_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NULL,
    order_id   INT NULL,
    score      INT NOT NULL DEFAULT 0,        -- 0..100
    reason     VARCHAR(255),
    resolved   TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(user_id)  ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 16. ACTIVITY LOG  (lightweight audit trail)
-- =====================================================================

CREATE TABLE activity_logs (
    log_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NULL,
    action     VARCHAR(80) NOT NULL,
    detail     TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 17. SAMPLE / SEED DATA
-- =====================================================================

INSERT INTO currencies (currency_code, symbol, name, rate_to_usd) VALUES
('USD', '$',     'US Dollar',          1.000000),
('IDR', 'Rp',    'Indonesian Rupiah',  15800.000000),
('SGD', 'S$',    'Singapore Dollar',   1.350000),
('MYR', 'RM',    'Malaysian Ringgit',  4.700000),
('JPY', '¥',     'Japanese Yen',       155.000000),
('EUR', '€',     'Euro',               0.920000),
('GBP', '£',     'British Pound',      0.790000);

INSERT INTO countries (country_code, country_name, currency_code, tax_rate, base_shipping) VALUES
('ID', 'Indonesia',     'IDR', 0.1100,  15000),
('US', 'United States', 'USD', 0.0700,  5),
('SG', 'Singapore',     'SGD', 0.0900,  3),
('MY', 'Malaysia',      'MYR', 0.0600,  10),
('JP', 'Japan',         'JPY', 0.1000,  500),
('GB', 'United Kingdom','GBP', 0.2000,  4),
('DE', 'Germany',       'EUR', 0.1900,  4);

INSERT INTO categories (category_name, icon) VALUES
('Elektronik',    '📱'),
('Fashion',       '👗'),
('Rumah Tangga',  '🏠'),
('Buku',          '📚'),
('Makanan',       '🍔'),
('Olahraga',      '⚽');

-- All sample passwords are "password123" (bcrypt hashed below)
-- hash generated with PHP password_hash('password123', PASSWORD_DEFAULT)
INSERT INTO users (name, email, password, role, country_code, preferred_currency, is_verified) VALUES
('Andi Pembeli',  'buyer1@mail.com',  '$2y$10$HzFjh.rPKE.el4gHxGQBuOUaKAnAPuHarIbzRJc4RccAmCQz642wO', 'buyer',  'ID', 'IDR', 1),
('Jane Buyer',    'buyer2@mail.com',  '$2y$10$HzFjh.rPKE.el4gHxGQBuOUaKAnAPuHarIbzRJc4RccAmCQz642wO', 'buyer',  'US', 'USD', 1),
('Toko Maju',     'seller1@mail.com', '$2y$10$HzFjh.rPKE.el4gHxGQBuOUaKAnAPuHarIbzRJc4RccAmCQz642wO', 'seller', 'ID', 'IDR', 1),
('Newbie Store',  'seller2@mail.com', '$2y$10$HzFjh.rPKE.el4gHxGQBuOUaKAnAPuHarIbzRJc4RccAmCQz642wO', 'seller', 'ID', 'IDR', 0),
('Admin',         'admin@mail.com',   '$2y$10$HzFjh.rPKE.el4gHxGQBuOUaKAnAPuHarIbzRJc4RccAmCQz642wO', 'admin',  'ID', 'IDR', 1);

INSERT INTO buyer_profiles (user_id, bio) VALUES
(1, 'Suka belanja kebutuhan rumah tangga.'),
(2, 'International tech enthusiast.');

INSERT INTO seller_profiles (user_id, shop_name, description, avg_rating, total_reviews, total_sales, cancellation_rate, delivery_success_rate, is_verified) VALUES
(3, 'Toko Maju Jaya',  'Penjual terpercaya sejak 2020.', 4.80, 120, 240, 1.50, 99.20, 1),
(4, 'Newbie Store',    'Toko baru, butuh dukungan kalian!', 0.00, 0, 0, 0.00, 100.00, 0);

INSERT INTO addresses (user_id, label, recipient, phone, line1, city, state, postal_code, country_code, latitude, longitude, is_default) VALUES
(1, 'Rumah', 'Andi Pembeli', '081234567890', 'Jl. Mawar No. 10', 'Jakarta', 'DKI Jakarta', '10110', 'ID', -6.2088, 106.8456, 1),
(2, 'Home',  'Jane Buyer',   '+15551234567', '123 Main St',      'Seattle', 'WA',          '98101', 'US', 47.6062, -122.3321, 1);

INSERT INTO products (seller_id, category_id, product_name, description, price, currency_code, stock, weight_grams, average_rating, total_reviews, total_sold) VALUES
(3, 1, 'Smartphone Pro X',     'Layar AMOLED 6.7", 256GB.',          5499000, 'IDR', 25, 350, 4.7, 56, 130),
(3, 1, 'Wireless Earbuds Z',   'Bluetooth 5.3, ANC aktif.',           899000, 'IDR', 60, 100, 4.6, 31, 90),
(3, 2, 'Kemeja Flanel Pria',   'Flanel premium, slim fit.',           199000, 'IDR', 100, 400, 4.8, 22, 65),
(3, 3, 'Set Panci Anti Lengket','5 pcs anti lengket food grade.',    459000, 'IDR', 40, 2500, 4.5, 11, 28),
(4, 4, 'Novel Petualangan',    'Cerita seru karya penulis lokal.',     79000, 'IDR', 200, 350, 0.0,  0,  0),
(4, 5, 'Snack Box Premium',    'Aneka camilan kekinian.',             129000, 'IDR', 80, 1200, 0.0,  0,  0);

INSERT INTO badges (code, label, description, icon, color) VALUES
('trusted_seller', 'Trusted Seller', 'Avg rating >= 4.5 with 50+ reviews', '🛡️', '#27ae60'),
('top_rated',      'Top Rated',      'Avg rating >= 4.8 with 100+ reviews', '⭐', '#f1c40f'),
('fast_response',  'Fast Response',  'Avg response < 30 minutes', '⚡', '#3498db'),
('verified',       'Verified',       'Identity / shop documents verified', '✅', '#9b59b6');

INSERT INTO seller_badges (user_id, badge_id) VALUES
(3, 1),
(3, 2),
(3, 4);

INSERT INTO cancellation_reasons (audience, label, requires_note, sort_order) VALUES
('buyer',  'Berubah pikiran',                       0, 1),
('buyer',  'Salah memilih produk / varian',         0, 2),
('buyer',  'Ongkir terlalu mahal',                  0, 3),
('buyer',  'Menemukan harga lebih murah di tempat lain', 1, 4),
('buyer',  'Lainnya',                               1, 99),
('seller', 'Stok kosong',                           0, 1),
('seller', 'Alamat pembeli tidak terjangkau',       0, 2),
('seller', 'Pembeli tidak respon',                  0, 3),
('seller', 'Indikasi pesanan mencurigakan',         1, 4),
('seller', 'Lainnya',                               1, 99);

-- A demonstration order for buyer1 from seller1
INSERT INTO orders (user_id, address_id, status, payment_status, payment_method,
                    subtotal, shipping_fee, tax_amount, total_amount, currency_code, exchange_rate,
                    tracking_number, courier, shipped_at, delivered_at)
VALUES
(1, 1, 'delivered', 'released', 'simulation',
 5499000, 15000, 604890, 6118890, 'IDR', 15800.000000,
 'JNE123456789', 'JNE',
 DATE_SUB(NOW(), INTERVAL 5 DAY),
 DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO order_items (order_id, product_id, seller_id, quantity, price)
VALUES (1, 1, 3, 1, 5499000);

INSERT INTO order_tracking (order_id, event_label, location, note, happened_at) VALUES
(1, 'Order placed',   'Jakarta',  'Pembeli berhasil checkout.',         DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 'Payment held',   'System',   'Dana ditahan (escrow simulasi).',    DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 'Packed',         'Jakarta',  'Penjual menyiapkan paket.',          DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 'Shipped',        'Jakarta',  'Diserahkan ke kurir JNE.',           DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 'In transit',     'Bandung',  'Paket sedang dalam perjalanan.',     DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 'Delivered',      'Jakarta',  'Paket diterima pembeli.',            DATE_SUB(NOW(), INTERVAL 1 DAY));

-- =====================================================================
-- 18. TRIGGERS - keep aggregate counters consistent
-- =====================================================================

DELIMITER $$

-- Only allow review when order is delivered
CREATE TRIGGER trg_review_before_insert
BEFORE INSERT ON reviews
FOR EACH ROW
BEGIN
    DECLARE v_status VARCHAR(20);
    SELECT o.status INTO v_status
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    WHERE oi.order_item_id = NEW.order_item_id;

    IF v_status IS NULL OR v_status <> 'delivered' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Order must be delivered before reviewing';
    END IF;
END$$

-- After review insert, update product rating + seller rating
CREATE TRIGGER trg_review_after_insert
AFTER INSERT ON reviews
FOR EACH ROW
BEGIN
    DECLARE v_product_id INT;
    DECLARE v_seller_id  INT;

    SELECT oi.product_id, oi.seller_id INTO v_product_id, v_seller_id
    FROM order_items oi
    WHERE oi.order_item_id = NEW.order_item_id;

    UPDATE products
    SET average_rating = (
            SELECT IFNULL(AVG(r.rating),0)
            FROM reviews r
            JOIN order_items oi ON r.order_item_id = oi.order_item_id
            WHERE oi.product_id = v_product_id
        ),
        total_reviews = (
            SELECT COUNT(*)
            FROM reviews r
            JOIN order_items oi ON r.order_item_id = oi.order_item_id
            WHERE oi.product_id = v_product_id
        )
    WHERE product_id = v_product_id;

    UPDATE seller_profiles
    SET avg_rating = (
            SELECT IFNULL(AVG(r.rating),0)
            FROM reviews r
            JOIN order_items oi ON r.order_item_id = oi.order_item_id
            WHERE oi.seller_id = v_seller_id
        ),
        total_reviews = (
            SELECT COUNT(*)
            FROM reviews r
            JOIN order_items oi ON r.order_item_id = oi.order_item_id
            WHERE oi.seller_id = v_seller_id
        )
    WHERE user_id = v_seller_id;
END$$

-- After order is delivered, increment seller's total_sales and product total_sold
CREATE TRIGGER trg_order_after_update
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF NEW.status = 'delivered' AND OLD.status <> 'delivered' THEN
        UPDATE seller_profiles sp
        JOIN order_items oi ON oi.seller_id = sp.user_id
        SET sp.total_sales = sp.total_sales + 1
        WHERE oi.order_id = NEW.order_id;

        UPDATE products p
        JOIN order_items oi ON oi.product_id = p.product_id
        SET p.total_sold = p.total_sold + oi.quantity
        WHERE oi.order_id = NEW.order_id;

        UPDATE buyer_profiles
        SET total_orders = total_orders + 1,
            total_spent  = total_spent + NEW.total_amount
        WHERE user_id = NEW.user_id;
    END IF;

    IF NEW.status = 'cancelled' AND OLD.status <> 'cancelled' THEN
        UPDATE seller_profiles sp
        JOIN order_items oi ON oi.seller_id = sp.user_id
        SET sp.total_cancellations = sp.total_cancellations + 1,
            sp.cancellation_rate = ROUND(
                (sp.total_cancellations + 1) /
                GREATEST(sp.total_sales + sp.total_cancellations + 1, 1) * 100, 2)
        WHERE oi.order_id = NEW.order_id;
    END IF;
END$$

DELIMITER ;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
