<?php
use RedBeanPHP\R as R;

/**
 * Homepage – seznam položek
 */
function homeController($twig)
{
    $items = R::findAll('item', ' ORDER BY id DESC ');
    echo $twig->render('home.html.twig', [
        'page_title' => 'Seznam položek',
        'items'      => $items,
    ]);
}

/**
 * Detail položky
 */
function itemDetailController($twig, $id)
{
    $item = R::load('item', (int)$id);
    if (!$item->id) {
        http_response_code(404);
        echo $twig->render('item_detail.html.twig', [
            'page_title' => 'Nenalezeno',
            'item'       => null,
        ]);
        return;
    }

    echo $twig->render('item_detail.html.twig', [
        'page_title' => $item->title,
        'item'       => $item,
    ]);
}

/**
 * Vytvoření nové položky (hodně basic, bez validací a CSRF kvůli přehlednosti)
 */
function itemCreateController($twig)
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';

        $item = R::dispense('item');
        $item->title = $title;
        $item->content = $content;
        $item->created_at = date('Y-m-d H:i:s');

        $id = R::store($item);

        header('Location: ?page=item&id=' . $id);
        exit;
    }

    echo $twig->render('item_form.html.twig', [
        'page_title' => 'Nová položka',
    ]);
}
