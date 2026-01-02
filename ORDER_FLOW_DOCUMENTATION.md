# Order Flow Documentation

## ✅ IMPLEMENTED ORDER FLOW

### 1️⃣ BUY ORDER FLOW

**Step 1: User Places BUY Order**
- User enters: `price` and `quantity`
- Example: BUY 0.1 BTC @ 40,000 USDT

**Step 2: Balance Check**
```php
required_amount = price × quantity
if (user.USDT.balance < required_amount) {
    → reject order (422 error)
}
```

**Step 3: Lock Balance (IMMEDIATELY)**
```php
user.USDT.balance -= required_amount
user.USDT.locked_balance += required_amount
```
✅ **IMPLEMENTED** in `OrderController::handleBuy()`

**Step 4: Create Order**
```php
status = 'open'
remaining_amount = amount
filled_amount = 0
```

---

### 2️⃣ SELL ORDER FLOW

**Step 1: User Places SELL Order**
- User enters: `price` and `quantity`
- Example: SELL 0.1 BTC @ 40,000 USDT

**Step 2: Balance Check**
```php
if (user.BTC.balance < quantity) {
    → reject order (422 error)
}
```

**Step 3: Lock Coin**
```php
user.BTC.balance -= quantity
user.BTC.locked_balance += quantity
```
✅ **IMPLEMENTED** in `OrderController::handleSell()`

**Step 4: Create Order**
```php
status = 'open'
remaining_amount = amount
filled_amount = 0
```

---

### 3️⃣ ORDER MATCHING FLOW

**Matching Rules:**
- ✅ BUY price >= SELL price (matches)
- ✅ Price-time priority (oldest orders first)
- ✅ Matches at the price of the existing order (not incoming order)

**Matching Quantity:**
```php
match_qty = min(
    buy.remaining_amount,
    sell.remaining_amount
)
```
✅ **IMPLEMENTED** in `OrderMatchingService::match()`

---

### 4️⃣ AFTER MATCH (WALLET UPDATES)

**Example: 0.1 BTC matches @ 40,000 USDT**

#### Buyer Side:
```php
// Buyer ordered at 40,000, matched at 40,000
locked_amount = 40,000 * 0.1 = 4,000 USDT
actual_cost = 40,000 * 0.1 = 4,000 USDT
fee = 4,000 * 0.001 = 4 USDT

// Updates:
buyer.USDT.locked_balance -= 4,000  // Release locked
buyer.USDT.balance -= 4             // Pay fee
buyer.BTC.balance += 0.1            // Receive BTC
```

#### Seller Side:
```php
// Seller ordered at 40,000, matched at 40,000
locked_amount = 0.1 BTC
proceeds = 40,000 * 0.1 = 4,000 USDT
fee = 4,000 * 0.001 = 4 USDT

// Updates:
seller.BTC.locked_balance -= 0.1    // Release locked BTC
seller.USDT.balance += 3,996        // Receive USDT minus fee
```

✅ **IMPLEMENTED** in `OrderMatchingService::updateWallets()`

---

### 5️⃣ PRICE DIFFERENCE HANDLING

**Example: Buyer orders @ 40,000, matches @ 39,000**

```php
locked_amount = 40,000 * 0.1 = 4,000 USDT
actual_cost = 39,000 * 0.1 = 3,900 USDT
refund = 4,000 - 3,900 = 100 USDT

// Updates:
buyer.USDT.locked_balance -= 4,000  // Release full locked
buyer.USDT.balance += 100           // Refund difference
buyer.USDT.balance -= 3.9           // Pay fee on actual cost
buyer.BTC.balance += 0.1            // Receive BTC
```

✅ **IMPLEMENTED** - Buyer gets refund if matched at better price

---

### 6️⃣ PARTIAL FILL CASE

**Example:**
- Buy order: 1 BTC @ 40,000
- Sell order: 0.4 BTC @ 40,000

**After Match:**
- ✅ Sell order → `status = 'filled'`
- ✅ Buy order → `status = 'partial'`, `remaining_amount = 0.6 BTC`
- ✅ Buy order locked balance: Only 0.4 BTC portion unlocked, 0.6 BTC stays locked

**Locked Balance After Partial Fill:**
```php
// Buyer still has locked:
remaining_locked = 0.6 * 40,000 = 24,000 USDT
```

✅ **IMPLEMENTED** - Only filled portion unlocks, remaining stays locked

---

### 7️⃣ CANCEL ORDER FLOW

**Only if:** `status = 'open'` OR `status = 'partial'`

#### BUY Order Cancelled:
```php
remaining_amount = order.remaining_amount
refund_usdt = remaining_amount × order.price

user.USDT.locked_balance -= refund_usdt
user.USDT.balance += refund_usdt
```
✅ **IMPLEMENTED** in `OrderController::refundBuyOrder()`

#### SELL Order Cancelled:
```php
remaining_qty = order.remaining_amount

user.BTC.locked_balance -= remaining_qty
user.BTC.balance += remaining_qty
```
✅ **IMPLEMENTED** in `OrderController::refundSellOrder()`

---

### 8️⃣ STATUS TRANSITIONS

```
open → partial (when first fill happens)
open → filled (when fully filled immediately)
open → cancelled (when user cancels)
partial → filled (when remaining fills)
partial → cancelled (when user cancels)
```

✅ **IMPLEMENTED** - All status transitions handled correctly

---

### 9️⃣ GOLDEN RULES (ALL IMPLEMENTED)

✅ **Balance lock happens IMMEDIATELY on order placement**
✅ **Balance unlock happens ONLY on match or cancel**
✅ **All wallet updates happen inside database transactions**
✅ **Frontend never trusted - all validation on backend**
✅ **Price-time priority matching**
✅ **Partial fills handled correctly**
✅ **Refunds for better prices**

---

### 🔟 DATABASE SCHEMA

**Orders Table:**
- ✅ `id`, `user_id`, `market_id`
- ✅ `type` (buy/sell)
- ✅ `price`, `amount`, `remaining_amount`
- ✅ `filled_amount` (for quick queries)
- ✅ `status` (open/partial/filled/cancelled)
- ✅ Proper indexes for performance

**Wallets Table:**
- ✅ `balance` (available)
- ✅ `locked_balance` (locked in orders)
- ✅ Unique constraint on (user_id, currency)

**Trades Table:**
- ✅ `buy_order_id`, `sell_order_id`
- ✅ `price`, `amount`, `fee_amount`
- ✅ Proper foreign keys

---

## ✅ VERIFICATION CHECKLIST

- [x] BUY order locks USDT immediately
- [x] SELL order locks base currency immediately
- [x] Matching uses price-time priority
- [x] Buyer gets refund if matched at better price
- [x] Partial fills unlock only filled portion
- [x] Cancellation refunds remaining locked balance
- [x] All operations in transactions
- [x] Proper status transitions
- [x] Fee deduction handled correctly
- [x] Wallet creation if doesn't exist

---

## 🎯 SUMMARY

**User places order → Balance locks immediately → Order matches → Locked balance settles → Asset/money credited → Order status updated**

All flows are production-ready and handle edge cases correctly.

