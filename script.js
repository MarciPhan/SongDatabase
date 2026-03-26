/* SOUBOR: script.js */

// Načtení dat z PHP (window.serverData definováno v index.php)
var songsDB = window.serverData.songs;
var UPLOAD_CONFIG = window.serverData.uploadConfig;
let currentEditingSong = null;

// Proměnné pro Chart.js instance (abychom je mohli zničit před překreslením)
let chartInstanceTop = null;
let chartInstanceFlop = null;

// Historie pro funkci Zpět (max 5 stavů)
let undoStack = [];
function saveUndoState() {
    undoStack.push(JSON.stringify(songsDB));
    if (undoStack.length > 5) undoStack.shift();
    updateUndoButton();
}
function undo() {
    if (undoStack.length === 0) return;
    const prevState = JSON.parse(undoStack.pop());
    // Hluboké nahrazení obsahu songsDB
    songsDB.length = 0;
    prevState.forEach(s => songsDB.push(s));
    renderTable();
    updateUndoButton();
    showToast("Akce vrácena zpět");
    
    // Synchronizace s barebones save (přepíšeme vše)
    const formData = new FormData();
    formData.append('action', 'overwrite_all');
    formData.append('data', JSON.stringify(songsDB));
    fetch('api_manage_song.php', { method: 'POST', body: formData });
}
function updateUndoButton() {
    const btn = document.getElementById('undoBtn');
    if (btn) btn.style.display = undoStack.length > 0 ? "inline-block" : "none";
}

// ==========================================
// 1. UI POMOCNÉ FUNKCE (Toast, Confirm, Prompt)
// ==========================================

function showToast(msg, isError = false) {
    const t = document.getElementById("toast");
    t.innerText = msg;
    t.className = isError ? "error show" : "show";
    setTimeout(() => { t.className = t.className.replace("show", ""); }, 3000);
}

function uiConfirm(text, title="Potvrzení") {
    return new Promise((resolve) => {
        const modal = document.getElementById('uiConfirmModal');
        document.getElementById('uiConfirmTitle').innerText = title;
        document.getElementById('uiConfirmText').innerText = text;
        const yesBtn = document.getElementById('uiConfirmYes');
        const noBtn = document.getElementById('uiConfirmNo');
        
        // Klonování pro odstranění starých listenerů
        yesBtn.replaceWith(yesBtn.cloneNode(true));
        noBtn.replaceWith(noBtn.cloneNode(true));
        
        document.getElementById('uiConfirmYes').onclick = () => { modal.style.display = 'none'; resolve(true); };
        document.getElementById('uiConfirmNo').onclick = () => { modal.style.display = 'none'; resolve(false); };
        modal.style.display = "flex";
    });
}

function uiPrompt(text, defaultValue = "", title="Vstup") {
    return new Promise((resolve) => {
        const modal = document.getElementById('uiPromptModal');
        const input = document.getElementById('uiPromptInput');
        document.getElementById('uiPromptTitle').innerText = title;
        document.getElementById('uiPromptText').innerText = text;
        input.value = defaultValue;
        
        const okBtn = document.getElementById('uiPromptOk');
        const cancelBtn = document.getElementById('uiPromptCancel');
        
        okBtn.replaceWith(okBtn.cloneNode(true));
        cancelBtn.replaceWith(cancelBtn.cloneNode(true));
        
        document.getElementById('uiPromptOk').onclick = () => { modal.style.display = 'none'; resolve(input.value); };
        document.getElementById('uiPromptCancel').onclick = () => { modal.style.display = 'none'; resolve(null); };
        
        modal.style.display = "flex";
        input.focus();
    });
}

function uiPickDate(dates, text, title = "Výběr data", showManual = true) {
    return new Promise(resolve => {
        const modal = document.getElementById('uiHistoryModal');
        document.getElementById('uiHistoryTitle').innerText = title;
        document.getElementById('uiHistoryText').innerText = text;
        const list = document.getElementById('uiHistoryList');
        const manual = document.getElementById('uiHistoryManual');
        const input = document.getElementById('uiHistoryInput');
        
        list.innerHTML = "";
        manual.style.display = showManual ? "block" : "none";
        input.value = "";
        
        if (dates.length === 0) {
            list.innerHTML = "<em style='color:#888; font-size:13px;'>Žádná historie k výběru.</em>";
            manual.style.display = "block"; // Vždy ukázat manuální, když není historie
        } else {
            dates.forEach(d => {
                const chip = document.createElement('div');
                chip.className = "history-chip";
                chip.style.cursor = "pointer";
                chip.innerHTML = `<span>${d.split('-').reverse().join('.')}</span>`;
                chip.onclick = () => {
                    modal.style.display = "none";
                    resolve(d);
                };
                list.appendChild(chip);
            });
        }
        
        const okBtn = document.getElementById('uiHistoryOk');
        const cancelBtn = document.getElementById('uiHistoryCancel');
        
        okBtn.replaceWith(okBtn.cloneNode(true));
        cancelBtn.replaceWith(cancelBtn.cloneNode(true));
        
        document.getElementById('uiHistoryOk').onclick = () => {
            const val = input.value ? toIsoDate(input.value) : null;
            modal.style.display = "none";
            resolve(val);
        };
        document.getElementById('uiHistoryCancel').onclick = () => {
            modal.style.display = "none";
            resolve(null);
        };
        
        modal.style.display = "flex";
    });
}

