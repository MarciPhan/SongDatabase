// ===============================================================
// CONFIG
// ===============================================================

const SHEET_ID = "10mZUOHEjT3uYCsHtAGOYtZoQj0okJfaXA2IVNyuO40c"; // Vaše ID tabulky
const SHEET_NAME = "Songs";
const LOG_SHEET_NAME = "SystemLog"; 

// Cesty k webu
const JSON_URL = "https://pisne.baptistejablonec.cz/data/songs.json";
const API_ENDPOINT = "https://pisne.baptistejablonec.cz/api_receive_sync.php";

// Definice hlavičky
const HEADERS = ["Name", "Author", "Tempo", "Category", "Tags", "Count", "Last", "History"];

// ===============================================================
// LOGOVÁNÍ (Vždy nejčerstvějších 30 záznamů)
// ===============================================================

function logStatus(message, type = "INFO") {
  const ss = SpreadsheetApp.openById(SHEET_ID);
  let logSheet = ss.getSheetByName(LOG_SHEET_NAME);
  
  // Pokud log list neexistuje, vytvoříme ho
  if (!logSheet) {
    logSheet = ss.insertSheet(LOG_SHEET_NAME);
    logSheet.appendRow(["Timestamp", "Type", "Message"]);
    logSheet.setColumnWidth(1, 150);
    logSheet.setColumnWidth(3, 400);
    logSheet.getRange("A1:C1").setFontWeight("bold");
  }

  const time = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), "yyyy-MM-dd HH:mm:ss");
  
  // 1. Vždy zapíšeme nový log na konec (aby byl nejčerstvější vidět)
  logSheet.appendRow([time, type, message]);
  
  // 2. Udržujeme pouze posledních 30 řádků (+1 hlavička)
  // Smažeme staré řádky odshora (hned pod hlavičkou), tím zmizí historie a zůstane jen novinky.
  const maxLogs = 30;
  const currentRows = logSheet.getLastRow();
  
  if (currentRows > maxLogs + 1) {
    // Kolik řádků musíme smazat?
    const rowsToDelete = currentRows - (maxLogs + 1);
    // Smažeme řádky od indexu 2 (řádek 1 je hlavička)
    logSheet.deleteRows(2, rowsToDelete);
  }
}

// ===============================================================
// PRÁCE S LISTEM (SHEET)
// ===============================================================

function getSongSheet() {
  const ss = SpreadsheetApp.openById(SHEET_ID);
  let sheet = ss.getSheetByName(SHEET_NAME);
  if (!sheet) {
    sheet = ss.insertSheet(SHEET_NAME);
    logStatus("Vytvořen nový list: " + SHEET_NAME, "SETUP");
  }
  return sheet;
}

// Kontrola a oprava struktury tabulky
function checkAndSetupSheet(sheet) {
  const lastRow = sheet.getLastRow();
  
  if (lastRow < 1) {
    sheet.getRange(1, 1, 1, HEADERS.length).setValues([HEADERS]);
    sheet.getRange(1, 1, 1, HEADERS.length).setFontWeight("bold").setBackground("#f3f3f3");
    sheet.setFrozenRows(1);
    logStatus("Vygenerována hlavička tabulky", "SETUP");
  } else {
    sheet.getRange(1, 1, 1, HEADERS.length).setValues([HEADERS]);
  }
}

function readSheetData() {
  const sh = getSongSheet();
  checkAndSetupSheet(sh); 

  const lastRow = sh.getLastRow();
  if (lastRow < 2) return []; 
  
  const range = sh.getRange(2, 1, lastRow - 1, HEADERS.length);
  const values = range.getValues();
  
  return values.map(row => {
    let dateStr = "";
    if (row[6] instanceof Date) {
      dateStr = Utilities.formatDate(row[6], Session.getScriptTimeZone(), "yyyy-MM-dd");
    } else {
      dateStr = row[6] ? row[6].toString().trim() : "";
    }

    let historyArr = [];
    try {
      if (row[7] && row[7].toString().trim() !== "") {
        historyArr = JSON.parse(row[7]);
      }
    } catch (e) {
      if (row[7]) historyArr = [row[7].toString()];
    }

    return {
      name: row[0].toString().trim(),
      author: row[1].toString(),
      tempo: row[2].toString(),
      category: row[3].toString(),
      tags: row[4].toString(),
      count: Number(row[5] || 0),
      last: dateStr,
      history: Array.isArray(historyArr) ? historyArr : []
    };
  }).filter(s => s.name !== "");
}

