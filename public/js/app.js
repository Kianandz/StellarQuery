let state = { view: 'databases', db: '', table: '', pk: '', page: 1, limit: 50, totalPages: 1, dbList: [], tableList: [], rawColumns: [], rawData: [], filteredData: [], tab: 'data', isDataLoaded: false };

const DOM = {
    vDb: document.getElementById('view-databases'), vTable: document.getElementById('view-tables'), vData: document.getElementById('view-data'),
    listDb: document.getElementById('list-db'), listTable: document.getElementById('list-table'), searchData: document.getElementById('search-data'), 
    dataHead: document.getElementById('data-head'), dataBody: document.getElementById('data-body'), bcDb: document.getElementById('bc-db'), bcTable: document.getElementById('bc-table')
};

const sleep = ms => new Promise(r => setTimeout(r, ms)); 
function showLoader(msg = 'Loading...') { document.getElementById('loader-msg').innerText = msg; document.getElementById('global-loader').classList.remove('hide-loader'); }
function hideLoader() { document.getElementById('global-loader').classList.add('hide-loader'); }
function escapeHTML(str) { return str.toString().replace(/[&<>'"]/g, t => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[t])); }
function showToast(msg) { UIkit.notification({message: msg, status: 'success', pos: 'bottom-right', timeout: 2500}); }

async function safeFetchJson(url, options = {}) {
    const res = await fetch(url, options); 
    const text = await res.text(); 
    if (!text.trim()) throw new Error("Respon server kosong.");
    let json;
    try { json = JSON.parse(text); } catch (e) { throw new Error("Respon server error: " + text.substring(0, 80)); }
    if (!res.ok || json.error) throw new Error(json.error || 'Server error');
    return json;
}

function saveState() { sessionStorage.setItem('tools_state', JSON.stringify({ view: state.view, db: state.db, table: state.table, tab: state.tab })); }
function restoreState() {
    const saved = sessionStorage.getItem('tools_state');
    if (saved) {
        const s = JSON.parse(saved); state.tab = s.tab || 'data';
        if (s.view === 'data' && s.db && s.table) { loadData(s.db, s.table); } 
        else if (s.view === 'tables' && s.db) { loadTables(s.db); } 
        else { loadDatabases(); }
    } else { loadDatabases(); }
}

function showView(view) {
    state.view = view; saveState();
    DOM.vDb.classList.add('sq-hidden'); DOM.vTable.classList.add('sq-hidden'); DOM.vData.classList.add('sq-hidden');
    
    if (view === 'databases') { 
        DOM.vDb.classList.remove('sq-hidden'); DOM.bcDb.classList.add('sq-hidden'); DOM.bcTable.classList.add('sq-hidden'); 
    } else if (view === 'tables') { 
        DOM.vTable.classList.remove('sq-hidden'); DOM.bcDb.classList.remove('sq-hidden'); DOM.bcTable.classList.add('sq-hidden'); 
        DOM.bcDb.querySelector('a').innerText = state.db; DOM.bcDb.querySelector('a').onclick = () => loadTables(state.db); 
    } else if (view === 'data') { 
        DOM.vData.classList.remove('sq-hidden'); DOM.bcTable.classList.remove('sq-hidden'); DOM.bcTable.querySelector('span').innerText = state.table; 
        
        document.getElementById('tab-data-btn').style.background = state.tab === 'data' ? '#fff' : 'transparent';
        document.getElementById('tab-data-btn').style.boxShadow = state.tab === 'data' ? '0 1px 2px rgba(0,0,0,0.05)' : 'none';
        document.getElementById('tab-structure-btn').style.background = state.tab === 'structure' ? '#fff' : 'transparent';
        document.getElementById('tab-structure-btn').style.boxShadow = state.tab === 'structure' ? '0 1px 2px rgba(0,0,0,0.05)' : 'none';
        
        if (state.tab === 'data') {
            document.getElementById('toolbar-data').classList.remove('sq-hidden');
        } else {
            document.getElementById('toolbar-data').classList.add('sq-hidden');
            document.getElementById('bulk-action-box').classList.add('sq-hidden');
        }
    }
}

function switchTableTab(tab) {
    state.tab = tab; saveState();
    if(tab === 'data') {
        if(state.isDataLoaded) { showView('data'); applyFilterAndPaginate(); } else loadData(state.db, state.table);
    } else { loadStructure(); }
}

function goToDatabases() { state.db = ''; state.table = ''; state.isDataLoaded = false; loadDatabases(); }
async function loadDatabases() { showLoader('Memuat Databases...'); try { state.dbList = await safeFetchJson('?action=databases'); renderDatabases(state.dbList); showView('databases'); } catch (err) { UIkit.modal.alert(err.message); } finally { hideLoader(); } }
function renderDatabases(dbs) {
    DOM.listDb.innerHTML = dbs.map(db => `
        <div class="sq-card sq-card-interactive" onclick="loadTables('${db}')">
            <div style="font-weight: 600; color: var(--accent); margin-bottom: 4px;">🗄️ ${db}</div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                <span style="font-size: 11px; color: var(--text-muted);">Database</span>
                <a href="?action=backup_db&db=${encodeURIComponent(db)}" class="sq-btn" style="font-size: 11px; padding: 2px 6px;" onclick="event.stopPropagation()">Backup</a>
            </div>
        </div>
    `).join('');
}

async function loadTables(db) { state.db = db; state.table = ''; state.isDataLoaded = false; showLoader(`Memuat Table...`); try { state.tableList = await safeFetchJson(`?action=tables&db=${db}`); renderTables(state.tableList); showView('tables'); } catch (err) { UIkit.modal.alert(err.message); } finally { hideLoader(); } }
function renderTables(tables) {
    DOM.listTable.innerHTML = tables.map(tbl => `
        <div class="sq-card sq-card-interactive" onclick="loadData('${state.db}', '${tbl}')">
            <div style="font-weight: 600; color: #0f172a;">📋 ${tbl}</div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Table</div>
        </div>
    `).join('');
}

async function loadData(db, table) {
    state.db = db; state.table = table; state.page = 1; DOM.searchData.value = '';
    state.isDataLoaded = false; document.getElementById('bulk-action-box').classList.add('sq-hidden');
    if(state.tab === 'structure') { await loadStructure(); showView('data'); return; }
    showLoader(`Menarik data dari ${table}...`);
    try {
        const json = await safeFetchJson(`?action=data&db=${db}&table=${table}`);
        state.pk = json.pk; state.rawColumns = json.columns || []; state.rawData = json.data || [];
        state.isDataLoaded = true; showView('data'); applyFilterAndPaginate();
    } catch (err) { DOM.dataHead.innerHTML = ''; DOM.dataBody.innerHTML = `<tr><td style="color:var(--danger); text-align:center; padding:20px;">Error: ${err.message}</td></tr>`; showView('data'); } finally { hideLoader(); }
}

function toggleInlineQuery() {
    let box = document.getElementById('inline-query-box');
    box.classList.toggle('sq-hidden');
    if(!box.classList.contains('sq-hidden')) {
        document.getElementById('inline-sql-input').value = `SELECT * FROM ${state.table} LIMIT 10;`;
        document.getElementById('inline-sql-input').focus();
    }
}

document.getElementById('inline-sql-input').addEventListener('keydown', e => { 
    if(e.ctrlKey && e.key === 'Enter') { e.preventDefault(); runInlineSql(); }
});

async function runInlineSql() {
    let sql = document.getElementById('inline-sql-input').value.trim(); if(!sql) return;
    showLoader('Eksekusi Query...');
    try {
        let r = await safeFetchJson('?action=query', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ db: state.db, sql: sql }) });
        if(r.type === 'modify') { showToast(`Sukses! Affected: ${r.affected}`); loadData(state.db, state.table); } 
        else { state.rawColumns = r.columns; state.rawData = r.data; applyFilterAndPaginate(); }
    } catch(e) { UIkit.modal.alert(e.message); } finally { hideLoader(); }
}

