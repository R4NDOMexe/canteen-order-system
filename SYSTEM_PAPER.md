**Project Title**: Canteen Order System

## System Paper

### Introduction
The canteen experiences heavy congestion and slow service during rush hours and peak times (e.g., breakfast, lunch, and class breaks). Long queues, manual order handling, and limited point-of-sale throughput result in delays, incorrect orders, and lost sales. The Canteen Order System aims to digitize ordering, reduce wait times, and streamline order processing for customers, sellers, and administrators.

### System Requirements
- The system shall allow customers to browse menu items and place orders online.
- The system shall support user registration and authentication for customers, sellers, and administrators.
- The system shall allow sellers to manage stores and menu items (add, edit, remove).
- The system shall process orders, track order status, and generate receipts.
- The system shall store order history and sales records for reporting.
- The system shall allow administrators to manage users and review system statistics.
- The system shall support image uploads for menu items and ads.
- The system shall be usable on mobile and desktop (responsive design).

### Specifications
- Platform: PHP (server-side), MySQL (database), HTML/CSS (frontend - JavaScript-free).
- Deployment: LAMP/WAMP/XAMPP stack.
- Authentication: PHP session-based login with role-based access control (customer, seller, admin).
- Data storage: Relational schema defined in `database/schema.sql`.
- File uploads: Stored under `uploads/` with subfolders for menu items, logos, and ads.
- Icons: Font Awesome 6.4.0 (loaded from CDN: https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css).
- Security: Basic input validation and prepared statements for DB queries (recommend: enforce HTTPS in production).

## System Design Document

### Dataflow Design
- Complete ASCII overview covering all key roles and actions:
  ```text
  | Canteen system
  |-- Customers (students)
  |    |-- browse menu
  |    |-- add to cart
  |    |-- checkout & place order
  |
  |-- Sellers
  |    |-- manage stores & menu items
  |    |-- view & update orders
  |
  |-- Administrators
  |    |-- approve registrations
  |    |-- manage users & stores
  |    |-- view system statistics
  ```
- **Shared Entry Flow**
  - Start (oval)
  - Sign up or Log in (rounded rect) – user enters credentials (via `register.php` or `index.php`)
  - Authenticated? (diamond)
    - No → back to "Sign up or Log in" or End
  - User role? (diamond) – based on account type (Customer, Seller, Admin)
    - Branch to respective swim-lane

- **Customer Lane (after login)**
  - Browse menu / add items (rounded rect) – `pages/customer/menu.php` → `pages/customer/add-to-cart.php`
  - View cart → Select payment method → Click Pay – `pages/customer/cart.php` → `pages/customer/checkout.php`
  - Payment approved? (diamond)
    - No → show error / back to cart
  - Order placed → Process order → Order confirmation and receipt generation – `pages/customer/process-order.php` → `pages/customer/receipt.php`
  - End
  - (Arrow from "Order placed" crosses to Seller lane)

- **Seller Lane (after login)**
  - Receive order notification (triggered by customer order) – `pages/seller/orders.php`
  - Prepare/ship order or update status – view & update orders in `pages/seller/orders.php`
  - Order complete? (diamond)
    - No → loop or wait
  - Send confirmation to customer – completed orders move to `pages/seller/history.php`
  - End

- **Administrator Lane (after login)**
  - Manage users / view stats / approve registrations / add stores… (each can be a rounded rect) – admin dashboards and management pages under `pages/master/` (e.g., `pages/master/users.php`, `pages/master/pending-registrations.php`, `pages/master/stores-management.php`, `pages/master/system-stats.php`)
  - Perform another admin action? (diamond)
    - Yes → loop back
    - No → End

- **Additional Notes**
  - The server interacts with `includes/config.php` for DB connection and uses PHP endpoints to read/write order and user data in MySQL (cylinder shape)
  - Arrows can cross lanes for interactions (e.g., customer order to seller)

### Testing Strategy
- Unit testing: test individual PHP functions and database queries where feasible.
- Integration testing: test page flows (registration, login, menu browsing, ordering, seller updates) against a local XAMPP environment.
- Manual exploratory testing: focus on rush-hour scenarios with many simultaneous orders, image upload paths, and role-based access.
- Acceptance testing: verify the system meets the functional requirements listed above.

