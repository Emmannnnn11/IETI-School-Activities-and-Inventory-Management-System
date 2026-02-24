# Software Used

## Programming Language

- **PHP 8.1**  
  Server-side scripting language used to build the application. Handles backend logic such as authentication, event and inventory management, form processing, and database interactions.

- **JavaScript (JS)**  
  Client-side scripting language for interactive elements on the web application. Powers the calendar, form validations, Bootstrap components, and dynamic behavior in the browser.

---

## Frameworks

- **Laravel 10**  
  PHP framework used to develop the IETI School Activities Scheduling and Inventory Management System. Provides routing, authentication, MVC structure, and the Artisan CLI.

- **Bootstrap 5.3**  
  CSS framework for responsive layouts, components (forms, modals, alerts, navbars), and utilities. Loaded via CDN.

- **Blade**  
  Laravel’s templating engine for server-side views.

---

## Database

- **PostgreSQL (Supabase)**  
  Hosted PostgreSQL used as the application database. Stores users, events, inventory items, and related data via Laravel’s Eloquent ORM and migrations. Connection pooling is handled by Supabase.

---

## Server Environment

- **Laravel Built-in Server**  
  Local development server (`php artisan serve`) for running the application during development.

- **Apache**  
  Web server option for production (e.g., via `.htaccess`).

---

## Testing Tools

- **PHPUnit 10**  
  PHP testing framework for unit and feature tests.

---

## Development Environment

- **Composer**  
  PHP dependency manager for Laravel, Guzzle, Sanctum, Laravel UI, and other packages.

- **Laravel Sail**  
  Docker-based local development environment (available as dev dependency).

- **Laravel Pint**  
  PHP code style fixer.

- **Spatie Laravel Ignition**  
  Error page and debugging experience for development.

---

## Additional Frontend Tools

| Tool             | Purpose                                      |
|------------------|----------------------------------------------|
| FullCalendar.js 6.1.8 | Interactive calendar for event display and management |
| Font Awesome 6   | Icons across the UI                          |
| Figtree (Bunny Fonts)  | Typography                             |
