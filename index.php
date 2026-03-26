<?php
require_once "logic.php";
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Knihovna písní</title>
    <link rel="stylesheet" href="styles.css?v=<?= $appVersion ?>_<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="site-header">
    <div class="wrap">
        <?php 
        $svgPiano = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:sub; margin-right:12px;"><path d="M2 9h20v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9Z"></path><path d="M6 13v10"></path><path d="M10 13v10"></path><path d="M14 13v10"></path><path d="M18 13v10"></path><path d="M2 13h20"></path></svg>';
        $svgStats = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:8px;"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>';
        $svgHistory = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:8px;"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><polyline points="3 3 3 8 8 8"></polyline><line x1="12" y1="7" x2="12" y2="12"></line><line x1="12" y1="12" x2="16" y2="14"></line></svg>';
        $svgAdd = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:8px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>';
        $svgPlay = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:8px;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>';
        $svgUndo = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:5px;"><path d="M3 7v6h6"></path><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"></path></svg>';
        $svgPencil = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:8px;"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>';
        $svgDownload = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:8px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';
        $svgAttach = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:8px;"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>';
        ?>
        <div class="title"><?= $svgPiano ?> Knihovna písní</div>
    </div>
</div>

<div class="wrap">

    <div class="dashboard-grid" style="grid-template-columns: 1fr;"> 
        <div class="card">
            <div class="panel-title">Menu</div>
            <div class="action-btn-grid">
                <div class="big-btn" onclick="openStats()"><?= $svgStats ?> Statistiky hraní</div>
                <div class="big-btn" onclick="renderGlobalHistory()"><?= $svgHistory ?> Historie hraní</div>
                <div class="big-btn" onclick="openAddModal()"><?= $svgAdd ?> Přidat píseň</div>
                <div class="big-btn primary" onclick="openPlayModal()"><?= $svgPlay ?> Zapsat hraní</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="panel-title" style="display:flex; justify-content:space-between; align-items:center;">
            <span>Seznam</span>
            <div style="display:flex; align-items:center; gap:10px;">
                <button id="undoBtn" class="btn btn-secondary" style="display:none; padding:4px 10px; font-size:12px; width:auto;" onclick="undo()" title="Vrátit poslední akci (až 5 kroků)"><?= $svgUndo ?> Zpět</button>
                <span style="font-size:13px; color:#888; font-weight:normal;"><?= count($songsData) ?> písní</span>
            </div>
        </div>
        
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px;">
            <input type="number" id="filterDays" placeholder="Nehráno dní (min)" min="0" style="flex:1; min-width:130px;" title="Zobrazit písně, které nebyly hrány X a více dní">
            
            <input type="text" id="tableSearch" placeholder="Hledat..." style="flex:2; min-width:180px;">
            
            <select id="tableTempo" style="flex:1; min-width:130px;">
                <option value="">Všechna tempa</option>
                <?php foreach ($tempos as $t) echo "<option>" . htmlspecialchars($t) . "</option>"; ?>
            </select>

            <select id="tableCat" style="flex:1; min-width:130px;">
                <option value="">Všechny kategorie</option>
                <?php foreach ($categories as $c) echo "<option>" . htmlspecialchars($c) . "</option>"; ?>
            </select>
            
            <select id="tableTag" style="flex:1; min-width:130px;">
                <option value="">Všechny tagy</option>
                <?php foreach ($tags as $t) echo "<option>" . htmlspecialchars($t) . "</option>"; ?>
            </select>
        </div>

        <div id="bulkActionsBar" class="bulk-actions-bar" style="display:none;">
            <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
                <span id="bulkCountText" style="font-weight:700; color:#d11a2a;">Zvoleno: 0</span>
                <select id="bulkActionSelect" style="width:auto; min-width:150px;">
                    <option value="">-- Hromadná akce --</option>
                    <option value="change_cat">Změnit kategorii</option>
                    <option value="change_tempo">Změnit tempo</option>
                    <option value="change_author">Změnit autora</option>
                    <option value="change_tags">Změnit tagy</option>
                    <option value="bulk_play">Zapsat datum</option>
                    <option value="bulk_remove_date">Odebrat datum z historie</option>
                    <option value="bulk_replace_date">Změnit datum v historii</option>
                    <option value="delete">Smazat vybrané</option>
                </select>
                <button type="button" class="btn" style="width:auto; padding:8px 15px;" onclick="applyBulkAction()">Provést</button>
                <button type="button" class="btn btn-secondary" style="width:auto; padding:8px 15px;" onclick="clearSelection()">Zrušit</button>
            </div>
        </div>

        <div class="table-wrapper">
            <table id="songsTable">
                <thead>
                    <tr>
                        <th style="width:40px; text-align:center;"><input type="checkbox" id="selectAllSongs" onclick="toggleSelectAll(this)"></th>
                        <th onclick="sortTable(1)">Název <span class="arrow">↕</span></th>
                        <th onclick="sortTable(2)">Kategorie <span class="arrow">↕</span></th>
                        <th onclick="sortTable(3)">Tempo <span class="arrow">↕</span></th> <th onclick="sortTable(4)">Tagy <span class="arrow">↕</span></th>
                        <th onclick="sortTable(5)" style="text-align:center">Počet <span class="arrow">↕</span></th>
                        <th onclick="sortTable(6)" style="text-align:right">Naposledy <span class="arrow">↕</span></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($songsData as $s): 
                    $last = $s["last"] ?? "";
                    if (isset($s["history"]) && is_array($s["history"]) && count($s["history"]) > 0) $last = $s["history"][0];
                    
                    $daysDiff = 99999;
                    if($last) {
                        $daysDiff = floor((time() - strtotime($last)) / 86400);
                    }

                    $cls = ($daysDiff > 180 && $last) ? "row-red" : "";
                    $safeData = htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8');
                ?>
                <tr class="songRow <?= $cls ?>" data-days="<?= $daysDiff ?>" data-name="<?= htmlspecialchars($s["name"]) ?>">
                    <td style="text-align:center;"><input type="checkbox" class="songCheckbox" value="<?= htmlspecialchars($s["name"]) ?>" onclick="updateBulkBar()"></td>
                    <td>
                        <div style="font-weight:600; color:#333;"><?= htmlspecialchars($s["name"]) ?></div>
                        <div style="font-size:12px; color:#888;"><?= htmlspecialchars($s["author"]) ?></div>
                    </td>
                    <td><span style="background:#f0f0f0; padding:3px 7px; border-radius:5px; font-size:12px;"><?= htmlspecialchars($s["category"]) ?></span></td>
                    
                    <td style="font-size:13px; color:#555;"><?= htmlspecialchars($s["tempo"] ?? "") ?></td>

                    <td>
                        <?php 
                        if(!empty($s["tags"])) {
                            foreach(explode(",", $s["tags"]) as $tag) {
                                $t = trim($tag);
                                if($t) echo "<span class='tag-badge'>" . htmlspecialchars($t) . "</span>";
                            }
                        }
                        ?>
                    </td>
                    <td style="text-align:center; font-weight:bold;"><?= $s["count"] ?></td>
                    <td style="font-family:monospace; color:#666; text-align:right;"><?= $last ? date("d.m.y", strtotime($last)) : "-" ?></td>
                    <td style="text-align:right; white-space:nowrap;">
                        <?php 
                        $canDownload = (!empty($s["pdf"]) || !empty($s["openlp"]));
                        $downloadClass = $canDownload ? "download-btn active" : "download-btn disabled";
                        $downloadTitle = $canDownload ? "Stáhnout přílohy" : "Žádné přílohy k dispozici";
                        ?>
                        <button onclick='uiDownloadChoice("<?= htmlspecialchars($s["name"]) ?>")' class="<?= $downloadClass ?>" title="<?= $downloadTitle ?>"><?= $svgDownload ?></button>
                        <button onclick='recordQuickPlay("<?= htmlspecialchars($s["name"]) ?>")' class="quick-play-btn" title="Rychle zapsat hraní"><?= $svgAdd ?></button>
                        <button onclick='deleteLastPlay("<?= htmlspecialchars($s["name"]) ?>")' class="quick-play-btn danger" style="color:#d11a2a; border-color:#d11a2a; margin-left:4px;" title="Smazat poslední datum"><?= $svgTrash = str_replace('18', '18', $svgHistory); // Temporary fallback for trash icon if not defined in PHP section ?>
                            <!-- Trash icon SVG -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                        </button>
                        <button onclick='openEditModal(<?= $safeData ?>)' class="edit-btn" title="Upravit"><?= $svgPencil ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <footer class="site-footer">
        Verze: <span class="version-hash"><?= $appVersion ?></span>
    </footer>
