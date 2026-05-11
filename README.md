# Sari-Sari Store — Inventory & POS System

## Stack
PHP 7.4+ · MySQL 5.7+ · Bootstrap 5.3 · Vanilla JS

## File Structure
```
sarisari/
├── index.php          ← Main POS page
├── api.php            ← All CRUD endpoints (create/update/delete/list/get)
├── config.php         ← DB credentials — EDIT THIS FIRST
├── database.sql       ← Run once to create DB + sample products
├── assets/
│   ├── logo.png
│   ├── css/style.css
│   └── js/app.js
└── uploads/           ← Product images saved here (auto-created)
```

## Setup (XAMPP / WAMP / Any PHP Server)

### Step 1 — Copy files
Place the `sarisari/` folder inside:
- XAMPP → `C:/xampp/htdocs/sarisari/`
- WAMP  → `C:/wamp64/www/sarisari/`
- Linux → `/var/www/html/sarisari/`

### Step 2 — Create the database
1. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Click **Import** → choose `database.sql` → click **Go**

Or via MySQL CLI:
```bash
mysql -u root -p < database.sql
```

### Step 3 — Edit config.php
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // your MySQL username
define('DB_PASS', '');           // your MySQL password (blank for XAMPP default)
define('DB_NAME', 'sarisari_db');
```

### Step 4 — Fix uploads folder permission (Linux/Mac only)
```bash
chmod 755 uploads/
```

### Step 5 — Open the app
```
http://localhost/sarisari/
```

---

## Features
- Add / Edit / Delete products with image upload
- POS grid with category filters and live search
- Cart with quantity controls
- VAT 12% toggle
- Payment modal with change calculator
- Stock level indicators (green / yellow / red)
- Responsive layout (mobile-friendly)

## API Endpoints (api.php)
| Action   | Method | Description           |
|----------|--------|-----------------------|
| list     | GET    | Get all/filtered products |
| get      | GET    | Get single product by ID  |
| create   | POST   | Add new product           |
| update   | POST   | Edit existing product     |
| delete   | POST   | Delete product by ID      |
