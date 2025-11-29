<?php
use Cocur\Slugify\Slugify;
use RedBeanPHP\R as R;
use Web\App\UploadManager;

const ADMIN_LEVELS = [
    'superadmin'  => 100,
    'admin'       => 80,
    'manager'     => 70,
    'editor'      => 60,
    'author'      => 40,
    'contributor' => 30,
    'viewer'      => 10,
];

function adminRoleLevels(): array
{
    return ADMIN_LEVELS;
}

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

    $level = $user->level ?? null;
    $roleLevels = adminRoleLevels();
    $fallbackLevel = $roleLevels[$user->role] ?? ADMIN_LEVELS['viewer'];

    if ($level === null || $level === '') {
        $level = $fallbackLevel;
        $user->level = $level;
        R::store($user);
    }

    return [
        'id'       => $user->id,
        'username' => $user->username,
        'role'     => $user->role,
        'level'    => $level,
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
    $currentId = isset($_POST['id']) ? (int) $_POST['id'] : null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !adminHasLevel(ADMIN_LEVELS['author'])) {
        return ['Nemáš oprávnění ukládat příspěvky.'];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $thumbnailUrl = trim($_POST['thumbnail_url'] ?? '');
        $slugInput = trim($_POST['slug'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $postTypeId = isset($_POST['post_type_id']) ? (int) $_POST['post_type_id'] : null;
        $categoryIds = isset($_POST['categories']) && is_array($_POST['categories'])
            ? array_map('intval', $_POST['categories'])
            : [];
        $tagIds = isset($_POST['tags']) && is_array($_POST['tags'])
            ? array_map('intval', $_POST['tags'])
            : [];

        if ($content === '') {
            $errors[] = 'Obsah příspěvku je povinný.';
        }

        if ($title === '') {
            $errors[] = 'Název je povinný.';
        }

        $availableTypes = fetchAllPostTypes();
        $availableTypeIds = array_map(fn ($type) => (int) $type['id'], $availableTypes);

        if (!$availableTypeIds) {
            $errors[] = 'Není dostupný žádný typ obsahu. Nejprve ho vytvoř.';
        }

        if ($postTypeId === null && $availableTypeIds) {
            $postTypeId = $availableTypeIds[0];
        }

        if ($postTypeId && !in_array($postTypeId, $availableTypeIds, true)) {
            $errors[] = 'Zvolený typ obsahu neexistuje.';
        }

        if ($thumbnailUrl !== '' && !filter_var($thumbnailUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL náhledového obrázku musí být ve správném formátu.';
        }

        if (!$errors) {
            $uploadManager = new UploadManager();
            $thumbnailUpload = $_FILES['thumbnail_upload'] ?? null;

            if ($thumbnailUpload && ($thumbnailUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $thumbnailUrl = $uploadManager->storeImage($thumbnailUpload);
                    adminRecordUpload($thumbnailUrl, 'image', $thumbnailUpload);
                } catch (\Throwable $e) {
                    $errors[] = 'Náhledový obrázek se nepodařilo nahrát: ' . $e->getMessage();
                }
            }
        }

        if (!$errors) {
            $post = $currentId ? R::load('post', $currentId) : R::dispense('post');
            $post->title = $title;
            $post->content = $content;
            $post->thumbnail_url = $thumbnailUrl ?: null;
            $post->excerpt = $excerpt !== '' ? $excerpt : null;
            $post->post_type_id = $postTypeId;
            $post->slug = adminGenerateUniqueSlug($slugInput !== '' ? $slugInput : $title, $post->id ?: null);
            $post->published_at = $post->published_at ?: date('Y-m-d H:i:s');
            $post->updated_at = date('Y-m-d H:i:s');
            R::store($post);

            syncPostRelations($post->id, $categoryIds, 'post_category', 'category_id');
            syncPostRelations($post->id, $tagIds, 'post_tag', 'tag_id');

            $tagNames = fetchTagNamesByIds($tagIds);
            $post->tags = $tagNames ? implode(',', $tagNames) : null;
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
        if (!adminHasLevel(ADMIN_LEVELS['manager'])) {
            return ['Nemáš oprávnění měnit nastavení.'];
        }

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

function adminManageUsers(): array
{
    $errors = [];
    $userForEdit = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!adminHasLevel(ADMIN_LEVELS['admin'])) {
            return [['Nemáš oprávnění spravovat uživatele.'], $userForEdit];
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : null;
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'editor';

        if ($username === '') {
            $errors[] = 'Uživatelské jméno je povinné.';
        }

        if (!array_key_exists($role, ADMIN_LEVELS)) {
            $errors[] = 'Neplatná role.';
        }

        $existing = R::findOne('user', ' username = ? AND id != ? ', [$username, $id ?? 0]);
        if ($existing) {
            $errors[] = 'Uživatel s tímto jménem již existuje.';
        }

        $user = $id ? R::load('user', $id) : R::dispense('user');

        if (!$id && $password === '') {
            $errors[] = 'Pro nový účet je heslo povinné.';
        }

        if (!$errors) {
            $user->username = $username;
            $user->role = $role;
            $user->level = ADMIN_LEVELS[$role];
            if ($password !== '') {
                $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
            }
            $user->created_at = $user->created_at ?: date('Y-m-d H:i:s');
            $user->updated_at = date('Y-m-d H:i:s');
            R::store($user);

            header('Location: ?action=users&saved=1');
            exit;
        }

        $userForEdit = $user;
    }

    if (isset($_GET['id'])) {
        $userForEdit = R::load('user', (int) $_GET['id']);
        if (!$userForEdit->id) {
            $userForEdit = null;
        }
    }

    return [$errors, $userForEdit];
}

function adminHandleUpload(?UploadManager $uploadManager = null): array
{
    $errors = [];
    $success = null;
    $uploadManager = $uploadManager ?: new UploadManager();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [$errors, $success];
    }

    if (!adminHasLevel(ADMIN_LEVELS['author'])) {
        return [['Nemáš oprávnění nahrávat soubory.'], $success];
    }

    $type = $_POST['upload_type'] ?? 'image';
    $file = $_FILES['uploaded_file'] ?? null;

    if (!$file) {
        return [['Nebyl vybrán soubor k nahrání.'], $success];
    }

    try {
        if ($type === 'file') {
            $success = $uploadManager->storeFile($file);
        } else {
            $success = $uploadManager->storeImage($file);
        }

        adminRecordUpload($success, $type, $file);
    } catch (\Throwable $e) {
        $errors[] = $e->getMessage();
    }

    return [$errors, $success];
}

function adminRecordUpload(string $path, string $type, array $file): void
{
    $upload = R::dispense('upload');
    $upload->path = $path;
    $upload->type = $type;
    $upload->original_name = $file['name'] ?? null;
    $upload->mime_type = $file['type'] ?? null;
    $upload->size = $file['size'] ?? null;
    $upload->created_at = date('Y-m-d H:i:s');

    R::store($upload);
}

function adminHasLevel(int $level): bool
{
    $current = adminCurrentUser();
    return $current && (($current['level'] ?? 0) >= $level);
}

function adminGenerateUniqueSlug(string $slugCandidate, ?int $currentId = null): string
{
    $baseSlug = adminSlugify($slugCandidate);

    if ($baseSlug === '') {
        $baseSlug = 'prispevek';
    }

    $slug = $baseSlug;
    $counter = 2;

    while (R::count('post', ' slug = ? AND id != ? ', [$slug, $currentId ?? 0])) {
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }

    return $slug;
}

function adminGenerateUniqueTermSlug(string $slugCandidate, string $table, ?int $currentId = null): string
{
    $baseSlug = adminSlugify($slugCandidate);

    if ($baseSlug === '') {
        $baseSlug = $table . '-polozka';
    }

    $slug = $baseSlug;
    $counter = 2;

    while (R::count($table, ' slug = ? AND id != ? ', [$slug, $currentId ?? 0])) {
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }

    return $slug;
}

function adminManageTaxonomy(string $table): array
{
    $errors = [];
    $saved = false;
    $editing = null;

    if (!in_array($table, ['category', 'tag', 'posttype'], true)) {
        return [['Neznámý typ taxonomie.'], false];
    }

    if (isset($_GET['delete']) && adminHasLevel(ADMIN_LEVELS['editor'])) {
        $deleteId = (int) $_GET['delete'];
        $bean = R::load($table, $deleteId);
        if ($bean->id) {
            R::trash($bean);
            $saved = true;
        }
    }

    if (isset($_GET['edit'])) {
        $editing = R::load($table, (int) $_GET['edit']);
        if (!$editing->id) {
            $editing = null;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!adminHasLevel(ADMIN_LEVELS['editor'])) {
            return [['Nemáš oprávnění upravovat tuto sekci.'], false];
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : null;
        $name = trim($_POST['name'] ?? '');
        $slugInput = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $errors[] = 'Název je povinný.';
        }

        if (!$errors) {
            $slug = adminGenerateUniqueTermSlug($slugInput !== '' ? $slugInput : $name, $table, $id);
            $existing = R::findOne($table, ' slug = ? AND id != ? ', [$slug, $id ?? 0]);

            if ($existing) {
                $errors[] = 'Slug musí být unikátní.';
            }
        }

        if (!$errors) {
            $bean = $id ? R::load($table, $id) : R::dispense($table);
            $bean->name = $name;
            $bean->slug = $slug;
            $bean->description = $description !== '' ? $description : null;
            $bean->created_at = $bean->created_at ?: date('Y-m-d H:i:s');
            $bean->updated_at = date('Y-m-d H:i:s');
            R::store($bean);

            $saved = true;
            $editing = null;
        }
    }

    return [$errors, $saved, $editing];
}

function adminSlugify(string $text): string
{
    static $slugify;

    if (!$slugify && class_exists(Slugify::class)) {
        $slugify = new Slugify();
    }

    if ($slugify instanceof Slugify) {
        return $slugify->slugify($text);
    }

    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);

    return trim($text, '-');
}
