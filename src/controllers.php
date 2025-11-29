<?php
use RedBeanPHP\R as R;

function fetchSettings(): array
{
    $settings = R::findAll('setting');
    $result = [];

    foreach ($settings as $setting) {
        $result[$setting->key] = $setting->value;
    }

    return $result;
}

function homeController($twig)
{
    $posts = R::findAll('post', ' ORDER BY published_at DESC ');
    $settings = fetchSettings();

    echo $twig->render('home.html.twig', [
        'page_title' => 'Aktuální příspěvky',
        'posts'      => $posts,
        'settings'   => $settings,
    ]);
}

function postDetailController($twig, int $id)
{
    $post = R::load('post', $id);

    if (!$post->id) {
        http_response_code(404);
        echo $twig->render('post_detail.html.twig', [
            'page_title' => 'Příspěvek nenalezen',
            'post'       => null,
        ]);
        return;
    }

    echo $twig->render('post_detail.html.twig', [
        'page_title' => $post->title,
        'post'       => $post,
    ]);
}
