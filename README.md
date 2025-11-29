# webappka

Jednoduchá webová aplikace s RedBeanPHP a Twig, rozšířená o instalační průvodce a základní administraci.

## Instalace
1. Otevři `/install/` v prohlížeči a projdi průvodce (ověření databáze, vytvoření administrátora, uložení `config/config.php`).
2. Po dokončení se přihlaš do `/admin/` stejnými údaji, které jsi nastavil během instalace.

## Veřejná část
- Domovská stránka vypisuje publikované příspěvky.
- Detaily příspěvků jsou dostupné přes SEO slug `?page=post&slug=titulek-clanku` (případně `id` jako záloha).

## Administrace
- Přehled statistik.
- Správa příspěvků (vytváření a editace) včetně generování slugů a nahrání náhledů do struktury `uploads/images/Y/m`.
- Správa uživatelů s úrovněmi oprávnění.
- Uložení základního nastavení webu (název, base URL).
- Správa uploadů (obrázky i ostatní soubory) do složek `uploads/images/Y/m` a `uploads/files/Y/m`.

## Doporučené Composer balíčky pro univerzální CMS
- `symfony/http-foundation` – robustní Request/Response vrstvy včetně práce s uploady.
- `league/flysystem` – abstrakce úložišť (disk, S3, FTP) pro snadné přepínání backendů.
- `egulias/email-validator` – validace e-mailů při registraci a správě uživatelů.
- `symfony/translation` – lokalizace textů a překlady administrace i frontendu.
- `spatie/laravel-permission` (nebo obecnější `spatie/permission`) – pokročilé řízení rolí a práv, pokud bude potřeba granulárnější RBAC.
