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
        $thumbnailUrl = trim($_POST['thumbnail_url'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $tagsInput = trim($_POST['tags'] ?? '');
        $id = isset($_POST['id']) ? (int) $_POST['id'] : null;

        if ($content === '') {
            $errors[] = 'Obsah příspěvku je povinný.';
        }

        if ($title === '') {
            $errors[] = 'Název je povinný.';
        }

        if ($thumbnailUrl !== '' && !filter_var($thumbnailUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL náhledového obrázku musí být ve správném formátu.';
        }

        if (!$errors) {
            $tags = array_filter(array_map('trim', explode(',', $tagsInput)));

            $post = $id ? R::load('post', $id) : R::dispense('post');
            $post->title = $title;
            $post->content = $content;
            $post->thumbnail_url = $thumbnailUrl ?: null;
            $post->excerpt = $excerpt !== '' ? $excerpt : null;
            $post->tags = $tags ? implode(',', $tags) : null;
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

        if ($baseUrl === '') {
            $baseUrl = '/';
        }

        if ($baseUrl[0] !== '/') {
            $errors[] = 'Základní URL musí začínat lomítkem (např. / nebo /blog/).';
        }

        if (substr($baseUrl, -1) !== '/') {
            $baseUrl .= '/';
        }

        if ($siteName === '') {
            $errors[] = 'Název webu je povinný.';
        }

        if (!$errors) {
            $settings = [
                'site_name' => $siteName,
                'base_url'  => $baseUrl,
            ];

            try {
                foreach ($settings as $key => $value) {
                    $bean = R::findOne('setting', ' `key` = ? ', [$key]) ?: R::dispense('setting');
                    $bean->key = $key;
                    $bean->value = $value;
                    R::store($bean);
                }
            } catch (\Throwable $e) {
                $errors[] = 'Nastavení se nepodařilo uložit. Ověř připojení k databázi.';
            }

            if (!$errors) {
                header('Location: ?action=settings&saved=1');
                exit;
            }
        }
    }

    return $errors;
}
