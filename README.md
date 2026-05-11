# Lumina POS

A production-ready Point of Sale system for hardware/construction supply stores built with PHP, Bootstrap, and MySQL.

## Project Structure

```
lumina-pos/
├── public/          ← Web root (browser-accessible pages)
│   ├── api/         ← AJAX API endpoints
│   └── *.php        ← All page files
├── app/
│   ├── core/        ← Database, Auth, Audit, Cart, ProductHelper
│   ├── helpers/     ← Error handler
│   ├── Repositories/← Data access layer
│   ├── Services/    ← Business logic layer
│   ├── bootstrap.php← Single app entry point
│   ├── layout.php   ← HTML layout wrapper
│   └── sidebar.php  ← Navigation sidebar
├── assets/
│   └── vendor/      ← Local vendor assets (Bootstrap, Chart.js, Tom Select)
├── config/
│   └── database.php ← DB credentials (not committed — see database.example.php)
├── storage/
│   ├── backups/     ← Database backups
│   └── logs/        ← Application logs
└── scripts/         ← One-time CLI scripts
```

## Setup

1. Clone the repo into your XAMPP `htdocs` folder:
   ```bash
   git clone https://github.com/albertii-alt/hardware-pos.git lumina-pos
   ```

2. Copy and configure the database config:
   ```bash
   cp config/database.example.php config/database.php
   # Edit config/database.php with your credentials
   ```

3. Import the database schema and seed data via phpMyAdmin or CLI.

4. Access the app at:
   ```
   http://localhost/lumina-pos/public/
   ```

## Default Login

| Username | Password | Role  |
|----------|----------|-------|
| admin    | (set on first run) | owner |