// Pomocníci pro inteligentní datum (neděle) a formátování
function getTypicalDate() {
    const d = new Date();
    const day = d.getDay(); // 0 = Neděle, 1 = Pondělí...
    if (day !== 0) {
        d.setDate(d.getDate() + (7 - day));
    }
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const date = String(d.getDate()).padStart(2, '0');
    return `${date}.${month}.${year}`; // Vrátíme v českém formátu
}

function toIsoDate(str) {
    if (!str) return "";
    str = str.trim();
    // Podpora formátu D.M.RRRR nebo DD.MM.RRRR
    const match = str.match(/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/);
    if (match) {
        let d = match[1].padStart(2, '0');
        let m = match[2].padStart(2, '0');
        let y = match[3];
        if (y.length === 2) y = "20" + y;
        return `${y}-${m}-${d}`;
    }
    return str; 
}

function fromIsoDate(iso) {
    if (!iso || iso === "-") return "-";
    const parts = iso.split('-');
    if (parts.length !== 3) return iso;
    return `${parts[2]}.${parts[1]}.${parts[0]}`;
}

function getRelativeTime(dateStr) {
    if (!dateStr) return "-";
    const now = new Date();
    const date = new Date(dateStr);
    
    // Vynulujeme čas pro porovnání dnů
    const d1 = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const d2 = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    
    const diffTime = d1 - d2;
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) return "Dnes";
    if (diffDays === 1) return "Včera";
    if (diffDays === -1) return "Zítra";
    
    if (diffDays > 0) {
        if (diffDays < 7) return "Před " + diffDays + " dny";
        if (diffDays < 30) return "Před " + Math.floor(diffDays / 7) + " týdny";
    } else {
        const absDays = Math.abs(diffDays);
        if (absDays < 7) return "Za " + absDays + " dní";
        return "V budoucnu (" + absDays + " dní)";
    }
    
    return date.toLocaleDateString('cs-CZ', { day: 'numeric', month: 'numeric', year: '2-digit' });
}

// ==========================================
// 2. MODAL LOGIC (Otevírání/Zavírání)
// ==========================================

function openModal(id) { document.getElementById(id).style.display = "flex"; }
function closeModal(id) { document.getElementById(id).style.display = "none"; }

function openPlayModal() {
    document.getElementById('playForm').reset();
    document.getElementById('playDateInput').value = getTypicalDate();
    openModal('modalPlay');
    setTimeout(() => document.getElementById('songSearchInput').focus(), 100);
}

// Zavření modalu kliknutím na pozadí
window.onclick = function(e) { 
    if(e.target.classList.contains('modal')) e.target.style.display="none"; 
}

// ==========================================
// 3. STATISTIKY (HUDEBNÍ SPEKTRUM)
// ==========================================

