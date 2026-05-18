CREATE DATABASE IF NOT EXISTS belanjamart;
USE belanjamart;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- ================= CATEGORIES =================
CREATE TABLE categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100)
);

INSERT INTO categories (category_name) VALUES
('Elektronik'),
('Fashion'),
('Rumah Tangga');

-- ================= USERS (BUYER) =================
CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ================= SELLERS =================
CREATE TABLE sellers (
  seller_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ================= PRODUCTS =================
CREATE TABLE products (
  product_id INT AUTO_INCREMENT PRIMARY KEY,
  seller_id INT,
  category_id INT,
  product_name VARCHAR(150),
  price DECIMAL(12,2),
  stock INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  average_rating DECIMAL(3,2) DEFAULT 0.00,
  total_reviews INT DEFAULT 0,
  FOREIGN KEY (seller_id) REFERENCES sellers(seller_id),
  FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

-- ================= ORDERS =================
CREATE TABLE orders (
  order_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending','shipped','delivered','cancelled'),
  delivered_at TIMESTAMP NULL,
  tracking_number VARCHAR(100),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ================= ORDER ITEMS =================
CREATE TABLE order_items (
  order_item_id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT,
  product_id INT,
  quantity INT,
  price DECIMAL(12,2),
  FOREIGN KEY (order_id) REFERENCES orders(order_id),
  FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- ================= REVIEWS =================
CREATE TABLE reviews (
  review_id INT AUTO_INCREMENT PRIMARY KEY,
  order_item_id INT UNIQUE,
  rating INT CHECK (rating BETWEEN 1 AND 5),
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id)
);

-- ================= REVIEW IMAGES =================
CREATE TABLE review_images (
  image_id INT AUTO_INCREMENT PRIMARY KEY,
  review_id INT,
  image_url VARCHAR(255),
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (review_id) REFERENCES reviews(review_id) ON DELETE CASCADE
);

-- ================= TRIGGER: VALIDASI REVIEW =================
DELIMITER $$

CREATE TRIGGER before_review_insert_val
BEFORE INSERT ON reviews
FOR EACH ROW
BEGIN
    DECLARE v_status VARCHAR(20);

    SELECT o.status INTO v_status
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    WHERE oi.order_item_id = NEW.order_item_id;

    IF v_status != 'delivered' OR v_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Order harus delivered untuk review';
    END IF;
END$$

-- ================= TRIGGER: UPDATE RATING =================
CREATE TRIGGER after_review_insert_sync
AFTER INSERT ON reviews
FOR EACH ROW
BEGIN
    DECLARE v_product_id INT;

    SELECT product_id INTO v_product_id
    FROM order_items
    WHERE order_item_id = NEW.order_item_id;

    UPDATE products
    SET 
        average_rating = (
            SELECT AVG(r.rating)
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
END$$

DELIMITER ;

COMMIT;