function exportCurrentTable() {
    let format = document.getElementById('export-format').value;
    window.location.href = `?action=backup_db&db=${encodeURIComponent(state.db)}&table=${encodeURIComponent(state.table)}&format=${format}`;
}

function importTrigger() {
    let input = document.createElement('input');
    input.type = 'file'; input.accept = '.csv, .sql';
    input.onchange = async () => {
        const file = input.files[0]; if(!file) return;
        const formData = new FormData(); formData.append('file', file); formData.append('db', state.db); formData.append('table', state.table);
        showLoader('Mengimpor data...');
        try {
            const json = await safeFetchJson('?action=import_data', { method: 'POST', body: formData });
            showToast(json.msg); loadData(state.db, state.table);
        } catch(err) { UIkit.modal.alert('Gagal import: ' + err.message); hideLoader(); }
    };
    input.click();
}

function openInsertModal() {
    if(!state.rawColumns || state.rawColumns.length === 0) return UIkit.modal.alert("Pilih tabel dulu!");
    let html = state.rawColumns.map(col => `
        <div>
            <label style="font-size: 11px; font-weight: 600; color: var(--text-muted);">${col} ${col === state.pk ? '(Auto Increment)' : ''}</label>
            <input type="text" id="ins_${col}" class="sq-input">
        </div>
    `).join('');
    document.getElementById('insert-form-container').innerHTML = html;
    UIkit.modal('#modal-insert').show();
}