function openStats() {
    openModal('modalStats');
    
    // Zničení starých grafů, pokud existují (Chart.js to vyžaduje)
    if (chartInstanceTop) chartInstanceTop.destroy();
    if (chartInstanceFlop) chartInstanceFlop.destroy();

    // Data připravená v PHP
    const statsData = window.serverData.stats;

    // 1. Graf TOP 20 (Nejhranější)
    const ctxTop = document.getElementById('topChart').getContext('2d');
    chartInstanceTop = new Chart(ctxTop, {
        type: 'bar',
        data: {
            labels: statsData.topLabels,
            datasets: [{
                label: 'Počet hraní',
                data: statsData.topData,
                backgroundColor: 'rgba(209, 26, 42, 0.7)', // Červená
                borderColor: 'rgba(209, 26, 42, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y', // Horizontální graf
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } }
        }
    });

    // 2. Graf FLOP 20 (Rarity)
    const ctxFlop = document.getElementById('flopChart').getContext('2d');
    chartInstanceFlop = new Chart(ctxFlop, {
        type: 'bar',
        data: {
            labels: statsData.flopLabels,
            datasets: [{
                label: 'Počet hraní',
                data: statsData.flopData,
                backgroundColor: 'rgba(100, 100, 100, 0.5)', // Šedá
                borderColor: 'rgba(100, 100, 100, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y', // Horizontální graf
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
}

// ==========================================
// 4. SPRÁVA PÍSNĚ (ADD / EDIT / DELETE)
// ==========================================

function openAddModal() {
    currentEditingSong = null;
    document.getElementById('manageTitle').innerText = "✨ Přidat píseň";
    document.getElementById('manageAction').value = "add";
    document.getElementById('manageForm').reset();
    
    // Skrytí prvků, které jsou jen pro editaci
    document.getElementById('btnDelete').style.display = "none";
    document.getElementById('editHistorySection').style.display = "none";
    document.getElementById('newHistoryDate').value = getTypicalDate();
    
    openModal('modalManage');
}

function openEditModal(song) {
    currentEditingSong = song;
    document.getElementById('manageTitle').innerText = "✏️ Upravit";
    document.getElementById('manageAction').value = "edit";
    document.getElementById('manageOriginalName').value = song.name;
    
    // Vyplnění formuláře
    document.getElementById('inpName').value = song.name;
    document.getElementById('inpAuthor').value = song.author;
    document.getElementById('inpCategory').value = song.category;
    document.getElementById('inpTempo').value = song.tempo; 
    document.getElementById('inpTags').value = song.tags;
    
    // Zobrazení prvků pro editaci
    document.getElementById('btnDelete').style.display = "block";
    document.getElementById('editHistorySection').style.display = "block";
    document.getElementById('attachmentSection').style.display = "block";
    document.getElementById('newHistoryDate').value = getTypicalDate();
    
    // Status příloh
    document.getElementById('pdfStatus').innerText = song.pdf ? "✅ Nahráno" : "❌ Chybí";
    document.getElementById('openlpStatus').innerText = song.openlp ? "✅ Nahráno" : "❌ Chybí";
    
    renderEditHistory(song.history);
    openModal('modalManage');
}

// Odeslání formuláře (Přidat / Upravit)
document.getElementById('manageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    saveUndoState();
    const btn = document.getElementById('btnManageSave');
    const formData = new FormData(this);
    
    btn.disabled = true; 
    btn.innerText = "Ukládám...";

    // Optimistická aktualizace
    const action = formData.get('action');
    const name = formData.get('name');
    const author = formData.get('author');
    const cat = formData.get('category');
    const tempo = formData.get('tempo');
    const tags = formData.get('tags');
    const oldName = formData.get('original_name');

    if (action === "add") {
        songsDB.push({ name, author, category: cat, tempo, tags, count: 0, last: "", history: [] });
    } else {
        const s = songsDB.find(x => x.name === oldName);
        if (s) {
            s.name = name; s.author = author; s.category = cat; s.tempo = tempo; s.tags = tags;
        }
    }
    
    closeModal('modalManage');
    renderTable();
    showToast(action === "add" ? "Přidávám..." : "Ukládám...");

    fetch('api_manage_song.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if(res.ok) {
                showToast("Uloženo");
            }
            else { 
                showToast(res.error, true);
                location.reload(); 
            }
            btn.disabled = false;
            btn.innerText = "Uložit";
        })
        .catch(err => { 
            showToast("Chyba sítě", true); 
            btn.disabled = false; 
            btn.innerText = "Uložit";
            location.reload();
        });
});

// Smazání písně
async function deleteSong() {
    if(! await uiConfirm("Opravdu smazat tuto píseň?", "Smazání")) return;
    saveUndoState();
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('original_name', document.getElementById('manageOriginalName').value);
    
    const name = formData.get('original_name');
    const idx = songsDB.findIndex(s => s.name === name);
    let backup = null;
    if (idx > -1) {
        backup = songsDB[idx];
        songsDB.splice(idx, 1);
    }
    
    closeModal('modalManage');
    renderTable();
    showToast("Mažu...");
    
    fetch('api_manage_song.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if(res.ok) showToast("Smazáno");
            else {
                showToast(res.error, true);
                location.reload();
            }
        })
        .catch(err => location.reload());
}

// ==========================================
// 5. HISTORIE (V EDITACI PÍSNĚ)
// ==========================================

function renderEditHistory(history) {
    const container = document.getElementById('historyChips');
    container.innerHTML = "";
    
    if(!history || history.length === 0) { 
        container.innerHTML = "<i style='color:#999;font-size:13px;'>Žádná historie</i>"; 
        return; 
    }
    
    // Seřadit sestupně
    history.sort((a,b) => new Date(b) - new Date(a));
    
    history.forEach(date => {
        const d = fromIsoDate(date);
        const chip = document.createElement('div');
        chip.className = "history-chip";
        chip.innerHTML = `
            <span class="chip-date" onclick="editHistoryDate('${date}')" title="Upravit">${d}</span>
            <span class="chip-remove" onclick="removeHistoryDate('${date}')" title="Smazat">&times;</span>
        `;
        container.appendChild(chip);
    });
}

function addHistoryDate() {
    const raw = document.getElementById('newHistoryDate').value;
    if(!raw) return showToast("Vyber datum!", true);
    const date = toIsoDate(raw);
    callHistoryApi('add', date);
    document.getElementById('newHistoryDate').value = getTypicalDate();
}

async function removeHistoryDate(date) {
    if(await uiConfirm("Smazat datum " + date + "?")) callHistoryApi('remove', date);
}

async function editHistoryDate(oldDate) {
    const songName = currentEditingSong ? currentEditingSong.name : "píseň";
    const oldD = fromIsoDate(oldDate);
    const newRaw = await uiPrompt(`Nové datum pro "${songName}" (DD.MM.RRRR):`, oldD, "Upravit historii");
    if(newRaw) {
        const newDate = toIsoDate(newRaw);
        if (newDate !== oldDate) {
            callHistoryApi('update', newDate, true, oldDate); 
        }
    }
}

// Volání API pro historii (lokální update jedné písně)
function callHistoryApi(action, date, reload = true, oldDate = null) {
    saveUndoState();
    const formData = new FormData();
    formData.append('song', currentEditingSong.name);
    formData.append('action', action);
    formData.append('date', date);
    if(oldDate) formData.append('old_date', oldDate);
    
    fetch('api_manage_history.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if(res.ok) {
                currentEditingSong = res.song;
                const idx = songsDB.findIndex(s => s.name === res.song.name);
                if (idx > -1) songsDB[idx] = res.song;
                renderEditHistory(res.song.history);
                renderTable();
                showToast("Uloženo");
            } else showToast("Chyba: " + res.error, true);
        });
}

