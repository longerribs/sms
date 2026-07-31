# Billing Logic Refactoring - Implementation Summary

## Changes Made

### 1. **Removed Immediate Debit** (clayon/api/v1/send_sms.php)
- ❌ Removed: Direct wallet debit on request creation
- ✅ Added: Reserved entry in wallet_ledger (for transparency)
- Status: Request created as `'pending'` (not debited)

### 2. **Added createRequest Method** (clayon/src/SMSService.php)
- Creates SMS request without debiting
- Inserts `wallet_ledger` entry with `'reserved'` type
- Returns request data with status='pending'

### 3. **Updated API Responses** (clayon/api/send.php, clayon/api/v1/send_sms.php)
- New fields: `sms_status`, `billing_status`, `info`
- Clear messaging: "Units will be debited only after provider confirms delivery"
- HTTP 202 (Accepted): Message queued, not sent yet

### 4. **Implemented Debit on Provider Confirmation** (clayon/src/Worker.php)
**Key Logic:**
- Only debit AFTER `$smsService->executeRealSend()` returns `true`
- Get provider_message_id from provider_sms_logs
- Atomically update: wallet_accounts + wallet_ledger
- Link ledger entry to provider_message_id (audit trail)

**Failed Messages:**
- Never debit (no wallet_accounts update)
- Log error and schedule retry or dead_letter
- Reserved units stay in wallet (never consumed)

### 5. **Added directDebit Method** (clayon/src/WalletService.php)
- Safe debit only when provider confirms
- Checks balance before debit (prevents negative)
- Uses transaction for atomicity
- Creates ledger entry with provider reference

## Status Lifecycle (Corrected)

```
pending → processing → completed ✅ DEBIT HERE
                    → failed ❌ NEVER DEBIT
```

## Debit Trigger Points

| Status | Debited | When | By |
|--------|---------|------|-----|
| `pending` | ❌ NO | Request created | - |
| `processing` | ❌ NO | Sending to provider | - |
| `completed` | ✅ YES | Provider confirms (HTTP 200+) | Worker.php |
| `failed` | ❌ NO | Provider rejects (4xx/5xx) | - |
| `refunded` | ❌ NO | Manual admin reversal | Admin |

## Wallet Ledger Changes

**Before (Incorrect):**
- `'debit'` entry on request creation
- No provider reference

**After (Correct):**
- `'reserved'` entry on request creation
- `'debit'` entry only on provider confirmation
- `'debit'` reference = provider_message_id (audit trail)

## Safety Properties

✅ Double-debit impossible (status check + transaction)
✅ Orphaned debits prevented (completed status required)
✅ Audit trail (provider_message_id linked)
✅ Negative balance prevented (balance check before debit)
✅ Atomic operations (transactions on all wallet changes)

## Testing Checklist

- [ ] Send SMS → Wallet shows as "reserved" (not debited)
- [ ] Worker processes successfully → Units debited with provider_message_id
- [ ] Provider failure → Units remain in wallet (never debited)
- [ ] Retry scenario → Only ONE debit on final success
- [ ] wallet_ledger shows correct sequence: reserved → debit (or reserved only)
- [ ] No orphaned reserved entries (completed = debited)

## Files Modified

1. `clayon/api/v1/send_sms.php` - Remove immediate debit
2. `clayon/api/send.php` - Update response, clear billing status
3. `clayon/src/SMSService.php` - Add createRequest method
4. `clayon/src/Worker.php` - Add debit logic on success
5. `clayon/src/WalletService.php` - Add directDebit method

## Next Steps

1. Run verification queries (see BILLING_GOLDEN_RULES.php Rule 5)
2. Test SMS send → Worker processing → Wallet debit flow
3. Test failed SMS → No debit scenario
4. Monitor worker logs for debit operations
5. Update frontend to show "reserved" vs "debited" states

---

**Status**: ✅ Implementation Complete | Ready for Testing