async function submitInsert() {
    let insertData = {};
    state.rawColumns.forEach(col => {
        let val = document.getElementById(`ins_${col}`).value.trim();
        if(val !== '') insertData[col] = val;
    });
    UIkit.modal('#modal-insert').hide(); showLoader('Menyimpan...');
    try {
        await safeFetchJson('?action=insert', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ db: state.db, table: state.table, data: insertData }) });
        showToast('Data berhasil ditambah!'); loadData(state.db, state.table);
    } catch(err) { UIkit.modal.alert(err.message); hideLoader(); }
}

async function loadStructure() {
    showLoader('Struktur Tabel...');
    await sleep(30);
    DOM.dataHead.innerHTML = '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
    try {
        const data = await safeFetchJson(`?action=structure&db=${state.db}&table=${state.table}`);
        DOM.dataBody.innerHTML = data.map(r => `<tr><td><b>${r.Field}</b></td><td><code style="color:var(--accent);">${r.Type}</code></td><td>${r.Null}</td><td style="color:#d97706; font-weight:600;">${r.Key}</td><td>${r.Default !== null ? r.Default : '<i>NULL</i>'}</td><td>${r.Extra}</td></tr>`).join('');
        showView('data');
    } catch(e) { DOM.dataBody.innerHTML = `<tr><td colspan="6" style="color:var(--danger); text-align:center;">${e.message}</td></tr>`; } finally { hideLoader(); }
}

async function jumpToRelated(prefix, idVal) {
    let target = state.tableList.find(t => t.toLowerCase() === prefix || t.toLowerCase() === prefix + 's' || t.toLowerCase() === prefix + 'es');
    if(target) {
        await loadData(state.db, target);
        DOM.searchData.value = idVal; 
        applyFilterAndPaginate(); 
    } else { UIkit.modal.alert("Tabel relasi untuk '" + prefix + "' tidak ditemukan!"); }
}

function applyFilterAndPaginate() {
    const term = DOM.searchData.value.toLowerCase().trim();
    state.filteredData = !term ? state.rawData : state.rawData.filter(row => Object.values(row).some(val => val !== null && val.toString().toLowerCase().includes(term)));
    state.totalPages = Math.max(1, Math.ceil(state.filteredData.length / state.limit));
    if (state.page > state.totalPages) state.page = 1;
    const start = (state.page - 1) * state.limit; const pageData = state.filteredData.slice(start, start + state.limit);
    renderData(state.rawColumns, pageData);
    document.getElementById('page-indicator').innerText = `Page ${state.page} / ${state.totalPages}`;
    document.getElementById('pagination-info').innerText = `Total: ${state.filteredData.length} records`;
}

