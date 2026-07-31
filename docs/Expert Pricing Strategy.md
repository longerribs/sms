# Clayon Expert Pricing Strategy

This document outlines the mathematical models and database relationships used to calculate SMS costs and profit margins for the Clayon Reseller Platform.

---

## 1. Consumption Strategy (Outbound SMS)
The platform uses a custom segment-based billing model designed to maximize reseller margins on longer messages.

### Character Mapping
*   **Segment Size**: 60 characters.
*   **Base Cost**: 1 Unit per segment for the first two segments.
*   **Penalty Cost**: 2 Units per segment for the 3rd segment and beyond.

| Characters | Segments | Total Unit Cost |
| :--- | :--- | :--- |
| 0 - 60 | 1 | 1 Unit |
| 61 - 120 | 2 | 2 Units |
| 121 - 180 | 3 | 4 Units (Penalty applied) |
| 181 - 240 | 4 | 6 Units |
| 241 - 300 | 5 | 8 Units |

**Logic Location**: `SMSService::calculateSegments()`

---

## 2. Purchase Strategy (Wallet Refill)
Profit is generated at the point of sale by applying a markup to the provider's base rate.

### The Profit Formula
The number of units a client receives is calculated by deflating the provider's base rate by the client's specific markup.

`Client Rate = Provider Base Rate / (1 + (Markup / 100))`

### Current Tiers (Example)
*Base Provider Rate: 2.0 Units/KES*

| Plan | Markup | Units Received per 1 KES |
| :--- | :--- | :--- |
| **Default** | 25% | 1.60 Units |
| **Premium** | 30% | 1.54 Units |
| **Starter** | 0.25 (Fixed) | 1.75 Units |

**Logic Location**: `PaymentCallback.php` (for automation) and `buy.php` (for preview).

---

## 3. Database Relationships

### Pricing Integration
*   **`system_settings`**: Stores the global `PROVIDER_UNITS_PER_KES` (Default: 2.0).
*   **`pricing_plans`**: Defines the `markup_value` and `provider_markup_type` (percentage or fixed).
*   **`clients`**: Linked to plans via `plan_id`. If `plan_id` is NULL, the system defaults to a 0% markup (Standard).

### Transactional Integrity
*   **`mpesa_transactions`**: Logs the initial KES amount.
*   **`wallet_accounts`**: Stores the final `balance_units` after applying the Reseller Rate.
*   **`wallet_ledger`**: Records the exact conversion rate used for that specific transaction for future audits.

---

## 4. Implementation Notes
To adjust your profit margins globally:
1.  **To change your provider's base cost**: Update `system_settings` where `setting_key = 'PROVIDER_UNITS_PER_KES'`.
2.  **To change a client's specific margin**: Update their `plan_id` in the `clients` table or modify the `markup_value` in `pricing_plans`.
