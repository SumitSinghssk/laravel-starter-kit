<?php

return [

    'enabled' => true,

    'ignore' => [
        'admin', 'admin/*', 'up',
        '*.php', '*.php/*', '.env*', '*/.env*', '.git/*', '.well-known/*',
        'wp-*', '*/wp-*', 'wordpress/*', 'xmlrpc*', 'cgi-bin/*', 'phpmyadmin*', 'vendor/*', '*.asp', '*.aspx', '*.cgi',
    ],

    'keep_days' => 90,

    'per_page' => 25,

];