function renderData(columns, data) {
    let chkHtml = state.pk ? `<th style="width: 30px; text-align:center;"><input type="checkbox" onchange="toggleAllCheck(this)"></th>` : '';
    let headHtml = columns.map(col => `<th>${col}</th>`).join('');
    DOM.dataHead.innerHTML = `<tr>${chkHtml}${headHtml}<th style="text-align:center;">Aksi</th></tr>`;
    
    if (data.length === 0) { DOM.dataBody.innerHTML = `<tr><td colspan="${columns.length+(state.pk?2:1)}" style="text-align:center; color:var(--text-muted); padding:30px;">No records found.</td></tr>`; return; }
    
    DOM.dataBody.innerHTML = data.map(row => {
        const pkVal = state.pk ? row[state.pk] : '';
        const tdChk = state.pk ? `<td style="text-align:center;"><input type="checkbox" class="row-chk" value="${escapeHTML(pkVal)}" onchange="updateBulkUI()"></td>` : '';
        
        const tds = columns.map(col => {
            let valRaw = row[col]; let isFk = col.endsWith('_id') && valRaw !== null && valRaw !== '';
            let inner = valRaw !== null ? escapeHTML(valRaw) : '<span style="color:#94a3b8; font-style:italic;">NULL</span>';
            if (isFk) inner = `<a href="javascript:void(0)" onclick="jumpToRelated('${col.replace('_id','')}', '${valRaw}')" style="color:var(--accent); font-weight:600; text-decoration:underline;" contenteditable="false">${inner}</a>`;
            return `<td tabindex="0" contenteditable="true" data-col="${col}" onfocus="storeOriginal(this)" onblur="updateField(this)">${inner}</td>`;
        }).join('');
        const act = state.pk ? `<button onclick="deleteData('${pkVal}')" class="sq-btn sq-btn-danger" style="padding: 2px 6px; font-size:11px;">Del</button>` : '';
        return `<tr data-pk-val="${escapeHTML(pkVal)}">${tdChk}${tds}<td style="text-align:center;">${act}</td></tr>`;
    }).join('');
    updateBulkUI();
}

function toggleAllCheck(el) {
    document.querySelectorAll('.row-chk').forEach(cb => cb.checked = el.checked);
    updateBulkUI();
}
function getCheckedIds() {
    return Array.from(document.querySelectorAll('.row-chk:checked')).map(cb => cb.value);
}
function updateBulkUI() {
    let ids = getCheckedIds();
    let box = document.getElementById('bulk-action-box');
    if (ids.length > 0) box.classList.remove('sq-hidden'); else box.classList.add('sq-hidden');
}

async function doBulkDelete() {
    let ids = getCheckedIds(); if(!ids.length) return;
    UIkit.modal.confirm(`Hapus ${ids.length} data terpilih?`).then(async () => {
        showLoader('Menghapus...');
        try {
            await safeFetchJson('?action=bulk_delete', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ db: state.db, table: state.table, pk_col: state.pk, ids: ids }) });
            showToast(`${ids.length} data dihapus!`); loadData(state.db, state.table);
        } catch (e) { UIkit.modal.alert(e.message); hideLoader(); }
    }, () => {});
}

async function doBulkCopy() {
    let ids = getCheckedIds(); if(!ids.length) return;
    showLoader('Duplikasi...');
    try {
        let res = await safeFetchJson('?action=bulk_copy', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ db: state.db, table: state.table, pk_col: state.pk, ids: ids }) });
        showToast(`${res.count} data diduplikat!`); loadData(state.db, state.table);
    } catch (e) { UIkit.modal.alert(e.message); hideLoader(); }
}

function doBulkExport() {
    let ids = getCheckedIds(); if(!ids.length) return;
    let format = document.getElementById('bulk-export-format').value;
    let form = document.createElement('form');
    form.method = 'POST'; form.action = '?action=backup_db'; form.target = '_blank';
    form.innerHTML = `
        <input type="hidden" name="db" value="${escapeHTML(state.db)}">
        <input type="hidden" name="table" value="${escapeHTML(state.table)}">
        <input type="hidden" name="format" value="${format}">
        <input type="hidden" name="pk_col" value="${escapeHTML(state.pk)}">
        <input type="hidden" name="ids" value='${escapeHTML(JSON.stringify(ids))}'>
    `;
    document.body.appendChild(form); form.submit(); document.body.removeChild(form);
    showToast('Download dimulai!');
}

document.addEventListener('keydown', e => {
    const el = document.activeElement;
    if(el && el.tagName === 'TD' && el.isContentEditable) {
        if(e.key === 'Escape') { el.innerHTML = el.dataset.original || ''; el.blur(); }
        if(e.ctrlKey) {
            let tr = el.closest('tr');
            let tds = Array.from(tr.querySelectorAll('td[contenteditable="true"]'));
            let idx = tds.indexOf(el);
            
            if(e.key === 'ArrowLeft' && tds[idx-1]) tds[idx-1].focus();
            if(e.key === 'ArrowRight' && tds[idx+1]) tds[idx+1].focus();
            if(e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                let targetTr = e.key === 'ArrowUp' ? tr.previousElementSibling : tr.nextElementSibling;
                if(targetTr) {
                    let targetTds = Array.from(targetTr.querySelectorAll('td[contenteditable="true"]'));
                    if(targetTds[idx]) { targetTds[idx].focus(); e.preventDefault(); }
                }
            }
        }
    }
});

