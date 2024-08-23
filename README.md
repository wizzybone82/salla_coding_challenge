# Product Management System

This Laravel-based Product Management System allows you to manage products, import them from CSV files, and synchronize with an external API.

## Features

1. CSV Import
   - Import products from a CSV file
   - Handle product variations from csv to make the syncying with the api easy
   - Handling the dublicated SKUS
   - Handling the product pricing
   - Soft delete outdated products

2. External API Synchronization
   - Daily sync with external product API
   - Update existing products and create new ones (If product id is not present in our database)
   - Calculate total quantity of the product from variations
   - Schedueling the product update command from kernel.php

3. Product Model
   - Variations now support quantity, and availability
   - Implements soft deletes
   - Changed the data type of variation column to JSON 

4. Unit Testing Library

   -For csv import
   -For Product Model
   -For Product Synchronization




## Commands

- `php artisan import:products`: Import products from CSV
- `php artisan products:sync-external`: Sync products with external API
-  php artisan test (For running the unit testing)


## file path

The CSV file should be in the public_path()
The CSV file for unit testing will be created in public_path() you will have to change the values from product import command file

## Installation

1. Clone the repository:
git clone

2. Install dependencies:
composer install

3. Copy `.env.example` to `.env` and configure your database settings.

4. Generate application key:
php artisan key:generate

5. Run migrations:
php artisan migrate

## Usage

### CSV Import

To import products from a CSV file:

1. Place your CSV file in the `public` directory with the name `products.csv`.
2. Run the import command:
php artisan import:products

The CSV should have the following structure:
- ID, Name, SKU, Price, Currency, Variations (JSON), Quantity, Status

### External API Sync

To sync products with the external API:

1. Run the sync command manually:
php artisan products:sync-external

2. The sync is scheduled to run daily at midnight. Ensure your server's cron job is set up:
* * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1

## Models

### Product

- Fields: id, name, sku, price, currency, variations (JSON), quantity, status
- Supports soft deletes

## External API

The system syncs with the following API endpoint:
https://5fc7a13cf3c77600165d89a8.mockapi.io/api/v5/products

## Error Handling

- Failed imports and syncs are logged
- Check Laravel logs for detailed error messages

