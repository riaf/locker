<?php
// DIC configuration

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];

    return new Slim\Views\PhpRenderer($settings['template_path'], $settings['global_variables']);
};

// database
$container['database'] = function ($c) {
    $settings = $c->get('settings')['database'];

    $database = new Aura\Sql\ExtendedPdo(
        $settings['dsn'], $settings['username'], $settings['password']
    );
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

    $database->exec('
        CREATE TABLE IF NOT EXISTS files (
            id VARCHER(100) PRIMARY KEY,
            title VARCHAR(100),
            description TEXT,
            passphrase TEXT,
            original_filename TEXT,
            download_count INT,
            expires_at DATETIME,
            created_at DATETIME
        );
    ');

    return $database;
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));

    return $logger;
};
