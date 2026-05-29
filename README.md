# SW Design System

Konfigurovatelný design systém pro WordPress (weby Smart Websites). Nastavíte barvy, tvarosloví, hloubku, pohyb a dark mode v adminu → plugin z toho vygeneruje skutečný CSS soubor (žádné inline). Obsahuje knihovnu komponent a nápovědu.

## Instalace
1. Nahrajte složku `sw-design-system` do `wp-content/plugins/`.
2. Aktivujte plugin ve **Pluginy**.
3. Nastavení najdete pod **Vzhled → SW Design System** (jen administrátoři).

Při aktivaci se vytvoří `wp-content/uploads/sw-ds/sw-tokens.css` s výchozími hodnotami. Soubor se přegeneruje při každém uložení nastavení.

## Jak to funguje
- `assets/sw-design-system.css` — statická komponentová pravidla + výchozí `:root` (fallback).
- `uploads/sw-ds/sw-tokens.css` — generované `:root{}` přepisy (a dark mode). Načítá se **za** statickým CSS, takže přebíjí výchozí hodnoty.
- `assets/sw-design-system.js` — runtime (nájezdy prvků, parallax). Idempotentní.

Nic se netiskne inline do `<head>`. Generovaný soubor je cacheovatelný (cache-busting přes `filemtime`), takže funguje s Varnishem i WP Compress.

## Plátno
Aby společné pozadí prosvítalo skrz sekce, dejte třídu `sw-canvas` na `<body>` nebo obalte sekce do `<div class="sw-canvas">`.

## Komponenty
V záložce **Komponenty** zkopírujte HTML a vložte do LiveCanvas (prázdná sekce → Edit Code). Styl se načte automaticky.

## Přenos na jiný web
Záložka **Presety → Přenos nastavení**: zkopírujte JSON a naimportujte ho na druhém webu. Nebo použijte hotový preset pro rychle odlišný vzhled.

## Struktura
```
sw-design-system/
├── sw-design-system.php          # bootstrap, konstanty, aktivace
├── uninstall.php                 # úklid
├── includes/
│   ├── class-swds-tokens.php     # registr tokenů (zdroj pravdy)
│   ├── class-swds-presets.php    # pojmenované sady
│   ├── class-swds-generator.php  # tokeny → CSS soubor
│   ├── class-swds-settings.php   # admin stránka, ukládání
│   ├── class-swds-frontend.php   # enqueue
│   └── view-settings.php         # HTML admin stránky
├── assets/
│   ├── sw-design-system.css      # komponentové CSS
│   ├── sw-design-system.js       # runtime
│   ├── admin.css / admin.js      # admin UI
└── components/                   # HTML snippety pro knihovnu
```
