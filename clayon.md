You are a senior full-stack system architect, backend engineer, frontend designer, database designer, and API product strategist.

Build a reseller/gateway SMS platform on top of TalkSasa. The system must let me sell SMS units to third-party clients through my own API, while my backend uses my private TalkSasa API key and my TalkSasa-funded SMS units to actually send the messages.

avoid creating md files to preserve tokens

Core business model:
- Customers pay me for SMS units.
- I buy SMS units from TalkSasa.
- I resell at a margin.
- Third parties never see my TalkSasa key.
- Third parties only see and use my own API keys.
- I control sender IDs, usage, wallet balances, pricing, logs, retries, and delivery status.

Tech constraints:
- Use PHP, MySQL, HTML, CSS, JavaScript.
- Must work on shared hosting and localhost/XAMPP.
- No Redis.
- No heavy background service.
- Use database-driven queue and cron workers.
- Prefer simple, robust, production-minded code with minimal complexity.

Provider integration rules:
- TalkSasa integration must use HTTPS + POST + JSON + Bearer token.
- My backend calls TalkSasa server-to-server.
- My system must act as a gateway/reseller layer on top of TalkSasa.
- Do not expose provider credentials to clients.
- Store provider request/response logs for every send attempt.

System goals:
1. A client-facing API where third parties can send SMS through my server.
2. A payment frontend where clients buy SMS units via M-Pesa API.
3. A reseller wallet/balance system.
4. A sender ID management layer.
5. A queue and worker system that supports:
   - immediate synchronous send attempt first
   - queue fallback to database if immediate send fails or times out
   - cron-based worker retries
   - delivery status updates
6. Strong API design, validation, authentication, logging, and rate limiting.
7. Optimized database schema for billing, queueing, delivery logs, payments, and auditing.
8. A clean frontend UX for:
   - buying units
   - viewing balance
   - viewing usage history
   - creating API keys
   - managing sender IDs
   - viewing send status and reports

Important architecture behavior:
- When a client calls my SMS endpoint, attempt an immediate synchronous send first.
- If the provider responds successfully within the request lifecycle, return success immediately and store the transaction.
- If the provider is slow, fails, or the network breaks, save the request to the database queue and let the worker retry later.
- Use database as the fallback queue, not Redis.
- Use a cron worker that scans pending jobs every minute or at a safe interval.
- The worker must be idempotent and must not double-send messages.
- Every send request must have a unique idempotency key or request reference.
- Balance deduction must be safe and auditable.
- On permanent failure, either refund units or mark for manual review based on configurable policy.

API design principles to follow:
- RESTful endpoints.
- Proper HTTP methods.
- JSON request and response format.
- Strict bearer token authentication for my clients.
- Per-client API keys hashed at rest.
- Validation of all inputs.
- Clear error messages without leaking sensitive internals.
- Rate limiting per API client.
- Idempotency protection.
- Request tracing with unique reference IDs.
- Consistent response envelope.
- Secure sender ID enforcement so a client can only use sender IDs assigned to them.
- Support both single SMS and bulk SMS payloads.
- Support long messages and segment calculation.
- Record SMS segment count because billing is based on actual units consumed.

SMS billing rules:
- Billing must account for SMS segments, not only message count.
- A message that consumes 2 segments costs 2 units at provider level.
- My platform must charge the client based on actual segments consumed plus my margin.
- Provide a flexible pricing engine.
- Support tiered pricing by customer type.
- Support wallet debit, credit, refund, and adjustment entries.
- Keep a ledger table for all wallet movements.
- Show how to compute customer charge, provider cost, and profit per request.

Pricing model requirement:
- Define pricing so I make a margin from the difference between my TalkSasa cost and my resale price.
- Support at least:
  - starter pricing
  - standard pricing
  - bulk pricing
- Allow pricing to be configured per customer, per plan, or globally.
- Do not hardcode the margin in the business logic.
- Make pricing editable from admin settings.

Frontend requirements:
Design the UI for:
1. Customer dashboard
   - current balance
   - recent sends
   - success/fail stats
   - quick send form
   - API key section
   - sender ID section
   - pricing info
   - payment top-up button
2. Unit purchase page
   - M-Pesa payment initiation
   - payment confirmation
   - pending/processing/success/fail states
3. API credentials page
   - create/revoke keys
   - show last used time
   - show permissions
4. SMS send page
   - single send
   - bulk send
   - live validation
   - estimated units before send
