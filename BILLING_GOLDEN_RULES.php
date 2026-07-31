<?php
/**
 * GOLDEN RULES - Bulk SMS Selling Platform Billing Logic
 * 
 * This prompt defines the correct billing behavior for a production SMS platform.
 * Current system: INCORRECT (debits on request, not on delivery)
 * Target system: CORRECT (debits only on confirmed delivery)
 */

/**
 * ╔════════════════════════════════════════════════════════════════════════════════════╗
 * ║                       GOLDEN RULES FOR SMS BILLING                                ║
 * ╚════════════════════════════════════════════════════════════════════════════════════╝
 */

// RULE 1: DEBITING PHILOSOPHY
// ══════════════════════════════════════════════════════════════════════════════════════
/**
 * NEVER debit units BEFORE confirming delivery success.
 * 
 * Why:
 * - Client sends SMS → System debits immediately (WRONG)
 * - If delivery fails → Client loses units but message didn't deliver (FRAUD RISK)
 * - Client should only pay for DELIVERED messages, not failed attempts
 * 
 * Correct Flow:
 * 1. Create SMS Request (status: pending) → DO NOT DEBIT
 * 2. Attempt to send via provider → DO NOT DEBIT YET
 * 3. Receive delivery confirmation from provider → ONLY THEN DEBIT
 * 4. Mark status: completed → Units already debited
 * 5. If delivery fails → NEVER debit, keep units in wallet
 * 
 * Current Implementation Issue:
 * ❌ send_sms.php debits IMMEDIATELY on line: UPDATE wallet_accounts SET balance_units = balance_units - ?
 * ❌ This happens BEFORE provider confirmation
 * ❌ No distinction between request creation and actual delivery
 */

// RULE 2: STATUS DEFINITIONS & DEBIT TIMING
// ══════════════════════════════════════════════════════════════════════════════════════
/**
 * STATUS LIFECYCLE:
 * 
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ pending (1)                                                         │
 * │ ├─ Meaning: Request created, waiting to be sent                   │
 * │ ├─ Debit Units?: NO                                               │
 * │ ├─ Duration: seconds to minutes                                   │
 * │ └─> Next: processing                                             │
 * └─────────────────────────────────────────────────────────────────────┘
 * 
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ processing (2)                                                      │
 * │ ├─ Meaning: Currently sending to provider API                     │
 * │ ├─ Debit Units?: NO (not confirmed yet)                           │
 * │ ├─ Duration: 5-30 seconds                                         │
 * │ └─> Next: completed OR failed                                    │
 * └─────────────────────────────────────────────────────────────────────┘
 * 
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ completed (3) ✅ DEBIT HERE                                        │
 * │ ├─ Meaning: Provider confirmed acceptance/delivery                │
 * │ ├─ Debit Units?: YES - ONLY in this state                         │
 * │ ├─ When?: Immediately after provider returns success              │
 * │ ├─ Confirmation: Provider message_id received                     │
 * │ └─> Final state                                                   │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ failed (4)                                                          │
 * │ ├─ Meaning: Provider rejected or failed to send                   │
 * │ ├─ Debit Units?: NEVER                                            │
 * │ ├─ Reason: Message was not delivered to end user                  │
 * │ ├─ Refund?: Units remain in wallet (never debited)                │
 * │ └─> Final state (possibly retry)                                  │
 * └─────────────────────────────────────────────────────────────────────┘
 */

// RULE 3: QUEUED MESSAGES VS COMPLETED MESSAGES
// ══════════════════════════════════════════════════════════════════════════════════════
/**
 * QUEUED MESSAGES (in sms_queue table):
 * ├─ Status in sms_requests: 'pending' or 'processing'
 * ├─ Wallet state: Units NOT YET DEBITED
 * ├─ Reason: Message hasn't been sent to provider yet
 * ├─ Outcome 1 (Success): sms_queue processed → status: completed → DEBIT units
 * ├─ Outcome 2 (Failure): sms_queue dead_letter → status: failed → DO NOT DEBIT
 * └─ Critical: Queue entries do NOT trigger debits. Only status:completed does.
 * 
 * COMPLETED MESSAGES:
 * ├─ Status: 'completed' (ONLY THIS STATUS)
 * ├─ Wallet state: Units ALREADY DEBITED
 * ├─ When debited: The moment provider confirmed (HTTP 200-201, message_id returned)
 * ├─ Verification: Check provider_sms_logs.provider_message_id IS NOT NULL
 * ├─ Refund eligibility: NO (unless manual admin refund)
 * └─ Delivery state: Provider accepted, message in their system
 * 
 * KEY DISTINCTION:
 * ❌ WRONG: "Queued in sms_queue" = "Should be debited"
 * ✅ CORRECT: "Status = completed" = "Has been debited"
 * 
 * A message can be:
 * - Queued (in sms_queue) + Pending (status) + NOT DEBITED
 * - Queued (in sms_queue) + Processing (status) + NOT DEBITED
 * - Queued (in sms_queue) + Completed (status) + ALREADY DEBITED ✅
 * - Not in queue + Completed (status) + ALREADY DEBITED ✅
 */

