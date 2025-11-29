<?php
use RedBeanPHP\R as R;

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/admin.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$action = $_GET['action'] ?? 'dashboard';
$errors = [];
$currentUser = adminCurrentUser();

if ($action === 'logout') {
    session_destroy();
    header('Location: ?action=login');
    exit;
}

if ($action === 'login') {
    $errors = adminHandleLogin();
    echo $twig->render('admin/login.html.twig', [
        'errors' => $errors,
    ]);
    return;
}

adminRequireLogin();
$currentUser = adminCurrentUser();

switch ($action) {
    case 'posts':
        $posts = R::findAll('post', ' ORDER BY published_at DESC ');
        echo $twig->render('admin/posts.html.twig', [
            'posts'  => $posts,
            'user'   => $currentUser,
            'title'  => 'Příspěvky',
        ]);
        break;

    case 'post_edit':
        $errors = adminSavePost();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $post = $id ? R::load('post', $id) : null;

        echo $twig->render('admin/post_form.html.twig', [
            'errors' => $errors,
            'post'   => $post,
            'user'   => $currentUser,
            'title'  => $id ? 'Upravit příspěvek' : 'Nový příspěvek',
        ]);
        break;

    case 'users':
        [$errors, $formUser] = adminManageUsers();
        $users = R::findAll('user', ' ORDER BY created_at DESC ');
        echo $twig->render('admin/users.html.twig', [
            'users'     => $users,
            'user'      => $currentUser,
            'title'     => 'Uživatelé',
            'roles'     => ADMIN_LEVELS,
            'form_user' => $formUser,
            'errors'    => $errors,
            'saved'     => isset($_GET['saved']),
        ]);
        break;

    case 'uploads':
        [$errors, $success] = adminHandleUpload();
        $imageSuccess = null;
        $fileSuccess = null;

        if (!$errors && $success) {
            if (($_POST['upload_type'] ?? 'image') === 'file') {
                $fileSuccess = $success;
            } else {
                $imageSuccess = $success;
            }
        }

        echo $twig->render('admin/uploads.html.twig', [
            'user'          => $currentUser,
            'title'         => 'Uploady',
            'image_errors'  => ($_POST['upload_type'] ?? 'image') === 'image' ? $errors : [],
            'file_errors'   => ($_POST['upload_type'] ?? 'image') === 'file' ? $errors : [],
            'image_success' => $imageSuccess,
            'file_success'  => $fileSuccess,
            'now'           => new DateTimeImmutable('now'),
        ]);
        break;

    case 'settings':
        $errors = adminSaveSettings();
        $settings = getSettingsWithDefaults();
        echo $twig->render('admin/settings.html.twig', [
            'errors'   => $errors,
            'settings' => $settings,
            'saved'    => isset($_GET['saved']),
            'user'     => $currentUser,
            'title'    => 'Nastavení',
        ]);
        break;

    case 'dashboard':
    default:
        $postCount = R::count('post');
        $userCount = R::count('user');
        echo $twig->render('admin/dashboard.html.twig', [
            'user'       => $currentUser,
            'title'      => 'Přehled',
            'postCount'  => $postCount,
            'userCount'  => $userCount,
        ]);
        break;
}