</div>

<datalist id="listCategories">
    <?php foreach ($categories as $c) echo "<option value='" . htmlspecialchars($c) . "'>"; ?>
</datalist>

<div id="modalManage" class="modal">
    <div class="modal-content large">
        <div class="modal-header">
            <div class="modal-title" id="manageTitle">Píseň</div>
            <span class="close-btn" onclick="closeModal('modalManage')">&times;</span>
        </div>
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
                        <select name="tempo" id="inpTempo">
                            <option value="">-- Vyber --</option>
                            <option value="pomalá">Pomalá</option>
                            <option value="střední">Střední</option>
                            <option value="rychlá">Rychlá</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label class="form-label">Tagy (čárkou)</label><input type="text" name="tags" id="inpTags" placeholder="tag1, tag2..."></div>

                <div id="editHistorySection" style="margin-top:20px; padding-top:15px; border-top:1px solid #eee;">
                <label class="form-label"><?= $svgHistory ?> Historie hraní</label>
                <div style="display:flex; gap:10px; margin-bottom:10px;">
                    <input type="text" id="newHistoryDate" placeholder="DD.MM.RRRR" style="flex:1">
                    <button type="button" class="btn" style="width:auto; padding:0 15px;" onclick="addHistoryDate()">Přidat</button>
                </div>
                <div id="historyChips" class="history-chips"></div>
            </div>

            <div id="attachmentSection" style="margin-top:20px; padding-top:15px; border-top:1px solid #eee; display:none;">
                <label class="form-label"><?= $svgAttach ?> Přílohy (PDF / OpenLP)</label>
                
                <div style="margin-bottom:12px;">
                    <div style="font-size:12px; color:#666; margin-bottom:4px;">Zpěvník (PDF): <span id="pdfStatus" style="font-weight:600;">-</span></div>
                    <div style="display:flex; gap:8px;">
                        <input type="file" id="uploadPdfInput" accept=".pdf" style="display:none;" onchange="handleFileUpload('pdf')">
                        <button type="button" class="btn btn-secondary" style="font-size:13px; padding:6px 12px;" onclick="document.getElementById('uploadPdfInput').click()">Nahrát PDF</button>
                    </div>
                </div>

                <div>
                    <div style="font-size:12px; color:#666; margin-bottom:4px;">Projektor (OpenLP): <span id="openlpStatus" style="font-weight:600;">-</span></div>
                    <div style="display:flex; gap:8px;">
                        <input type="file" id="uploadOpenlpInput" accept=".xml,.sng,.txt,.pdf" style="display:none;" onchange="handleFileUpload('openlp')">
                        <button type="button" class="btn btn-secondary" style="font-size:13px; padding:6px 12px;" onclick="document.getElementById('uploadOpenlpInput').click()">Nahrát OpenLP</button>
                    </div>
                </div>
                
                <!-- Honeypot proti botům -->
                <input type="text" name="website" style="display:none !important;" tabindex="-1" autocomplete="off">
            </div>

                <div style="margin-top:20px; display:flex; gap:10px;">
                    <button type="submit" class="btn" id="btnManageSave">Uložit</button>
                    <button type="button" class="btn btn-danger" id="btnDelete" onclick="deleteSong()" style="display:none;">Smazat</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modalPlay" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Zapsat hraní</div>
            <span class="close-btn" onclick="closeModal('modalPlay')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="playForm">
                <div class="form-group">
                    <label class="form-label">Vyber píseň</label>
                    <div class="autocomplete-wrapper">
                        <input type="text" id="songSearchInput" placeholder="Začni psát název..." autocomplete="off">
                        <input type="hidden" name="song" id="songHiddenInput">
                        <div id="suggestionsBox" class="suggestions-box"></div>
                    </div>
                </div>
                <div class="form-group"><label class="form-label">Datum (DD.MM.RRRR)</label><input type="text" name="date" id="playDateInput" placeholder="DD.MM.RRRR" required></div>
                <button type="submit" class="btn" id="btnSavePlay" style="margin-top:10px;">Uložit</button>
            </form>
        </div>
    </div>
