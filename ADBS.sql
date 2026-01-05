-- Enable necessary extensions
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Drop tables in correct order (if needed for reset)
DROP TABLE IF EXISTS shipping CASCADE;
DROP TABLE IF EXISTS order_items CASCADE;
DROP TABLE IF EXISTS orders CASCADE;
DROP TABLE IF EXISTS product_reviews CASCADE;
DROP TABLE IF EXISTS products CASCADE;
DROP TABLE IF EXISTS categories CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TYPE IF EXISTS user_role CASCADE;
DROP TYPE IF EXISTS order_status CASCADE;
DROP TYPE IF EXISTS product_condition CASCADE;
DROP TYPE IF EXISTS payment_method CASCADE;
DROP TYPE IF EXISTS payment_status CASCADE;

-- Create ENUM types
CREATE TYPE user_role AS ENUM ('buyer', 'seller', 'admin');
CREATE TYPE order_status AS ENUM ('pending', 'confirmed', 'shipped', 'delivered', 'cancelled');
CREATE TYPE product_condition AS ENUM ('new', 'like_new', 'good', 'fair', 'poor');
CREATE TYPE payment_method AS ENUM ('cash_on_delivery', 'bank_transfer', 'credit_card', 'tele_birr', 'cbe_birr');
CREATE TYPE payment_status AS ENUM ('pending', 'paid', 'failed');

-- Create tables
CREATE TABLE users (
    user_id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(50) DEFAULT 'Mekelle',
    role user_role DEFAULT 'buyer',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP
);

CREATE TABLE categories (
    category_id SERIAL PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    parent_id INTEGER,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(category_id) ON DELETE SET NULL
);

