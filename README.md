# GOA POS

Point of sale for a single restaurant, bar or cafe in South Sudan. One server on the venue's local network runs everything, so orders and kitchen tickets keep working when the internet is down. Design doc: https://claude.ai/code/artifact/de34b050-d95f-4619-a50e-4bf3d9179e12

Stack: Laravel 13 (API, Sanctum tokens, SQLite) and a Vue 3 touch interface built with Vite and Tailwind.

## Phase 1 (this code)

- **Staff login by PIN**, with roles: admin, manager, server, bartender, cashier, kitchen.
- **Menu** with categories, modifiers (required and optional choices with price changes) and a sold-out ("86") switch for managers.
- **One order model for all three service styles:** restaurant tables, bar tabs and counter orders.
- **Courses:** items can be held and sent by course.
- **Ticket screens per station** (kitchen, bar, barista): tickets age from white to amber (10 min) to red (20 min), with Done and recall.
- **Voids:** unsent items can be removed by anyone; items already sent need a manager and a reason. Every void and menu change goes to the audit log.
- Prices are stored as integer piasters (1 SSP = 100) and copied onto each order line when sold.

## Phase 2

- **Payments:** SSP cash, US dollar cash at a manager-set rate (change always given in SSP), mobile money and card with a transaction reference. Bills can be split across several payments, with tips.
- **Bills:** optional service charge and tax, either included in menu prices or added on top, all set from the Manager screen. Bills and receipts print on 80 mm paper from the browser.
- **Closing:** an order closes when fully paid; anything unsent goes to its station then, so pay-first counter orders reach the barista.
- **Cash drawer:** open with an SSP and USD float; a manager counts up and sees how much the drawer is over or short.
- **End-of-day report:** bills, sales, service charge, tax, tips, totals per payment type, voids and top items, using South Sudan time.

The tax rate defaults to 0% until a manager sets it; confirm the current rate and receipt rules with an accountant.

Not yet built: refunds, inventory, cloud sync and backup.

## Run it

```sh
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

Open `http://<server-ip>:8000` on each terminal or kitchen screen. Demo PINs from the seeder:

| PIN | Role |
| --- | --- |
| 1000 | Admin |
| 1111 | Manager |
| 2222 | Server |
| 3333 | Bartender |
| 4444 | Cashier |
| 5555 | Kitchen (ticket screens only) |

Change these before using the system for real.

## Tests

```sh
php artisan test
```