// RULE 4: TRANSACTION ATOMICITY & SAFETY
// ══════════════════════════════════════════════════════════════════════════════════════
/**
 * CORRECT SEQUENCE (with transactions):
 * 
 * BEGIN TRANSACTION
 *   1. INSERT sms_requests (status: pending)
 *   2. INSERT sms_queue (status: pending)
 *   3. INSERT wallet_ledger (entry_type: reserved, units: X) [OPTIONAL - for transparency]
 * COMMIT
 * [At this point: NO UNITS DEBITED, only reserved in ledger]
 * 
 * LATER: Background worker processes queue entry
 * BEGIN TRANSACTION
 *   1. Query sms_queue WHERE status='pending' FOR UPDATE LOCK
 *   2. Call provider API (TalkSasa, etc.)
 *   3. If success (HTTP 200, message_id received):
 *      a. UPDATE sms_requests SET status='completed'
 *      b. UPDATE wallet_accounts SET balance_units = balance_units - X [DEBIT HERE]
 *      c. INSERT wallet_ledger (entry_type: debit, reference: provider_message_id)
 *      d. UPDATE sms_queue SET status='locked' → then DELETE
 *   4. If failure (HTTP 4xx/5xx):
 *      a. UPDATE sms_requests SET status='failed'
 *      b. DO NOT update wallet_accounts (NO DEBIT)
 *      c. INSERT sms_attempts (error_message, http_code)
 *      d. UPDATE sms_queue SET status='failed', next_attempt_at=NOW()+INTERVAL 5 MINUTE
 * COMMIT/ROLLBACK
 * 
 * Safety Properties:
 * ✅ Double-debit impossible (transaction lock + status check)
 * ✅ No orphaned debits (debit only with completed status)
 * ✅ Audit trail (wallet_ledger has timestamps + reference)
 * ✅ Refund clarity (only refunded status allows reversal)
 */

// RULE 5: WALLET LEDGER STRUCTURE
// ══════════════════════════════════════════════════════════════════════════════════════
/**
 * wallet_ledger entries MUST map to sms_requests status transitions:
 * 
 * Entry Type: 'credit'
 * └─ When: Client pays, admin adds units, refund issued
 * └─ Examples: initial_balance, topup_payment_success, manual_refund
 * └─ Impact: Wallet balance INCREASES
 * 
 * Entry Type: 'debit'
 * └─ When: SMS successfully delivered (provider confirmed)
 * └─ Examples: sms_delivery_cost, provider_message_id_received
 * └─ Reference: Must link to provider_sms_logs.provider_message_id
 * └─ Impact: Wallet balance DECREASES
 * └─ CRITICAL: Should ONLY exist when sms_requests.status='completed'
 * 
 * Entry Type: 'refund'
 * └─ When: Reversing a previous debit (admin action or failed delivery)
 * └─ Examples: failed_delivery_refund, customer_complaint_refund
 * └─ Reference: Must link to original debit entry_id
 * └─ Impact: Wallet balance INCREASES
 * 
 * Query to find inconsistencies:
 * SELECT sr.id, sr.status, COUNT(wl.id) as ledger_entries
 * FROM sms_requests sr
 * LEFT JOIN wallet_ledger wl ON wl.reference = sr.request_reference
 * WHERE sr.status='completed' AND wl.entry_type != 'debit'
 * GROUP BY sr.id
 * HAVING COUNT(wl.id) = 0;
 * [Result = 0 means all completed messages have debit entry]
 */

