<?php
require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/controllers.php';

$page = $_GET['page'] ?? 'home';

switch ($page) {
    case 'home':
        homeController($twig);
        break;

    case 'post':
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        postDetailController($twig, $id);
        break;

    default:
        http_response_code(404);
        echo $twig->render('home.html.twig', [
            'page_title' => '404 - Stránka nenalezena',
            'posts'      => [],
        ]);
        break;
}