// ==========================================
// 6. ZÁPIS HRANÍ (NAŠEPTÁVAČ)
// ==========================================

const searchInput = document.getElementById('songSearchInput');
const suggestionsBox = document.getElementById('suggestionsBox');

if(searchInput) {
    searchInput.addEventListener('input', function() {
        const val = this.value.toLowerCase();
        document.getElementById('songHiddenInput').value = ""; // Reset hidden ID
        
        if(val.length < 2) { 
            suggestionsBox.style.display = 'none'; 
            return; 
        }
        
        const matches = songsDB.filter(s => 
            s.name.toLowerCase().includes(val) || 
            (s.author && s.author.toLowerCase().includes(val))
        ).slice(0, 10);
        
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

// Odeslání formuláře Zapsat
document.getElementById('playForm').addEventListener('submit', function(e) {
    e.preventDefault();
    saveUndoState();
    const btn = document.getElementById('btnSavePlay');
    const formData = new FormData(this);
    
    const songName = formData.get('song');
    const dateRaw = formData.get('date');
    const dateVal = toIsoDate(dateRaw);

    if(!songName) return showToast("Vyber píseň ze seznamu!", true);

    // Kontrola duplicity pro dnešní den
    const foundSong = songsDB.find(s => s.name === songName);
    if (foundSong) {
        let history = foundSong.history || [];
        if (history.length === 0 && foundSong.last) history = [foundSong.last];
        if (history.includes(dateVal)) {
            showToast("⚠️ Tato píseň už je pro tento den zapsána!", true);
            return;
        }
    }

    btn.disabled = true; 
    btn.innerText = "Ukládám...";
    
    // Použijeme zkonvertované datum
    formData.set('date', dateVal);
    
    fetch('api_local.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if(res.ok) {
                const s = songsDB.find(x => x.name === songName);
                if (s) {
                    if (!s.history) s.history = [];
                    s.history.push(dateVal);
                    s.history.sort((a,b) => new Date(b) - new Date(a));
                    s.last = s.history[0];
                    s.count = s.history.length;
                }
                closeModal('modalPlay');
                renderTable();
                showToast("Zapsáno");
                btn.disabled = false;
                btn.innerText = "Uložit";
            }
            else { 
                showToast("Chyba: " + res.error, true); 
                btn.disabled = false; 
                btn.innerText = "Uložit"; 
            }
        });
});

async function recordQuickPlay(songName) {
    saveUndoState();
    const dateVal = new Date().toISOString().split('T')[0];
    const s = songsDB.find(x => x.name === songName);
    
    if (s && s.history && s.history.includes(dateVal)) {
        if (!await uiConfirm(`Píseň "${songName}" už je pro dnešek zapsána. Přesto přidat další záznam?`, "Duplicitní zápis")) return;
    }

    const formData = new FormData();
    formData.append('song', songName);
    formData.append('date', dateVal);

    // Optimistická aktualizace
    if (s) {
        if (!s.history) s.history = [];
        s.history.push(dateVal);
        s.history.sort((a,b) => new Date(b) - new Date(a));
        s.last = s.history[0];
        s.count = s.history.length;
    }
    renderTable();
    showToast(`Zapsáno: ${songName}`);
    
    fetch('api_local.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if(!res.ok) {
                showToast("Při synchronizaci se vyskytla chyba: " + res.error, true);
                location.reload();
            }
        })
        .catch(err => {
            showToast("Chyba sítě při synchronizaci", true);
            location.reload();
        });
}

// ==========================================
// 7. GLOBÁLNÍ HISTORIE (VŠECHNY PÍSNĚ)
// ==========================================

function renderGlobalHistory() {
    const container = document.getElementById('globalHistoryContainer');
    let all = [];
    
    // Sesbírání všech záznamů
    songsDB.forEach(s => {
        if(s.history && s.history.length > 0) {
            s.history.forEach(d => all.push({date: d, name: s.name}));
        } else if (s.last) {
            all.push({date: s.last, name: s.name});
        }
    });
    
    // Řazení podle data (nejnovější nahoře)
    all.sort((a,b) => new Date(b.date) - new Date(a.date));
    
    let html = "<ul class='history-list'>";
    all.slice(0, 100).forEach(item => { // Limit 100 záznamů
        const d = fromIsoDate(item.date);
        html += `
            <li class="history-item">
                <div class="h-date">${d}</div>
                <div class="h-name">${item.name}</div>
                <div class="h-actions">
                    <button class="icon-btn" onclick="editHistoryItem('${item.name}', '${item.date}')" title="Upravit">Upravit</button>
                    <button class="icon-btn" onclick="deleteHistoryItem('${item.name}', '${item.date}')" title="Smazat" style="color:#ff4d4d">&times;</button>
                </div>
            </li>`;
    });
    html += "</ul>";
    
    container.innerHTML = html || "<p style='text-align:center;color:#999;margin-top:20px;'>Zatím prázdné.</p>";
    openModal('modalHistory');
}

