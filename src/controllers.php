<?php
use RedBeanPHP\R as R;

function homeController($twig)
{
    $posts = R::findAll('post', ' ORDER BY published_at DESC ');
    foreach ($posts as $post) {
        hydratePostWithRelations($post);
    }
    $settings = fetchSettings();

    echo $twig->render('home.html.twig', [
        'page_title' => 'Aktuální příspěvky',
        'posts'      => $posts,
        'settings'   => $settings,
    ]);
}

function postDetailController($twig, int $id)
{
    $slug = $_GET['slug'] ?? null;

    if ($slug) {
        $post = R::findOne('post', ' slug = ? ', [$slug]);
    } else {
        $post = R::load('post', $id);
    }

    if (!$post->id) {
        http_response_code(404);
        echo $twig->render('post_detail.html.twig', [
            'page_title' => 'Příspěvek nenalezen',
            'post'       => null,
        ]);
        return;
    }

    hydratePostWithRelations($post);

    echo $twig->render('post_detail.html.twig', [
        'page_title' => $post->title,
        'post'       => $post,
    ]);
}
