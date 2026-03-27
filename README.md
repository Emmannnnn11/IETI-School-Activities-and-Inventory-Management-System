# IETI School Activities Scheduling and Inventory Management System

A comprehensive web-based system for managing school activities and inventory at IETI College of Science and Technology Inc.

## Features

### Event Management
- **Role-based Access Control**: Admin, College Head, Senior Head, Junior Head, Teacher, Staff / Head Maintenance
- **Event Creation**: Admin and Heads can create events with date, time, location, and inventory requirements
- **Event Approval Workflow**: Admin approves or rejects events
- **Status Tracking**: Approved, Pending, Rejected with color indicators
- **Calendar View**: Calendar that displays events and their status

### Inventory Management
- **Item Categories**: Configurable categories (e.g., Sound System, Instruments, Sports, Furniture, Electronics)
- **Inventory Access**: Admin, Staff, and Head Maintenance can manage inventory
- **Category Restrictions**: Users can be restricted to specific inventory categories (read/edit access depends on role)
- **Quantity Tracking**: Available vs Total quantity tracking
- **Status Management**: Available, Maintenance, Unavailable statuses
- **Event Integration**: Inventory items can be linked to events

### Data Safety
- **Soft Deletes**: Users and inventory items are soft-deleted by default (can be recovered from the database if needed)

### IETI Branding
- **Official Colors**: Yellow, Green, Black, and White based on IETI logo
- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Modern UI**: Clean and professional interface

## Technology Stack

- **Backend**: PHP 8.1+ with Laravel 10
- **Database**: PostgreSQL-Supabase
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Calendar**: FullCalendar.js
- **Icons**: Font Awesome 6

## Installation

### Prerequisites
- PHP 8.1 or higher
- MySQL 5.7 or higher
- Composer
- Web server (Apache/Nginx)

### Step 1: Clone the Repository
```bash
git clone <repository-url>
cd Scheduling and Inventory System
```

### Step 2: Install Dependencies
```bash
composer install
```

### Step 3: Environment Configuration
```bash
cp .env.example .env
```

Edit the `.env` file with your database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ieti_event_db
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

### Step 4: Generate Application Key
```bash
php artisan key:generate
```

### Step 5: Run Database Migrations
```bash
php artisan migrate
```

### Step 6: Seed the Database
```bash
php artisan db:seed
```

### Step 7: Set Permissions
```bash
chmod -R 755 storage
chmod -R 755 bootstrap/cache
```

### Step 8: Start the Development Server
```bash
php artisan serve
```

Visit `http://localhost:8000` in your browser.

## Default Accounts

Create accounts using the Admin User Management page (`/users`). For demonstrations, avoid committing real passwords to documentation.

## User Roles & Permissions

### Administrator (admin)
- Full system access
- Can create, edit, and delete events
- Can approve/reject events
- Can manage inventory
- Can manage users

### College Head / Senior Head / Junior Head (college_head, senior_head, junior_head)
- Can create and edit events
- Can view events


### Staff / Head Maintenance (staff, Head Maintenance)
- Can manage inventory (create/update/confirm returns)
- Can view events
- Cannot approve/reject events (admin only)

## System Features

### Event Management
1. **Create Event**: Admin/Heads create events
2. **Event Approval**: Admin approves or rejects events
3. **Status Tracking**: Status updates with color indicators
4. **Calendar Integration**: Events appear on the calendar

### Inventory Management
1. **Add Items**: Create new inventory items with categories
2. **Track Quantities**: Monitor available vs total quantities
3. **Status Updates**: Mark items as available, maintenance, or unavailable
4. **Event Integration**: Link items to specific events

### Calendar Features
- **Monthly/Weekly Views**: Switch between different calendar views
- **Color Coding**: Events colored by status (Green/Orange/Red)
- **Event Details**: Click events to view full details
- **Responsive Design**: Works on all device sizes

## Sample Data

The system includes sample data:
- 20+ inventory items across different categories
- Pre-configured user accounts
- Event-inventory relationships

## Customization

### Adding New Inventory Categories
Edit the category options in `resources/views/inventory/create.blade.php` and `resources/views/inventory/edit.blade.php`.

### Modifying User Roles
Update the role definitions in `app/Models/User.php` and related middleware.

### Styling Changes
Modify the CSS variables in `resources/views/layouts/app.blade.php` to change colors and styling.

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check your `.env` file database credentials
   - Ensure your database server is running (MySQL/PostgreSQL)
   - Verify database exists

2. **Permission Errors**
   - Run `chmod -R 755 storage bootstrap/cache`
   - Check web server user permissions

3. **Composer Issues**
   - Run `composer install --no-dev` for production
   - Check PHP version compatibility

4. **Calendar Not Loading**
   - Check browser console for JavaScript errors
   - Verify FullCalendar.js is loading correctly

5. **PostgreSQL: duplicate key value violates unique constraint on `inventory_items_pkey`**
   - This usually means the ID sequence is out of sync after import/manual inserts.
   - Fix by resetting the sequence to the current max ID:
     - `SELECT setval(pg_get_serial_sequence('inventory_items','id'), (SELECT COALESCE(MAX(id),1) FROM inventory_items), true);`

6. **PostgreSQL: same error on `event_items_pkey`**
   - Run the migration that syncs the sequence: `php artisan migrate` (migration `2026_02_02_000001_sync_event_items_sequence`).
   - Or run manually: `SELECT setval(pg_get_serial_sequence('event_items','id'), (SELECT COALESCE(MAX(id),1) FROM event_items), true);`

7. **Why are `event_items` IDs not 1, 2, 3, … 50?**
   - The `id` column is an auto-increment primary key. PostgreSQL (and MySQL) never reuse numbers: when you delete rows, the next insert still gets the next number, so you get gaps (e.g. 1, 3, 7, 12). This is normal and correct.
   - IDs are for uniqueness, not for display order. If you need row numbers 1 to N for display, use a query with `ROW_NUMBER()` or add a separate column; do not rely on `id` being sequential.

## Support

For technical support or questions about the system, please contact the development team.

## License

This project is developed specifically for IETI College of Science and Technology Inc.

---

**IETI College of Science and Technology Inc.**  
*Since 1974*
