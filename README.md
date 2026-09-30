# DairyPro — Files to Replace

Extract this zip **into your project root**, overwriting the matching paths.
It only contains files that changed — everything else in your codebase is
untouched.

## 1. DELETE these files (no longer used)

- resources/views/batches/index.blade.php  → then delete the now-empty `resources/views/batches/` folder
- resources/views/khata/worker-combo-receipt.blade.php
- app/Models/WorkerTransaction.php

## 2. RUN a new migration after copying files in

```
php artisan migrate
```

This adds `sales.amount_tendered` and drops the old `worker_transactions`
table + the unused `workers.balance` column.

## 3. What changed, by request

1. **Search bar** — new `resources/views/components/search-bar.blade.php`,
   reused on Raw Materials, Customers, Suppliers, Sales History and the
   Products page. Pixel-identical to the POS search bar.
2. **Dynamic timing** — `app/Models/Setting.php` now maps your
   Settings → "Invoice Timing / Region" choice to a real timezone. The POS
   clock ticks live in that timezone (see the `liveClock` Alpine helper in
   `layouts/app.blade.php`), and every receipt's printed time uses it too.
3. **Products + Batch Production merged** into one page
   (`products/index.blade.php` — see `ProductController@index`). The
   separate Batch Production nav item, route, and view are gone;
   `batches.store` / `batches.exportCsv` still work exactly as before.
4. **Duplicate blocking** — Customer, Supplier and Product names are now
   checked case-insensitively (not just exact-case), and a Customer/Supplier
   can no longer share the same phone number as each other.
5. **Receipts** — `sales/show.blade.php` rebuilt to match your reference
   image (Receipt No, local date/time, Customer Details, SR/Item/Qty/
   Rate/Amount table, Total Items/Qty, Subtotal/Discount/Net Total, Amount
   Received/Change Due, Payment Mode/Status, thank-you note, then
   **GoravAI + WhatsApp support at the bottom**, no Operator line). Same
   settings-driven header/footer applied to the milk-collection and khata
   payment receipts.
6. **Reports (monthly) page** — added a "Total Workers' Salary" card, and
   restyled the Revenue vs Milk Purchase Cost chart with the Dashboard's
   gradient-fill look.
7. **Worker Khata module removed** — the salary/advance/bonus ledger, its
   tab on the Khata page, and its receipts are gone. The Workers page
   itself (roster, monthly salary, active/inactive) is untouched.
8. **Cash / other payment methods no longer forced to equal the total** —
   the cashier can hand in more than the total and the POS now shows a
   live "Change Due" figure; the receipt prints "Amount Received" and
   "Change Due" using the real cash tendered. Khata and Partial Khata
   behavior is unchanged. New nullable `sales.amount_tendered` column
   stores what was actually handed over — `paid_amount` still always
   equals the total (that's the real revenue); the difference is just
   change given back, not extra income.

## Notes / things worth knowing

- The receipt's "Change Due" only shows a nonzero figure for sales made
  **after** this update — older invoices don't have `amount_tendered`
  saved, so they display 0 change (which matches how they were actually
  paid).
- If you want Product names to also refuse duplicating a Raw Material
  name (or some other cross-check I haven't covered), tell me and I'll add
  it — I only added the Customer↔Supplier phone check since that was the
  clearest real-world duplicate-entry mistake.
