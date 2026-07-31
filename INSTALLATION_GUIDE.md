# Clayon SMS Platform - Installation & Configuration Guide

## System status

The current project is ready for local deployment and testing. The implementation uses the workspace root as the application base, loads `.env2` automatically, and reserves wallet units until provider confirmation arrives.

## Quick start

### Step 1: Run the full setup script

```bash
php setup/run-all-setup.php
```

This will:

- Create the `clayon_sms` database
- Initialize the schema from `sql/schema.sql`
- Create the default admin account
- Seed pricing plans and approved sender IDs
- Generate an admin API key

### Step 2: Configure TalkSasa and app settings

Edit `.env2` in the project root:

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=clayon_sms
DB_USERNAME=root
DB_PASSWORD=
TALKSASA_API_KEY=your_api_key_here
APP_URL=http://localhost/sms
```

### Step 3: Start the queue worker

Add this to crontab:

```bash
* * * * * php /full/path/to/sms/src/Worker.php >> /var/log/clayon-worker.log 2>&1
```

---

## Access the system

After setup completes, use these URLs:

- http://localhost/sms/QUICK_START.php
- http://localhost/sms/setup/verify-setup.php
- http://localhost/sms/pages/login.html
- http://localhost/sms/pages/dashboard.html
- http://localhost/sms/index.php

---

## Admin credentials

The setup script creates the default admin user:

```text
Email: simonjogu001@gmail.com
Phone: 0711486334
Initial balance: 10,000 SMS units
```

Save the generated API key securely.

---

## Project structure

```text
api/              # REST endpoints
callback/         # Delivery and payment callbacks
config/           # Configuration and validation classes
pages/            # Frontend pages
setup/            # Setup and verification scripts
src/              # SMS, queue, wallet, and worker services
sql/              # Database schema
bootstrap.php     # App bootstrap
index.php         # Home page
```

---

## API reference

All endpoints require a Bearer token:

```http
Authorization: Bearer YOUR_API_KEY_HERE
```

### Send SMS

```http
POST /sms/api/send
Content-Type: application/json
```

Example:

```json
{
  "recipient": "+254712345678",
  "message": "Hello World"
}
```

Note: the gateway currently defaults the sender ID to `TALKSASA` if `sender_id` is omitted.

Current behavior:

- The request is created as pending
- Wallet units are reserved, not debited immediately
- The wallet is debited only after the provider confirms success

### Balance

```http
GET /sms/api/balance
```

### History

```http
GET /sms/api/history?limit=50&offset=0
```

### Sender IDs

```http
GET /sms/api/sender-ids
POST /sms/api/sender-ids
```

### Wallet ledger

```http
GET /sms/api/ledger?limit=50&offset=0
```

### Initiate payment

```http
POST /sms/api/payment/initiate
```

---

## Configuration notes

### `.env2`

The application loads `.env2` automatically from the project root.

### Queue settings

The worker uses constants from `config/Config.php`:

- `QUEUE_WORKER_LIMIT`
- `QUEUE_RETRY_DELAY`
- `QUEUE_MAX_ATTEMPTS`
- `QUEUE_DEAD_LETTER_POLICY`

---

## Testing your installation

### Verify setup

```bash
php setup/verify-setup.php
```

### Test API locally

```bash
curl -X GET http://localhost/sms/api/balance \
  -H "Authorization: Bearer API_KEY"
```

---

## Security notes

- Never commit `.env2` to source control
- Keep API keys in secure storage
- Prefer HTTPS in production
- Review wallet and queue logs regularly

---

Version: 1.1.0
Last updated: July 2026
