# webappka

Jednoduchá webová aplikace s RedBeanPHP a Twig, rozšířená o instalační průvodce a základní administraci.

## Instalace
1. Otevři `/install/` v prohlížeči a projdi průvodce (ověření databáze, vytvoření administrátora, uložení `config/config.php`).
2. Po dokončení se přihlaš do `/admin/` stejnými údaji, které jsi nastavil během instalace.

## Veřejná část
- Domovská stránka vypisuje publikované příspěvky.
- Detaily příspěvků jsou dostupné přes `?page=post&id=ID`.

## Administrace
- Přehled statistik.
- Správa příspěvků (vytváření a editace).
- Přehled uživatelů.
- Uložení základního nastavení webu (název, base URL).
