<?php
use RedBeanPHP\R as R;

function adminCurrentUser(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['admin_user_id'])) {
        return null;
    }

    $user = R::load('user', (int) $_SESSION['admin_user_id']);

    if (!$user->id) {
        unset($_SESSION['admin_user_id']);
        return null;
    }

    return [
        'id'       => $user->id,
        'username' => $user->username,
        'role'     => $user->role,
    ];
}

function adminRequireLogin(): void
{
    if (!adminCurrentUser()) {
        header('Location: ?action=login');
        exit;
    }
}

function adminHandleLogin(): array
{
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = R::findOne('user', ' username = ? ', [$username]);

        if (!$user || !password_verify($password, $user->password_hash)) {
            $errors[] = 'Neplatné přihlašovací údaje.';
        } else {
            $_SESSION['admin_user_id'] = $user->id;
            header('Location: ?action=dashboard');
            exit;
        }
    }

    return $errors;
}

function adminSavePost(): array
{
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $id = isset($_POST['id']) ? (int) $_POST['id'] : null;

        if ($title === '') {
            $errors[] = 'Název je povinný.';
        }

        if (!$errors) {
            $post = $id ? R::load('post', $id) : R::dispense('post');
            $post->title = $title;
            $post->content = $content;
            $post->published_at = $post->published_at ?: date('Y-m-d H:i:s');
            $post->updated_at = date('Y-m-d H:i:s');
            R::store($post);

            header('Location: ?action=posts');
            exit;
        }
    }

    return $errors;
}

function adminSaveSettings(): array
{
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $siteName = trim($_POST['site_name'] ?? '');
        $baseUrl = trim($_POST['base_url'] ?? '/');

        if ($siteName === '') {
            $errors[] = 'Název webu je povinný.';
        }

        if (!$errors) {
            $settings = [
                'site_name' => $siteName,
                'base_url'  => $baseUrl,
            ];

            foreach ($settings as $key => $value) {
                $bean = R::findOne('setting', ' `key` = ? ', [$key]) ?: R::dispense('setting');
                $bean->key = $key;
                $bean->value = $value;
                R::store($bean);
            }

            header('Location: ?action=settings&saved=1');
            exit;
        }
    }

    return $errors;
}