async function editHistoryItem(songName, oldDate) {
    const oldD = fromIsoDate(oldDate);
    const newRaw = await uiPrompt(`Nové datum pro "${songName}" (DD.MM.RRRR):`, oldD, "Oprava záznamu");
    if(newRaw) {
        const newDate = toIsoDate(newRaw);
        if (newDate !== oldDate) {
            callHistoryApiGlobal(songName, 'update', newDate, oldDate); 
        }
    }
}

async function deleteHistoryItem(songName, date) {
    if(await uiConfirm("Smazat záznam " + songName + " (" + date + ")?")) {
        callHistoryApiGlobal(songName, 'remove', date);
    }
}

function callHistoryApiGlobal(songName, action, date, oldDate = null) {
    saveUndoState();
    const formData = new FormData();
    formData.append('song', songName);
    formData.append('action', action);
    formData.append('date', date);
    if(oldDate) formData.append('old_date', oldDate);
    
    fetch('api_manage_history.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if(res.ok) {
                const idx = songsDB.findIndex(s => s.name === res.song.name);
                if (idx > -1) songsDB[idx] = res.song;
                renderGlobalHistory(); // Re-render the history modal content
                renderTable();
                showToast("Uloženo");
            }
            else showToast("Chyba: " + res.error, true);
        });
}

// ==========================================
// 8. FILTRY A ŘAZENÍ (TABULKA)
// ==========================================

const tRows = document.querySelectorAll('.songRow');

// Vzájemná aktualizace dropdownů (aby zobrazovaly jen relevantní možnosti)
function updateDropdownFilters() {
    const catSelect = document.getElementById('tableCat');
    const tagSelect = document.getElementById('tableTag');
    
    const selectedCat = catSelect.value;
    const selectedTag = tagSelect.value;
    
    // 1. Dostupné tagy pro vybranou kategorii
    const availableTags = new Set();
    songsDB.forEach(s => {
        if (selectedCat === "" || s.category === selectedCat) {
            if (s.tags) s.tags.split(',').forEach(t => availableTags.add(t.trim()));
        }
    });

    // 2. Dostupné kategorie pro vybraný tag
    const availableCats = new Set();
    songsDB.forEach(s => {
        const songTags = s.tags ? s.tags.split(',').map(t => t.trim()) : [];
        if (selectedTag === "" || songTags.includes(selectedTag)) {
            if (s.category) availableCats.add(s.category);
        }
    });

    // Pomocná funkce pro vykreslení options
    const renderOptions = (selectEl, validSet, currentValue, allLabel) => {
        const wasSelected = currentValue;
        selectEl.innerHTML = `<option value="">${allLabel}</option>`;
        Array.from(validSet).sort().forEach(val => {
            if(!val) return;
            const opt = document.createElement('option');
            opt.value = val;
            opt.innerText = val;
            if(val === wasSelected) opt.selected = true;
            selectEl.appendChild(opt);
        });
    };

    // Logika, aby se nepřekreslovalo to, co právě edituji
    if (document.activeElement === catSelect) {
         renderOptions(tagSelect, availableTags, selectedTag, "Všechny tagy");
    } else if (document.activeElement === tagSelect) {
         renderOptions(catSelect, availableCats, selectedCat, "Všechny kategorie");
    } else {
         renderOptions(tagSelect, availableTags, selectedTag, "Všechny tagy");
         renderOptions(catSelect, availableCats, selectedCat, "Všechny kategorie");
    }
}

// Listenery pro filtry
document.getElementById('tableSearch').addEventListener('input', renderTable);
document.getElementById('tableCat').addEventListener('change', renderTable);
document.getElementById('tableTag').addEventListener('change', renderTable);
document.getElementById('tableTempo').addEventListener('change', renderTable); // Listener pro tempo
document.getElementById('filterDays').addEventListener('input', renderTable);

