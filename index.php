<?php
require_once "config.php";

// 1. NAČTENÍ DAT
$songsData = [];
if (file_exists($LOCAL_DB)) {
    $jsonContent = file_get_contents($LOCAL_DB);
    $songsData = json_decode($jsonContent, true);
}
if (!is_array($songsData)) $songsData = [];

// 2. EXTRAKCE PRO FILTRY A DATALISTY
$categories = [];
$tags = [];
$tempos = [];

foreach ($songsData as $s) {
    if (!empty($s["category"]) && !in_array($s["category"], $categories)) $categories[] = $s["category"];
    if (!empty($s["tempo"]) && !in_array($s["tempo"], $tempos)) $tempos[] = $s["tempo"];
    if (!empty($s["tags"])) {
        foreach (explode(",", $s["tags"]) as $t) {
            $t = trim($t);
            if ($t && !in_array($t, $tags)) $tags[] = $t;
        }
    }
}
sort($categories);
sort($tags);
sort($tempos);

// 3. DATA PRO GRAF
$topSongs = $songsData;
usort($topSongs, fn($a, $b) => ($b["count"] ?? 0) <=> ($a["count"] ?? 0));
$topSongs = array_slice($topSongs, 0, 10);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Knihovna písní</title>
<link rel="stylesheet" href="styles.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* --- BASE --- */
body { background: #f4f4f4; color: #333; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding-bottom: 50px; }
*,*::before,*::after{box-sizing:border-box}
*{margin:0;padding:0}

/* --- HEADER --- */
.site-header{ background:#d11a2a; color:#fff; padding:15px; box-shadow:0 2px 8px rgba(0,0,0,.1); margin-bottom: 20px; }
.site-header .title{ font-weight:700; font-size:20px; text-align:center; }
.wrap { max-width: 1000px; margin: 0 auto; padding: 0 15px; }

/* --- DASHBOARD --- */
.dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
@media (max-width: 800px) { .dashboard-grid { grid-template-columns: 1fr; } }

.control-panel { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e0e0e0; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
.panel-title { font-size: 18px; font-weight: 700; margin-bottom: 15px; color: #d11a2a; border-bottom: 2px solid #f5f5f5; padding-bottom: 10px; }

.action-btn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.big-btn {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 15px; background: #fff; border: 2px solid #e0e0e0; border-radius: 10px;
    font-weight: 600; color: #444; cursor: pointer; transition: all 0.2s; text-align: center;
    min-height: 90px;
}
.big-btn span { font-size: 26px; margin-bottom: 5px; display: block; }
.big-btn:hover { border-color: #d11a2a; color: #d11a2a; transform: translateY(-2px); background: #fff5f5; }
.big-btn.primary { background: #d11a2a; color: #fff; border-color: #d11a2a; }
.big-btn.primary:hover { background: #b0121e; }

/* --- TLAČÍTKA A FORMULÁŘE --- */
.btn { display: block; width: 100%; border: none; background: #d11a2a; color: #fff; border-radius: 8px; padding: 12px; font-size: 16px; font-weight: 600; cursor: pointer; text-align: center; }
.btn:active { transform: scale(0.98); }
.btn-secondary { background: #eee; color: #333; border: 1px solid #ccc; }

.form-group { margin-bottom: 15px; }
.form-label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; font-size: 14px; }
input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; font-size: 16px; background: #fff; }
input:focus, select:focus { border-color: #d11a2a; outline: none; }

/* --- MODAL --- */
.modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 15px; }
.modal-content { background: #fff; width: 100%; max-width: 500px; max-height: 90vh; border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
.modal-content.large { max-width: 700px; }
.modal-header { padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fff; }
.modal-title { font-size: 18px; font-weight: 700; margin: 0; color: #333; }
.close-btn { font-size: 24px; color: #aaa; cursor: pointer; padding: 0 10px; }
.close-btn:hover { color: #d11a2a; }
.modal-body { padding: 20px; overflow-y: auto; flex-grow: 1; }
#modalPlay .modal-body, #modalManage .modal-body { overflow-y: visible; padding-bottom: 100px; }
.modal-footer { padding: 15px; border-top: 1px solid #eee; background: #f9f9f9; display: flex; justify-content: flex-end; gap: 10px; }

/* --- TABULKA --- */
.table-wrapper { overflow-x: auto; border-radius: 8px; border: 1px solid #eee; margin-top: 15px; }
table { width: 100%; border-collapse: collapse; background: #fff; min-width: 500px; }
th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #eee; font-size: 15px; }
th { background: #f8f8f8; font-weight: 600; color: #555; cursor: pointer; white-space: nowrap; user-select: none; }
th:hover { background: #eee; color: #d11a2a; }
.row-red { background: #fff5f5; }
th span.arrow { font-size: 0.8em; color: #bbb; margin-left: 5px; }
th.asc span.arrow { color: #d11a2a; content: '▲'; }
th.desc span.arrow { color: #d11a2a; content: '▼'; }

/* --- HISTORIE LIST --- */
.history-list { list-style: none; padding: 0; margin: 0; }
.history-item { 
    display: flex; justify-content: space-between; align-items: center; 
    padding: 12px 10px; border-bottom: 1px solid #f0f0f0; 
}
.history-item:hover { background: #fafafa; }
.h-date { font-weight: bold; color: #d11a2a; width: 100px; }
.h-name { flex-grow: 1; font-weight: 500; }
.h-actions { display: flex; gap: 15px; }
.icon-btn { cursor: pointer; font-size: 18px; color: #999; border: none; background: none; padding: 0; }
.icon-btn:hover { color: #d11a2a; transform: scale(1.2); }

/* --- AUTOCOMPLETE --- */
.autocomplete-wrapper { position: relative; width: 100%; }
.suggestions-box { 
    position: absolute; top: 100%; left: 0; right: 0; 
    background: #fff; border: 1px solid #d2d2d7; border-top: none; 
    border-radius: 0 0 8px 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); 
    z-index: 100; max-height: 200px; overflow-y: auto; display: none; 
}
.suggestion-item { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #f5f5f5; }
.suggestion-item:hover { background: #f9f9f9; color: #d11a2a; }

/* --- FILTRY --- */
.filter-box { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
.filter-box input, .filter-box select { flex: 1; min-width: 120px; }

/* --- EDITACE HISTORIE (v modalu) --- */
.history-section { margin-top: 20px; border-top: 2px solid #eee; padding-top: 15px; }
.history-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.history-chip { 
    display: flex; align-items: center; gap: 8px; background: #f0f0f0; border: 1px solid #ddd; 
    border-radius: 20px; padding: 6px 12px; font-size: 13px; 
}
.chip-date { cursor: pointer; border-bottom: 1px dotted #999; }
.chip-remove { color: #ff4d4d; font-weight: bold; cursor: pointer; font-size: 16px; margin-left: 5px; }
.chip-remove:hover { color: #d00; transform: scale(1.2); }
.add-history-row { display: flex; gap: 10px; margin-bottom: 10px; }
.add-history-row input { flex: 1; }
.add-history-row button { width: auto; white-space: nowrap; }

</style>
</head>
<body>

<div class="site-header">
    <div class="wrap">
        <div class="title" style="font-size: 22px; font-weight: bold; color: white;">🎹 Knihovna písní</div>
    </div>
</div>

<div class="wrap">

    <div class="dashboard-grid">
        <div class="control-panel">
            <div class="panel-title">🎛️ Menu</div>
            <div class="action-btn-grid">
                <div class="big-btn" onclick="openModal('modalGenerator')"><span>🎲</span> Generátor</div>
                <div class="big-btn" onclick="renderGlobalHistory()"><span>📅</span> Historie</div>
                <div class="big-btn" onclick="openAddModal()"><span>✨</span> Přidat</div>
                <div class="big-btn primary" onclick="openModal('modalPlay')"><span>➕</span> Zapsat</div>
            </div>
        </div>
        <div class="control-panel">
            <div class="panel-title">📊 Top 5</div>
            <div style="height:200px; position:relative;">
                <canvas id="chartTop"></canvas>
            </div>
        </div>
    </div>

    <div class="control-panel">
        <div class="panel-title" style="border:none; margin-bottom:10px;">📚 Seznam (<?= count($songsData) ?>)</div>
        
        <div class="filter-box">
            <input type="text" id="tableSearch" placeholder="Hledat...">
            <select id="tableCat"><option value="">Všechny kategorie</option><?php foreach ($categories as $c) echo "<option>$c</option>"; ?></select>
            <select id="tableTag"><option value="">Všechny tagy</option><?php foreach ($tags as $t) echo "<option>$t</option>"; ?></select>
        </div>

        <div class="table-wrapper">
            <table id="songsTable">
                <thead>
                    <tr>
                        <th onclick="sortTable(0)">Název <span class="arrow">↕</span></th>
                        <th onclick="sortTable(1)">Kategorie <span class="arrow">↕</span></th>
                        <th onclick="sortTable(2)">Počet <span class="arrow">↕</span></th>
                        <th onclick="sortTable(3)">Naposledy <span class="arrow">↕</span></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($songsData as $s): 
                    $last = $s["last"] ?? "";
                    if (isset($s["history"]) && is_array($s["history"]) && count($s["history"]) > 0) $last = $s["history"][0];
                    
                    $days = 9999;
                    if($last) $days = (time() - strtotime($last)) / 86400;
                    $cls = ($days > 180 && $last) ? "row-red" : "";
                    
                    $safeData = htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8');
                ?>
                <tr class="songRow <?= $cls ?>">
                    <td><b><?= htmlspecialchars($s["name"]) ?></b><br><small style="color:#777"><?= htmlspecialchars($s["author"]) ?></small></td>
                    <td><?= htmlspecialchars($s["category"]) ?></td>
                    <td style="text-align:center"><b><?= $s["count"] ?></b></td>
                    <td style="font-family:monospace"><?= $last ? date("d.m.y", strtotime($last)) : "-" ?></td>
                    <td style="text-align:right">
                        <button onclick='openEditModal(<?= $safeData ?>)' style="background:none; border:none; font-size:20px; cursor:pointer;">✏️</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<datalist id="listCategories">
    <?php foreach ($categories as $c) echo "<option value='$c'>"; ?>
</datalist>
<datalist id="listTempos">
    <option value="rychlá">
    <option value="střední">
    <option value="pomalá">
    <?php foreach ($tempos as $t) if(!in_array($t, ['rychlá','střední','pomalá'])) echo "<option value='$t'>"; ?>
</datalist>

<div id="modalManage" class="modal">
    <div class="modal-content large">
        <div class="modal-header"><div class="modal-title" id="manageTitle">Píseň</div><span class="close-btn" onclick="closeModal('modalManage')">&times;</span></div>
        <div class="modal-body">
            <form id="manageForm">
                <input type="hidden" name="action" id="manageAction" value="add">
                <input type="hidden" name="original_name" id="manageOriginalName">
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group"><label class="form-label">Název *</label><input type="text" name="name" id="inpName" required></div>
                    <div class="form-group"><label class="form-label">Autor</label><input type="text" name="author" id="inpAuthor"></div>
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label class="form-label">Kategorie</label>
                        <input type="text" name="category" id="inpCategory" list="listCategories" placeholder="Vyber nebo napiš...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tempo</label>
                        <input type="text" name="tempo" id="inpTempo" list="listTempos" placeholder="Vyber nebo napiš...">
                    </div>
                </div>
                <div class="form-group"><label class="form-label">Tagy</label><input type="text" name="tags" id="inpTags" placeholder="tag1, tag2..."></div>

                <div id="editHistorySection" class="history-section" style="display:none;">
                    <label class="form-label">📅 Historie hraní</label>
                    <div class="add-history-row">
                        <input type="date" id="newHistoryDate">
                        <button type="button" class="btn btn-secondary" onclick="addHistoryDate()">+ Přidat</button>
                    </div>
                    <div id="historyChips" class="history-chips"></div>
                </div>

                <div style="margin-top:20px; display:flex; gap:10px;">
                    <button type="submit" class="btn" id="btnManageSave">Uložit</button>
                    <button type="button" class="btn" id="btnDelete" onclick="deleteSong()" style="background:#fff; color:red; border:1px solid red; display:none;">Smazat píseň</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modalGenerator" class="modal">
    <div class="modal-content">
        <div class="modal-header"><div class="modal-title">🎲 Generátor</div><span class="close-btn" onclick="closeModal('modalGenerator')">&times;</span></div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Kategorie</label>
                <select id="genCategory">
                    <option value="">-- Vše --</option>
                    <?php foreach ($categories as $c) echo "<option value='$c'>$c</option>"; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Tempo</label>
                <select id="genTempo">
                    <option value="">-- Vše --</option>
                    <?php foreach ($tempos as $t) echo "<option value='$t'>$t</option>"; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Tag</label>
                <select id="genTag">
                    <option value="">-- Vše --</option>
                    <?php foreach ($tags as $t) echo "<option value='$t'>$t</option>"; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Nehráno (měsíců)</label><input type="number" id="genMonths" value="0"></div>
            <div class="form-group"><label class="form-label">Počet písní</label><input type="number" id="genCount" value="3"></div>
            <button class="btn" onclick="generatePlaylist()">Generovat</button>
        </div>
    </div>
</div>

<div id="modalResult" class="modal">
    <div class="modal-content">
        <div class="modal-header"><div class="modal-title">Výsledek</div><span class="close-btn" onclick="closeModal('modalResult')">&times;</span></div>
        <div class="modal-body" id="resultContainer"></div>
        <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('modalResult')">Zavřít</button></div>
    </div>
</div>

<div id="modalPlay" class="modal">
    <div class="modal-content">
        <div class="modal-header"><div class="modal-title">Zapsat hraní</div><span class="close-btn" onclick="closeModal('modalPlay')">&times;</span></div>
        <div class="modal-body">
            <form id="playForm">
                <div class="form-group">
                    <label class="form-label">Vyber píseň</label>
                    <div class="autocomplete-wrapper">
                        <input type="text" id="songSearchInput" placeholder="Začni psát..." autocomplete="off">
                        <input type="hidden" name="song" id="songHiddenInput">
                        <div id="suggestionsBox" class="suggestions-box"></div>
                    </div>
                </div>
                <div class="form-group"><label class="form-label">Datum</label><input type="date" name="date" value="<?= date("Y-m-d") ?>" required></div>
                <button type="submit" class="btn" id="btnSavePlay">Uložit</button>
            </form>
        </div>
    </div>
</div>

<div id="modalHistory" class="modal">
    <div class="modal-content large">
        <div class="modal-header"><div class="modal-title">📅 Celková historie</div><span class="close-btn" onclick="closeModal('modalHistory')">&times;</span></div>
        <div class="modal-body">
            <div style="font-size:14px; color:#666; margin-bottom:15px; background:#f9f9f9; padding:10px; border-radius:8px;">
                Zde můžeš upravovat historii. Klikni na <span style="font-weight:bold">✏️</span> pro změnu data nebo <span style="font-weight:bold; color:red">&times;</span> pro smazání záznamu.
            </div>
            <div id="globalHistoryContainer"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('modalHistory')">Zavřít</button></div>
    </div>
</div>

<script>
const songsDB = <?= json_encode($songsData) ?>;
let currentEditingSong = null;

// --- MODAL UTILS ---
function openModal(id) { document.getElementById(id).style.display = "flex"; }
function closeModal(id) { document.getElementById(id).style.display = "none"; }
window.onclick = function(e) { if(e.target.classList.contains('modal')) e.target.style.display="none"; }

// --- SPRÁVA PÍSNĚ ---
function openAddModal() {
    currentEditingSong = null;
    document.getElementById('manageTitle').innerText = "✨ Přidat píseň";
    document.getElementById('manageAction').value = "add";
    document.getElementById('manageForm').reset();
    document.getElementById('btnDelete').style.display = "none";
    document.getElementById('editHistorySection').style.display = "none";
    openModal('modalManage');
}

function openEditModal(song) {
    currentEditingSong = song;
    document.getElementById('manageTitle').innerText = "✏️ Upravit: " + song.name;
    document.getElementById('manageAction').value = "edit";
    document.getElementById('manageOriginalName').value = song.name;
    
    document.getElementById('inpName').value = song.name;
    document.getElementById('inpAuthor').value = song.author;
    document.getElementById('inpCategory').value = song.category;
    document.getElementById('inpTempo').value = song.tempo;
    document.getElementById('inpTags').value = song.tags;
    
    document.getElementById('btnDelete').style.display = "block";
    document.getElementById('editHistorySection').style.display = "block";
    
    renderEditHistory(song.history);
    openModal('modalManage');
}

document.getElementById('manageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnManageSave');
    const formData = new FormData(this);
    btn.disabled = true; btn.innerText = "Ukládám...";
    
    fetch('api_manage_song.php', { method: 'POST', body: formData }).then(r=>r.json()).then(res => {
        if(res.ok) location.reload();
        else { alert(res.error); btn.disabled=false; btn.innerText="Uložit"; }
    });
});

function deleteSong() {
    if(!confirm("Smazat tuto píseň?")) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('original_name', document.getElementById('manageOriginalName').value);
    
    fetch('api_manage_song.php', { method: 'POST', body: formData }).then(r=>r.json()).then(res => {
        if(res.ok) location.reload();
        else alert(res.error);
    });
}

// --- HISTORIE (CHIPS V MODALU) ---
function renderEditHistory(history) {
    const container = document.getElementById('historyChips');
    container.innerHTML = "";
    if(!history || history.length === 0) { container.innerHTML = "<i style='color:#999'>Žádná historie</i>"; return; }
    
    history.sort((a,b) => new Date(b) - new Date(a));

    history.forEach(date => {
        const d = new Date(date).toLocaleDateString('cs-CZ');
        const chip = document.createElement('div');
        chip.className = "history-chip";
        chip.innerHTML = `<span class="chip-date" onclick="editHistoryDate('${date}')" title="Upravit datum">${d}</span>
                          <span class="chip-remove" onclick="removeHistoryDate('${date}')" title="Smazat">&times;</span>`;
        container.appendChild(chip);
    });
}

function addHistoryDate() {
    const date = document.getElementById('newHistoryDate').value;
    if(!date) return alert("Vyber datum!");
    callHistoryApi('add', date);
    document.getElementById('newHistoryDate').value = "";
}

function removeHistoryDate(date) {
    if(confirm("Smazat datum " + date + "?")) callHistoryApi('remove', date);
}

function editHistoryDate(oldDate) {
    const newDate = prompt("Nové datum (YYYY-MM-DD):", oldDate);
    if(newDate && newDate !== oldDate) {
        callHistoryApi('update', newDate, true, oldDate); 
    }
}

function callHistoryApi(action, date, reload = true, oldDate = null) {
    const formData = new FormData();
    formData.append('song', currentEditingSong.name);
    formData.append('action', action);
    formData.append('date', date);
    if(oldDate) formData.append('old_date', oldDate);
    
    fetch('api_manage_history.php', { method: 'POST', body: formData }).then(r=>r.json()).then(res => {
        if(res.ok) {
            currentEditingSong = res.song;
            if(reload) renderEditHistory(currentEditingSong.history);
        } else alert("Chyba: " + res.error);
    });
}

// --- GENERÁTOR ---
function generatePlaylist() {
    const cat = document.getElementById('genCategory').value;
    const tempo = document.getElementById('genTempo').value;
    const tag = document.getElementById('genTag').value;
    const months = parseInt(document.getElementById('genMonths').value);
    const count = parseInt(document.getElementById('genCount').value);

    let pool = songsDB.filter(s => {
        // Filtry (Pokud je prázdné, bere se vše)
        if (cat && s.category !== cat) return false;
        if (tempo && s.tempo !== tempo) return false;
        if (tag && (!s.tags || !s.tags.includes(tag))) return false;
        
        let lastDateStr = s.last;
        if (s.history && s.history.length > 0) lastDateStr = s.history[0];
        
        if (months > 0 && lastDateStr) {
            const cutoff = new Date(); cutoff.setMonth(cutoff.getMonth() - months);
            if (new Date(lastDateStr) > cutoff) return false;
        }
        return true;
    });

    pool.sort(() => Math.random() - 0.5);
    let result = pool.slice(0, count);

    closeModal('modalGenerator');
    
    const container = document.getElementById('resultContainer');
    if(result.length === 0) {
        container.innerHTML = "<p style='text-align:center; padding:20px;'>Nic nenalezeno.</p>";
    } else {
        let html = "<ul style='list-style:none; padding:0;'>";
        result.forEach(s => {
            html += `<li style='padding:10px; border-bottom:1px solid #eee;'>
                        <b>${s.name}</b> <span style='color:#777'>(${s.tempo})</span><br>
                        <small>${s.author}</small>
                     </li>`;
        });
        html += "</ul>";
        container.innerHTML = html;
    }
    openModal('modalResult');
}

// --- ZÁPIS (AUTOCOMPLETE) ---
const searchInput = document.getElementById('songSearchInput');
const suggestionsBox = document.getElementById('suggestionsBox');
if(searchInput) {
    searchInput.addEventListener('input', function() {
        const val = this.value.toLowerCase();
        document.getElementById('songHiddenInput').value = "";
        if(val.length < 2) { suggestionsBox.style.display = 'none'; return; }
        
        const matches = songsDB.filter(s => s.name.toLowerCase().includes(val) || s.author.toLowerCase().includes(val)).slice(0, 10);
        
        suggestionsBox.innerHTML = '';
        if(matches.length > 0) {
            suggestionsBox.style.display = 'block';
            matches.forEach(s => {
                const div = document.createElement('div');
                div.className = 'suggestion-item';
                div.innerHTML = `<b>${s.name}</b> <span style='color:#888;font-size:12px'>${s.author}</span>`;
                div.onclick = () => {
                    searchInput.value = s.name;
                    document.getElementById('songHiddenInput').value = s.name;
                    suggestionsBox.style.display = 'none';
                };
                suggestionsBox.appendChild(div);
            });
        } else {
            suggestionsBox.style.display = 'none';
        }
    });
}

document.getElementById('playForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSavePlay');
    const formData = new FormData(this);
    if(!formData.get('song')) return alert("Vyber píseň!");
    
    btn.disabled = true; btn.innerText = "Ukládám...";
    fetch('api_local.php', { method: 'POST', body: formData }).then(r=>r.json()).then(res => {
        if(res.ok) location.reload();
        else { alert("Chyba: " + res.error); btn.disabled=false; btn.innerText="Uložit"; }
    });
});

// --- GLOBÁLNÍ HISTORIE (S EDITACÍ) ---
function renderGlobalHistory() {
    const container = document.getElementById('globalHistoryContainer');
    let all = [];
    songsDB.forEach(s => {
        if(s.history && s.history.length > 0) {
            s.history.forEach(d => all.push({date: d, name: s.name}));
        } else if (s.last) {
            all.push({date: s.last, name: s.name});
        }
    });
    
    all.sort((a,b) => new Date(b.date) - new Date(a.date));
    
    let html = "<ul class='history-list'>";
    all.slice(0, 100).forEach(item => { 
        const d = new Date(item.date).toLocaleDateString('cs-CZ');
        html += `<li class="history-item">
                    <div class="h-date">${d}</div>
                    <div class="h-name">${item.name}</div>
                    <div class="h-actions">
                        <button class="icon-btn" onclick="editHistoryItem('${item.name}', '${item.date}')" title="Změnit datum">✏️</button>
                        <button class="icon-btn" onclick="deleteHistoryItem('${item.name}', '${item.date}')" title="Smazat záznam" style="color:#ff4d4d">&times;</button>
                    </div>
                 </li>`;
    });
    html += "</ul>";
    container.innerHTML = html || "<p style='text-align:center'>Prázdné.</p>";
    openModal('modalHistory');
}

function editHistoryItem(songName, oldDate) {
    const newDate = prompt("Nové datum (YYYY-MM-DD):", oldDate);
    if(newDate && newDate !== oldDate) {
        // Nejprve smažeme, pak přidáme (API neumí rename, takže update přes remove+add)
        callHistoryApiGlobal(songName, 'update', newDate, oldDate); 
    }
}

function deleteHistoryItem(songName, date) {
    if(confirm("Opravdu smazat záznam " + songName + " (" + date + ")?")) {
        callHistoryApiGlobal(songName, 'remove', date);
    }
}

function callHistoryApiGlobal(songName, action, date, oldDate = null) {
    const formData = new FormData();
    formData.append('song', songName);
    formData.append('action', action);
    formData.append('date', date);
    if(oldDate) formData.append('old_date', oldDate);
    
    fetch('api_manage_history.php', { method: 'POST', body: formData }).then(r=>r.json()).then(res => {
        if(res.ok) location.reload();
        else alert("Chyba: " + res.error);
    });
}

// --- FILTRY ---
const tRows = document.querySelectorAll('.songRow');
function filterTable() {
    const q = document.getElementById('tableSearch').value.toLowerCase();
    const cat = document.getElementById('tableCat').value;
    const tag = document.getElementById('tableTag').value;
    
    tRows.forEach(row => {
        const txt = row.innerText.toLowerCase();
        const rCat = row.children[1].innerText;
        let show = true;
        if(cat && rCat !== cat) show = false;
        if(q && !txt.includes(q)) show = false;
        row.style.display = show ? "" : "none";
    });
}
document.getElementById('tableSearch').addEventListener('input', filterTable);
document.getElementById('tableCat').addEventListener('change', filterTable);
document.getElementById('tableTag').addEventListener('change', filterTable);

// --- OPRAVENÉ ŘAZENÍ (Robustní pro data) ---
let sortDir = {};
function sortTable(n) {
    const table = document.getElementById("songsTable");
    const tbody = table.querySelector("tbody");
    const rows = Array.from(tbody.rows);
    
    document.querySelectorAll("th span.arrow").forEach(sp => sp.innerHTML = "↕");
    
    sortDir[n] = !sortDir[n];
    const dir = sortDir[n] ? 1 : -1;
    
    const th = table.rows[0].cells[n];
    th.querySelector("span.arrow").innerHTML = dir === 1 ? "▲" : "▼";
    
    rows.sort((a, b) => {
        const x = a.cells[n].innerText.trim();
        const y = b.cells[n].innerText.trim();
        
        // 1. Čísla (Počet - index 2)
        if(n === 2) {
            return dir * (parseInt(x) - parseInt(y));
        }
        // 2. Data (Naposledy - index 3)
        if(n === 3) {
            // Funkce pro převod českého data 30.11.2025 na timestamp
            const getTs = (s) => {
                if(s === "-" || s === "") return 0;
                // Rozdělit podle mezer a teček
                const p = s.split(/[\. ]+/); 
                // p[0]=den, p[1]=mesic, p[2]=rok
                if(p.length >= 3) return new Date(p[2], p[1]-1, p[0]).getTime();
                return 0;
            };
            return dir * (getTs(x) - getTs(y));
        }
        // 3. Text (Ostatní)
        return dir * x.localeCompare(y, 'cs');
    });
    
    rows.forEach(r => tbody.appendChild(r));
}

// Chart
new Chart(document.getElementById('chartTop'), {
    type:"doughnut",
    data:{
        labels: <?= json_encode(array_column(array_slice($topSongs,0,5), "name")) ?>,
        datasets:[{ data: <?= json_encode(array_column(array_slice($topSongs,0,5), "count")) ?>, backgroundColor: ['#d11a2a', '#e84a5f', '#ff847c', '#fecea8', '#99b898'] }]
    },
    options:{ responsive:true, maintainAspectRatio: false, plugins:{ legend:{position:'right'} } }
});
</script>

</body>
</html>
