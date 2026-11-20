<div align="center">

# 🏟️ CSE Hit The Grounds

### The Main Website

*Public pages, team listings, registration, and an admin area, all in one place.*

<br>

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Livewire](https://img.shields.io/badge/Livewire-Volt_+_Flux-FB70A9?style=for-the-badge&logo=livewire&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind-4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-7-646CFF?style=for-the-badge&logo=vite&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)

<br>

[Prerequisites](#-prerequisites) · [Setup](#%EF%B8%8F-local-development-setup) · [Run](#-running-the-application) · [Commands](#-useful-commands)

</div>

### Access development from another device

Set `VITE_DEV_HOST` in `.env` to your computer's LAN IPv4 address (use
`ipconfig` on Windows), then run `composer run dev`. On another device on the
same network, open `http://<your-LAN-IP>:8000`.

The development servers listen on ports 8000 (Laravel) and 5173 (Vite).
Allow those ports through your firewall for your trusted local network if
needed. Update `VITE_DEV_HOST` and restart development if your IP changes.
Some shared Wi-Fi networks block connections between devices.

---

## ✨ About

The main website for **CSE Hit The Grounds**, built with **Laravel 12**, **Livewire / Volt + Flux**, and **Tailwind CSS 4 + daisyUI** (bundled with Vite).

It includes:

* 🌐 **Public pages:** home, timeline, rules, awards, partners, memories
* 🎓 **Teams:** university and industry team listings
* 📝 **Registration:** company and team sign-up
* 🔐 **Admin area:** for managing teams

---

## 📋 Prerequisites

| Requirement | Notes |
| :-- | :-- |
| **PHP 8.2+** | with `mbstring`, `xml`, `dom`, and `sqlite3` extensions |
| **Composer** | PHP dependency manager |
| **Node.js & NPM** | for the Vite / Tailwind frontend |
| **SQLite** *(default)* | or MySQL / PostgreSQL |

---

## 🛠️ Local Development Setup

### 1️⃣ Clone the Repository
```bash
git clone <repository-url>
cd main-website
```

### 2️⃣ Install System Extensions (Ubuntu/Debian)
If you hit missing platform extension errors, install them:
```bash
sudo apt update
sudo apt install -y php8.3-xml php8.3-mbstring php8.3-sqlite3 composer nodejs npm
```

### 3️⃣ Install Dependencies
```bash
composer install
npm install
```

### 4️⃣ Environment Configuration
```bash
cp .env.example .env
```

The default `.env.example` uses SQLite. Create the database file:
```bash
touch database/database.sqlite
```

> [!NOTE]
> For MySQL/PostgreSQL, update the `DB_*` values in `.env` instead.

### 5️⃣ Generate Application Key
```bash
php artisan key:generate
```

### 6️⃣ Run Migrations and Seed
```bash
php artisan migrate --seed
```

> [!NOTE]
> The seeder creates a test user (`test@example.com`) and an admin user (`admin@example.com`). To promote any other user, run `php artisan user:set-admin <email>`.

---

## 🚀 Running the Application

Start the server, queue worker, log viewer, and Vite dev server together:

```bash
composer run dev
```

The site is served at **http://localhost:8000**.

---

## 🧰 Useful Commands

| Task | Command |
| :-- | :-- |
| Migrate | `php artisan migrate` |
| Fresh migration + seed | `php artisan migrate:fresh --seed` |
| Clear caches | `php artisan optimize:clear` |
| Run tests | `composer test` (or `php artisan test`) |
| Build production assets | `npm run build` |

---

## Optional tournament Playoffs

Run `php artisan migrate` when deploying this change. Playoffs default to off
for every tournament year. With the tournament enabled, an admin can use
**Admin Dashboard > Tournament: Playoffs > Enable Playoffs > Save tournament settings**.

When enabled, scorers can select **Playoffs** under **Qualification & bracket**
and schedule any number of matches between saved group qualifiers, including
repeat appearances. Only teams with a published, finished Playoff win are
eligible for quarter-finals. Semi-finals and the final follow the existing rules.

Playoffs appear in the public Matches tab and stage filters, and published player
figures contribute to Stats. They do not appear in group tables or the public
bracket. Turning the switch off restores direct group-qualifier eligibility for
quarter-finals, hides Playoff fixtures and controls, blocks their scoring actions,
and excludes their figures from derived player totals. Saved matches and player
figures are retained and included again when enabled. Settings changes refresh
public pages through the existing tournament revision polling.

The migration refuses rollback while Playoff match history exists; use the
dashboard switch to disable the feature without discarding history.

---

<div align="center">

Made with ❤️ for **CSE Hit The Grounds**

</div>