CREATE TABLE products (
    product_id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    brand VARCHAR(100) NOT NULL,
    model VARCHAR(100),
    color VARCHAR(50),
    purchase_date DATE,
    condition product_condition DEFAULT 'good',
    specifications JSONB,
    price DECIMAL(10,2) NOT NULL CHECK (price > 0),
    stock_quantity INTEGER DEFAULT 1 CHECK (stock_quantity >= 0),
    total_sales INTEGER DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0,
    is_available BOOLEAN DEFAULT TRUE,
    category_id INTEGER NOT NULL,
    seller_id INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE orders (
    order_id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL CHECK (total_amount >= 0),
    status order_status DEFAULT 'pending',
    payment_method payment_method,
    payment_status payment_status DEFAULT 'pending',
    notes TEXT,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE order_items (
    order_item_id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL CHECK (quantity > 0),
    unit_price DECIMAL(10,2) NOT NULL CHECK (unit_price >= 0),
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

CREATE TABLE shipping (
    shipping_id SERIAL PRIMARY KEY,
    order_id INTEGER UNIQUE NOT NULL,
    shipping_address TEXT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    status order_status DEFAULT 'pending',
    tracking_number VARCHAR(100),
    shipping_cost DECIMAL(10,2) DEFAULT 50.00,
    estimated_delivery DATE,
    actual_delivery DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);

CREATE TABLE product_reviews (
    review_id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE(product_id, user_id)
);

-- Create indexes for better performance
CREATE INDEX idx_products_seller_id ON products(seller_id);
CREATE INDEX idx_products_category_id ON products(category_id);
CREATE INDEX idx_products_is_available ON products(is_available) WHERE is_available = TRUE;
CREATE INDEX idx_orders_user_id ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_product_id ON order_items(product_id);
CREATE INDEX idx_shipping_order_id ON shipping(order_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);

-- Create functions

-- Function to update product rating
CREATE OR REPLACE FUNCTION update_product_rating()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE products
    SET average_rating = (
        SELECT COALESCE(AVG(rating), 0)
        FROM product_reviews
        WHERE product_id = NEW.product_id
    ),
    updated_at = CURRENT_TIMESTAMP
    WHERE product_id = NEW.product_id;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger for product rating updates
CREATE TRIGGER trg_update_product_rating
AFTER INSERT OR UPDATE OR DELETE ON product_reviews
FOR EACH ROW
EXECUTE FUNCTION update_product_rating();

-- Function to calculate discounted price based on condition
CREATE OR REPLACE FUNCTION calculate_discounted_price(
    original_price DECIMAL,
    product_condition product_condition
)
RETURNS DECIMAL AS $$
DECLARE
    discount_rate DECIMAL;
    discounted_price DECIMAL;
BEGIN
    -- Define discount rates based on condition
    CASE product_condition
        WHEN 'new' THEN discount_rate := 0.05; -- 5% discount for new
        WHEN 'like_new' THEN discount_rate := 0.10; -- 10% discount
        WHEN 'good' THEN discount_rate := 0.15; -- 15% discount
        WHEN 'fair' THEN discount_rate := 0.25; -- 25% discount
        WHEN 'poor' THEN discount_rate := 0.40; -- 40% discount
        ELSE discount_rate := 0.10; -- Default 10%
    END CASE;
    
    -- Calculate discounted price
    discounted_price := original_price * (1 - discount_rate);
    
    -- Ensure price is not negative
    IF discounted_price < 0 THEN
        discounted_price := 0;
    END IF;
    
    RETURN ROUND(discounted_price, 2);
END;
$$ LANGUAGE plpgsql;

-- Function to update order total amount
CREATE OR REPLACE FUNCTION update_order_total()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE orders
    SET total_amount = (
        SELECT COALESCE(SUM(quantity * unit_price), 0)
        FROM order_items
        WHERE order_id = NEW.order_id
    ) + COALESCE(
        (SELECT shipping_cost FROM shipping WHERE order_id = NEW.order_id),
        0
    ),
    updated_at = CURRENT_TIMESTAMP
    WHERE order_id = NEW.order_id;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger for order total updates
CREATE TRIGGER trg_update_order_total
AFTER INSERT OR UPDATE OR DELETE ON order_items
FOR EACH ROW
EXECUTE FUNCTION update_order_total();

-- Function to update product stock when order is cancelled
CREATE OR REPLACE FUNCTION restore_product_stock()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.status != 'cancelled' AND NEW.status = 'cancelled' THEN
        UPDATE products p
        SET stock_quantity = p.stock_quantity + oi.quantity,
            total_sales = p.total_sales - oi.quantity,
            updated_at = CURRENT_TIMESTAMP
        FROM order_items oi
        WHERE oi.order_id = NEW.order_id
        AND p.product_id = oi.product_id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger for restoring stock on order cancellation
CREATE TRIGGER trg_restore_product_stock
AFTER UPDATE OF status ON orders
FOR EACH ROW
EXECUTE FUNCTION restore_product_stock();

-- Function to update timestamps
CREATE OR REPLACE FUNCTION update_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Triggers for automatic timestamp updates
CREATE TRIGGER trg_update_users_timestamp
BEFORE UPDATE ON users
FOR EACH ROW
EXECUTE FUNCTION update_timestamp();

CREATE TRIGGER trg_update_products_timestamp
BEFORE UPDATE ON products
FOR EACH ROW
EXECUTE FUNCTION update_timestamp();

CREATE TRIGGER trg_update_orders_timestamp
BEFORE UPDATE ON orders
FOR EACH ROW
EXECUTE FUNCTION update_timestamp();

CREATE TRIGGER trg_update_shipping_timestamp
BEFORE UPDATE ON shipping
FOR EACH ROW
EXECUTE FUNCTION update_timestamp();

-- Function to get product statistics for seller
CREATE OR REPLACE FUNCTION get_seller_statistics(seller_id_param INTEGER)
RETURNS TABLE (
    total_products INTEGER,
    active_products INTEGER,
    total_sales INTEGER,
    total_revenue DECIMAL,
    average_rating DECIMAL
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        COUNT(p.product_id)::INTEGER as total_products,
        COUNT(CASE WHEN p.is_available AND p.stock_quantity > 0 THEN 1 END)::INTEGER as active_products,
        COALESCE(SUM(p.total_sales), 0)::INTEGER as total_sales,
        COALESCE(SUM(p.total_sales * p.price), 0)::DECIMAL as total_revenue,
        COALESCE(AVG(p.average_rating), 0)::DECIMAL as average_rating
    FROM products p
    WHERE p.seller_id = seller_id_param;
END;
$$ LANGUAGE plpgsql;

-- Trigger to ensure new products are always available
CREATE OR REPLACE FUNCTION ensure_product_available()
RETURNS TRIGGER AS $$
BEGIN
    -- Always set is_available to TRUE for new products
    NEW.is_available := TRUE;
    
    -- Ensure stock is at least 1 if not specified
    IF NEW.stock_quantity IS NULL OR NEW.stock_quantity < 1 THEN
        NEW.stock_quantity := 1;
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger that runs before insert
CREATE TRIGGER trg_ensure_product_available
BEFORE INSERT ON products
FOR EACH ROW
EXECUTE FUNCTION ensure_product_available();

-- Insert initial categories (matches your PHP code structure)
INSERT INTO categories (category_name, parent_id, description) VALUES
-- Parent categories
('Smartphones', NULL, 'Mobile phones and smartphones'),
('Laptops', NULL, 'Laptops and notebooks'),
('Tablets', NULL, 'Tablets and iPads'),
('Accessories', NULL, 'Phone and laptop accessories'),

-- Child categories for Smartphones
('iPhone', 1, 'Apple iPhone devices'),
('Android Phones', 1, 'Android smartphones'),
('Feature Phones', 1, 'Basic mobile phones'),

-- Child categories for Laptops
('Windows Laptops', 2, 'Laptops running Windows'),
('MacBooks', 2, 'Apple MacBook laptops'),
('Gaming Laptops', 2, 'High-performance gaming laptops'),

-- Child categories for Accessories
('Headphones', 4, 'Headphones and earphones'),
('Chargers', 4, 'Chargers and adapters'),
('Cases & Covers', 4, 'Phone and laptop cases'),
('Cables', 4, 'USB and charging cables');

-- Insert sample users (NOW INCLUDING USER WITH ID 8)
INSERT INTO users (username, email, password_hash, full_name, phone, address, city, role) 
VALUES 
('Gebretnsae', 'gerewani@gmail.com', crypt('gere123', gen_salt('bf')), 'Gere User', '+251913243556', 'Mekelle University', 'Mekelle', 'seller'),
('shshay', 'shshay@gmail.com', crypt('shshay123', gen_salt('bf')), 'Shshay Seller', '+251926345241', 'Mekelle university', 'Mekelle', 'seller'),
('Daniel', 'daniel@gmail.com', crypt('dani123', gen_salt('bf')), 'Dani Buyer', '+251976543287', 'Hawelti', 'Mekelle', 'buyer'),
('Dawit', 'dawit@gmail.com', crypt('dave123', gen_salt('bf')), 'Dawit User', '+251913246754', 'Mekelle University', 'Mekelle', 'admin'),
('Eyob', 'eyob@gmai.com', crypt('eyob123', gen_salt('bf')), 'Eyob Seller', '+2519265423176', 'Adi Haki', 'Mekelle', 'seller'),
('Aklilu', 'aklilu@gmail.com', crypt('aklil123', gen_salt('bf')), 'Aklilu Buyer', '+251933653987', 'Quha', 'Mekelle', 'buyer'),
('Tsion', 'tsion@gmail.com', crypt('tsion123', gen_salt('bf')), 'Tsion Seller', '+25197645112', 'Qedamay Weyane', 'Mekelle', 'seller'),
-- ADDED: User with ID 8 to match your session
('TestSeller8', 'testseller8@gmail.com', crypt('test123', gen_salt('bf')), 'Test Seller 8', '+251911111111', 'Mekelle', 'Mekelle', 'seller');

-- Insert sample products (using existing seller IDs 1-8)
INSERT INTO products (name, description, brand, model, color, purchase_date, condition, specifications, price, stock_quantity, category_id, seller_id) 
VALUES 
('iPhone 13 Pro', 'Excellent condition, 6 months old, battery health 95%', 'Apple', 'iPhone 13 Pro', 'Graphite', '2023-01-15', 'like_new', 
 '{"storage": "256GB", "ram": "6GB", "battery": "3095mAh", "screen": "6.1inch Super Retina XDR"}', 
 850.00, 5, 5, 1),

('Dell XPS 15', 'Like new laptop, used for 3 months, comes with original box', 'Dell', 'XPS 15 9510', 'Silver', '2023-02-20', 'like_new',
 '{"storage": "512GB SSD", "ram": "16GB", "battery": "86Wh", "screen": "15.6inch 4K UHD"}',
 1200.00, 10, 8, 2),

('Samsung Galaxy S21', 'Good condition, minor scratches on screen, works perfectly', 'Samsung', 'Galaxy S21', 'Phantom Gray', '2022-05-10', 'good',
 '{"storage": "128GB", "ram": "8GB", "battery": "4000mAh", "screen": "6.2inch Dynamic AMOLED"}',
 550.00, 15, 6, 3),

('AirPods Pro', 'New, sealed box, 2nd generation', 'Apple', 'AirPods Pro 2', 'White', '2023-04-01', 'new',
 '{"storage": "N/A", "ram": "N/A", "battery": "6 hours", "screen": "N/A"}',
 200.00, 20, 9, 4),

('Lenovo ThinkPad', 'Fair condition, keyboard worn but functional, great for students', 'Lenovo', 'ThinkPad T480', 'Black', '2020-08-15', 'fair',
 '{"storage": "256GB SSD", "ram": "8GB", "battery": "24Wh + 24Wh", "screen": "14inch FHD"}',
 400.00, 25, 8, 5);

-- Create views for common queries

-- View for available products (what products.php uses)
CREATE VIEW available_products AS
SELECT 
    p.*,
    c.category_name,
    u.full_name as seller_name,
    u.city as seller_city,
    u.phone as seller_phone,
    calculate_discounted_price(p.price, p.condition) as discounted_price
FROM products p
JOIN categories c ON p.category_id = c.category_id
JOIN users u ON p.seller_id = u.user_id
WHERE p.is_available = TRUE 
  AND p.stock_quantity > 0
  AND u.is_active = TRUE
ORDER BY p.created_at DESC;

-- View for seller dashboard
CREATE VIEW seller_dashboard AS
SELECT 
    u.user_id,
    u.full_name,
    COUNT(p.product_id) as total_products,
    COUNT(CASE WHEN p.is_available AND p.stock_quantity > 0 THEN 1 END) as active_products,
    COALESCE(SUM(p.total_sales), 0) as total_sold,
    COALESCE(SUM(p.total_sales * p.price), 0) as total_revenue
FROM users u
LEFT JOIN products p ON u.user_id = p.seller_id
WHERE u.role = 'seller'
GROUP BY u.user_id, u.full_name;

-- View for user order history
CREATE VIEW user_orders_summary AS
SELECT 
    o.*,
    COUNT(oi.order_item_id) as item_count,
    STRING_AGG(p.name, ', ') as product_names,
    s.shipping_address,
    s.phone as shipping_phone,
    s.status as shipping_status,
    s.shipping_cost
FROM orders o
LEFT JOIN order_items oi ON o.order_id = oi.order_id
LEFT JOIN products p ON oi.product_id = p.product_id
LEFT JOIN shipping s ON o.order_id = s.order_id
GROUP BY o.order_id, s.shipping_id, s.shipping_address, s.phone, s.status, s.shipping_cost;

-- Grant permissions (adjust as needed for your PostgreSQL setup)
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO postgres;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO postgres;

-- Add constraints for better data integrity
ALTER TABLE products ADD CONSTRAINT chk_price_positive CHECK (price > 0);
ALTER TABLE products ADD CONSTRAINT chk_stock_non_negative CHECK (stock_quantity >= 0);
ALTER TABLE order_items ADD CONSTRAINT chk_quantity_positive CHECK (quantity > 0);
ALTER TABLE orders ADD CONSTRAINT chk_total_amount_non_negative CHECK (total_amount >= 0);

-- Create indexes for full-text search (optional)
CREATE INDEX idx_products_search ON products 
USING GIN (to_tsvector('english', name || ' ' || description || ' ' || brand || ' ' || model));

-- Create materialized view for frequently accessed data (refresh as needed)
CREATE MATERIALIZED VIEW popular_products AS
SELECT 
    p.product_id,
    p.name,
    p.brand,
    p.price,
    p.average_rating,
    p.total_sales,
    calculate_discounted_price(p.price, p.condition) as discounted_price,
    c.category_name,
    u.full_name as seller_name
FROM products p
JOIN categories c ON p.category_id = c.category_id
JOIN users u ON p.seller_id = u.user_id
WHERE p.is_available = TRUE 
  AND p.stock_quantity > 0
ORDER BY p.total_sales DESC, p.average_rating DESC
LIMIT 50;

-- Refresh function for materialized view
CREATE OR REPLACE FUNCTION refresh_popular_products()
RETURNS VOID AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY popular_products;
END;
$$ LANGUAGE plpgsql;

-- Create a function to search products with filters
CREATE OR REPLACE FUNCTION search_products(
    search_text TEXT DEFAULT NULL,
    category_id_param INTEGER DEFAULT NULL,
    min_price_param DECIMAL DEFAULT 0,
    max_price_param DECIMAL DEFAULT 10000,
    condition_param product_condition DEFAULT NULL,
    seller_id_param INTEGER DEFAULT NULL
)
RETURNS TABLE (
    product_id INTEGER,
    name VARCHAR,
    brand VARCHAR,
    model VARCHAR,
    condition product_condition,
    price DECIMAL,
    discounted_price DECIMAL,
    stock_quantity INTEGER,
    category_name VARCHAR,
    seller_name VARCHAR,
    city VARCHAR,
    average_rating DECIMAL
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        p.product_id,
        p.name,
        p.brand,
        p.model,
        p.condition,
        p.price,
        calculate_discounted_price(p.price, p.condition) as discounted_price,
        p.stock_quantity,
        c.category_name,
        u.full_name as seller_name,
        u.city,
        p.average_rating
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    JOIN users u ON p.seller_id = u.user_id
    WHERE p.is_available = TRUE
      AND p.stock_quantity > 0
      AND u.is_active = TRUE
      AND p.price BETWEEN min_price_param AND max_price_param
      AND (category_id_param IS NULL OR p.category_id = category_id_param)
      AND (condition_param IS NULL OR p.condition = condition_param)
      AND (seller_id_param IS NULL OR p.seller_id = seller_id_param)
      AND (
          search_text IS NULL 
          OR to_tsvector('english', p.name || ' ' || p.description || ' ' || p.brand || ' ' || p.model) 
             @@ plainto_tsquery('english', search_text)
          OR p.name ILIKE '%' || search_text || '%'
          OR p.brand ILIKE '%' || search_text || '%'
          OR p.model ILIKE '%' || search_text || '%'
      )
    ORDER BY p.created_at DESC;
END;
$$ LANGUAGE plpgsql;

-- Insert some sample reviews
INSERT INTO product_reviews (product_id, user_id, rating, comment) 
VALUES 
(1, 3, 5, 'Excellent product, exactly as described!'),
(2, 3, 4, 'Great laptop, fast shipping'),
(1, 2, 4, 'Good condition, happy with purchase');

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- 1. Show all users with their IDs
SELECT '=== ALL USERS IN DATABASE ===' as title;
SELECT user_id, username, email, role, full_name FROM users ORDER BY user_id;

-- 2. Show all available products
SELECT '=== ALL AVAILABLE PRODUCTS ===' as title;
SELECT 
    p.product_id,
    p.name,
    p.price,
    p.stock_quantity,
    p.is_available,
    p.seller_id,
    u.username as seller_username,
    u.full_name as seller_name,
    c.category_name
FROM products p
JOIN users u ON p.seller_id = u.user_id
JOIN categories c ON p.category_id = c.category_id
WHERE p.is_available = TRUE AND p.stock_quantity > 0
ORDER BY p.created_at DESC;

-- 3. Test product insertion with seller_id = 8
SELECT '=== TEST: INSERT NEW PRODUCT WITH SELLER_ID = 8 ===' as title;
-- This is what happens when you submit the add_product.php form
/*
INSERT INTO products (
    name, description, brand, model, color, purchase_date, 
    condition, specifications, price, stock_quantity, 
    category_id, seller_id
) VALUES (
    'Test Product from Seller 8',
    'This is a test product added by seller with ID 8',
    'TestBrand',
    'TestModel',
    'Black',
    '2023-01-01',
    'good',
    '{"storage": "128GB", "ram": "8GB", "battery": "4000mAh", "screen": "6.1inch"}',
    299.99,
    10,
    6,  -- Android Phones category
    8   -- Seller ID 8 (TestSeller8) - THIS NOW EXISTS!
);
*/

-- 4. Show what products.php will display
SELECT '=== WHAT PRODUCTS.PHP WILL DISPLAY ===' as title;
SELECT 
    p.product_id,
    p.name,
    p.brand,
    p.price,
    p.stock_quantity,
    p.created_at,
    u.user_id as seller_id,
    u.username as seller_username,
    c.category_name,
    'AVAILABLE IN MARKET' as status
FROM products p
JOIN users u ON p.seller_id = u.user_id
JOIN categories c ON p.category_id = c.category_id
WHERE p.is_available = TRUE 
  AND p.stock_quantity > 0
  AND u.is_active = TRUE
ORDER BY p.created_at DESC;

-- 5. Verify all seller IDs exist
SELECT '=== VERIFY ALL SELLER IDs EXIST ===' as title;
SELECT 
    DISTINCT p.seller_id,
    u.username,
    u.full_name,
    CASE 
        WHEN u.user_id IS NOT NULL THEN '✓ EXISTS'
        ELSE '✗ MISSING'
    END as status
FROM products p
LEFT JOIN users u ON p.seller_id = u.user_id;

-- Final success message
SELECT '=== DATABASE SETUP COMPLETE ===' as title;
SELECT 'You can now add products with seller_id = 8' as message;
SELECT 'Available seller IDs: ' || STRING_AGG(user_id::text, ', ') as available_sellers
FROM users WHERE role = 'seller';



SELECT current_database();
SELECT table_name FROM information_schema.tables WHERE table_schema = 'public';


DO $$
DECLARE
    r RECORD;
BEGIN
    FOR r IN (
        SELECT tablename
        FROM pg_tables
        WHERE schemaname = 'public'
    ) LOOP
        EXECUTE 'DROP TABLE IF EXISTS ' || quote_ident(r.tablename) || ' CASCADE';
    END LOOP;
END $$;














