# Newsletter Rotator

A simple PHP app to demo safe, provider-aware newsletter sending.

## Quick Start

1. Make sure Docker & Docker Compose are installed.
2. Run:
   ```bash
   docker-compose up -d
   ```
   This starts MySQL and auto-imports the test data.
3. Start the app:
   ```bash
   php -S localhost:8000
   ```
4. Open http://localhost:8000 in your browser.

## Project Structure
- `index.php` — Main app
- `db-init/subscribers.sql` — Database schema & test data (auto-imported)
- `services/Rotator.php` — Rotation logic

## Database
- `subscribers` — All subscribers
- `sent_emails` — Tracks sent emails

## Config
Edit `config.php` if you need to change DB settings.

---
No manual SQL import needed. All setup is automatic with Docker Compose.
