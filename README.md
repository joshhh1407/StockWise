# YouthCo. Shop — AI Inventory Management System (PHP + MySQL)

## Setup (XAMPP)
1. Copy this folder to `C:\xampp\htdocs\YouthCo`.
2. Start **Apache** and **MySQL** in XAMPP.
3. phpMyAdmin -> **Import** -> choose `database.sql` (creates `youthco_db` with all tables).
4. Open `http://localhost/YouthCo/seed_demo.php` once -> demo account `admin` / `admin123` with sample products and 30 days of sales. Then delete `seed_demo.php`.
5. Copy `.env.example` to `.env` in the project root, then set your database credentials and `GEMINI_API_KEY` there. Open `http://localhost/YouthCo/` and log in.

**Already imported the OLD database.sql?** Either drop `youthco_db` and import the new `database.sql` (clean start),
or open `http://localhost/YouthCo/upgrade.php` once (keeps your data; add `?user=yourusername` to choose which account keeps the old products). Then delete `upgrade.php`.

## One shared inventory
Products, stock, sales, reports and AI results are stored in the database and shared by every account. A product added (or restocked / sold) while logged in as one account is saved and visible after logging in as any other account. `owner_id` only records who created the product.

## File structure
```
login.php  register.php  logout.php          authentication
index.php                                     Dashboard
inventory.php                                 Inventory (add / edit / stock +/- / delete)
sales.php                                     Sales / Stock-Out (auto-deducts stock)
analytics.php                                 Analytics
ai_insights.php  ai_chat.php                  AI Insights + "Ask the AI" chat endpoint
reports.php                                   5 reports (preview, CSV, Print/Save as PDF)
seed_demo.php  upgrade.php  database.sql  ai_test.php   demo data, DB upgrade, schema, AI connection test
config/config.php                             DB + Gemini settings
includes/  db, auth, functions, ai_engine, header, footer, auth layout
assets/css/style.css   assets/js/app.js
```

## The AI (Google Gemini)
- **Built-in AI engine** (`includes/ai_engine.php`): rule-based PHP. Computes avg daily sales, days left, suggested order,
  low / overstock / fast / slow products. Always works, no internet.
- **Gemini** answers the chat. Put your key in `.env` as `GEMINI_API_KEY`
  (get one at aistudio.google.com/apikey). If Gemini is busy (503) it retries up to 3 times, then the built-in engine answers.
  Each answer shows which engine replied. Use `ai_test.php` to check the connection (delete it afterwards).
- Free-tier Gemini has rate limits. PHP needs the cURL extension (`extension=curl` in php.ini).
- `.env` contains local credentials and is excluded by `.gitignore`. Never upload or share it; `.env.example` is a blank template.

## Notes
- Low stock = stock <= the product's low-stock level; High = stock >= high-stock level.
- Inventory search is live (filters while you type). Light/dark mode button and a color picker (presets + color wheel) in the top bar, saved per device; sales show as paper receipts, stock shows as shelf meters, numbers count up and bars grow when a page opens (reduced-motion is respected); the layout works on phones (slide-in menu, tables become cards).
- Registering requires ticking the Terms and Agreement checkbox (the terms open in a pop-up; also checked on the server).
- Passwords are hashed (`password_hash`); forms use CSRF tokens; queries use prepared statements.