5. Reports page
   - delivery status
   - logs
   - request/response history
   - exportable records
6. Admin dashboard
   - customers
   - balances
   - price plans
   - queued jobs
   - failed jobs
   - delivery logs
   - M-Pesa payments
   - provider usage
   - audit logs

M-Pesa payment flow:
- Use the existing project’s M-Pesa template and integrate into this system.
- User clicks buy units.
- Payment request is initiated.
- Callback updates payment status.
- After confirmed payment, credit the customer wallet with SMS units.
- Record every payment in a transaction table.
- Support webhook/callback validation.
- Prevent duplicate wallet credits from repeated callbacks using reference locking and idempotency.

Backend requirements:
- Use clean MVC or modular structure.
- Use Composer if needed.
- Use `.env` or secure config files for secrets.
- Use cURL or Guzzle for external API calls, but keep deployment simple.
- Use prepared statements everywhere.
- Validate phone numbers, sender IDs, messages, API keys, and balances.
- Log every provider request and response.
- Implement retries with exponential or controlled backoff.
- Maintain delivery timestamps, attempts, and final status.
- Add audit logs for admin actions.
- Use transactions for wallet deduction and queue insertion.
- Prevent race conditions in balance deductions.

Database design goals:
Design an optimized schema for this exact system. Include tables for:
- clients/customers
- API keys
- sender IDs
- wallet balances
- wallet ledger
- pricing plans
- SMS queue
- SMS attempts
- provider SMS logs
- M-Pesa transactions
- delivery reports
- webhook logs
- rate limits or usage counters
- audit logs
- system settings
- job retries / dead-letter records
- optional customer notifications

Make the schema normalized but practical for shared hosting. Avoid overengineering. Use foreign keys where helpful. Use indexes for:
- client_id
- status
- created_at
- next_attempt_at
- provider_message_id
- request_reference
- transaction_reference

Database behavior requirements:
- Queue jobs must be claimable safely by the worker.
- A queued job must move through statuses such as:
  queued → sending → sent
  queued → sending → failed → retrying
  queued → sending → dead_letter / refunded / manual_review
- Every attempt must be recorded.
- Store the provider raw response.
- Store the client request payload.
- Store the computed billing values:
  - provider segment count
  - provider cost
  - sell price
  - profit
- Keep a reusable ledger rather than overwriting balances silently.

Minimum tables to include and optimize:
- clients
- client_api_keys
- sender_ids
- pricing_plans
- wallet_accounts
- wallet_ledger
- sms_requests
- sms_queue
- sms_attempts
- provider_sms_logs
- mpesa_transactions
- delivery_reports
- audit_logs
- system_settings

Recommended fields for core tables:you can adjust as needed during implementation, but this is a solid starting point.
1. clients
   - id
   - name
   - email
   - phone
   - status
   - plan_id
   - created_at

2. client_api_keys
   - id
   - client_id
   - key_hash
   - key_prefix
   - last_used_at
   - status
   - created_at

3. sender_ids
   - id
   - client_id
   - sender_id
   - approval_status
   - status
   - created_at

4. wallet_accounts
   - client_id
   - balance_units
   - reserved_units
   - updated_at

5. wallet_ledger
   - id
   - client_id
   - entry_type
   - units
   - reference
   - note
   - created_at

6. pricing_plans
   - id
   - plan_name
   - provider_markup_type
   - markup_value
   - min_topup
   - status
   - created_at

7. sms_requests
   - id
   - client_id
   - request_reference
   - recipient
   - message
   - sender_id
   - estimated_segments
   - estimated_cost
   - final_cost
   - status
   - created_at

8. sms_queue
   - id
   - sms_request_id
   - client_id
   - status
   - attempts
   - next_attempt_at
   - locked_at
   - locked_by
   - created_at
   - updated_at

9. sms_attempts
   - id
   - sms_request_id
   - attempt_no
   - provider_request_payload
   - provider_response_payload
   - http_code
   - error_message
   - sent_at
   - created_at

10. provider_sms_logs
    - id
    - provider_message_id
    - sms_request_id
    - direction
    - sms_type
    - sender_name
    - recipient
    - sms_count
    - provider_cost
    - status
    - provider_date
    - raw_payload
    - created_at

11. mpesa_transactions
    - id
    - client_id
    - checkout_request_id
    - merchant_request_id
    - phone
    - amount
    - units_credited
    - status
    - callback_payload
    - created_at
    - updated_at

