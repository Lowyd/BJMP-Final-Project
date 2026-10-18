<?php
// Development defaults for a local XAMPP installation only.
// For deployment: copy config.local.example.php to config.local.php and edit it.
return array_replace([
    'app_name' => 'BJMP',
    'timezone' => 'Asia/Manila',
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'jmris',
    'db_user' => 'root',
    'db_pass' => '',
    'session_name' => 'JMRIS_SESSION',
], is_file(__DIR__ . '/config.local.php') ? require __DIR__ . '/config.local.php' : []);