### Test Scenarios
- Scenario 1: New user registers, logs in, places an order, and receives a receipt.
- Scenario 2: Existing customer adds multiple items to the cart, updates quantities, and completes checkout.
- Scenario 3: Seller views a new incoming order and marks it as "In Progress" and then "Completed".
- Scenario 4: Admin approves or rejects pending registrations and views system statistics.
- Scenario 5: Attempt to upload a malformed image (large file or invalid type) for a menu item — verify handling.
- Scenario 6: Concurrent orders during a simulated rush (multiple browser sessions) — verify order integrity.

### Expected Results
- Users can register and log in successfully with the correct role access.
- Orders placed by customers appear in the seller's order queue and update status changes propagate to customers.
- Receipts are generated and stored with the order record.
- Admin pages correctly reflect user/store states and system statistics.
- Invalid uploads are rejected with a user-friendly error.

### Bug Report Template
- **Title**: Short descriptive title
- **Reported By**: Name / role
- **Date**: YYYY-MM-DD
- **Environment**: e.g., Windows, XAMPP 8.1, Chrome 110
- **Steps to Reproduce**:
  1. Step one
  2. Step two
  3. ...
- **Expected Result**: What should happen
- **Actual Result**: What happened
- **Severity**: Critical / Major / Minor
- **Screenshots / Logs**: Attach relevant output or screenshots
- **Notes / Workaround**: Any temporary workaround

### Bug Report Examples

- **Bug #1 — Login fails; session not established**
  - **Reported By**: QA Tester
  - **Date**: 2026-2-25
  - **Environment**: Windows, XAMPP 8.1, Chrome 110
  - **Steps to Reproduce**:
    1. Register a new customer account via `register.php`.
    2. Attempt to log in using the new credentials on `index.php`.
  - **Expected Result**: User is redirected to the customer dashboard and session is created.
  - **Actual Result**: Login appears successful but user stays on the login page and no session variables are set.
  - **Severity**: Major
  - **Notes / Workaround**: Clear cookies and try again; restart Apache.

- **Bug #2 — Cart item quantities reset when navigating back**
  - **Reported By**: QA Tester
  - **Date**: 2026-2-25
  - **Environment**: Windows, XAMPP, Firefox
  - **Steps to Reproduce**:
    1. Add items with varying quantities to cart on `pages/customer/menu.php`.
    2. Click through to item details and then return to the menu.
  - **Expected Result**: Cart retains selected quantities and items.
  - **Actual Result**: Quantities reset to default; some items disappear from the cart.
  - **Severity**: Minor
  - **Notes / Workaround**: Re-add items before checkout.

- **Bug #3 — Incorrect total at checkout (tax/discount not applied)**
  - **Reported By**: Seller / QA
  - **Date**: 2026-2-26
  - **Environment**: Windows, XAMPP 8.1, Edge
  - **Steps to Reproduce**:
    1. Add items to cart that trigger tax and a discount promotion.
    2. Proceed to `pages/customer/checkout.php` and view the final total.
  - **Expected Result**: Final total includes tax and discount calculations.
  - **Actual Result**: Final total omits tax (or applies discount incorrectly), leading to mismatched receipts.
  - **Severity**: Major
  - **Screenshots / Logs**: Checkout summary shows subtotal but missing tax line.
  - **Notes / Workaround**: Calculate tax manually and confirm with seller before fulfilling order.

- **Bug #4 — Image upload returns server error for large files**
  - **Reported By**: Seller
  - **Date**: 2026-2-26
  - **Environment**: Windows, XAMPP, Chrome
  - **Steps to Reproduce**:
    1. Go to `pages/seller/add-item.php` and upload an image >5MB.
  - **Expected Result**: Upload rejected with informative error (file too large) or image resized.
  - **Actual Result**: Server returns HTTP 500 and no user-facing error is shown.
  - **Severity**: Major
  - **Screenshots / Logs**: Apache error log shows "Allowed memory size exhausted" or PHP upload error code.
  - **Notes / Workaround**: Resize image to <2MB locally before upload.

