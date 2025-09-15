# PropX - Property Management System

## Overview

PropX is a comprehensive, self-contained property management system designed for landlords and property managers to efficiently manage their rental properties. It provides a user-friendly, single-page dashboard to oversee properties, tenants, finances, and rent collection.

The application is built with a simple and robust technology stack, making it easy to set up and maintain.

## Technology Stack

*   **Backend:** PHP
*   **Database:** SQLite (file-based)
*   **Frontend:** HTML, Tailwind CSS, vanilla JavaScript

## Features

*   **Dashboard:** Get an at-a-glance overview of your property portfolio with key metrics like total properties, tenant count, monthly revenue, and available bedspaces.
*   **Property Management:** Add and manage your properties, including details like address, contact information, and financial data.
*   **Tenant Management:** Keep a detailed record of all your tenants, assign them to specific properties and bedspaces, and track their lease information.
*   **Bedspace Management:** Organize and manage individual bedspaces within each property.
*   **Financial Tracking:**
    *   **Income:** Log all incoming payments from tenants.
    *   **Expenses:** Record all property-related expenses, categorized for easy tracking (e.g., maintenance, utilities).
    *   **Reporting:** Generate financial reports for any date range to see a clear breakdown of income, expenses, and net profit.
*   **Rent Reminders:** Automatically track upcoming and overdue rent payments. Send friendly reminders to tenants directly via WhatsApp with a single click.

## How to Set Up

1.  **Web Server:** You need a web server with PHP support, such as Apache or Nginx.
2.  **PHP Configuration:** Ensure the `pdo_sqlite` extension is enabled in your PHP installation. This is usually enabled by default.
3.  **Deploy Files:** Copy all the project files into a directory within your web server's document root (e.g., `htdocs` for Apache).
4.  **Permissions:** Make sure the web server has write permissions for the `propertymanagement.sqlite` file, as it will need to read from and write to the database.
5.  **Access:** Open your web browser and navigate to the `index.php` file (e.g., `http://localhost/your-project-folder/index.php`).

## Database

The application uses a single SQLite database file named `propertymanagement.sqlite` to store all data.

If you need to reset the database to its original state or create it for the first time, you can run the `regenerate_db.php` script by accessing it in your browser. **Warning: This will erase all existing data.**