// Řazení tabulky
let sortDir = {};
function sortTable(n) {
    const table = document.getElementById("songsTable");
    const tbody = table.querySelector("tbody");
    const rows = Array.from(tbody.rows);
    
    // Reset šipek
    document.querySelectorAll("th span.arrow").forEach(sp => sp.innerHTML = "↕");
    
    // Přepnutí směru
    sortDir[n] = !sortDir[n];
    const dir = sortDir[n] ? 1 : -1;
    
    // Nastavení aktivní šipky
    const th = table.rows[0].cells[n];
    th.querySelector("span.arrow").innerHTML = dir === 1 ? "▲" : "▼";
    
    rows.sort((a, b) => {
        const x = a.cells[n].innerText.trim();
        const y = b.cells[n].innerText.trim();
        
        // Index 3: Tempo (Custom logic)
        if(n === 3) {
            const weights = { "rychlá": 3, "střední": 2, "pomalá": 1 };
            const wx = weights[x] || 0;
            const wy = weights[y] || 0;
            return dir * (wx - wy);
        }
        
        // Index 5: Počet hraní (číslo)
        if(n === 5) return dir * (parseInt(x) - parseInt(y));
        
        // Index 6: Datum (dd.mm.yy)
        if(n === 6) { 
            const getTs = (s) => {
                if(s === "-" || s === "") return 0;
                const p = s.split(/[\. ]+/); 
                // p[0]=den, p[1]=měsíc, p[2]=rok
                if(p.length >= 3) {
                    let y = p[2]; if(y.length === 2) y = "20" + y;
                    return new Date(y, p[1]-1, p[0]).getTime();
                }
                return 0;
            };
            
            const tsX = getTs(x);
            const tsY = getTs(y);
            
            // Pokud obě mají datum: seřaď podle data (dir)
            if (tsX !== 0 && tsY !== 0) return dir * (tsX - tsY);
            
            // Pokud jedno má datum: to s datem má vždy přednost (vždy nahoře)
            if (tsX !== 0 && tsY === 0) return -1;
            if (tsX === 0 && tsY !== 0) return 1;
            
            // Pokud obě nemají datum: seřaď abecedně podle názvu (vždy A-Z)
            const nameX = a.dataset.name || "";
            const nameY = b.dataset.name || "";
            return nameX.localeCompare(nameY, 'cs');
        }
        
        // Ostatní: Textové porovnání (Název, Kat, Tempo, Tagy)
        return dir * x.localeCompare(y, 'cs');
    });
    
    rows.forEach(r => tbody.appendChild(r));
}

// ==========================================
// 9. HROMADNÉ AKCE A OPTIMISTICKÉ AKTUALIZACE
// ==========================================

function updateBulkBar() {
    const checkboxes = document.querySelectorAll('.songCheckbox');
    const checked = [];
    
    checkboxes.forEach(cb => {
        const row = cb.closest('.songRow');
        if (cb.checked) {
            checked.push(cb.value);
            if (row) row.classList.add('selected');
        } else {
            if (row) row.classList.remove('selected');
        }
    });

    const bar = document.getElementById('bulkActionsBar');
    const countText = document.getElementById('bulkCountText');
    
    if (checked.length > 0) {
        bar.style.display = "block";
        countText.innerText = "Zvoleno: " + checked.length;
    } else {
        bar.style.display = "none";
        document.getElementById('selectAllSongs').checked = false;
    }
}

function toggleSelectAll(master) {
    const rows = document.querySelectorAll('.songRow');
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            row.querySelector('.songCheckbox').checked = master.checked;
        }
    });
    updateBulkBar();
}

function clearSelection() {
    document.querySelectorAll('.songCheckbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllSongs').checked = false;
    updateBulkBar();
}

function getUniqueDates(songNames) {
    const dates = new Set();
    songNames.forEach(name => {
        const s = songsDB.find(s => s.name === name);
        if (s && s.history) s.history.forEach(d => dates.add(d));
    });
    return Array.from(dates).sort().reverse();
}

