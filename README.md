# 🎵 Knihovna a Generátor Písní pro Hudební Skupinku

Jednoduchá, ale mocná webová aplikace pro správu písní, evidenci hraní a generování playlistů pro chválové skupiny.

Aplikace kombinuje **rychlost lokálního JSONu** s **robustností Google Tabulek**. Frontend běží na PHP a data se ukládají lokálně, zatímco na pozadí probíhá automatická synchronizace s Google Sheets, která slouží jako administrace a záloha.

---

## ✨ Funkce

### 🎸 Pro uživatele (Frontend)
* **Seznam písní:** Přehledná tabulka s řazením a filtrováním (podle názvu, kategorie, tagů).
* **Zápis hraní:** Jednoduchý formulář pro rychlé zaznamenání, že se píseň hrála.
* **Generátor playlistu:** Náhodný výběr písní podle kritérií (např. rychlé chvály, nehráno X měsíců, limit počtu písní).
* **Historie:** Detailní přehled (kalendářní i seznamový) o tom, kdy a co se hrálo.
* **Statistiky:** Grafický přehled nejčastěji hraných písní (Top 5).

### 🛠 Pro správce (Editace)
* **Přidat píseň:** Formulář pro vložení nové skladby.
* **Upravit píseň:** Možnost změnit název, autora, tóninu, tempo i tagy.
* **Editace historie:** Zpětná úprava nebo smazání konkrétního data hraní (pokud došlo k chybě při zápisu).
* **Mazání písní:** Úplné odstranění písně z databáze.

### 🔄 Synchronizace (Backend)
* Data se primárně ukládají do lokálního souboru `data/songs.json` (okamžitá odezva).
* Při každé změně (zápis, úprava, smazání) se na pozadí asynchronně zavolá **Google Apps Script**.
* Skript zajistí obousměrnou synchronizaci s Google Tabulkou, takže máte data vždy zálohovaná a přístupná i v Excelu.

---

## 🚀 Instalace

### 1. Požadavky
* Webhosting s podporou **PHP 7.4** nebo novější.
* Přístup k FTP pro nahrání souborů.
* Google účet (pro vytvoření synchronizačního skriptu).

### 2. Struktura souborů
Nahrajte všechny soubory na váš server. Struktura by měla vypadat takto:

```text
/
├── index.php              # Hlavní aplikace (Frontend)
├── config.php             # Konfigurace cest a API
├── styles.css             # Styly vzhledu
├── script.js              # Frontendová logika
├── logic.php              # Pomocná PHP logika (načítání dat)
├── api_local.php          # Backend pro zápis hraní
├── api_add_song.php       # Backend pro přidání písně
├── api_manage_song.php    # Backend pro úpravu/mazání písně
├── api_manage_history.php # Backend pro úpravu historie
├── api_receive_sync.php   # Příjem dat z Google Sheets (callback)
├── api_search.php         # Vyhledávání (volitelné)
└── data/                  # Složka pro data
    └── songs.json         # Databáze písní
````

🚨 **Důležité:** Složka `data/` a soubor `songs.json` musí mít práva pro zápis (CHMOD 777 nebo 775 podle nastavení serveru).

### 3\. Konfigurace webu

Otevřete soubor `config.php` a nastavte cestu k databázi a URL vašeho skriptu (ten získáte v kroku 4).

```php
<?php
// Cesta k lokální DB
$LOCAL_DB = __DIR__ . "/data/songs.json";

// URL Google Apps Scriptu (Deployment URL)
$API_URL = "[https://script.google.com/macros/s/VAS_KOD_SKRIPTU/exec](https://script.google.com/macros/s/VAS_KOD_SKRIPTU/exec)";
?>
```

### 4\. Nastavení Google Sheets (Synchronizace)

Tato část propojí vaši aplikaci s Google Tabulkou.

1.  Vytvořte novou **Google Tabulku**.
2.  V horním menu přejděte na **Rozšíření (Extensions) \> Apps Script**.
3.  Do editoru vložte kód ze souboru `code.gs` (součást tohoto projektu).
4.  V kódu skriptu upravte proměnnou `SHEET_ID` (najdete ji v URL adrese vaší tabulky).
5.  Klikněte na **Nasazení (Deploy) \> Nové nasazení (New deployment)**.
6.  Vyberte typ: **Webová aplikace (Web app)**.
7.  Nastavte oprávnění přesně takto:
      * **Description:** (libovolné, např. "SongSync")
      * **Execute as:** `Me` (Já)
      * **Who has access:** `Anyone` (Kdokoliv)
8.  Potvrďte a zkopírujte vygenerovanou **Web App URL**.
9.  Tuto URL vložte do `config.php` na vašem webu.

-----

## 💡 Jak to technicky funguje

1.  **Čtení:** Aplikace čte data primárně z `data/songs.json`. Díky tomu je načítání okamžité a nezávisí na rychlosti Google API.
2.  **Zápis:** Když uživatel zapíše hraní nebo upraví píseň, PHP skript uloží změnu lokálně do JSONu.
3.  **Sync:** Okamžitě po uložení PHP zavolá Google Apps Script (Webhook).
4.  **Merge:** Google Script porovná data, aktualizuje Tabulku a případné změny z Tabulky pošle zpět na web (do souboru `api_receive_sync.php`).

-----

## 📱 Použité technologie

  * **Frontend:** HTML5, CSS3 (Moderní Grid/Flexbox), Vanilla JavaScript.
  * **Backend:** PHP (zpracování API požadavků).
  * **Database:** JSON soubor (NoSQL přístup).
  * **Vizualizace:** Chart.js (pro grafy statistik).
  * **Cloud:** Google Apps Script & Google Sheets.

-----

## ⚠️ Řešení problémů

  * **Data se neukládají:** Zkontrolujte přes FTP, zda má složka `data/` nastavená práva **777** (zápis povolen pro všechny).
  * **Chyba synchronizace:** Ověřte, že v `config.php` je správná URL a že Google Script je nasazen s právy přístupu pro **"Anyone" (Kdokoliv)**.
  * **Duplicity v historii:** Ujistěte se, že používáte nejnovější verzi souborů `api_local.php` a `script.js`, které obsahují opravy pro kontrolu duplicitních dat.

-----

Made with ❤️ for worship teams.
