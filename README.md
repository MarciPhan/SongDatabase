# 🎹 Knihovna Písní (Song Library)

Jednoduchá, lehká a mobilní webová aplikace pro správu hudebního repertoáru. Běží na čistém PHP bez nutnosti SQL databáze (data se ukládají do JSON). Ideální pro kapely nebo hudebníky, kteří si chtějí udržovat přehled o tom, co a kdy hráli.

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat&logo=php&logoColor=white)
![Data](https://img.shields.io/badge/Data-JSON-orange?style=flat)
![Chart.js](https://img.shields.io/badge/Chart.js-Stats-ff6384?style=flat)

## ✨ Klíčové Funkce

* **🗂 Databáze bez SQL:** Všechna data jsou uložena v lokálním souboru `songs.json`. Snadné zálohování a přenositelnost.
* **📱 Mobile-First Design:** Responzivní rozhraní optimalizované pro rychlé použití na mobilu (např. během zkoušky).
* **📅 Historie hraní:** Sledování, kdy byla která píseň naposledy hrána. Automatické řazení podle data.
* **📊 Hudební Spektrum:** Moderní grafické statistiky porovnávající **Top 20** (nejhranější hity) a **Rarity 20** (zapomenuté klenoty) pomocí Chart.js.
* **🔍 Chytré filtry:** Filtrace podle kategorie, tagů nebo počtu dní od posledního hraní.
* **☁️ Google Sync (Volitelné):** Podpora synchronizace dat s Google Sheets (přes Google Apps Script).

## 🚀 Instalace a Spuštění

Tato aplikace nevyžaduje žádnou složitou instalaci. Stačí běžný webhosting nebo lokální server s podporou PHP.

### 1. Klonování repozitáře
```bash
git clone [https://github.com/tve-uzivatelske-jmeno/knihovna-pisni.git](https://github.com/tve-uzivatelske-jmeno/knihovna-pisni.git)
````

### 2\. Příprava složek

Ujistěte se, že skript má právo zápisu do složky `data` (nebo tam, kde je definován `$LOCAL_DB` v `config.php`).

```bash
mkdir data
chmod 777 data  # Nebo nastavte vlastníka (chown www-data:www-data)
```

### 3\. Konfigurace

Otevřete soubor `config.php` a upravte nastavení podle potřeby:

```php
<?php
// Cesta k JSON databázi
$LOCAL_DB = __DIR__ . "/data/songs.json";

// (Volitelné) URL Google Apps Scriptu pro synchronizaci
$API_URL = ""; 
?>
```

## 📂 Struktura Projektu

  * `index.php` - Hlavní rozhraní aplikace (Dashboard, Seznam, Modaly).
  * `logic.php` - Backend logika pro přípravu dat a výpočty statistik.
  * `api_*.php` - API endpointy pro AJAX volání (přidávání, editace, historie).
  * `script.js` - Frontend logika, ovládání grafů a modalů.
  * `styles.css` - Stylování aplikace.
  * `data/songs.json` - Hlavní úložiště dat.

## 📊 Statistiky (Hudební Spektrum)

Místo nudných tabulek aplikace využívá vizuální "Hudební spektrum":

1.  **Síň slávy:** Horizontální graf 20 nejhranějších písní.
2.  **Podzemí:** Graf 20 nejméně hraných písní (pro oživení repertoáru).

## 🔄 Synchronizace s Google Sheets (Volitelné)

Pokud chcete zálohovat data do tabulky nebo je editovat hromadně v Excelu/Google Sheets:

1.  Vytvořte Google Sheet a připojte k němu Google Apps Script.
2.  Script musí přijímat `doPost` a `doGet` požadavky.
3.  Vložte URL publikovaného skriptu do `config.php` jako `$API_URL`.
4.  Aplikace automaticky odešle data při každé změně (Add/Edit).

## 🛠 Použité Technologie

  * **Backend:** PHP (Native)
  * **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
  * **Knihovny:** [Chart.js](https://www.chartjs.org/) (CDN)

## 📝 Licence

Tento projekt je open-source. Můžete jej volně upravovat a používat pro své potřeby.

```
```
