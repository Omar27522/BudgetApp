# 💰 Personal Finance & Budget App

A lightweight, self-hosted, and ultra-secure personal finance and budgeting portal built with modern PHP and SQLite. Designed for privacy, high portability, and zero bloated external dependencies.

---

## ✨ Features at a Glance

* **📋 Monthly Bills ("Cash Out")**:
  - Track recurring fixed expenses (Rent, Utilities, Insurance, Subscriptions).
  - Flexible due date tags (`1st`, `14th`, `Friday`, `check day`).
  - 1-click status toggle (`Paid` / `Unpaid`) with partial payment tracking and payment date logging.
* **💵 Income Streams**:
  - Track expected vs. actual received paychecks, freelance side gigs, commissions, and tips.
  - **"Keep this income" toggle**: Distinguish recurring base paychecks from variable commissions.
  - **"Date Hits"**: Quick inline calendar picker to log when money hits the bank account.
* **🛒 Purchases & Variable Spending Envelopes**:
  - Custom budget categories (Groceries, Dining, Gas, Tithe, Misc) with color-coded spending progress bars.
  - Fast expense and receipt logging.
* **⛽ Gas & Fuel Tracker**:
  - Fill-up logger capturing total cost, gallons, station, and automated Price/Gallon calculation.
  - Optional odometer tracking for vehicle maintenance records.
* **📅 Multi-Year & Multi-Month Management**:
  - Chronological `<` and `>` quick navigation buttons.
  - Hierarchical Year and Month dropdown switcher.
  - **1-Click New Month Duplicator**: Automatically clones recurring bills, income streams, and envelopes into the next month.
* **📊 Live KPI Financial Summary**:
  - Real-time calculations of Income Received, Bills Paid, Variable Budget Remaining, and Net Cashflow.
* **📦 1-Click Complete System & Data Backup**:
  - Immediate on-demand download of a compressed ZIP archive named `backup_YYYY-MM-DD_HH-mm.zip`.
  - Bundles the raw SQLite database file alongside structured CSV files per month for total future-proof portability.

---

## 🔒 Security Architecture

The application is engineered from the ground up for strict security when deployed to public web hosting:

