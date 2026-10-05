<?php

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/pos',
    'prefix' => '',
    'domain' => null,
    'middleware' => ['web'],
    'limiters' => ['login' => 'login'],
    'views' => true,
    'features' => [],
];
