/* SOUBOR: script.js */

const songsDB = window.serverData.songs;
let currentEditingSong = null;

// Proměnné pro Chart.js instance (abychom je mohli zničit před překreslením)
let chartInstanceTop = null;
let chartInstanceFlop = null;

// UI UTILS
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

// MODAL LOGIC
function openModal(id) { document.getElementById(id).style.display = "flex"; }
function closeModal(id) { document.getElementById(id).style.display = "none"; }
window.onclick = function(e) { if(e.target.classList.contains('modal')) e.target.style.display="none"; }

// --- STATISTIKY (NOVÉ - HUDEBNÍ SPEKTRUM) ---
function openStats() {
    openModal('modalStats');
    
    // Zničení starých grafů, pokud existují (Chart.js to vyžaduje)
    if (chartInstanceTop) chartInstanceTop.destroy();
    if (chartInstanceFlop) chartInstanceFlop.destroy();

    const statsData = window.serverData.stats;

    // 1. Graf TOP 20
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
    document.getElementById('manageTitle').innerText = "✏️ Upravit";
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
        else { showToast(res.error, true); btn.disabled=false; btn.innerText="Uložit"; }
    }).catch(err => { showToast("Chyba sítě", true); btn.disabled=false; });
});

async function deleteSong() {
    if(! await uiConfirm("Opravdu smazat?", "Smazání")) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('original_name', document.getElementById('manageOriginalName').value);
    
    fetch('api_manage_song.php', { method: 'POST', body: formData }).then(r=>r.json()).then(res => {
        if(res.ok) location.reload();
        else showToast(res.error, true);
    });
}

// --- HISTORIE ---
function renderEditHistory(history) {
    const container = document.getElementById('historyChips');
    container.innerHTML = "";
    if(!history || history.length === 0) { container.innerHTML = "<i style='color:#999;font-size:13px;'>Žádná historie</i>"; return; }
    history.sort((a,b) => new Date(b) - new Date(a));
    history.forEach(date => {
        const d = new Date(date).toLocaleDateString('cs-CZ');
        const chip = document.createElement('div');
        chip.className = "history-chip";
        chip.innerHTML = `<span class="chip-date" onclick="editHistoryDate('${date}')" title="Upravit">${d}</span>
                          <span class="chip-remove" onclick="removeHistoryDate('${date}')" title="Smazat">&times;</span>`;
        container.appendChild(chip);
    });
}
function addHistoryDate() {
    const date = document.getElementById('newHistoryDate').value;
    if(!date) return showToast("Vyber datum!", true);
    callHistoryApi('add', date);
    document.getElementById('newHistoryDate').value = "";
}
async function removeHistoryDate(date) {
    if(await uiConfirm("Smazat datum " + date + "?")) callHistoryApi('remove', date);
}
async function editHistoryDate(oldDate) {
    const newDate = await uiPrompt("Nové datum (YYYY-MM-DD):", oldDate, "Upravit");
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
            showToast("Uloženo");
            if(reload) setTimeout(() => location.reload(), 300);
        } else showToast("Chyba: " + res.error, true);
    });
}

// --- ZÁPIS HRANÍ ---
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
    
    const songName = formData.get('song');
    const dateVal = formData.get('date');

    if(!songName) return showToast("Vyber píseň!", true);

    const foundSong = songsDB.find(s => s.name === songName);
    if (foundSong) {
        let history = foundSong.history || [];
        if (history.length === 0 && foundSong.last) history = [foundSong.last];
        if (history.includes(dateVal)) {
            showToast("⚠️ Tato píseň už je pro tento den zapsána!", true);
            return;
        }
    }

    btn.disabled = true; btn.innerText = "Ukládám...";
    fetch('api_local.php', { method: 'POST', body: formData }).then(r=>r.json()).then(res => {
        if(res.ok) location.reload();
        else { showToast("Chyba: " + res.error, true); btn.disabled=false; btn.innerText="Uložit"; }
    });
});

