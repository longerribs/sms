# Clayon SMS Reseller Platform

A production-ready SMS reseller platform for sending messages through the TalkSasa gateway with M-Pesa Daraja wallet top-ups. Features database-driven queue processing, unit reservations, automated delivery reports, developer documentation portal, and zero-dependency HTTP stream fallbacks.

## Overview

Clayon provides a complete SMS reseller infrastructure:

- **Client Management & Security**: API key authentication (`Bearer YOUR_API_KEY`), bcrypt password hashing, and role-based access.
- **Wallet & Billing Engine**: Balance tracking with unit reservation before send, debit upon provider acceptance, and immutable ledger audit trails.
- **Resilient SMS Gateway**: Dual-engine HTTP dispatch (`curl` with automatic fallback to native PHP stream contexts + OpenSSL CA verification) guaranteeing delivery on servers where the PHP curl extension is unavailable.
- **Automated M-Pesa Top-Up**: Daraja STK Push with dynamic tunnel detection (works seamlessly across ngrok restarts) and instant wallet crediting via webhooks.
- **Developer API & Portal**: Comprehensive interactive documentation with live endpoint testing and copy-paste code snippets for cURL, PHP, Python, and Node.js.
- **Analytics & Reporting**: Delivery rates, SMS logs, provider tracking IDs, and downloadable transaction history.

---

## Project Structure

```text
api/                     # REST API endpoints
  ├── balance.php        # Sanitized wallet balance query (API key)
  ├── send.php           # Public SMS dispatch endpoint
  └── v1/
      ├── balance.php    # Session-based balance endpoint
      ├── check_payment.php # Polls M-Pesa payment status
      ├── send_sms.php   # Internal SMS send API
      └── stk_push.php   # M-Pesa Daraja STK Push initiator
callback.php             # M-Pesa Daraja webhook entry point
dlr.php                  # Delivery Report (DLR) webhook
buy.php                  # Wallet top-up page with real-time STK modal
send.php                 # SMS composer and dispatch dashboard
reports.php              # Analytics, delivery rates, and ledger
developer.php            # Interactive developer API documentation
config/                  # Database, Auth, Response, and Validator classes
src/                     # SMSService, QueueService, WalletService, PaymentCallback
sql/                     # Database schemas and seed data
bootstrap.php            # Core bootstrap and environment loading
env_loader.php           # Standalone .env2 file loader
```

---

## Quick Start

### 1. Database Initialization

Import the database schema using MySQL CLI or phpMyAdmin:

```bash
mysql -u root -p clayon_sms < sql/sms_clayon_sms.sql
```

### 2. Configure Environment Variables

Create or update `.env2` in the project root:

```env
# Application Environment
APP_ENV=development
APP_DEBUG=true
APP_URL=https://your-domain-or-ngrok.ngrok-free.app/sms

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=sms_clayon_sms
DB_USERNAME=root
DB_PASSWORD=

# TalkSasa Integration (SMS Provider)
TALKSASA_API_KEY="your_talksasa_api_key_here"
TALKSASA_URL=https://bulksms.talksasa.com/api/v3/sms/send

# M-Pesa Daraja Configuration (STK Push)
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_SHORTCODE=your_business_shortcode
MPESA_TILL_NUMBER=your_till_number
MPESA_PASSKEY=your_passkey
MPESA_AUTH_URL=https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials
MPESA_STK_PUSH_URL=https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest
```

### 3. Background Queue Worker (Optional)

For high-volume bulk campaigns, schedule the background worker via crontab or Windows Task Scheduler:

```bash
* * * * * php /path/to/sms/src/Worker.php >> /path/to/logs/worker.log 2>&1
```

---

## API Reference

All Developer API endpoints require authentication via Bearer token:

```http
Authorization: Bearer YOUR_API_KEY
Accept: application/json
```

### 1. Check Wallet Balance

```http
GET /sms/api/balance.php
```

#### Example Response (HTTP 200 OK)
Returns sanitized wallet balance data with no internal primary keys or profile identifiers:

```json
{
  "status": "success",
  "message": "Wallet balance retrieved",
  "data": {
    "balance_units": 63.0,
    "reserved_units": 0.0,
    "available_units": 63.0,
    "currency": "KES",
    "updated_at": "2026-11-02 22:18:37"
  },
  "timestamp": "2026-11-02 22:18:37"
}
```

| Field | Type | Description |
|---|---|---|
| `balance_units` | Float | Total credited units in the wallet |
| `reserved_units` | Float | Units temporarily held for pending sends |
| `available_units` | Float | Usable units available for new messages |
| `currency` | String | Billing currency code (`KES`) |
| `updated_at` | String | Timestamp of balance snapshot |

---

### 2. Send SMS

```http
POST /sms/api/send.php
Content-Type: application/json
```

#### Request Body
```json
{
  "recipient": "254712345678",
  "message": "Hello from Clayon SMS",
  "sender_id": "TALKSASA"
}
```

#### Example Response (HTTP 200 OK)
```json
{
  "status": "success",
  "message": "Message sent successfully.",
  "data": {
    "request_id": 14,
    "reference": "req_6ae8e1f5c34e2",
    "recipient": "254712345678",
    "segments": 1,
    "estimated_cost": 1,
    "sms_status": "accepted",
    "billing_status": "debited"
  }
}
```

---

### 3. M-Pesa STK Push Payment

Top up wallet balance programmatically or via the UI:

```http
POST /sms/api/v1/stk_push.php
Content-Type: application/json
```

#### Request Body
```json
{
  "amount": 100,
  "phone": "254712345678"
}
```

- Triggers an instant M-Pesa PIN prompt on the customer's handset.
- **Dynamic Tunnel Detection**: Automatically detects the incoming tunnel URL (e.g. ngrok) and registers the callback endpoint seamlessly.
- **Webhook Processing**: Upon successful PIN entry, Safaricom calls `callback.php` which validates the transaction, credits units based on the client's markup plan, and records a ledger transaction atomically.

---

## Billing & Rate Model

1. **Segment Calculation**:
   - Standard 60 characters per segment.
   - Segments 1 & 2: 1 unit each.
   - Segment 3+: 2 units each.
2. **Safe Debit Flow**:
   - Units are checked and reserved when the request is created.
   - Balance is debited only after the provider gateway acknowledges acceptance (`HTTP 200/202`).
   - If the gateway rejects the send, no balance deduction occurs.

---

## Security & Reliability

- **No Credential Leakage**: `.env` and `.env2` are strictly ignored by `.gitignore`.
- **Sanitized APIs**: Sensitive database primary keys (`client_id`) are stripped from client-facing responses.
- **BOM-Free Headers**: Clean JSON output with no leading Byte Order Marks to guarantee standard HTTP compliance.
- **Idempotent Webhooks**: M-Pesa callback processor validates transaction states to prevent double-crediting.

---

## Version & Changelog

- **Version**: 1.2.0
- **Updated**: November 2026
- **Changelog**:
  - Added zero-dependency stream context fallback with CA certificate validation in `SMSService`.
  - Added dynamic ngrok reverse proxy detection to `stk_push.php`.
  - Sanitized `api/balance.php` response to strip sensitive internal IDs.
  - Updated developer documentation and live code examples in `developer.php`.
