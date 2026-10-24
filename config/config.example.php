<?php
/**
 * Database & Application Configuration Template
 * 
 * Copy this file to 'config.php' in this directory (or project root)
 * and fill in your actual credentials.
 * 
 * NOTE: 'config.php' is ignored by Git to prevent exposing credentials.
 */

return [
    // -------------------------------------------------------------------------
    // Database Configuration (MySQL / MariaDB)
    // Supports both local (e.g., localhost, Docker) and remote database servers.
    // -------------------------------------------------------------------------
    'database' => [
        // Database driver (mysql)
        'driver'    => 'mysql',

        // Hostname or IP address:
        // - Local: '127.0.0.1' or 'localhost'
        // - Remote / Cloud: 'db.example.com' or remote IP address
        'host'      => '127.0.0.1',

        // Port (default MySQL port is 3306)
        'port'      => 3306,

        // Database schema / catalog name
        'database'  => 'basephp_db',

        // Database username
        'username'  => 'db_user',

        // Database password
        'password'  => 'your_secure_password_here',

        // Character encoding and collation
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',

        // PDO connection options
        'options'   => [
            // Throw exceptions on SQL errors
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return associative arrays by default
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use native prepared statements
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Connection timeout in seconds
            PDO::ATTR_TIMEOUT            => 5,

            // Optional: For secure remote connections requiring SSL/TLS, uncomment below:
            // PDO::MYSQL_ATTR_SSL_CA    => '/path/to/ca-cert.pem',
            // PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ],
    ],

    // -------------------------------------------------------------------------
    // Application Settings
    // -------------------------------------------------------------------------
    'app' => [
        'name'        => 'BasePHP',
        'environment' => 'development', // 'development' or 'production'
        'debug'       => true,
        'url'         => 'http://localhost:8000',
    ],
];
