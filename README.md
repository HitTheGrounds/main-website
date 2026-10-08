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

---

## ✨ About

The main website for **CSE Hit The Grounds**, built with **Laravel 12**, **Livewire / Volt + Flux**, and **Tailwind CSS 4 + daisyUI** (bundled with Vite).

It includes:

* 🌐 **Public pages:** home, timeline, rules, awards, committee, partners, gallery
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

<div align="center">

Made with ❤️ for **CSE Hit The Grounds**

</div>
