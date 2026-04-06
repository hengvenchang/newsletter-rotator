# Approach & Solution

To solve the provider rate-limiting and blacklisting problem, we:
- Grouped subscribers by normalized email provider (e.g., Hotmail, Outlook, etc.)
- Enforced a strict hourly send limit per provider (100/hour)
- Used a round-robin batching algorithm to interleave providers in each batch
- Tracked sent emails in a separate table to avoid duplicates
- Handled edge cases (e.g., only one provider left, provider aliases)

This ensures no provider exceeds safe limits, prevents blacklisting, and distributes emails fairly across all domains.
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
