# SMS Reseller Pricing Breakdown

> **System Config**: Segment = 60 characters | Provider Base Rate = 2.0 Units/KES | Default Markup = 25%

---

## Part 1 — Provider vs Reseller Unit Economics

| Metric | TalkSasa (Provider) | Your Reseller (Default Plan, 25% Markup) |
| :--- | :---: | :---: |
| **Units per KES 1** | 2.0 Units | 1.6 Units |
| **KES per 1 Unit** | KES 0.500 | KES 0.625 |
| **Markup Applied** | — | +25% on wholesale |
| **Your Profit per Unit** | — | KES 0.125 |
| **Gross Profit Margin** | — | 25% *(markup on cost)* |
| **Net Profit Margin** | — | 20% *(profit / revenue)* |

---

## Part 2 — Message Segment Pricing (Per Message Sent)

| Message Length | Segments | Units Charged | Provider Cost (KES) | Customer Pays (KES) | Reseller Profit (KES) |
| :--- | :---: | :---: | :---: | :---: | :---: |
| 1 – 60 chars | 1 | **1 Unit** | 0.50 | **0.625** | +0.125 |
| 61 – 120 chars | 2 | **2 Units** | 1.00 | **1.250** | +0.250 |
| 121 – 180 chars | 3 | **4 Units** *(tier kicks in)* | 2.00 | **2.500** | +0.500 |
| 181 – 240 chars | 4 | **6 Units** | 3.00 | **3.750** | +0.750 |
| 241 – 300 chars | 5 | **8 Units** | 4.00 | **5.000** | +1.000 |

> [!NOTE]
> **Tiered Rule**: Segments 1 & 2 = 1 unit each. Segment 3 onwards = **2 units each**.
> This is why costs jump from 2 → 4 units between 120 and 180 characters.

---

## Part 3 — Top-Up Comparison (What Customer Gets per KES Spent)

| Top-Up Amount (KES) | Units Credited | SMS (60-char) Messages | SMS (120-char) Messages | You Pay Provider (KES) | Your Profit (KES) |
| :---: | :---: | :---: | :---: | :---: | :---: |
| KES 100 | 160 Units | 160 messages | 80 messages | 80.00 | **+20.00** |
| KES 500 | 800 Units | 800 messages | 400 messages | 400.00 | **+100.00** |
| KES 1,000 | 1,600 Units | 1,600 messages | 800 messages | 800.00 | **+200.00** |
| KES 5,000 | 8,000 Units | 8,000 messages | 4,000 messages | 4,000.00 | **+1,000.00** |

---

## Part 4 — Plan Comparison (All Pricing Plans)

| Plan | Markup Type | Markup Value | Units per KES 1 | KES per Unit | Profit per Unit | Gross Margin | Net Margin |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Starter** | Fixed | +0.25 Units/KES | 1.75 Units | KES 0.571 | KES 0.071 | ~12.5% | ~12.4% |
| **Default** | Percentage | 25% | 1.60 Units | KES 0.625 | KES 0.125 | 25% | **20%** |
| **Premium** | Percentage | 30% | 1.54 Units | KES 0.650 | KES 0.150 | 30% | **23.1%** |

> [!TIP]
> The **Premium Plan** generates the highest profit per unit (KES 0.150), but gives customers fewer units per shilling, making it best for clients who value premium sender IDs and priority support.

---

## Part 5 — Profit Margin Analysis

### Margin Definitions

| Term | Formula | Meaning |
| :--- | :--- | :--- |
| **Gross Profit Margin** | `(Revenue - Provider Cost) / Provider Cost × 100` | How much above wholesale you are selling |
| **Net Profit Margin** | `(Revenue - Provider Cost) / Revenue × 100` | Share of every shilling collected that is profit |

---

### Default Plan (25% Markup) — Margin at Every Top-Up Level

| Customer Pays (KES) | Units Sold | Revenue | Provider Cost | Gross Profit | Gross Margin | Net Margin |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| 100 | 160 | KES 100.00 | KES 80.00 | **KES 20.00** | 25% | **20.0%** |
| 500 | 800 | KES 500.00 | KES 400.00 | **KES 100.00** | 25% | **20.0%** |
| 1,000 | 1,600 | KES 1,000.00 | KES 800.00 | **KES 200.00** | 25% | **20.0%** |
| 5,000 | 8,000 | KES 5,000.00 | KES 4,000.00 | **KES 1,000.00** | 25% | **20.0%** |

---

### All Plans — Side-by-Side Profit on KES 1,000 Top-Up

| Plan | Units Sold | Provider Cost | Your Revenue | **Your Profit** | Net Margin |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Starter** | 1,750 | KES 875.00 | KES 1,000.00 | **KES 125.00** | 12.5% |
| **Default** | 1,600 | KES 800.00 | KES 1,000.00 | **KES 200.00** | 20.0% |
| **Premium** | 1,540 | KES 770.00 | KES 1,000.00 | **KES 230.00** | 23.1% |

> [!IMPORTANT]
> Net Margin is **fixed per plan regardless of top-up amount** — you always earn the same percentage on every shilling collected. Profitability scales linearly with volume.