</div>

<div id="modalHistory" class="modal">
    <div class="modal-content large">
        <div class="modal-header">
            <div class="modal-title"><?= $svgHistory ?> Celková historie</div>
            <span class="close-btn" onclick="closeModal('modalHistory')">&times;</span>
        </div>
        <div class="modal-body" style="padding:0;">
            <div id="globalHistoryContainer" style="padding:0 20px 20px 20px;"></div>
        </div>
    </div>
</div>

<div id="modalStats" class="modal">
    <div class="modal-content large"> <div class="modal-header">
            <div class="modal-title"><?= $svgStats ?> Hudební spektrum</div>
            <span class="close-btn" onclick="closeModal('modalStats')">&times;</span>
        </div>
        <div class="modal-body">
            <p style="text-align:center; color:#666; font-size:14px; margin-bottom:20px;">
                Porovnání 20 nejhranějších hitů a 20 největších rarit.
            </p>
            
            <div class="charts-container">
                <div class="chart-box">
                    <h3 style="text-align:center; color:#d11a2a; margin-bottom:10px;">Nejhranější TOP 20</h3>
                    <div style="height: 400px; position: relative;">
                        <canvas id="topChart"></canvas>
                    </div>
                </div>
                <div class="chart-box">
                    <h3 style="text-align:center; color:#555; margin-bottom:10px;">Nejméně hrané TOP 20</h3>
                    <div style="height: 400px; position: relative;">
                        <canvas id="flopChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="stats-compact-row" style="margin-top:20px;">
                <div class="stat-item">
                    <span class="stat-val"><?= count($songsData) ?></span>
                    <span class="stat-lbl">Písní celkem</span>
                </div>
                <div style="width:1px; background:#eee;"></div>
                <div class="stat-item">
                    <span class="stat-val"><?= $totalPlays ?></span>
                    <span class="stat-lbl">Celkem zahráno</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="uiConfirmModal" class="modal" style="z-index: 11000;"><div class="modal-content" style="max-width: 320px; padding:20px;"><h3 id="uiConfirmTitle" style="margin-bottom:10px; color:#333; font-size:18px;">Potvrzení</h3><p id="uiConfirmText" class="ui-dialog-content" style="font-size:15px;"></p><div class="ui-dialog-btns"><button id="uiConfirmYes" class="btn">Ano</button><button id="uiConfirmNo" class="btn btn-secondary">Ne</button></div></div></div>
