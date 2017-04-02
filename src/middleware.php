<?php
// Application middleware

$container = $app->getContainer();

$app->add(new \Slim\Middleware\HttpBasicAuthentication([
    'users' => $container->get('settings')['admin_users'],
    'path' => '/admin',
]));