function storeOriginal(el) { if(el.innerText === 'NULL' || el.querySelector('a')) { el.dataset.original = el.innerHTML; } else { el.dataset.original = el.innerText; } }

async function updateField(el) {
    let newVal = el.innerText.trim(); let oldVal = el.dataset.original; 
    if (el.innerHTML === oldVal) return;
    if (!state.pk) { UIkit.modal.alert("Primary key tidak ditemukan!"); el.innerHTML = oldVal; return; }
    
    const pkVal = el.closest('tr').dataset.pkVal; const col = el.dataset.col;
    el.contentEditable = "false";
    try {
        await safeFetchJson('?action=update', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ db: state.db, table: state.table, pk_col: state.pk, pk_val: pkVal, col: col, val: newVal }) });
        showToast('Tersimpan!');
        if (col === state.pk) el.closest('tr').dataset.pkVal = newVal;
        const trg = state.rawData.find(r => r[state.pk] == pkVal); if (trg) trg[col] = newVal;
        
        if(col.endsWith('_id')) el.innerHTML = `<a href="javascript:void(0)" onclick="jumpToRelated('${col.replace('_id','')}', '${newVal}')" style="color:var(--accent); font-weight:600; text-decoration:underline;" contenteditable="false">${newVal}</a>`;
        else el.innerHTML = escapeHTML(newVal);
    } catch (err) { UIkit.modal.alert(err.message); el.innerHTML = oldVal; }
    el.contentEditable = "true";
}

async function deleteData(pkVal) {
    UIkit.modal.confirm(`Hapus baris PK = ${pkVal}?`).then(async () => {
        showLoader('Menghapus...');
        try {
            await safeFetchJson('?action=delete', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ db: state.db, table: state.table, pk_col: state.pk, pk_val: pkVal }) });
            state.rawData = state.rawData.filter(r => r[state.pk] != pkVal); applyFilterAndPaginate(); showToast('Dihapus!');
        } catch (e) { UIkit.modal.alert(e.message); } finally { hideLoader(); }
    }, () => {});
}

async function manageTable(actionType) {
    let body = { db: state.db, table: state.table }; let actUrl = `?action=${actionType}_table`;
    if(actionType === 'rename') {
        let newName = prompt(`Rename tabel '${state.table}' ke:`, state.table);
        if(!newName || newName === state.table) return; body.new_name = newName;
    } else if(actionType === 'truncate') {
        UIkit.modal.confirm(`⚠ Kosongkan seluruh data di '${state.table}'?`).then(() => execManageTable(actUrl, body, actionType), () => {});
        return;
    } else if(actionType === 'drop') {
        let conf = prompt(`☠️ Ketik nama tabel '${state.table}' untuk konfirmasi DROP:`);
        if(conf !== state.table) { UIkit.modal.alert("Dibatalkan."); return; }
    }
    execManageTable(actUrl, body, actionType);
}

async function execManageTable(actUrl, body, actionType) {
    showLoader('Memproses...');
    try {
        await safeFetchJson(actUrl, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body) });
        showToast('Berhasil!');
        if(actionType === 'drop' || actionType === 'rename') loadTables(state.db); else loadData(state.db, state.table);
    } catch(e) { UIkit.modal.alert(e.message); } finally { hideLoader(); }
}

function openCreateTableModal() {
    document.getElementById('new-tbl-name').value = ''; 
    document.getElementById('create-cols-container').innerHTML = '';
    addCreateColRow(); addCreateColRow(); addCreateColRow(); 
    UIkit.modal('#modal-create-table').show();
}

function addCreateColRow() {
    document.getElementById('create-cols-container').insertAdjacentHTML('beforeend', `
    <div style="display: grid; grid-template-columns: 2fr 2fr 1fr 40px 40px 40px; gap: 8px; align-items: center;" class="col-row">
        <div><input type="text" class="sq-input c-name" placeholder="nama_kolom" style="height:32px;"></div>
        <div>
            <select class="sq-select c-type" style="height:32px; padding:0 4px;">
                <option value="INT">INT</option><option value="VARCHAR">VARCHAR</option><option value="TEXT">TEXT</option><option value="DATE">DATE</option><option value="DATETIME">DATETIME</option>
            </select>
        </div>
        <div><input type="number" class="sq-input c-len" placeholder="255" style="height:32px;"></div>
        <div style="text-align:center"><input type="checkbox" class="c-ai"></div>
        <div style="text-align:center"><input type="checkbox" class="c-pk"></div>
        <div><button onclick="this.closest('.col-row').remove()" class="sq-btn sq-btn-danger" style="padding:4px 8px;">X</button></div>
    </div>`);
}