<div id="uiPromptModal" class="modal" style="z-index: 11000;"><div class="modal-content" style="max-width: 320px; padding:20px;"><h3 id="uiPromptTitle" style="margin-bottom:10px; color:#333; font-size:18px;">Vstup</h3><p id="uiPromptText" style="margin-bottom:10px; color:#666; font-size:14px;"></p><input type="text" id="uiPromptInput" style="margin-bottom:15px;"><div class="ui-dialog-btns"><button id="uiPromptOk" class="btn">OK</button><button id="uiPromptCancel" class="btn btn-secondary">Zrušit</button></div></div></div>
<div id="uiHistoryModal" class="modal" style="z-index: 11000;">
    <div class="modal-content" style="max-width: 400px; padding:20px;">
        <h3 id="uiHistoryTitle" style="margin-bottom:10px; color:#333; font-size:18px;">Výběr data</h3>
        <p id="uiHistoryText" style="margin-bottom:15px; color:#666; font-size:14px;"></p>
        <div id="uiHistoryList" class="history-chips" style="max-height:200px; overflow-y:auto; border:1px solid #eee; padding:10px; border-radius:8px; display:flex; flex-wrap:wrap; gap:8px; margin-bottom:15px;">
            <!-- Čipy se doplní JS -->
        </div>
        <div id="uiHistoryManual" style="margin-bottom:15px; display:none;">
            <label style="display:block; font-size:12px; color:#888; margin-bottom:4px;">Nebo zadejte jiné (D.M.RRRR):</label>
            <input type="text" id="uiHistoryInput" style="width:100%;">
        </div>
        <div class="ui-dialog-btns">
            <button id="uiHistoryOk" class="btn">OK</button>
            <button id="uiHistoryCancel" class="btn btn-secondary">Zrušit</button>
        </div>
    </div>
</div>

<!-- MODAL: DOWNLOAD CHOICE -->
<div id="modalDownload" class="modal">
    <div class="modal-content" style="max-width:350px;">
        <div class="modal-header">
            <h3 class="modal-title"><?= $svgDownload ?> Stáhnout píseň</h3>
            <span class="close-btn" onclick="closeModal('modalDownload')">&times;</span>
        </div>
        <div class="modal-body" style="text-align:center;">
            <p id="downloadSongName" style="font-weight:600; margin-bottom:20px;"></p>
            <div style="display:grid; gap:10px;">
                <a id="btnDownloadPdf" href="#" target="_blank" class="btn"><?= $svgDownload ?> Zpěvník (PDF)</a>
                <a id="btnDownloadOpenlp" href="#" target="_blank" class="btn btn-secondary"><?= $svgDownload ?> Projektor (OpenLP)</a>
            </div>
        </div>
    </div>
</div>

<div id="toast">Zpráva</div>

<script>
    window.serverData = {
        songs: <?= json_encode($songsData) ?>,
        uploadConfig: { magic: '<?= $UPLOAD_SECRET ?>' },
        stats: {
            topLabels: <?= json_encode(array_column($top20, "name")) ?>,
            topData: <?= json_encode(array_column($top20, "count")) ?>,
            flopLabels: <?= json_encode(array_column($flop20, "name")) ?>,
            flopData: <?= json_encode(array_column($flop20, "count")) ?>
        }
    };
</script>
<script src="script.js?v=<?= $appVersion ?>_<?= time() ?>"></script>

</body>
</html>