async function applyBulkAction() {
    saveUndoState();
    const action = document.getElementById('bulkActionSelect').value;
    const checked = Array.from(document.querySelectorAll('.songCheckbox:checked')).map(cb => cb.value);
    
    if (!action) return showToast("Vyber akci!", true);
    if (checked.length === 0) return showToast("Vyber písně!", true);
    
    const formData = new FormData();
    checked.forEach(name => formData.append('names[]', name));
    
    if (action === 'delete') {
        if (!await uiConfirm(`Opravdu smazat ${checked.length} písní?`, "Hromadné mazání")) return;
        formData.append('action', 'bulk_delete');
    } else if (action === 'change_cat') {
        const newCat = await uiPrompt("Nová kategorie:", "", "Hromadná změna kategorie");
        if (!newCat) return;
        formData.append('action', 'bulk_update');
        formData.append('category', newCat);
    } else if (action === 'change_tempo') {
        const newTempo = await uiPrompt("Nové tempo (pomalá / střední / rychlá):", "", "Hromadná změna tempa");
        if (!newTempo) return;
        formData.append('action', 'bulk_update');
        formData.append('tempo', newTempo);
    } else if (action === 'change_author') {
        const newAuthor = await uiPrompt("Nový autor:", "", "Hromadná změna autora");
        if (newAuthor === null) return;
        formData.append('action', 'bulk_update');
        formData.append('author', newAuthor);
    } else if (action === 'change_tags') {
        const newTags = await uiPrompt("Nové tagy (čárkou):", "", "Hromadná změna tagů");
        if (newTags === null) return;
        formData.append('action', 'bulk_update');
        formData.append('tags', newTags);
    } else if (action === 'bulk_play') {
        const defDate = getTypicalDate();
        const input = await uiPrompt("Zapsat datum pro tyto písně (DD.MM.RRRR):", defDate, "Hromadný zápis");
        if (!input) return;
        const finalDate = toIsoDate(input);
        
        if (!await uiConfirm(`Zapsat datum pro ${checked.length} písní (${fromIsoDate(finalDate)})?`, "Hromadný zápis")) return;
        formData.append('action', 'bulk_add_history');
        formData.append('date', finalDate);
    } else if (action === 'bulk_remove_date') {
        const uniqueDates = getUniqueDates(checked);
        const dateToRemove = await uiPickDate(uniqueDates, "Vyberte datum, které chcete odstranit z historie vybraných písní:", "Hromadné odebrání data");
        if (!dateToRemove) return;
        
        formData.append('action', 'bulk_remove_history');
        formData.append('date', dateToRemove);
    } else if (action === 'bulk_replace_date') {
        const uniqueDates = getUniqueDates(checked);
        const oldDate = await uiPickDate(uniqueDates, "Vyberte datum, které chcete nahradit:", "Hromadná změna data");
        if (!oldDate) return;
        
        const defNew = getTypicalDate();
        const newInput = await uiPrompt("Zadejte nové datum (DD.MM.RRRR):", defNew, "Hromadná změna data");
        if (!newInput) return;
        const newDate = toIsoDate(newInput);
        
        formData.append('action', 'bulk_replace_history');
        formData.append('old_date', oldDate);
        formData.append('new_date', newDate);
    }
    
    showToast("Provádím...");
    
    // Optimistická aktualizace pro historii
    if (action === 'bulk_play') {
        const dateVal = formData.get('date');
        checked.forEach(name => {
            const s = songsDB.find(s => s.name === name);
            if (s) {
                if (!s.history) s.history = [];
                if (!s.history.includes(dateVal)) {
                    s.history.push(dateVal);
                    s.history.sort((a,b) => new Date(b) - new Date(a));
                    s.last = s.history[0];
                    s.count = s.history.length;
                }
            }
        });
        renderTable();
        clearSelection();
    } else if (action === 'bulk_remove_date') {
        const d = formData.get('date');
        checked.forEach(name => {
            const s = songsDB.find(s => s.name === name);
            if (s && s.history) {
                s.history = s.history.filter(h => h !== d);
                s.last = s.history.length > 0 ? s.history[0] : "";
                s.count = s.history.length;
            }
        });
        renderTable();
        clearSelection();
    } else if (action === 'bulk_replace_date') {
        const oldD = formData.get('old_date');
        const newD = formData.get('new_date');
        checked.forEach(name => {
            const s = songsDB.find(s => s.name === name);
            if (s && s.history) {
                let found = false;
                s.history = s.history.map(h => {
                    if (h === oldD) { found = true; return newD; }
                    return h;
                });
                if (found) {
                    s.history = Array.from(new Set(s.history)).sort().reverse();
                    s.last = s.history[0];
                    s.count = s.history.length;
                }
            }
        });
        renderTable();
        clearSelection();
    }

    fetch('api_manage_song.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                showToast("Hotovo");
                if (action === 'delete') {
                    checked.forEach(name => {
                        const idx = songsDB.findIndex(s => s.name === name);
                        if (idx > -1) songsDB.splice(idx, 1);
                    });
                } else if (action !== 'bulk_play' && action !== 'bulk_remove_date' && action !== 'bulk_replace_date') { // Optimistické akce
                    const map = { 'change_cat': 'category', 'change_tempo': 'tempo', 'change_author': 'author', 'change_tags': 'tags' };
                    const field = map[action];
                    const val = formData.get(field);
                    if (field) {
                        checked.forEach(name => {
                            const s = songsDB.find(s => s.name === name);
                            if (s) s[field] = val;
                        });
                    }
                }
                clearSelection();
                renderTable();
            } else {
                showToast(res.error, true);
                if (action.startsWith('bulk')) location.reload(); // Rollback pro hromadné akce
            }
        });
}

