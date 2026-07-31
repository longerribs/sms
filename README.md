# Clayon SMS Reseller Platform

A production-ready SMS reseller platform for sending messages through the TalkSasa gateway. The current implementation is configured for the local project root in this workspace and uses a database-driven queue plus wallet reservations before provider confirmation.

## Overview

Clayon manages:

- Client accounts and API key authentication
- Wallet balances, reserved units, and ledger history
- SMS request creation and queue-based processing
- Sender ID approvals and pricing plans
- M-Pesa STK push payments and callbacks

## Project structure

```text
api/                 # REST endpoints
callback/            # Payment and delivery callbacks
config/              # DB, auth, response, and validation classes
pages/               # Frontend pages
setup/               # Bootstrap and installation scripts
src/                 # SMS, queue, wallet, pricing, worker logic
sql/                 # Database schema
bootstrap.php        # App bootstrap
index.php            # Entry page
```

## Quick start

### 1. Create the database and seed the default data

```bash
php setup/run-all-setup.php
```

This creates the `clayon_sms` database, initializes the schema, creates the admin account, seeds pricing plans, and generates an admin API key.

### 2. Configure environment values

Create or update `.env2` in the project root with at least:

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=clayon_sms
DB_USERNAME=root
DB_PASSWORD=
TALKSASA_API_KEY=your_api_key_here
APP_URL=http://localhost/sms
```

### 3. Run the queue worker

Add this to your crontab so pending SMS jobs are processed:

```bash
* * * * * php /full/path/to/sms/src/Worker.php >> /path/to/logs/worker.log 2>&1
```

### 4. Open the dashboard

- Login page: http://localhost/sms/pages/login.html
- Dashboard: http://localhost/sms/pages/dashboard.html
- Admin UI: http://localhost/sms/admin/dashboard.php

## API reference

All endpoints require a Bearer token.

```http
Authorization: Bearer clay_xxx...
```

### Send SMS

```bash
POST /sms/api/send
Content-Type: application/json
```

Request body:

```json
{
  "sender_id": "CLAYON",
  "recipient": "+254712345678",
  "message": "Hello from Clayon"
}
```

Current behavior:

- The request is created as `pending`
- Units are reserved, not debited immediately
- A successful provider response debits the wallet later
- The API returns `202 Accepted` for queued requests

Example response:

```json
{
  "status": "success",
  "data": {
    "request_id": 12,
    "reference": "req_123456789",
    "recipient": "+254712345678",
    "segments": 1,
    "estimated_cost": 1,
    "sms_status": "pending_provider_confirmation",
    "billing_status": "reserved_not_debited"
  }
}
```

### Balance

```bash
GET /sms/api/balance
```

### SMS history

```bash
GET /sms/api/history?limit=50&offset=0
```

### Sender IDs

```bash
GET /sms/api/sender-ids
POST /sms/api/sender-ids
```

### Wallet ledger

```bash
GET /sms/api/ledger?limit=50&offset=0
```

### Payment initiation

```bash
POST /sms/api/payment/initiate
```

## Billing model

The current billing flow follows a safer, delivery-confirmation model:

1. Create the SMS request and reserve units
2. Attempt provider delivery
3. Debit the wallet only when the provider confirms success
4. Leave the units untouched on failed or rejected sends

This avoids charging clients for messages that never reach the provider or the end recipient.

## Configuration

### Environment variables

The project loads `.env2` automatically through the bootstrap and database helpers.

```env
TALKSASA_API_KEY=your_key
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=clayon_sms
DB_USERNAME=root
DB_PASSWORD=
APP_URL=http://localhost/sms
```

### Key settings

- `QUEUE_WORKER_LIMIT`: jobs processed per cron cycle
- `QUEUE_RETRY_DELAY`: retry delay in minutes
- `QUEUE_MAX_ATTEMPTS`: maximum retry attempts
- `QUEUE_DEAD_LETTER_POLICY`: `manual_review` or `refund`

## Database notes

The setup script creates the database and initializes the schema from `sql/schema.sql`.

Core tables include:

- `clients`
- `client_api_keys`
- `wallet_accounts`
- `wallet_ledger`
- `sms_requests`
- `sms_queue`
- `sms_attempts`
- `provider_sms_logs`
- `mpesa_transactions`

## Testing

```bash
php setup/init-db.php
php setup/verify-setup.php
```

You can also test the API by using the generated admin API key from the setup output.

## Security notes

- Keep `.env2` out of version control
- Store API keys securely and rotate them periodically
- Prefer HTTPS in production
- Review wallet and queue logs regularly

## Version

- Version: 1.1.0
- Last updated: July 2026
- Status: Production-ready for local deployment and testing
