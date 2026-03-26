# Knihovna Písní (Song Library)

**Lehká webová aplikace pro správu hudebního repertoáru.**

Tento systém je navržen pro hudebníky a kapely vyžadující efektivní správu svého repertoáru. Aplikace je postavena na technologii PHP a využívá JSON úložiště namísto SQL databáze. Toto řešení zajišťuje vysokou rychlost, snadné zálohování a maximální přenositelnost bez nutnosti složité konfigurace serveru.

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat&logo=php&logoColor=white)
![Data](https://img.shields.io/badge/Data-JSON-orange?style=flat)
![Chart.js](https://img.shields.io/badge/Chart.js-Stats-ff6384?style=flat)

---

## Hlavní funkce

*   **Bezdatabázový provoz:** Veškerá data jsou uložena v souboru `data/songs.json`. Není vyžadováno nastavení MySQL ani jiného databázového serveru.
*   **Optimalizované rozhraní:** Responzivní design navržený prioritně pro mobilní zařízení, umožňující rychlou interakci během zkoušek nebo vystoupení.
*   **Sledování historie:** Evidence dat jednotlivých přehrání s automatickým vyhodnocováním frekvence užití skladeb.
*   **Hudební spektrum:** Pokročilé vizuální statistiky využívající knihovnu Chart.js. Přehledné porovnání nejhranějších skladeb (Top 20) a méně frektventovaných titulů.
*   **Efektivní vyhledávání:** Filtrace podle názvu, autora, kategorií, štítků (tagů) nebo času od posledního zařazení do programu.
*   **Synchronizace s Google Cloud:** Volitelná podpora pro propojení s Google Sheets prostřednictvím Google Apps Scriptu pro hromadnou správu dat.

---

## Požadavky a instalace

Software vyžaduje webový server s podporou PHP ve verzi 7.4 nebo vyšší.

### 1. Klonování repozitáře
```bash
git clone https://github.com/MarciPhan/SongDatabase.git
cd SongDatabase
```

### 2. Konfigurace oprávnění
Webový server musí mít oprávnění k zápisu do adresáře pro data (výchozí cesta je `data/`).

```bash
mkdir -p data
chmod 775 data
# Doporučené nastavení vlastníka (příklad pro Debian/Ubuntu):
# chown www-data:www-data data
```

### 3. Nastavení parametrů
Základní konfigurace se nachází v souboru `config.php`:

```php
<?php
// Cesta k JSON databázi
$LOCAL_DB = __DIR__ . "/data/songs.json";

// Endpoint pro synchronizaci s Google API (ponechte prázdné, pokud nevyužíváte)
$API_URL = ""; 
?>
```

---

## Architektura projektu

*   `index.php` – Centrální rozhraní a dashboard systému.
*   `logic.php` – Serverová logika pro zpracování dat a generování statistik.
*   `api_*.php` – Modulární asynchronní rozhraní pro manipulaci s daty.
*   `script.js` – Klientská logika, vizualizace dat a správa uživatelských interakcí.
*   `styles.css` – Definice vizuálního stylu.
*   `data/songs.json` – Hlavní datový soubor.

---

## Integrace s Google Sheets

Systém umožňuje export a synchronizaci dat s tabulkami Google:

1.  Vytvořte Google Sheet a připojte Google Apps Script.
2.  Implementujte metody `doPost` a `doGet`.
3.  Přiřaďte URL adresu nasazeného skriptu do proměnné `$API_URL` v `config.php`.
4.  Aplikace bude automaticky odesílat aktualizace při každé změně záznamu.

---

## Technologie

*   **Server-side:** PHP 7.4 / 8.x
*   **Client-side:** HTML5, CSS3, JavaScript (ES6+)
*   **Vizualizace:** [Chart.js](https://www.chartjs.org/)

## Licence

Projekt je šířen jako open-source software pod licencí MIT. Je povolen libovolný rozvoj a úprava pro soukromé i komerční účely.