async function executeCreateTable() {
    let tName = document.getElementById('new-tbl-name').value.trim(); if(!tName) return UIkit.modal.alert("Nama tabel wajib diisi!");
    let cols = [];
    document.querySelectorAll('.col-row').forEach(row => {
        let name = row.querySelector('.c-name').value.trim();
        if(name) cols.push({ name: name, type: row.querySelector('.c-type').value, len: row.querySelector('.c-len').value, ai: row.querySelector('.c-ai').checked, pk: row.querySelector('.c-pk').checked });
    });
    if(cols.length === 0) return UIkit.modal.alert("Minimal 1 kolom!");
    
    showLoader("Membuat Tabel...");
    try {
        await safeFetchJson('?action=create_table', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ db: state.db, name: tName, cols: cols }) }); 
        UIkit.modal('#modal-create-table').hide(); showToast("Tabel dibuat!"); loadTables(state.db); 
    } catch(e) { UIkit.modal.alert(e.message); } finally { hideLoader(); }
}

async function generateFaker() {
    let qty = prompt("Jumlah baris data dummy (max 1000):", "50");
    if(!qty || isNaN(qty) || qty <= 0) return;
    showLoader(`Generating ${qty} baris...`);
    try {
        let res = await safeFetchJson('?action=generate_dummy', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ db: state.db, table: state.table, rows: qty }) });
        showToast(`${res.count} Dummy Data masuk!`); loadData(state.db, state.table);
    } catch(e) { UIkit.modal.alert(e.message); hideLoader(); }
}

function openMonitorModal() { UIkit.modal('#modal-monitor').show(); loadMonitor(); }
async function loadMonitor() {
    const head = document.getElementById('monitor-head'); const body = document.getElementById('monitor-body');
    body.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:20px;">Loading...</td></tr>';
    try {
        let res = await safeFetchJson('?action=monitor'); let procs = res.processes;
        if(procs.length === 0) return body.innerHTML = '<tr><td colspan="10" style="text-align:center;">Kosong</td></tr>';
        let keys = Object.keys(procs[0]);
        head.innerHTML = keys.map(k => `<th>${k}</th>`).join('') + `<th>Aksi</th>`;
        body.innerHTML = procs.map(p => `<tr>` + keys.map(k => `<td>${escapeHTML(p[k] || '')}</td>`).join('') + `<td><button onclick="killProcess(${p.Id})" class="sq-btn sq-btn-danger" style="padding:2px 6px; font-size:11px;">KILL</button></td></tr>`).join('');
    } catch(e) { body.innerHTML = `<tr><td colspan="10" style="color:var(--danger);">${e.message}</td></tr>`; }
}
async function killProcess(id) {
    UIkit.modal.confirm(`Kill process ID ${id}?`).then(async () => {
        try {
            await safeFetchJson('?action=kill_process', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id: id }) });
            loadMonitor(); showToast(`Process ${id} killed!`);
        } catch(e) { UIkit.modal.alert(e.message); }
    }, () => {});
}

function openLogViewer() { UIkit.modal('#modal-log').show(); loadLogContent(); }
async function loadLogContent() {
    let f = document.getElementById('log-file-select').value;
    let b = document.getElementById('log-content-box');
    b.innerText = "Loading...";
    try {
        let r = await safeFetchJson(`?action=get_log&file=${f}`);
        b.innerText = r.content; b.scrollTop = b.scrollHeight;
    } catch(e) { b.innerText = "Error: " + e.message; }
}
async function clearCurrentLog() {
    let f = document.getElementById('log-file-select').value;
    UIkit.modal.confirm(`Clear ${f}.log?`).then(async () => {
        try {
            await safeFetchJson('?action=clear_log', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ file: f }) });
            loadLogContent(); showToast('Cleared!');
        } catch(e) { UIkit.modal.alert(e.message); }
    }, () => {});
}

