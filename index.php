<?php
require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/controllers.php';

// velmi jednoduché routování pomocí GET parametru `page`
$page = $_GET['page'] ?? 'home';

switch ($page) {
    case 'home':
        homeController($twig);
        break;

    case 'item':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        itemDetailController($twig, $id);
        break;

    case 'item_create':
        itemCreateController($twig);
        break;

    default:
        http_response_code(404);
        echo $twig->render('home.html.twig', [
            'page_title' => '404 - Stránka nenalezena',
            'items'      => [],
        ]);
        break;
}
