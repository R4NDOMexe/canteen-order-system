# Canteen Order System

A complete web-based canteen ordering system built with HTML, CSS, and PHP. Features a McDonald's kiosk-style interface for customers and a comprehensive seller dashboard.

## Features

### Authentication
- Login system with username/password
- Role-based access (Customer, Teacher, Seller)
- Secure session management
- Logout functionality

### Customer View
- Browse menu by categories (McDonald's kiosk-style interface)
- Add items to cart
- View and manage cart
- Checkout with multiple payment options
- View order history
- Priority queue for teachers

### Seller View
- Dashboard with statistics
- Manage orders (priority queue for teachers)
- Store management (add/delete stores)
- Sales statistics with filters (Today, Monthly, Yearly)
- Receipt history with auto-purge

### Payment Options
- **GCash** - Online payment via GCash
- **PayMaya** - Online payment via PayMaya
- **Pay at Front** - Pay at counter within 10 minutes

### Receipt System
- Auto-generated receipt codes (e.g., #AU001)
- Screenshot notice for customers
- Shows store name, order list, and total
- Auto-purges after 36 hours

### Priority Queue
- Teacher orders automatically get priority
- Displayed at top of seller's order queue
- Highlighted with special badge

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)

### Setup Steps

1. **Extract the files** to your web server directory (e.g., `htdocs/canteen-system`)

2. **Create the database**:
   - Open phpMyAdmin or MySQL command line
   - Create a database named `canteen_system`
   - Import the `database/schema.sql` file

3. **Configure database connection**:
   - Open `includes/config.php`
   - Update the database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USERNAME', 'your_username');
     define('DB_PASSWORD', 'your_password');
     define('DB_NAME', 'canteen_system');
     ```

4. **Update application URL**:
   - In `includes/config.php`, update:
     ```php
     define('APP_URL', 'http://localhost/canteen-system');
     ```

5. **Access the system**:
   - Open your browser
   - Navigate to `http://localhost/canteen-system`

## Default Login Credentials

| Username | Password | Role |
|----------|----------|------|
| admin | admin123 | Seller |
| teacher1 | admin123 | Teacher |
| student1 | admin123 | Customer |

## File Structure

```
canteen-system/
├── assets/
│   └── css/
│       └── style.css          # Main stylesheet
├── database/
│   └── schema.sql             # Database schema
├── includes/
│   └── config.php             # Configuration and functions
├── pages/
│   ├── customer/              # Customer pages
│   │   ├── menu.php           # Browse menu
│   │   ├── cart.php           # Shopping cart
│   │   ├── checkout.php       # Checkout page
│   │   ├── process-order.php  # Process orders
│   │   ├── receipt.php        # Receipt display
│   │   ├── orders.php         # Order list
│   │   ├── view-order.php     # Order details
│   │   ├── history.php        # Order history
│   │   └── add-to-cart.php    # Add to cart handler
│   └── seller/                # Seller pages
│       ├── dashboard.php      # Seller dashboard
│       ├── orders.php         # Order management
│       ├── view-order.php     # Order details
│       ├── previous-orders.php# Completed orders
│       ├── stores.php         # Store management
│       ├── sales.php          # Sales statistics
│       └── history.php        # Receipt history
├── index.php                  # Login page
├── logout.php                 # Logout handler
└── README.md                  # This file
```

## Usage Guide

### For Customers

1. **Login** with your credentials
2. **Browse Menu** - Click on categories or view all items
3. **Add to Cart** - Click "Add" button on menu items
4. **View Cart** - Click cart icon or "My Cart" in sidebar
5. **Checkout** - Review items and proceed to payment
6. **Select Payment** - Choose GCash, PayMaya, or Pay at Front
7. **View Receipt** - Screenshot your receipt for reference
8. **Track Orders** - View order status in "My Orders"

### For Teachers
- Same as customers, but orders get automatic priority
- Priority orders appear at the top of seller's queue

### For Sellers

1. **Login** with seller credentials
2. **Dashboard** - View today's statistics and recent orders
3. **Manage Orders**:
   - View active orders (priority orders first)
   - Confirm payments for "Pay at Front" orders
   - Update order status (Preparing → Ready → Completed)
4. **Manage Stores**:
   - Add new stores
   - Activate/deactivate stores
   - Delete stores
5. **View Sales**:
   - Filter by Today, Monthly, or Yearly
   - View sales charts and order details
6. **Receipt History**:
   - View all receipts
   - Auto-purges after 36 hours

## Customization

### Changing Colors
Edit `assets/css/style.css` and modify the CSS variables:
```css
:root {
    --primary: #FFC72C;        /* Main brand color */
    --primary-dark: #DA291C;   /* Dark accent */
    --secondary: #292929;      /* Text color */
    /* ... */
}
```

### Adding Menu Items
1. Login as seller
2. Go to "My Stores"
3. Add items directly to the database (menu_items table)

### Modifying Receipt Expiry
Edit `includes/config.php`:
```php
define('RECEIPT_EXPIRY_HOURS', 36); // Change to desired hours
```

## Security Notes

- Change default passwords immediately after installation
- Use HTTPS in production
- Keep PHP and MySQL updated
- Regularly backup your database
- The system uses password hashing for security

## Troubleshooting

### Database Connection Error
- Check credentials in `includes/config.php`
- Ensure MySQL is running
- Verify database exists

### Pages Not Loading
- Check file permissions (755 for directories, 644 for files)
- Verify URL in `includes/config.php`
- Check web server error logs

### Session Issues
- Ensure `session_start()` is not blocked
- Check PHP session configuration
- Clear browser cookies

## License

This project is open source. Feel free to modify and use as needed.

## Support

For issues or questions, please check the code comments or refer to the troubleshooting section.

---

**MARKA NI LANCE TO GAGO!** 