let sqlHistory = JSON.parse(localStorage.getItem('sqlHistory') || '[]'); let sqlIdx = -1;
function openSqlModal() { UIkit.modal('#modal-sql').show(); sqlIdx = sqlHistory.length; setTimeout(() => document.getElementById('sql-input').focus(), 100); }

document.getElementById('sql-input').addEventListener('keydown', e => { 
    if(e.ctrlKey && e.key === 'Enter') { e.preventDefault(); runRawSql(); }
    if(e.key === 'ArrowUp' && sqlIdx > 0) { sqlIdx--; e.target.value = sqlHistory[sqlIdx]; }
    if(e.key === 'ArrowDown') { if(sqlIdx < sqlHistory.length-1) { sqlIdx++; e.target.value = sqlHistory[sqlIdx]; } else { sqlIdx=sqlHistory.length; e.target.value=''; } }
});

async function runRawSql() {
    let sql = document.getElementById('sql-input').value.trim(); if(!sql) return;
    if(sqlHistory[sqlHistory.length-1] !== sql) { sqlHistory.push(sql); if(sqlHistory.length > 20) sqlHistory.shift(); localStorage.setItem('sqlHistory', JSON.stringify(sqlHistory)); }
    sqlIdx = sqlHistory.length;
    let b = document.getElementById('sql-result-container'); showLoader('Eksekusi Query...');
    try {
        let r = await safeFetchJson('?action=query', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ db: state.db, sql: sql }) });
        if(r.type === 'modify') { b.innerHTML = `<div style="padding: 10px; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius:4px;">✅ Sukses! Affected rows: ${r.affected}</div>`; }
        else {
            if(r.data.length===0){ b.innerHTML = `<div style="text-align:center; padding:15px; color:var(--text-muted);">0 rows returned</div>`; hideLoader(); return; }
            let h = `<table class="sq-table"><thead><tr>` + r.columns.map(c=>`<th>${c}</th>`).join('') + `</tr></thead><tbody>`;
            r.data.forEach(row => { h += `<tr>` + r.columns.map(c=>`<td>${row[c]!==null?escapeHTML(row[c]):'<span style="color:#94a3b8">NULL</span>'}</td>`).join('') + `</tr>`; });
            b.innerHTML = h + `</tbody></table>`;
        }
    } catch(e) { b.innerHTML = `<div style="padding: 10px; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; border-radius:4px;">${e.message}</div>`; } finally { hideLoader(); }
}

DOM.searchData.addEventListener('input', () => { state.page = 1; applyFilterAndPaginate(); });
document.getElementById('search-db').addEventListener('input', e => renderDatabases(state.dbList.filter(db => db.toLowerCase().includes(e.target.value.toLowerCase()))));
document.getElementById('search-table').addEventListener('input', e => renderTables(state.tableList.filter(tbl => tbl.toLowerCase().includes(e.target.value.toLowerCase()))));
function changePage(d) { let n = state.page + d; if (n >= 1 && n <= state.totalPages) { state.page = n; applyFilterAndPaginate(); } }

if (!document.getElementById('global-loader').classList.contains('hide-loader')) hideLoader();
const inlineSqlInput = document.getElementById('inline-sql-input');
if (inlineSqlInput) {
    inlineSqlInput.addEventListener('keydown', e => { 
        if(e.ctrlKey && e.key === 'Enter') { e.preventDefault(); runInlineSql(); }
    });
}

if (DOM.searchData) {
    DOM.searchData.addEventListener('input', () => { state.page = 1; applyFilterAndPaginate(); });
}

const searchDbEl = document.getElementById('search-db');
if (searchDbEl) {
    searchDbEl.addEventListener('input', e => renderDatabases(state.dbList.filter(db => db.toLowerCase().includes(e.target.value.toLowerCase()))));
}

const searchTableEl = document.getElementById('search-table');
if (searchTableEl) {
    searchTableEl.addEventListener('input', e => renderTables(state.tableList.filter(tbl => tbl.toLowerCase().includes(e.target.value.toLowerCase()))));
}

function changePage(d) { 
    let n = state.page + d; 
    if (n >= 1 && n <= state.totalPages) { 
        state.page = n; 
        applyFilterAndPaginate(); 
    } 
}

const globalLoader = document.getElementById('global-loader');
if (globalLoader && !globalLoader.classList.contains('hide-loader')) {
    hideLoader();
}

restoreState();