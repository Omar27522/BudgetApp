<?php
/**
 * Database Configuration (Optional)
 * 
 * If you move your 'budget.sqlite' database outside the web root (e.g. on cPanel / shared hosting),
 * rename this file to 'config.php' and set the absolute path to your database.
 * 
 * Example:
 *   return [
 *       'db_path' => '/home/yourusername/budget_data/budget.sqlite'
 *   ];
 */
return [
    'db_path' => null, // null defaults to automatic detection or db/budget.sqlite
];