function writeSheetData(data) {
  const sh = getSongSheet();
  checkAndSetupSheet(sh);
  
  data.sort((a, b) => a.name.localeCompare(b.name, 'cs'));

  const rows = data.map(s => [
    s.name,
    s.author,
    s.tempo,
    s.category,
    s.tags,
    s.count,
    s.last,
    JSON.stringify(s.history)
  ]);

  const lastRow = sh.getLastRow();
  if (lastRow > 1) {
    sh.getRange(2, 1, lastRow - 1, HEADERS.length).clearContent();
  }

  if (rows.length > 0) {
    sh.getRange(2, 1, rows.length, HEADERS.length).setValues(rows);
  }
}

function jsonResponse(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}

// ===============================================================
// HLAVNÍ SYNCHRONIZACE (MERGE)
// ===============================================================

function performFullSync() {
  logStatus("Spouštím synchronizaci...", "START");
  
  try {
    const response = UrlFetchApp.fetch(JSON_URL + "?t=" + new Date().getTime());
    let jsonData = [];
    try { 
      jsonData = JSON.parse(response.getContentText()); 
    } catch (e) {
      logStatus("Chyba parsování JSON z webu: " + e.toString(), "ERROR");
    }
    
    const sheetData = readSheetData();

    const jsonMap = new Map();
    if (Array.isArray(jsonData)) jsonData.forEach(item => jsonMap.set(item.name, item));
    
    const sheetMap = new Map();
    sheetData.forEach(item => sheetMap.set(item.name, item));

    const mergedData = [];
    const allNames = new Set([...sheetMap.keys(), ...jsonMap.keys()]);

    allNames.forEach(name => {
      const sItem = sheetMap.get(name);
      const jItem = jsonMap.get(name);

      let finalItem = {};

      if (sItem && jItem) {
        const historySet = new Set([...(sItem.history || []), ...(jItem.history || [])]);
        if (sItem.last) historySet.add(sItem.last);
        if (jItem.last) historySet.add(jItem.last);
        
        const mergedHistory = Array.from(historySet).sort().reverse();
        
        finalItem = {
          name: sItem.name,          
          author: sItem.author, 
          tempo: sItem.tempo,
          category: sItem.category,
          tags: sItem.tags,
          count: Math.max(sItem.count, jItem.count),
          last: mergedHistory.length > 0 ? mergedHistory[0] : "",
          history: mergedHistory
        };
      } else if (sItem) {
        finalItem = sItem;
      } else if (jItem) {
        finalItem = {
            name: jItem.name,
            author: jItem.author || "",
            tempo: jItem.tempo || "",
            category: jItem.category || "",
            tags: jItem.tags || "",
            count: jItem.count || 0,
            last: jItem.last || "",
            history: jItem.history || (jItem.last ? [jItem.last] : [])
        };
      }

      mergedData.push(finalItem);
    });

    writeSheetData(mergedData);

    const payload = { songs: mergedData };
    const options = {
      method: "post",
      contentType: "application/json",
      payload: JSON.stringify(payload),
      muteHttpExceptions: true
    };

    const apiResponse = UrlFetchApp.fetch(API_ENDPOINT, options);
    const apiResult = JSON.parse(apiResponse.getContentText());

    if (apiResult.ok) {
        logStatus(`Úspěch. Synch: ${mergedData.length} písní.`, "SUCCESS");
        return { ok: true, total: mergedData.length };
    } else {
        logStatus("Chyba API Web: " + apiResult.error, "ERROR");
        return { error: apiResult.error };
    }

  } catch (e) {
    logStatus("Kritická chyba: " + e.toString(), "CRITICAL");
    return { error: e.toString() };
  }
}

// ===============================================================
// UI & ENDPOINTS
// ===============================================================

function onOpen() {
  const ui = SpreadsheetApp.getUi();
  ui.createMenu('🎵 Hudba Sync')
      .addItem('🔄 Spustit synchronizaci', 'manualSync')
      .addItem('🛠 Opravit strukturu tabulky', 'fixStructureOnly')
      .addToUi();
}

function manualSync() {
  const res = performFullSync();
  const ui = SpreadsheetApp.getUi();
  if (res.ok) ui.alert("✅ Hotovo! Písní: " + res.total);
  else ui.alert("❌ Chyba: " + res.error);
}

function fixStructureOnly() {
  const sh = getSongSheet();
  checkAndSetupSheet(sh);
  SpreadsheetApp.getUi().alert("✅ Struktura a hlavička tabulky byly zkontrolovány.");
}

function doGet(e) {
  const action = e?.parameter?.action;
  if (action === "sync_to_sheet") return jsonResponse(performFullSync());
  return jsonResponse({ status: "Ready" });
}