function renderTable() {
    const tbody = document.querySelector('#songsTable tbody');
    const searchTerm = document.getElementById('tableSearch').value.toLowerCase();
    const cat = document.getElementById('tableCat').value;
    const tag = document.getElementById('tableTag').value;
    const tempo = document.getElementById('tableTempo').value;
    const minDays = document.getElementById('filterDays').value;

    tbody.innerHTML = '';
    
    songsDB.sort((a,b) => a.name.localeCompare(b.name, 'cs'));

    songsDB.forEach(s => {
        const last = s.last || "";
        let daysDiff = 99999;
        if(last) {
            daysDiff = Math.floor((new Date() - new Date(last)) / 86400000);
        }

        const txt = (s.name + " " + (s.author || "") + " " + s.category + " " + (s.tags || "")).toLowerCase();
        let show = true;
        if(cat && s.category !== cat) show = false;
        if(tempo && s.tempo !== tempo) show = false;
        if(tag && !(s.tags || "").toLowerCase().includes(tag.toLowerCase())) show = false;
        if(searchTerm && !txt.includes(searchTerm)) show = false;
        if(minDays && daysDiff < parseInt(minDays)) show = false;

        if (!show) return;

        const cls = (daysDiff > 180 && last) ? "row-red" : "";
        const safeData = JSON.stringify(s).replace(/'/g, "&apos;").replace(/"/g, "&quot;");
        
        const tr = document.createElement('tr');
        tr.className = `songRow ${cls}`;
        tr.dataset.days = daysDiff;
        tr.dataset.name = s.name;
        
        let tagsHtml = "";
        if (s.tags) {
            s.tags.split(',').forEach(tag => {
                const t = tag.trim();
                if (t) tagsHtml += `<span class='tag-badge'>${t}</span>`;
            });
        }

        const displayDate = last ? fromIsoDate(last) : "-";
        const relativeLast = last ? getRelativeTime(last) : "-";
        const lastShort = last ? last.substring(2).split('-').reverse().join('.') : "-"; // dd.mm.yy

        const hasPdf = !!s.pdf;
        const hasOpenlp = !!s.openlp;
        const canDownload = hasPdf || hasOpenlp;

        const downloadBtn = canDownload
            ? `<button onclick='uiDownloadChoice("${s.name.replace(/"/g, "&quot;")}")' class="download-btn active" title="Stáhnout přílohy">Stáhnout</button>`
            : `<button class="download-btn disabled" title="Žádné přílohy k dispozici">Stáhnout</button>`;

        tr.innerHTML = `
            <td style="text-align:center;"><input type="checkbox" class="songCheckbox" value="${s.name}" onclick="updateBulkBar()"></td>
            <td>
                <div style="font-weight:600; color:#333;">${s.name}</div>
                <div style="font-size:12px; color:#888;">${s.author || ""}</div>
            </td>
            <td><span style="background:#f0f0f0; padding:3px 7px; border-radius:5px; font-size:12px;">${s.category}</span></td>
            <td style="font-size:13px; color:#555;">${s.tempo || ""}</td>
            <td>${tagsHtml}</td>
            <td style="text-align:center; font-weight:bold;">${s.count}</td>
            <td style="font-family:inherit; color:#666; text-align:right;" title="${relativeLast}">${lastShort}</td>
            <td style="text-align:right; white-space:nowrap;">
                ${downloadBtn}
                <button onclick='recordQuickPlay("${s.name.replace(/"/g, "&quot;")}")' class="quick-play-btn" title="Rychle zapsat hraní">+</button>
                <button onclick='openEditModal(${safeData})' class="edit-btn" title="Upravit">Upravit</button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    updateDropdownFilters();
}

// Volba stahování
function uiDownloadChoice(songName) {
    const s = songsDB.find(x => x.name === songName);
    if (!s) return;
    
    document.getElementById('downloadSongName').innerText = s.name;
    const btnPdf = document.getElementById('btnDownloadPdf');
    const btnOpenlp = document.getElementById('btnDownloadOpenlp');
    
    if (s.pdf) {
        btnPdf.href = "pdf/" + encodeURIComponent(s.pdf);
        btnPdf.classList.remove('disabled');
        btnPdf.style.opacity = "1";
        btnPdf.style.pointerEvents = "auto";
    } else {
        btnPdf.classList.add('disabled');
        btnPdf.style.opacity = "0.4";
        btnPdf.style.pointerEvents = "none";
    }
    
    if (s.openlp) {
        btnOpenlp.href = "openlp/" + encodeURIComponent(s.openlp);
        btnOpenlp.classList.remove('disabled');
        btnOpenlp.style.opacity = "1";
        btnOpenlp.style.pointerEvents = "auto";
    } else {
        btnOpenlp.classList.add('disabled');
        btnOpenlp.style.opacity = "0.4";
        btnOpenlp.style.pointerEvents = "none";
    }
    
    openModal('modalDownload');
}

// Nahrávání souborů
async function handleFileUpload(type) {
    if (!currentEditingSong) return;
    const input = document.getElementById(type === 'pdf' ? 'uploadPdfInput' : 'uploadOpenlpInput');
    if (!input.files || input.files.length === 0) return;
    
    const file = input.files[0];
    const formData = new FormData();
    formData.append('song', currentEditingSong.name);
    formData.append('type', type);
    formData.append('file', file);
    formData.append('magic', UPLOAD_CONFIG.magic);
    
    showToast("Nahrávám...");
    
    try {
        const r = await fetch('api_upload.php', { method: 'POST', body: formData });
        const res = await r.json();
        if (res.ok) {
            showToast("Uloženo");
            // Aktualizace v lokální DB
            currentEditingSong[type] = res.filename;
            const songOnDb = songsDB.find(x => x.name === currentEditingSong.name);
            if (songOnDb) songOnDb[type] = res.filename;
            
            // UI update
            document.getElementById(type + 'Status').innerHTML = `<span style="color:#22c55e">✅ Nahráno (${res.filename})</span>`;
            renderTable(); // Osvěžit tlačítka v tabulce
        } else {
            showToast("Chyba: " + res.error, true);
        }
    } catch (e) {
        showToast("Chyba při komunikaci se serverem.", true);
    }
    input.value = ""; // Reset inputu
}

window.addEventListener('DOMContentLoaded', () => {
    // Okamžité vykreslení moderní tabulky
    if (typeof renderTable === 'function') renderTable();
    if (typeof updateUndoButton === 'function') updateUndoButton();
});