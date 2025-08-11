# Orter - Android Ordering App Backend ⚙️

[![PHP](https://img.shields.io/badge/PHP-8.0+-8892BF.svg?logo=php)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20.svg?logo=laravel)](https://laravel.com)


This repository contains the source code for the Laravel REST API that powers the **Orter Android application**.

- **📱 View the Android Client:** [AshanHimantha/android-app-orter](https://github.com/AshanHimantha/android-app-orter)

## ✨ Features

- **Secure RESTful Endpoints:** Well-defined API endpoints for all application functionalities.
- **Token-based Authentication:** Stateless and secure user authentication using **Laravel Sanctum**.
- **Product & Order Management:** Full CRUD (Create, Read, Update, Delete) capabilities for core application data.
- **Eloquent ORM:** Leverages Laravel's powerful Object-Relational Mapper for elegant database interactions.
- **Database Seeding:** Includes a seeder to quickly populate the database with sample products for development and testing.

---

## 🛠️ Technology Stack

- **Framework:** [Laravel 11](https://laravel.com/)
- **Language:** [PHP](https://www.php.net/)
- **Security:** [Laravel Sanctum](https://laravel.com/docs/8.x/sanctum) for API token authentication.
- **Database:** [Eloquent ORM](https://laravel.com/docs/8.x/eloquent) with a relational database (MySQL is configured by default).
- **Dependency Manager:** [Composer](https://getcomposer.org/)

---

## 🚀 Getting Started

Follow these instructions to get a copy of the project up and running on your local machine for development and testing purposes.

### Prerequisites

- **PHP:** Version 8.0 or higher
- **Composer:** [PHP Dependency Manager](https://getcomposer.org/download/)
- **Database:** A running instance of MySQL
- **Git:** [Version Control System](https://git-scm.com/)

### Installation & Setup

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/AshanHimantha/orter-android-backend.git
    cd orter-android-backend
    ```

2.  **Install PHP dependencies:**
    ```bash
    composer install
    ```

3.  **Configure your environment:**
    -   First, copy the example environment file.
        ```bash
        cp .env.example .env
        ```
    -   Then, generate your unique application key.
        ```bash
        php artisan key:generate
        ```

4.  **Set up the database:**
    -   Create a new database in your MySQL instance (e.g., `orter_db`).
    -   Open the newly created `.env` file and update the `DB_*` variables with your database credentials:
        ```env
        DB_CONNECTION=mysql
        DB_HOST=127.0.0.1
        DB_PORT=3306
        DB_DATABASE=orter_db
        DB_USERNAME=your_db_user
        DB_PASSWORD=your_db_password
        ```

5.  **Run database migrations and seeders:**
    -   This crucial command will create all the necessary tables and populate the `products` table with sample data so the app has content to display.
        ```bash
        php artisan migrate --seed
        ```

6.  **Run the development server:**
    ```bash
    php artisan serve
    ```
    The API is now running and accessible at `http://localhost:8000`.

---

## 📝 API Endpoints

The API is available under the `/api` prefix.

| Method | Endpoint                    | Description                                | Protected |
| :----- | :-------------------------- | :----------------------------------------- | :-------- |
| `POST` | `/api/register`             | Register a new user.                       | No        |
| `POST` | `/api/login`                | Authenticate a user and get a Sanctum token.| No        |
| `GET`  | `/api/products`             | Get a list of all available products.      | Yes       |
| `GET`  | `/api/products/{id}`        | Get details for a single product.          | Yes       |
| `POST` | `/api/orders`               | Place a new order with items from the cart.| Yes       |
| `GET`  | `/api/orders`               | Get the authenticated user's order history.| Yes       |
| `POST` | `/api/logout`               | Log the user out and revoke the token.     | Yes       |

> **Note:** Endpoints marked as **Protected** require a valid Bearer Token in the `Authorization` header.

---

