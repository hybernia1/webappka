<?php
use RedBeanPHP\R as R;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../packages/upload-manager/src/UploadManager.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/schema.php';

$configPath = __DIR__ . '/../config/config.php';

if (!file_exists($configPath)) {
    if (PHP_SAPI !== 'cli' && (strpos($_SERVER['REQUEST_URI'] ?? '', '/install') !== 0)) {
        header('Location: /install/');
        exit;
    }

    throw new RuntimeException('Chybí konfigurační soubor. Spusť instalaci v adresáři /install.');
}

$config = require $configPath;

if (!isset($config['dsn'])) {
    throw new RuntimeException('V konfiguraci chybí DSN pro databázové připojení.');
}

R::setup(
    $config['dsn'],
    $config['user'] ?? null,
    $config['password'] ?? null
);

if (!R::testConnection()) {
    throw new RuntimeException('Nepodařilo se připojit k databázi.');
}

if (!empty($config['freeze'])) {
    R::freeze(true);
}

ensureDatabaseSchema();

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader, [
    'cache' => false,
]);

$settingsMap = fetchSettings();
$settingsWithDefaults = getSettingsWithDefaults();

$twig->addGlobal('site_name', $settingsWithDefaults['site_name']);
$twig->addGlobal('base_url', $settingsWithDefaults['base_url']);
$twig->addGlobal('settings', $settingsMap);