12. delivery_reports
    - id
    - sms_request_id
    - provider_message_id
    - status
    - delivered_at
    - failed_at
    - failure_reason
    - created_at

13. audit_logs
    - id
    - actor_type
    - actor_id
    - action
    - entity_type
    - entity_id
    - metadata
    - created_at

14. system_settings
    - id
    - setting_key
    - setting_value
    - updated_at

Operational logic to implement:
- Validate API bearer token.
- Validate sender_id ownership.
- Validate recipient number format.
- Validate message length and segment estimate.
- Check wallet balance before sending.
- Reserve or deduct units atomically.
- Attempt immediate synchronous send.
- If immediate path fails, save to queue.
- Cron worker processes queued items.
- Update delivery report and ledger after provider response.
- Retry safely with capped attempts.
- Refund or flag unresolved failures.
- Prevent duplicate SMS sends.
- Support localhost testing and production shared hosting.

Implementation output required from you:
1. A clear system architecture.
2. Frontend page list and page behavior.
3. API endpoint list with request/response examples.
4. Database schema with SQL table creation statements.
5. Queue and worker logic.
6. M-Pesa top-up flow.
7. Pricing model logic.
8. Security and validation rules.
9. Folder/file structure for the PHP project.
10. A phased implementation plan.

Style requirements:
- Keep the system simple, practical, and production-ready.
- Prefer maintainability over fancy abstractions.
- Think like a backend engineer, frontend designer, and systems architect at once.
- Optimize for shared hosting.
- Avoid Redis, Kafka, RabbitMQ, or complicated infra.
- Use only what is necessary to make the system reliable, auditable, and profitable.

Now produce:
- the architecture
- the database schema
- the endpoint design
- the UI plan
- the worker strategy
- the pricing strategy
- and the implementation steps
in a way that I can directly build from it.

Folder/File Structure:
```text
clayon/
├── api/
│   ├── v1/
│   │   ├── send_sms.php       # Client API endpoint
│   │   ├── balance.php        # Client balance check
│   │   └── history.php        # Client message history
├── src/
│   ├── QueueService.php       # Pure MySQL queue logic
│   ├── SMSService.php         # TalkSasa integration logic
│   ├── PaymentCallback.php    # M-Pesa STK callback handler
│   ├── SmsCallback.php        # TalkSasa delivery report handler
│   ├── Worker.php             # Cron-triggered worker
│   └── Auth.php               # API key validation
├── sql/
│   └── schema.sql             # Consolidated database schema
├── templates/                # Frontend UI templates
└── clayon.md                 # System documentation (this file)
```

Technical Implementation Details:

1. Hybrid Queuing Strategy:
   - Synchronous Path: When a client calls `send_sms.php`, the system first records the request in `sms_requests`. It then attempts an immediate `curl` call to TalkSasa via `QueueService::sendCriticalSMS()`.
   - Asynchronous Fallback: If the sync call fails or takes > 5s, the item is inserted into `sms_queue` with a status of `pending`. The client receives a "queued" response.

2. Worker Logic (Cron):
   - A cron job runs `src/Worker.php` every minute.
   - The worker uses `QueueService::claimJobs()` to atomically lock rows in `sms_queue` (preventing double sends on overlapping cron runs).
   - It attempts the send and updates both `sms_attempts` (for logs) and `sms_queue` (releasing or moving to dead_letter).

3. Payment & Callback Integration:
   - Follows the project template from `confirmation.php`.
   - Uses `GET_LOCK()` to prevent race conditions during wallet crediting.
   - Validates `CheckoutRequestID` and records raw payloads for auditing.
   - Credits `balance_units` in `wallet_accounts` and creates a ledger entry.

4. Delivery Reports (Webhooks):
   - `src/SmsCallback.php` listens for TalkSasa's delivery reports.
   - It matches `provider_message_id` to update the `delivery_reports` and `sms_requests` tables.

Style requirements:
- Keep the system simple, practical, and production-ready.
- Prefer maintainability over fancy abstractions.
- Think like a backend engineer, frontend designer, and systems architect at once.
- Optimize for shared hosting.
- Avoid Redis, Kafka, RabbitMQ, or complicated infra.
- Use only what is necessary to make the system reliable, auditable, and profitable.

Now proceed with the implementation using the provided SQL and PHP templates in the `clayon/` directory.