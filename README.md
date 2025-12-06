🎵 Knihovna a Generátor Písní pro Hudební Skupinku

Jednoduchá webová aplikace pro správu písní, evidenci hraní a generování playlistů pro hudební skupinky (chvály).

Aplikace funguje na principu PHP frontendu a JSON databáze, která se automaticky synchronizuje s Google Tabulkou (jako zálohou a administrací).

✨ Funkce

🎸 Pro uživatele (Frontend)

Seznam písní: Přehledná tabulka s řazením a filtrováním (podle názvu, kategorie, tagů).

Zápis hraní: Jednoduchý formulář pro zaznamenání, že se píseň hrála (datum se uloží do historie).

Generátor playlistu: Náhodný výběr písní podle kritérií (rychlá/pomalá, nehráno X měsíců, počet písní).

Historie: Kalendářní a seznamový přehled, kdy se co hrálo.

Statistiky: Graf nejhranějších písní.

🛠 Pro správce (Editace)

Přidat píseň: Formulář pro vložení nové písně.

Upravit píseň: Možnost změnit název, autora, tóninu, tempo i tagy.

Editace historie: Zpětná úprava nebo smazání konkrétních dat hraní (když se spletete).

Mazání písní: Odstranění písně z databáze.

🔄 Synchronizace (Backend)

Data se ukládají do lokálního souboru data/songs.json.

Při každé změně (zápis, úprava) se na pozadí spustí Google Apps Script.

Skript zajistí obousměrnou synchronizaci s Google Tabulkou (Excel), takže máte data vždy zálohovaná a přístupná i v tabulkovém procesoru.

🚀 Instalace

1. Požadavky

Webhosting s podporou PHP 7.4+ (nebo novější).

Přístup k FTP pro nahrání souborů.

Google účet (pro Google Sheets synchronizaci).

2. Nahrání souborů

Nahrajte všechny soubory z tohoto repozitáře na váš server.

Struktura:

/
├── index.php             (Hlavní aplikace)
├── config.php            (Konfigurace cest)
├── api_local.php         (Backend pro zápis hraní)
├── api_add_song.php      (Backend pro přidání písně)
├── api_manage_song.php   (Backend pro úpravu/mazání písně)
├── api_manage_history.php(Backend pro úpravu historie)
├── api_receive_sync.php  (Příjem dat z Google Sheets)
├── styles.css            (Vzhled)
└── data/                 (Složka pro data - MUSÍ MÍT PRÁVA ZÁPISU 777)
    └── songs.json        (Databáze písní)


3. Konfigurace

Otevřete soubor config.php.

Nastavte cestu k vaší JSON databázi (pokud měníte složku).

Vložte URL vašeho Google Apps Scriptu (viz níže).

<?php
$LOCAL_DB = __DIR__ . "/data/songs.json";
$API_URL = "[https://script.google.com/macros/s/VAS_KOD_SKRIPTU/exec](https://script.google.com/macros/s/VAS_KOD_SKRIPTU/exec)";
?>


4. Nastavení Google Sheets (Synchronizace)

Vytvořte novou Google Tabulku.

V horním menu vyberte Rozšíření > Apps Script.

Zkopírujte obsah souboru code.gs (najdete v repozitáři nebo v dokumentaci) do editoru.

Upravte v kódu SHEET_ID (ID vaší tabulky z URL adresy).

Klikněte na Nasazení (Deploy) > Nové nasazení.

Vyberte typ Webová aplikace.

Nastavte:

Spustit jako: Já (Me)

Kdo má přístup: Kdokoliv (Anyone)

Zkopírujte vygenerovanou URL a vložte ji do config.php na vašem webu.

💡 Jak to funguje

Čtení: Aplikace čte data primárně z data/songs.json, což je velmi rychlé.

Zápis: Když upravíte píseň, PHP ji uloží do JSONu.

Sync: PHP ihned zavolá Google Apps Script. Ten si stáhne nový JSON, porovná ho s Tabulkou a sjednotí data (merge). Výsledek pošle zpět na web.

Díky tomu máte rychlý web a zároveň robustní zálohu v Excelu.

📱 Použité technologie

Frontend: HTML5, CSS3 (Grid/Flexbox), JavaScript (Vanilla).

Backend: PHP.

Data: JSON soubor.

Knihovny: Chart.js (grafy).

Cloud: Google Apps Script (synchronizace).

⚠️ Řešení problémů

Data se neukládají: Zkontrolujte, zda má složka data/ a soubor songs.json oprávnění pro zápis (CHMOD 777 nebo 7
