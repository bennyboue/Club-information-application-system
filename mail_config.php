<?php
return [
    'host' => 'smtp.example.com',       // SMTP server
    'username' => 'your@email.com',     // SMTP username
    'password' => 'your_password',      // SMTP password
    'port' => 587,                      // SMTP port (587 for TLS)
    'encryption' => 'tls',              // Encryption: 'tls' or 'ssl'
    'from_email' => 'noreply@yourschool.edu',
    'from_name' => 'School Club System',
    'debug' => 0                        // 0=off, 1=client messages, 2=client and server messages
];