| Security Layer | Implementation Details |
| :--- | :--- |
| **Directory Scope Isolation** | Database files live in `data/budget_db/` completely **outside** the web root (`app/`). Web visitors cannot access or download raw database files over HTTP. |
| **2-Step Honeypot Login** | Step 1 accepts any username without querying the server, neutralizing username enumeration and brute-force user probing. |
| **Progressive Rate Limiting** | Every failed attempt adds a progressive **+5 second penalty** (5s, 10s, 15s...) tracked by IP. Submit is locked with a real-time countdown timer. |
| **Timing-Attack Resistance** | Invalid usernames run a dummy bcrypt hash verification and randomized microsecond jitter sleep so response times are indistinguishable from valid accounts. |
| **Apache Hardening (`.htaccess`)** | Disables directory browsing (`-Indexes`), blocks access to sensitive files (`.sqlite`, `.db`, `.log`, `.env`, `.csv`, `.xlsx`), and enforces security headers (`X-Frame-Options`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection`). |
| **Session Protection** | Session IDs are regenerated upon login, cookies are flagged `HttpOnly`, and API endpoints reject unauthenticated requests with `401 Unauthorized` JSON. |

---

## 📁 Directory Structure

```
BudgetApp/
├── README.md                             <-- Complete documentation
│
├── app/                                  <-- WEB DOCUMENT ROOT (Internet Facing)
│   ├── .htaccess                         <-- Production security & file protection
│   ├── index.php                         <-- Main dashboard controller
│   ├── login.php                         <-- 2-Step Honeypot login & rate limiter
│   ├── logout.php                        <-- Secure session termination
│   ├── backup.php                        <-- On-demand ZIP & CSV backup generator
│   │
│   ├── api/                              <-- JSON REST APIs (Auth guarded)
│   │   ├── bills.php
│   │   ├── income.php
│   │   └── months.php
│   │
│   ├── assets/
│   │   └── css/
│   │       └── style.css                 <-- Desktop & mobile responsive stylesheet
│   │
│   ├── database/
│   │   ├── db.php                        <-- PDO SQLite connection & auto-migration
│   │   └── config.sample.php             <-- Optional path config override
│   │
│   ├── src/
│   │   ├── Auth/                         <-- Auth & AuthGuard access control
│   │   ├── Helpers/                      <-- Formatting utilities (currency, dates)
│   │   └── Models/                       <-- Month, Bill, Income, Category, Purchase, Gas
│   │
│   └── views/
│       ├── layout/                       <-- header.php, kpi_banner.php, footer.php
│       └── tabs/                         <-- bills_income.php, purchases_budget.php, gas_tracker.php
│
└── data/                                 <-- PRIVATE STORAGE (Outside Web Scope)
    └── budget_db/
        ├── .htaccess                     <-- "Require all denied" web access blocker
        ├── budget.sqlite                 <-- Primary SQLite database (WAL mode enabled)
        └── Current budget Report.xlsx    <-- Offline backup spreadsheet
```

---

## 🚀 Installation & Local Development

### Prerequisites
* **PHP 8.1+** with extensions enabled:
  - `pdo_sqlite`
  - `sqlite3`
  - `zip` (for manual compressed backup generation)

### Running Locally with PHP Built-in Server
1. Clone or download the repository:
   ```bash
   git clone https://github.com/yourusername/budget-app.git
   cd budget-app
   ```
2. Start the PHP server pointing directly to the `app/` directory:
   ```bash
   php -S 127.0.0.1:8000 -t app
   ```
3. Open your browser to:
   ```
   http://127.0.0.1:8000
   ```

---

## 🌐 Production Deployment (cPanel / Subdomain)

### Example Target: `budget.latinospc.com`

1. **Upload Private Database Folder**:
   - In cPanel File Manager, upload the `data/budget_db/` folder to `/home4/latinspc/data/budget_db/`.
   - This ensures the database is completely outside `public_html/`.
2. **Create Subdomain in cPanel**:
   - **Subdomain:** `budget`
   - **Domain:** `latinospc.com`
   - **Document Root:** `/home4/latinspc/budget.latinospc.com` (or your subdomain directory).
3. **Upload Web Application**:
   - Upload all files and folders located **inside `app/`** directly into your subdomain document root.
   - The database bridge in `app/database/db.php` will automatically locate `/home4/latinspc/data/budget_db/budget.sqlite` with zero manual configuration.
4. **Access the Portal**:
   - Visit `https://budget.latinospc.com`

---

## 📦 Disaster Recovery & Portable Backups

Clicking the **📦 Backup** button in the header navigation triggers an instant, on-demand download of a compressed ZIP file named:

```
backup_YYYY-MM-DD_HH-mm.zip
```

### Contents of the Backup Archive:
* **`database/budget.sqlite`**: The complete raw SQLite database. To restore, simply overwrite the file in `data/budget_db/budget.sqlite`.
* **`csv/master_months_summary.csv`**: Multi-month financial summary (Total income, bills, expenses, and net cashflow).
* **`csv/<YYYY-MM_MonthLabel>/`**: Dedicated month folders containing individual clean CSVs:
  - `bills.csv`
  - `bill_payments.csv`
  - `income_streams.csv`
  - `purchases.csv`
  - `budget_categories.csv`
  - `gas_tracker.csv`
* **`csv/master/`**: Multi-month historical tables (`all_bills_history.csv`, `all_income_history.csv`, `all_purchases_history.csv`, `all_gas_history.csv`) for effortless importing into PostgreSQL, MySQL, Excel, or Google Sheets.
* **`README_RESTORE.txt`**: Restore guide and archive metadata.

---

## 🔑 Managing Credentials

### First-Time Account Setup
When launching on a fresh database with 0 registered users, visiting the site automatically prompts the **First-Time Setup** screen to define your private master credentials.

### Resetting Master Credentials
To reset the portal credentials and re-open the initial setup screen:
```powershell
php -r "require 'app/database/db.php'; Db::getConnection()->exec('DELETE FROM users; DELETE FROM login_attempts;');"
```

---

## 📄 License
MIT License. Free for personal and commercial use.