- **Bug #5 — Race condition during concurrent checkouts (duplicate/overwritten orders)**
  - **Reported By**: QA / Load Tester
  - **Date**: 2026-03-01
  - **Environment**: Windows, XAMPP, multiple browser sessions
  - **Steps to Reproduce**:
    1. Using multiple browser sessions or machines, perform checkout simultaneously for different customers.
  - **Expected Result**: Each checkout creates a distinct order record with unique order IDs.
  - **Actual Result**: Some orders receive the same ID or one order overwrites another (database constraint / transaction issue).
  - **Severity**: Critical
  - **Screenshots / Logs**: MySQL error: "Duplicate entry for key 'PRIMARY'" or missing rows in orders table.
  - **Notes / Workaround**: Stagger checkouts; enforce DB transactions and AUTO_INCREMENT protection.

- **Bug #6 — Admin pending registrations page shows empty list**
  - **Reported By**: Admin
  - **Date**: 2026-03-04
  - **Environment**: Windows, XAMPP, Chrome
  - **Steps to Reproduce**:
    1. As admin, navigate to `pages/master/pending-registrations.php`.
  - **Expected Result**: Pending registrations are listed for review.
  - **Actual Result**: Page shows empty list despite entries in the `users` table with `status='pending'`.
  - **Severity**: Major
  - **Screenshots / Logs**: Query debug shows `SELECT * FROM users WHERE status='pending'` returns rows but page loop produces no output (possible template or variable mismatch).
  - **Notes / Workaround**: Check template rendering loop and variable names; reload page or restart Apache.

## User Manual

### Getting Started
1. Deploy the project to your web server document root (e.g., `htdocs/canteen-system`).
2. Ensure a working PHP + MySQL stack (XAMPP, WAMP, or similar).
3. Import the database schema: use `database/schema.sql` to create the database and tables.
4. Edit `includes/config.php` to set database host, username, password, and database name.
5. Start the webserver and open the app in a browser (e.g., `http://localhost/canteen-system/`).

### For Customers
- Register an account via the registration page (`register.php`).
- Browse menu items at `pages/customer/menu.php`.
- Add items to your cart (`pages/customer/add-to-cart.php`) and view the cart at `pages/customer/cart.php`.
- Proceed to checkout at `pages/customer/checkout.php` and complete the payment/confirmation step.
- View order history at `pages/customer/history.php` and receipts at `pages/customer/receipt.php`.

### For Sellers
- Log in with a seller account.
- Manage your store and menu at `pages/seller/stores.php` and `pages/seller/add-item.php`.
- View and update orders at `pages/seller/orders.php` and `pages/seller/history.php`.
- Generate sales reports at `pages/seller/sales.php`.

### For Administrators
- Log in with an admin account.
- Review pending registrations in `pages/master/pending-registrations.php`.
- Manage users and stores via `pages/master/users.php` and `pages/master/stores-management.php`.
- View system-wide statistics in `pages/master/system-stats.php`.

## Technical Documentation

### Code Overview
- Entry points: `index.php` (login/dashboard router), `register.php` (user signup), `logout.php` (session cleanup).
- Page structure: `/pages/customer/`, `/pages/seller/`, `/pages/master/` — role-based PHP templates.
- Configuration: `includes/config.php` holds DB credentials, constants, and common utility functions.
- Styling: `assets/css/style.css` — single responsive stylesheet (no frameworks).
- Uploads: `uploads/menu_items/`, `uploads/logos/`, `uploads/ads/` — file storage for images.
- Database: MySQL schema at [database/schema.sql](database/schema.sql).
- Architecture: Server-side rendered PHP with sessions; JavaScript-free for compatibility.

