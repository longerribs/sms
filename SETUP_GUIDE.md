# Clayon SMS Reseller Platform - Setup Guide

## Quick start

### 1. Database setup

Run the bootstrap script from the project root:

```bash
php setup/run-all-setup.php
```

This creates the `clayon_sms` database, initializes the schema, seeds pricing plans, and generates an admin API key.

### 2. Environment configuration

The app loads `.env2` automatically. Make sure it contains:

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=clayon_sms
DB_USERNAME=root
DB_PASSWORD=
TALKSASA_API_KEY=your_talksasa_api_key_here
APP_URL=http://localhost/sms
```

### 3. API endpoint overview

All API routes require a Bearer token:

```http
Authorization: Bearer clay_xxx...
```

Available endpoints:

- `POST /sms/api/send` - Create and queue an SMS
- `GET /sms/api/balance` - Get wallet balance
- `GET /sms/api/history?limit=50&offset=0` - View SMS history
- `GET /sms/api/ledger?limit=50&offset=0` - View wallet ledger
- `GET /sms/api/sender-ids` - List approved sender IDs
- `POST /sms/api/sender-ids` - Request a new sender ID
- `POST /sms/api/payment/initiate` - Start M-Pesa payment

### 4. Frontend pages

- Login: `pages/login.html`
- Dashboard: `pages/dashboard.html`
- Admin: `admin/dashboard.php`

### 5. Background worker setup

The queue worker processes pending SMS jobs. Add this to your crontab:

```bash
* * * * * php /full/path/to/sms/src/Worker.php >> /full/path/to/logs/worker.log 2>&1
```

### 6. Creating or rotating API keys

You can generate a fresh key with the setup scripts or by calling the auth helper from a PHP script. The setup flow already creates the initial administrator key.

### 7. Testing the system

#### Test SMS send

```bash
curl -X POST http://localhost/sms/api/send \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer clay_xxx..." \
  -d '{
    "sender_id": "TEST",
    "recipient": "+254123456789",
    "message": "Hello from Clayon!"
  }'
```

#### Test balance

```bash
curl -X GET http://localhost/sms/api/balance \
  -H "Authorization: Bearer clay_xxx..."
```

### 8. Billing behavior

The current implementation reserves wallet units when a request is created and debits them only after the provider confirms success. Failed sends do not consume units.

### 9. Troubleshooting

**Database connection failed**
- Check the values in `.env2`
- Verify MySQL is running and the database exists

**API key not working**
- Confirm the key exists in the `client_api_keys` table
- Ensure the client and key are both active

**SMS not sending**
- Review `sms_queue` and `sms_attempts`
- Check the worker log output
- Confirm `TALKSASA_API_KEY` is present in `.env2`

**M-Pesa payment failure**
- Verify the M-Pesa credentials in `.env2`
- Check the callback URL and the `mpesa_transactions` table

### 10. Monitoring

```sql
SELECT status, COUNT(*) AS count FROM sms_queue GROUP BY status;
SELECT * FROM sms_requests ORDER BY created_at DESC LIMIT 20;
SELECT * FROM mpesa_transactions WHERE status = 'failed';
```

## Support

Check the worker logs, the `sms_attempts` table, and the `audit_logs` table for issues.

---

Version: 1.1.0
Last updated: July 2026
