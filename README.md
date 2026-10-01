# Khelmandu

Khelmandu is a PHP and MySQL futsal matchmaking and ground-booking app based on the project proposal in `khelmandu.docx`.

## Requirements

- PHP 8.1+ with `pdo_mysql`, `fileinfo`, and sessions enabled
- MySQL 8.0+
- A web server with PHP support (Apache, Nginx + PHP-FPM, or PHP's development server)

## Setup on Windows

PHP and MySQL must be installed and running. XAMPP is one convenient option; this workspace currently has a local-development `config.php` set for XAMPP's default `root` account with no password. Change it if your MySQL credentials differ. Do not use blank database credentials in production.

1. Install/start XAMPP and start **MySQL** in the XAMPP Control Panel.
2. Open Command Prompt in this project directory and import the schema:

	```bat
	C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
	```

	If the root account has a password, add `-p`. Alternatively, open `http://localhost/phpmyadmin`, select **Import**, and choose `database/schema.sql`.
3. Start the app from this directory using the PHP bundled with XAMPP:

	```bat
	C:\xampp\php\php.exe -S localhost:8000
	```

4. Open `http://localhost:8000` and create a team or player account. To grant administrator access, run this SQL in phpMyAdmin or the MySQL command line, then sign out and back in:

	```sql
	UPDATE users SET role = 'admin' WHERE email = 'your-admin-email@example.com';
	```

Ensure PHP has `pdo_mysql`, `fileinfo`, and sessions enabled. Identity documents are stored in the sibling `khelmandu-private/verification` directory, outside the web root; the PHP process must be allowed to create and write to it.

The app uses one captain-owned account per team; do not share the captain's password. Team identity documents are private uploads and can be reviewed by an administrator. Online payment processing is intentionally not included, in line with the proposal's initial limitation.

## Pages

- `dashboard.php`: upcoming fixtures, requests, and booking overview
- `teams.php`: discover teams and send match requests
- `matches.php`: accept or decline requests and view the fixture schedule
- `venues.php`: browse grounds and request time slots
- `bookings.php`: review and cancel your bookings
- `team.php`: edit team details and upload a verification document
- `player.php`: create a player profile, find teams, and request to join a side
- `admin.php`: review teams, manage venues, and oversee bookings

All mutations use authenticated POST requests with CSRF protection. Passwords are stored with PHP's password hashing API, and SQL access uses prepared PDO statements.