### Database Schema & Relationships
**Core Tables:**
- **users** — Stores customer, seller, teacher, student, and admin accounts with fields: `id`, `username`, `password`, `full_name`, `email`, `role` (ENUM: customer, seller, teacher, student, master), `status` (ENUM: pending, approved, rejected, inactive, active), `photo_id_path`, `created_at`, `updated_at`.
- **stores** — Seller-managed stores with fields: `id`, `seller_id` (FK → users), `store_name`, `description`, `logo`, `is_active`, `created_at`.
- **categories** — Menu item categories with fields: `id`, `name`, `image`, `display_order`.
- **menu_items** — Food/drink offerings with fields: `id`, `store_id` (FK → stores), `category_id` (FK → categories), `name`, `description`, `price`, `image`, `is_available`, `created_at`.
- **orders** — Order records with fields: `id`, `order_code`, `customer_id` (FK → users), `store_id` (FK → stores), `total_amount`, `payment_method` (ENUM: gcash, paymaya, pay_at_front), `payment_status` (ENUM: pending, paid, void), `order_status` (ENUM: pending, preparing, ready, completed, cancelled), `is_priority`, `created_at`, `paid_at`, `completed_at`, `void_after`.
- **order_items** — Line items for each order with fields: `id`, `order_id` (FK → orders), `menu_item_id` (FK → menu_items), `quantity`, `unit_price`, `subtotal`.
- **receipts** — Receipt history (36-hour auto-purge) with fields: `id`, `receipt_code`, `order_id` (FK → orders), `customer_id` (FK → users), `store_name`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `created_at`, `expires_at`.

**Key Relationships:**
- Users (role=customer/student) place Orders → OrderItems → MenuItems
- Users (role=seller) manage Stores → MenuItems (via categories)
- Users (role=master/teacher) approve user registrations and manage system
- Orders track payment and order status throughout lifecycle (pending → preparing → ready → completed)

### Configuration Guide

**Database Setup:**
1. Create MySQL database: `CREATE DATABASE canteen_system;`
2. Import schema: `mysql -u root -p canteen_system < database/schema.sql`
3. Edit [includes/config.php](includes/config.php) with credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'canteen_system');
   ```

**File Permissions:**
- `uploads/` subdirectories must be writable by Apache/PHP user: `chmod 755 uploads/*`
- Ensure [includes/config.php](includes/config.php) is readable but not world-visible (restrict in web server config)

**Session & Cookie Configuration:**
- Sessions stored server-side (file or DB) in `php.ini`: `session.save_path = /tmp` or database storage
- Secure cookies in production: update php.ini with:
  ```
  session.secure = 1
  session.http_only = 1
  session.samesite = 'Strict'
  ```

**Image Upload Validation:**
- Whitelist extensions: `.jpg`, `.jpeg`, `.png`, `.gif`
- Max file size: enforce in PHP.ini (`upload_max_filesize = 5M`) and in code
- Store uploads outside document root if possible; serve via script (e.g., `download.php?id=X`)

### Security Considerations

**Current Implementation:**
- Role-based access control: pages check `$_SESSION['role']` to permit/deny actions
- Prepared statements recommended throughout codebase
- Password hashing: ensure using `password_hash()` (PHP 5.5+) with bcrypt
- Input validation: basic sanitization in [includes/config.php](includes/config.php)

**Known Gaps & Recommendations:**
- **CSRF:** No CSRF tokens currently implemented → add token generation/validation in forms
- **SQL Injection:** Verify all queries use prepared statements; grep codebase for `$_POST/$_GET` string interpolation
- **XSS Prevention:** Output escaping with `htmlspecialchars()` on user-supplied data in templates
- **Authentication:** Use HTTPS in production to prevent session hijacking
- **File Upload Security:** Rename uploaded files with UUIDs or timestamps; prohibit direct execution in upload folders
- **Error Handling:** Set `display_errors = Off` in production; log to file instead

### Installation (Quick)
1. Install XAMPP (or similar) and start Apache + MySQL.
2. Place the project folder in your web server root (e.g., `htdocs/canteen-system`).
3. Create a MySQL database and import `database/schema.sql`.
4. Update database credentials in `includes/config.php`.
5. Ensure `uploads/` subfolders are writable by the web server user.
6. Open the app in your browser and register an admin/seller/customer account for testing.

### Notable Files and Paths
- Database schema: [database/schema.sql](database/schema.sql)
- Configuration: [includes/config.php](includes/config.php)
- Customer pages: [pages/customer/menu.php](pages/customer/menu.php), [pages/customer/checkout.php](pages/customer/checkout.php)
- Seller pages: [pages/seller/orders.php](pages/seller/orders.php), [pages/seller/add-item.php](pages/seller/add-item.php)
- Admin pages: [pages/master/dashboard.php](pages/master/dashboard.php), [pages/master/users.php](pages/master/users.php)


