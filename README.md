# GearUp!

A cross-game virtual item trading platform built with PHP and MySQL. Users can buy, sell, and trade in-game items across Counter-Strike 2, Dota 2, Rust, and Team Fortress 2.

## Features

**User Side**
- Account registration and login with password hashing (bcrypt)
- Forgot password flow via SMTP email with 6-digit verification code
- Personal inventory with search, filter by game, and sort options
- Market system — list items for sale, browse listings, purchase with credits
- Trade offer system — propose item-for-item trades with up to 4 items per side
- Transaction history with the ability to request trade/purchase reversions
- Profile management with avatar upload
- Top-up balance request system with email verification

**Admin Side**
- User management — edit credentials, adjust credits, soft-delete/restore accounts
- Admin role management with a protected super-admin account
- Revert request review — approve or deny with strict validation (checks item ownership before reverting)
- Account deletion request review
- Top-up request review and approval

## Tech Stack

- **Backend:** PHP 8.1+ (procedural, mysqli)
- **Database:** MySQL / MariaDB
- **Frontend:** Vanilla HTML, CSS (custom design system with CSS variables), JavaScript
- **Email:** Raw SMTP via `stream_socket_client()` (no dependencies)
- **Auth:** Session-based with `password_hash()` / `password_verify()`

## Project Structure

```
├── index.php / index_func.php       Login & forgot password
├── register.php                     Registration
├── home.php / home_func.php         Home page with featured items carousel
├── inventory.php / inventory_func.php   User inventory
├── market.php / market_func.php     Market listings (buy/sell/trade offers)
├── offers.php / offers_func.php     Trade offer management
├── profile.php / profile_func.php   Profile editing & active listings
├── history.php                      Transaction history & revert requests
├── topup.php                        Balance top-up request flow
├── admin_users.php                  Admin — user management
├── admin_requests.php               Admin — revert/deletion/topup requests
├── nav.php                          Shared navigation (role-aware)
├── connections.php                  DB connection, session helper, SMTP, email templates
├── logout.php                       Session destruction
├── styles.css                       Auth pages styling
├── nav_styles.css                   App-wide design system
├── gearup.sql                       Full database schema + sample data
└── .env                             Environment config (not tracked)
```

## Setup

### Requirements

- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.4+
- A web server that runs PHP (Apache, Nginx, XAMPP, or a shared host)
- An SMTP email account (Gmail App Password works)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/xxDCBxx/GearUp.git
   cd GearUp
   ```

2. **Create the database**
   - Create a MySQL database (e.g., `gearup`)
   - Import the schema: `mysql -u root -p gearup < gearup.sql`
   - Or use phpMyAdmin's Import tab to upload `gearup.sql`

3. **Configure environment**
   
   Create a `.env` file in the project root:
   ```
   DB_SERVER=localhost
   DB_USERNAME=root
   DB_PASSWORD=
   DB_NAME=gearup

   EMAIL_SMTP_HOST=smtp.gmail.com
   EMAIL_SMTP_PORT=587
   EMAIL_SMTP_USER=your_email@gmail.com
   EMAIL_SMTP_PASS=your_app_password
   EMAIL_SMTP_SECURE=tls
   EMAIL_SMTP_FROM=your_email@gmail.com
   EMAIL_SMTP_FROM_NAME=GearUp
   ```

4. **Run locally**
   - Place the project in your web server's document root (e.g., `htdocs` for XAMPP)
   - Access via `http://localhost/` or `http://localhost/GearUp/`

### Default Accounts (from sample data)

| Username | Password | Role |
|----------|----------|------|
| admin | admin123 | Admin |
| user1 | (hashed in dump) | User |
| user2 | (hashed in dump) | User |

## Deployment (InfinityFree)

1. Create a free account at [infinityfree.com](https://www.infinityfree.com)
2. Create a MySQL database from the panel and import `gearup.sql` via phpMyAdmin
3. Update your `.env` with the InfinityFree DB credentials (host is NOT localhost)
4. Upload all project files into the `htdocs` folder via FTP (FileZilla)
5. Visit your subdomain — the login page should load

## Supported Games

| Game | Items |
|------|-------|
| Counter-Strike 2 | AK-47, AWP, Karambit, Desert Eagle, M4A4 |
| Dota 2 | Dragonclaw Hook, Timebreaker, Kantusa, Arcanas |
| Rust | Alien Red, Big Grin, Glory AK47, Fireman Jacket |
| Team Fortress 2 | Golden Frying Pan, Australium Rocket Launcher, Max's Head |

## Security

- Prepared statements (mysqli) on all database queries
- Password hashing with `PASSWORD_BCRYPT`
- Session-based authentication with PRG pattern
- XSS prevention via `htmlspecialchars()` on all user output
- `.env` file excluded from version control
- Soft-delete for user accounts (data preserved, restorable)
- Admin role verification on every admin page load

## License

This project was built for educational purposes.