// RULE 6: HANDLING PROVIDER RESPONSES
// ══════════════════════════════════════════════════════════════════════════════════════
/**
 * SUCCESS RESPONSES (debit immediately):
 * ├─ HTTP 200: Message queued in provider system
 * ├─ HTTP 201: Message accepted and scheduled
 * ├─ Body: Contains "message_id" or "request_id"
 * ├─ Action: UPDATE sms_requests SET status='completed', final_cost=X
 * ├─ Debit: INSERT wallet_ledger (debit, units, message_id)
 * └─ Then: UPDATE wallet_accounts balance
 * 
 * FAILURE RESPONSES (do NOT debit):
 * ├─ HTTP 400: Invalid recipient or message format
 * ├─ HTTP 401/403: Authentication error (retry possible)
 * ├─ HTTP 422: Validation error (endpoint won't accept)
 * ├─ HTTP 429: Rate limit (retry with backoff)
 * ├─ HTTP 5xx: Server error (retry possible)
 * ├─ Connection timeout: Retry possible
 * ├─ Action: UPDATE sms_requests SET status='failed'
 * ├─ Wallet: DO NOT TOUCH (balance remains unchanged)
 * └─ Then: Increment sms_queue attempts, schedule retry
 * 
 * TalkSasa Specific:
 * ├─ Success: {"success": true, "message_id": "TALKSASA-123456"}
 * ├─ Failure: {"success": false, "error": "Invalid recipient"}
 * ├─ Rate limit: HTTP 429 + {"error": "Rate limit exceeded"}
 * └─ Timeout: No response after 30 seconds
 */

// RULE 7: QUEUE RETRY LOGIC (no extra debits)
// ══════════════════════════════════════════════════════════════════════════════════════
/**
 * Retry attempts should NEVER trigger additional debits:
 * 
 * Scenario: Message fails on attempt 1, succeeds on attempt 2
 * ├─ Attempt 1: status='processing' → fails → status='failed' → NO DEBIT
 * ├─ Queue: sms_queue.attempts=1, next_attempt_at=NOW()+5MIN
 * ├─ Attempt 2: status='processing' → succeeds → status='completed' → DEBIT ONCE
 * ├─ Queue: Removed from queue
 * └─ Wallet: Only ONE debit entry for this message_id
 * 
 * Multiple debits prevention:
 * ├─ Check: IF sms_requests.status='completed' THEN skip debit
 * ├─ Protection: UPDATE sms_requests SET status='completed' atomically with debit
 * ├─ Audit: Query wallet_ledger for duplicate reference entries (should not exist)
 * └─ Alert: If found, flag as potential double-charge incident
 */

// RULE 8: CURRENT SYSTEM PROBLEMS
// ══════════════════════════════════════════════════════════════════════════════════════
/**
 * PROBLEM 1: Immediate Debit on Request Creation
 * Location: clayon/api/v1/send_sms.php (line 82-83)
 * ```php
 * $stmt = $db->prepare("UPDATE wallet_accounts SET balance_units = balance_units - ? WHERE client_id = ?");
 * $stmt->execute([$segments, $clientId]);
 * ```
 * Issue: Debits BEFORE sending to provider, BEFORE status is 'completed'
 * Risk: Failed messages waste client units
 * Severity: CRITICAL
 * 
 * PROBLEM 2: No Status Check Before Debit
 * Location: Same file, no validation of sms_requests.status
 * Issue: Could debit queued messages, pending messages, failed messages
 * Risk: Debit inconsistency, multiple debits, audit trail failures
 * Severity: CRITICAL
 * 
 * PROBLEM 3: sms_queue Only Records Status, Doesn't Drive Billing
 * Current: sms_queue.status tracked separately from sms_requests.status
 * Issue: Queue status ('locked', 'pending', 'failed') not linked to debit logic
 * Should be: sms_requests.status='completed' is the SINGLE source of truth for debit
 * Risk: Orphaned queue entries, inconsistent billing state
 * Severity: HIGH
 * 
 * PROBLEM 4: No Refund Mechanism for Failed Queued Messages
 * Current: If queued message fails, no automatic refund
 * Should be: Check sms_requests.status='failed' and refund_units if needed
 * Risk: Clients lose units on failed sends (major complaint source)
 * Severity: HIGH
 */

// RULE 9: CORRECTED FLOW (IMPLEMENTATION PLAN)
// ══════════════════════════════════════════════════════════════════════════════════════
/**
 * PHASE 1: Request Creation (NO DEBIT)
 * ─────────────────────────────────────
 * Endpoint: POST /api/send
 * Input: {recipient, message, sender_id}
 * 
 * Steps:
 * 1. Validate input (phone, message length)
 * 2. Calculate segments = ceil(len(message) / 160)
 * 3. Check balance >= segments [CHECK ONLY, no debit]
 * 4. BEGIN TRANSACTION
 * 5.   INSERT sms_requests (status='pending')
 * 6.   INSERT wallet_ledger (entry_type='reserved', units=segments) [optional]
 * 7. COMMIT
 * 8. Enqueue: INSERT sms_queue (sms_request_id, status='pending')
 * 9. Return: {request_id, status='queued', segments, estimated_cost} [HTTP 202]
 * 
 * Wallet State: NOT DEBITED
 * Queue State: Waiting for worker
 */

