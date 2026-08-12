# DairyPro — Dairy Business Management System

Built by **GoravAI** (gorav.click)

A single-tenant Laravel 13 web app to run a dairy business: production recipes, batch
manufacturing with live cost tracking, sales/invoicing, customers, stock, reports, and
an AI Assistant powered by Ollama Cloud.

## 1. What's inside

- **Dashboard** — today's revenue, low stock alerts, 7-day revenue trend chart, top products, recent sales.
- **Products** — name, SKU, price, stock, and a recipe (list of ingredients + quantities needed per batch).
- **Ingredients** — raw materials with stock, cost per unit, and reorder level.
- **Batch Production** — pick a product, set a multiplier, see **live Batch Cost & Cost/Unit** update as you
  type, then run the batch. Stock is deducted from ingredients and added to the product automatically.
- **Sales** — create invoices with multiple line items, auto price lookup, stock deduction, printable invoice view.
- **Customers** — simple customer directory.
- **Reports** — date-range revenue trend and product performance.
- **AI Assistant** — a chat panel that talks to your Ollama Cloud account for production/cost questions.

The UI uses Tailwind CSS (via CDN, no build step needed), Google Fonts (Plus Jakarta Sans + Inter),
Alpine.js for the interactive bits, Chart.js for graphs, and Lucide icons (real SVG icons, no emojis).

## 2. Requirements

- PHP 8.2 or newer, with the usual extensions (mbstring, pdo_mysql, openssl, tokenizer, xml, ctype, json)
- Composer
- MySQL 8 (or MariaDB) — the app is set up for MySQL by default
- (Optional) An Ollama Cloud API key if you want the AI Assistant to actually respond — https://ollama.com

## 3. Setup — step by step

```bash
# 1. Go into the project folder
cd dairypro

# 2. Install PHP packages (this downloads Laravel itself, so you need internet access here)
composer install

# 3. Copy the environment file
cp .env.example .env

# 4. Generate the app encryption key
php artisan key:generate

# 5. Open .env and set your MySQL details
#    DB_DATABASE=dairypro
#    DB_USERNAME=root
#    DB_PASSWORD=yourpassword
# Then create the database itself, e.g.:
#    mysql -u root -p -e "CREATE DATABASE dairypro"

# 6. Run migrations and load demo data (sample ingredients, products, a demo login)
php artisan migrate --seed

# 7. Start the app
php artisan serve
```

Now open **http://localhost:8000** in your browser.

**Demo login:**
- Email: `admin@gorav.click`
- Password: `password`

## 4. Turning on the AI Assistant

1. Sign up at https://ollama.com and open the Cloud section to get an API key.
2. Open `.env` and fill in:
   ```
   OLLAMA_CLOUD_API_KEY=your-key-here
   OLLAMA_CLOUD_MODEL=llama3.1
   ```
3. Restart `php artisan serve`. The AI Assistant page will now get real replies.

If you leave the key blank, the assistant page still works but tells the user it isn't configured yet
instead of crashing.

## 5. Notes for going live

- Set `APP_ENV=production` and `APP_DEBUG=false` in `.env` before deploying.
- Run `php artisan config:cache` and `php artisan route:cache` on the server for speed.
- The Tailwind/Chart.js/Lucide/Google Fonts are loaded from CDNs for simplicity — for a fully offline
  build you can swap these for local npm-built assets later.
- Branding ("Gorav" in white, "AI" in blue, linking to gorav.click) lives in
  `resources/views/layouts/app.blade.php` — search for `brand_url` if you want to change it.

## 6. Folder map

```
app/Http/Controllers/   All page logic (Dashboard, Products, Ingredients, Batches, Sales, Reports, AI)
app/Models/              Eloquent models (Product, Ingredient, Sale, BatchProduction, ...)
app/Services/            OllamaCloudService — talks to Ollama Cloud
resources/views/         Blade templates, organized by feature
database/migrations/     Database table definitions
database/seeders/        Demo data
routes/web.php           All app routes
```