// --- GLOBÁLNÍ HISTORIE ---
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
                        <button class="icon-btn" onclick="editHistoryItem('${item.name}', '${item.date}')" title="Upravit">✏️</button>
                        <button class="icon-btn" onclick="deleteHistoryItem('${item.name}', '${item.date}')" title="Smazat" style="color:#ff4d4d">&times;</button>
                    </div>
                 </li>`;
    });
    html += "</ul>";
    container.innerHTML = html || "<p style='text-align:center;color:#999;margin-top:20px;'>Prázdné.</p>";
    openModal('modalHistory');
}
async function editHistoryItem(songName, oldDate) {
    const newDate = await uiPrompt("Nové datum (YYYY-MM-DD):", oldDate, "Oprava");
    if(newDate && newDate !== oldDate) {
        callHistoryApiGlobal(songName, 'update', newDate, oldDate); 
    }
}
async function deleteHistoryItem(songName, date) {
    if(await uiConfirm("Smazat záznam " + songName + " (" + date + ")?")) {
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
        else showToast("Chyba: " + res.error, true);
    });
}

// --- FILTRY & SORT ---
const tRows = document.querySelectorAll('.songRow');

// Vzájemná filtrace dropdownů
function updateDropdownFilters() {
    const catSelect = document.getElementById('tableCat');
    const tagSelect = document.getElementById('tableTag');
    
    const selectedCat = catSelect.value;
    const selectedTag = tagSelect.value;
    
    // 1. Tagy pro kategorii
    const availableTags = new Set();
    songsDB.forEach(s => {
        if (selectedCat === "" || s.category === selectedCat) {
            if (s.tags) s.tags.split(',').forEach(t => availableTags.add(t.trim()));
        }
    });

    // 2. Kategorie pro tag
    const availableCats = new Set();
    songsDB.forEach(s => {
        const songTags = s.tags ? s.tags.split(',').map(t => t.trim()) : [];
        if (selectedTag === "" || songTags.includes(selectedTag)) {
            if (s.category) availableCats.add(s.category);
        }
    });

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

    if (document.activeElement === catSelect) {
         renderOptions(tagSelect, availableTags, selectedTag, "Všechny tagy");
    } else if (document.activeElement === tagSelect) {
         renderOptions(catSelect, availableCats, selectedCat, "Všechny kategorie");
    } else {
         renderOptions(tagSelect, availableTags, selectedTag, "Všechny tagy");
         renderOptions(catSelect, availableCats, selectedCat, "Všechny kategorie");
    }
}

function filterTable() {
    const q = document.getElementById('tableSearch').value.toLowerCase();
    const cat = document.getElementById('tableCat').value;
    const tag = document.getElementById('tableTag').value;
    const minDays = document.getElementById('filterDays').value;
    
    tRows.forEach(row => {
        const txt = row.innerText.toLowerCase();
        const rCat = row.cells[1].innerText;
        const rTags = row.cells[2].innerText.toLowerCase();
        const rDays = parseInt(row.getAttribute('data-days')) || 99999;
        
        let show = true;
        
        if(cat && rCat !== cat) show = false;
        if(tag && !rTags.includes(tag.toLowerCase())) show = false;
        if(q && !txt.includes(q)) show = false;
        if(minDays && rDays < parseInt(minDays)) show = false;
        
        row.style.display = show ? "" : "none";
    });

    updateDropdownFilters();
}

document.getElementById('tableSearch').addEventListener('input', filterTable);
document.getElementById('tableCat').addEventListener('change', filterTable);
document.getElementById('tableTag').addEventListener('change', filterTable);
document.getElementById('filterDays').addEventListener('input', filterTable);

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
        if(n === 3) return dir * (parseInt(x) - parseInt(y));
        if(n === 4) { 
            const getTs = (s) => {
                if(s === "-" || s === "") return 0;
                const p = s.split(/[\. ]+/); 
                if(p.length >= 3) return new Date(p[2], p[1]-1, p[0]).getTime();
                return 0;
            };
            return dir * (getTs(x) - getTs(y));
        }
        return dir * x.localeCompare(y, 'cs');
    });
    rows.forEach(r => tbody.appendChild(r));
}
