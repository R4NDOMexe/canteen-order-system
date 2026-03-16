-- Canteen Order System Database Schema
-- Run this in your MySQL database

CREATE DATABASE IF NOT EXISTS canteen_system;
USE canteen_system;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('customer', 'seller', 'teacher', 'student', 'master') NOT NULL DEFAULT 'customer',
    status ENUM('pending', 'approved', 'rejected', 'inactive', 'active') DEFAULT 'pending',
    photo_id_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Stores table
CREATE TABLE stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    store_name VARCHAR(100) NOT NULL,
    description TEXT,
    logo VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    image VARCHAR(255),
    display_order INT DEFAULT 0
);

-- Menu items table
CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255),
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(20) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    store_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('gcash', 'paymaya', 'pay_at_front') NOT NULL,
    payment_status ENUM('pending', 'paid', 'void') DEFAULT 'pending',
    order_status ENUM('pending', 'preparing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
    is_priority BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    void_after TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);

-- Order items table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
);

-- Receipts table (for history - auto-purges after 36 hours)
CREATE TABLE receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_code VARCHAR(20) NOT NULL UNIQUE,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    store_name VARCHAR(100) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_status VARCHAR(20) NOT NULL,
    order_status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_expires_at (expires_at)
);

-- Insert default admin/seller user (password: admin123)
INSERT INTO users (username, password, full_name, email, role, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@canteen.com', 'seller', 'approved'),
('teacher1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Teacher One', 'teacher1@school.com', 'teacher', 'approved'),
('student1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student One', 'student1@school.com', 'student', 'approved');

-- Insert default categories
INSERT INTO categories (name, image, display_order) VALUES
('Meals', 'assets/images/cat-meals.png', 1),
('Snacks', 'assets/images/cat-snacks.png', 2),
('Drinks', 'assets/images/cat-drinks.png', 3),
('Desserts', 'assets/images/cat-desserts.png', 4);

-- Insert sample store
INSERT INTO stores (seller_id, store_name, description, logo) VALUES
(1, 'Main Canteen', 'Your favorite school canteen with delicious meals!', 'assets/images/store-logo.png');

-- Insert sample menu items
INSERT INTO menu_items (store_id, category_id, name, description, price, image) VALUES
(1, 1, 'Chicken Rice Meal', 'Steamed rice with fried chicken and vegetables', 85.00, 'assets/images/chicken-rice.jpg'),
(1, 1, 'Beef Steak', 'Tender beef steak with gravy and mashed potatoes', 120.00, 'assets/images/beef-steak.jpg'),
(1, 1, 'Fish Fillet', 'Crispy fish fillet with tartar sauce', 95.00, 'assets/images/fish-fillet.jpg'),
(1, 2, 'French Fries', 'Crispy golden fries', 45.00, 'assets/images/fries.jpg'),
(1, 2, 'Chicken Nuggets', '6 pieces of crispy chicken nuggets', 55.00, 'assets/images/nuggets.jpg'),
(1, 3, 'Iced Tea', 'Refreshing iced tea', 35.00, 'assets/images/iced-tea.jpg'),
(1, 3, 'Soft Drink', 'Coca-cola or Sprite', 30.00, 'assets/images/soda.jpg'),
(1, 4, 'Brownie', 'Chocolate brownie', 40.00, 'assets/images/brownie.jpg');
