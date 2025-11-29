<?php
use RedBeanPHP\R as R;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require __DIR__ . '/../vendor/autoload.php';

// 1) Připojení k databázi (příklad: MySQL)
R::setup(
    'mysql:host=db.dw173.webglobe.com;dbname=iluze_net6;charset=utf8mb4',
    'iluze_net6',
    'p27uuejD'
);

// Volitelné: v produkci vypnout automatické změny schématu
// R::freeze(true);

// 2) Twig – načtení šablon z /templates
$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader, [
    'cache' => false, // v produkci můžeš nastavit např. __DIR__.'/../var/cache/twig'
]);

// 3) Globální proměnné pro Twig – např. základní title, base_url atd.
$twig->addGlobal('site_name', 'Moje RedBean + Twig App');
$twig->addGlobal('base_url', '/'); // případně /mojeapp/ apod.