/**
 * PHASE 2: Worker Processing (DEBIT ON SUCCESS ONLY)
 * ────────────────────────────────────────────────────
 * Trigger: Background cron worker
 * Frequency: Every 1-2 minutes
 * 
 * Steps:
 * 1. BEGIN TRANSACTION
 * 2. SELECT * FROM sms_queue WHERE status='pending' LIMIT 20 FOR UPDATE
 * 3. For each queue entry:
 *    a. Get sms_request details
 *    b. Call provider API (TalkSasa)
 *    c. Parse response
 *    d. If HTTP 200-201 AND message_id:
 *       i. UPDATE sms_requests SET status='completed'
 *       ii. UPDATE wallet_accounts SET balance_units -= segments [DEBIT HERE]
 *       iii. INSERT wallet_ledger (debit, segments, message_id reference)
 *       iv. INSERT provider_sms_logs (provider_message_id, status)
 *       v. DELETE FROM sms_queue
 *    e. Else (failure):
 *       i. UPDATE sms_requests SET status='failed'
 *       ii. DO NOT touch wallet_accounts
 *       iii. INSERT sms_attempts (error details)
 *       iv. UPDATE sms_queue (attempts++, next_attempt_at, status='pending')
 * 4. COMMIT
 * 
 * Wallet State After Success: DEBITED
 * Wallet State After Failure: NOT DEBITED (units safe)
 * Queue State: Removed or scheduled for retry
 */

/**
 * PHASE 3: Delivery Reports (Optional Enhancement)
 * ──────────────────────────────────────────────────
 * Some providers send webhooks when delivery confirmed
 * Current logic: Debit on acceptance (message_id received)
 * Future: Could debit on actual delivery (end-user received SMS)
 * 
 * For now: Accept payment on provider acceptance (not final delivery)
 * Reason: Provider accepts = our liability, we must pay provider
 */

// RULE 10: VALIDATION CHECKLIST
// ══════════════════════════════════════════════════════════════════════════════════════
/**
 * Before deploying corrected logic, verify:
 * 
 * ☐ sms_requests.status='completed' has wallet_ledger.entry_type='debit' entry
 * ☐ sms_requests.status='failed' has NO wallet_ledger.entry_type='debit' entry
 * ☐ sms_requests.status='pending' has NO wallet_ledger.entry_type='debit' entry
 * ☐ wallet_ledger.reference matches provider_message_id (for auditing)
 * ☐ No duplicate wallet_ledger entries for same sms_request_id
 * ☐ wallet_accounts.balance_units = SUM(credits) - SUM(debits) [reconciliation]
 * ☐ All failed messages with retry attempts eventually reach 'completed' or 'dead_letter'
 * ☐ Client cannot send if balance < segments [before creation, prevents negative balance]
 * ☐ All debits have timestamp and audit trail
 * ☐ Wallet_ledger.note contains enough context for customer service investigation
 */

// ═══════════════════════════════════════════════════════════════════════════════════════
// IMPLEMENTATION SUMMARY
// ═══════════════════════════════════════════════════════════════════════════════════════

/**
 * Current System State: ❌ INCORRECT
 * ├─ Debits on request creation (before sending)
 * ├─ No distinction between queued and delivered
 * ├─ No refund mechanism for failed queued messages
 * ├─ Potential for negative wallet balances
 * └─ Client-facing issue: "Why did I lose units if message failed?"
 * 
 * Target System State: ✅ CORRECT
 * ├─ Debits only when provider confirms (status='completed')
 * ├─ Queued messages don't consume units (yet)
 * ├─ Failed messages automatically refund (never debited)
 * ├─ Atomic transactions prevent double-debit
 * ├─ Audit trail shows when, why, and by what (provider_message_id)
 * └─ Client-facing benefit: "Only pay for delivered messages"
 * 
 * Changes Required:
 * 1. Remove immediate debit from send.php / send_sms.php
 * 2. Add debit logic to Worker.php (only on success response)
 * 3. Add status check before any wallet operation
 * 4. Update wallet_ledger with provider_message_id reference
 * 5. Add refund logic for permanently failed messages
 * 6. Update frontend to show proper status (queued vs delivered)
 */

?>
