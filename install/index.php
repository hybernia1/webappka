<?php
use RedBeanPHP\R as R;

require __DIR__ . '/../vendor/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$configFile = __DIR__ . '/../config/config.php';

function render(string $content)
{
    $siteName = 'Webappka instalace';
    echo '<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8"><title>' . $siteName . '</title>';
    echo '<link rel="stylesheet" href="../assets/style.css">';
    echo '</head><body><main style="max-width:760px;margin:32px auto;">';
    echo '<h1>Instalace</h1>';
    echo $content;
    echo '</main></body></html>';
}

if (file_exists($configFile)) {
    render('<p>Konfigurační soubor již existuje. <a href="../index.php">Pokračovat na web</a>.</p>');
    exit;
}

$step = $_GET['step'] ?? 'welcome';

if ($step === 'welcome') {
    $content = '<p>Pro dokončení instalace prosím projdi jednoduchého průvodce.</p>';
    $content .= '<p><a class="button" href="?step=database">Začít</a></p>';
    render($content);
    exit;
}

if ($step === 'database') {
    $errors = [];
    $dsn = $_POST['dsn'] ?? 'mysql:host=localhost;dbname=webappka;charset=utf8mb4';
    $user = $_POST['user'] ?? 'root';
    $password = $_POST['password'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            R::setup($dsn, $user, $password);
            if (!R::testConnection()) {
                throw new RuntimeException('Nepodařilo se připojit k databázi.');
            }

            $_SESSION['install']['dsn'] = $dsn;
            $_SESSION['install']['user'] = $user;
            $_SESSION['install']['password'] = $password;

            header('Location: ?step=site');
            exit;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    $content = '<h2>Krok 1: Připojení k databázi</h2>';
    if ($errors) {
        $content .= '<div class="alert error">' . implode('<br>', $errors) . '</div>';
    }
    $content .= '<form method="post">'
        . '<label>DSN</label>'
        . '<input type="text" name="dsn" value="' . htmlspecialchars($dsn) . '" required>'
        . '<label>Uživatel</label>'
        . '<input type="text" name="user" value="' . htmlspecialchars($user) . '" required>'
        . '<label>Heslo</label>'
        . '<input type="password" name="password" value="' . htmlspecialchars($password) . '">' 
        . '<button type="submit">Ověřit a pokračovat</button>'
        . '</form>';

    render($content);
    exit;
}

if ($step === 'site') {
    if (empty($_SESSION['install']['dsn'])) {
        header('Location: ?step=database');
        exit;
    }

    $errors = [];
    $siteName = $_POST['site_name'] ?? 'Moje nová webappka';
    $baseUrl = $_POST['base_url'] ?? '/';
    $adminUser = $_POST['admin_user'] ?? 'admin';
    $adminPass = $_POST['admin_pass'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($adminPass === '') {
            $errors[] = 'Heslo administrátora je povinné.';
        }

        if (!$errors) {
            try {
                R::setup($_SESSION['install']['dsn'], $_SESSION['install']['user'], $_SESSION['install']['password']);
                if (!R::testConnection()) {
                    throw new RuntimeException('Nepodařilo se znovu připojit k databázi.');
                }

                // vytvoření tabulek
                $userBean = R::findOne('user', ' username = ? ', [$adminUser]) ?: R::dispense('user');
                $userBean->username = $adminUser;
                $userBean->password_hash = password_hash($adminPass, PASSWORD_BCRYPT);
                $userBean->role = 'admin';
                $userBean->created_at = $userBean->created_at ?: date('Y-m-d H:i:s');
                R::store($userBean);

                $settingName = R::findOne('setting', ' `key` = ? ', ['site_name']) ?: R::dispense('setting');
                $settingName->key = 'site_name';
                $settingName->value = $siteName;
                R::store($settingName);

                $settingUrl = R::findOne('setting', ' `key` = ? ', ['base_url']) ?: R::dispense('setting');
                $settingUrl->key = 'base_url';
                $settingUrl->value = $baseUrl;
                R::store($settingUrl);

                // uložit konfiguraci
                if (!is_dir(dirname($configFile))) {
                    mkdir(dirname($configFile), 0775, true);
                }

                $configContent = "<?php\nreturn [\n    'dsn' => '" . addslashes($_SESSION['install']['dsn']) . "',\n    'user' => '" . addslashes($_SESSION['install']['user']) . "',\n    'password' => '" . addslashes($_SESSION['install']['password']) . "',\n    'site_name' => '" . addslashes($siteName) . "',\n    'base_url' => '" . addslashes($baseUrl) . "',\n];\n";

                file_put_contents($configFile, $configContent);

                header('Location: ?step=finish');
                exit;
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    $content = '<h2>Krok 2: Nastavení webu</h2>';
    if ($errors) {
        $content .= '<div class="alert error">' . implode('<br>', $errors) . '</div>';
    }

    $content .= '<form method="post">'
        . '<label>Název webu</label>'
        . '<input type="text" name="site_name" value="' . htmlspecialchars($siteName) . '" required>'
        . '<label>Základní URL (např. / nebo /webappka/)</label>'
        . '<input type="text" name="base_url" value="' . htmlspecialchars($baseUrl) . '">' 
        . '<label>Admin uživatel</label>'
        . '<input type="text" name="admin_user" value="' . htmlspecialchars($adminUser) . '" required>'
        . '<label>Admin heslo</label>'
        . '<input type="password" name="admin_pass" value="' . htmlspecialchars($adminPass) . '" required>'
        . '<button type="submit">Vytvořit konfiguraci</button>'
        . '</form>';

    render($content);
    exit;
}

if ($step === 'finish') {
    $content = '<h2>Hotovo</h2>';
    $content .= '<p>Instalace byla dokončena. Pokračuj na <a href="../">web</a> nebo do <a href="../admin/">administrace</a>.</p>';
    render($content);
    exit;
}

header('Location: ?step=welcome');
