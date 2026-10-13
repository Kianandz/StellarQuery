<header class="sq-navbar">
    <div class="sq-brand">
        <span>StellarQuery</span>
        <span style="background: #1e293b; color: #94a3b8; padding: 1px 6px; border-radius: 3px; font-size: 10px;">DB Manager</span>
    </div>
    <div class="sq-nav-items">
        <ul id="breadcrumb" class="sq-flex sq-gap-2" style="list-style: none; font-size: 12px; color: #94a3b8; margin: 0;">
            <li><a href="javascript:void(0)" onclick="goToDatabases()" style="color: #38bdf8; text-decoration: none; font-weight: 600;">Databases</a></li>
            <li id="bc-db" class="sq-hidden">/ <a href="javascript:void(0)" style="color: #38bdf8; text-decoration: none;"></a></li>
            <li id="bc-table" class="sq-hidden">/ <span style="color: #fff; font-weight: 600;"></span></li>
        </ul>
        <button onclick="openMonitorModal()" class="sq-btn">Monitor</button>
        <button onclick="openLogViewer()" class="sq-btn">Logs</button>
        <button onclick="openSqlModal()" class="sq-btn" style="background: #b45309; border-color: #d97706; color: #fff;">SQL Query</button>
        <a href="?logout=1" class="sq-btn sq-btn-danger">Logout</a>
    </div>
</header>

<main style="padding: 16px; flex-grow: 1; display: flex; flex-direction: column;">
    
    <!-- VIEW DATABASES -->
    <div id="view-databases" style="width: 100%;">
        <div class="sq-margin-bottom">
            <input type="text" id="search-db" placeholder="Cari database..." class="sq-input" style="max-width: 100%;">
        </div>
        <div id="list-db" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;"></div>
    </div>

    <!-- VIEW TABLES -->
    <div id="view-tables" class="sq-hidden" style="width: 100%;">
        <div class="sq-flex sq-flex-between sq-margin-bottom sq-gap-2">
            <input type="text" id="search-table" placeholder="Cari tabel..." class="sq-input" style="flex-grow: 1;">
            <button onclick="openCreateTableModal()" class="sq-btn sq-btn-primary" style="white-space: nowrap;">+ New Table</button>
        </div>
        <div id="list-table" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px;"></div>
    </div>

    <!-- VIEW DATA -->
    <div id="view-data" class="sq-hidden" style="width: 100%; display: flex; flex-direction: column; flex-grow: 1;">
        
        <!-- TOOLBAR ATAS -->
        <div class="sq-card sq-flex sq-flex-between sq-margin-bottom" style="padding: 8px 12px; background: #fff;">
            <div class="sq-flex sq-gap-2">
                <div class="sq-flex" style="background: var(--bg-subtle); padding: 2px; border-radius: 4px; border: 1px solid var(--border-color);">
                    <button onclick="switchTableTab('data')" id="tab-data-btn" class="sq-btn" style="border: none; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.05); color: #000;">Data</button>
                    <button onclick="switchTableTab('structure')" id="tab-structure-btn" class="sq-btn" style="border: none; background: transparent; color: #000;">Structure</button>
                </div>
                <div class="sq-flex">
                    <button class="sq-btn" type="button" uk-toggle="target: #manage-dropdown">Manage ▾</button>
                    <div id="manage-dropdown" uk-dropdown="mode: click" style="padding: 0;">
                        <ul class="sq-card" style="padding: 4px; min-width: 130px; list-style: none;">
                            <li><a href="javascript:void(0)" onclick="manageTable('rename')" style="display: block; padding: 4px 8px; color: inherit; text-decoration: none;">Rename</a></li>
                            <li><a href="javascript:void(0)" onclick="manageTable('truncate')" style="display: block; padding: 4px 8px; color: #d97706; text-decoration: none;">Truncate</a></li>
                            <li><hr style="border: none; border-top: 1px solid var(--border-color); margin: 4px 0;"></li>
                            <li><a href="javascript:void(0)" onclick="manageTable('drop')" style="display: block; padding: 4px 8px; color: var(--danger); text-decoration: none; font-weight: 600;">Drop Table</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div id="toolbar-data" class="sq-flex sq-gap-2">
                <input type="text" id="search-data" placeholder="Cari data (Ctrl+F)..." class="sq-input" style="width: 180px; height: 32px;">
                <button onclick="toggleInlineQuery()" class="sq-btn">Query</button>
                <button onclick="openInsertModal()" class="sq-btn sq-btn-primary">+ Add Row</button>
                <button onclick="generateFaker()" class="sq-btn" style="background: #7c3aed; border-color: #7c3aed; color: #fff;">Faker</button>
                <select id="export-format" class="sq-select" style="width: 70px; height: 32px; padding: 0 4px;">
                    <option value="sql">SQL</option>
                    <option value="csv">CSV</option>
                    <option value="json">JSON</option>
                </select>
                <button onclick="exportCurrentTable()" class="sq-btn" style="background: #ea580c; border-color: #ea580c; color: #fff;">Export</button>
                <button onclick="importTrigger()" class="sq-btn" style="background: #0284c7; border-color: #0284c7; color: #fff;">Import</button>
            </div>
        </div>

        <!-- INLINE QUERY BOX -->
        <div id="inline-query-box" class="sq-card sq-hidden sq-margin-bottom" style="background: #09090b; color: #f8fafc; border-color: #27272a;">
            <label style="font-size: 11px; color: #94a3b8; display: block; margin-bottom: 4px;">Inline SQL (Ctrl+Enter):</label>
            <textarea id="inline-sql-input" rows="2" class="sq-textarea" style="background: #18181b; color: #38bdf8; border-color: #27272a; font-family: monospace;"></textarea>
            <div style="text-align: right; margin-top: 8px;">
                <button onclick="runInlineSql()" class="sq-btn sq-btn-primary">Execute Query</button>
            </div>
        </div>

        <!-- BULK ACTION BAR -->
        <div id="bulk-action-box" class="sq-card sq-hidden sq-flex sq-gap-2 sq-margin-bottom" style="background: #f0fdf4; border-color: #bbf7d0;">
            <span style="font-weight: 600; color: #166534; font-size: 12px;">Bulk Actions:</span>
            <button onclick="doBulkDelete()" class="sq-btn sq-btn-danger">Delete Selected</button>
            <button onclick="doBulkCopy()" class="sq-btn" style="background: #0d9488; border-color: #0d9488; color: #fff;">Copy Selected</button>
            <select id="bulk-export-format" class="sq-select" style="width: 70px; height: 30px;">
                <option value="sql">SQL</option>
                <option value="csv">CSV</option>
                <option value="json">JSON</option>
            </select>
            <button onclick="doBulkExport()" class="sq-btn" style="background: #ea580c; border-color: #ea580c; color: #fff;">Export Selected</button>
        </div>

        <!-- TABEL UTAMA -->
        <div class="sq-table-wrapper" style="flex-grow: 1;">
            <table class="sq-table">
                <thead id="data-head"></thead>
                <tbody id="data-body"></tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <div class="sq-card sq-flex sq-flex-between" style="padding: 8px 12px; margin-top: 8px; background: #fff;">
            <div id="pagination-info" style="color: var(--text-muted); font-size: 12px;">Showing...</div>
            <div class="sq-flex sq-gap-2">
                <button id="btn-prev" onclick="changePage(-1)" class="sq-btn">Prev</button>
                <span id="page-indicator" class="sq-btn" style="background: #f8fafc; color: var(--text-muted); cursor: default;">Page 1</span>
                <button id="btn-next" onclick="changePage(1)" class="sq-btn">Next</button>
            </div>
        </div>
    </div>
</main>

<!-- MODALS (CLEAN & MINIMAL) -->
<div id="modal-create-table" uk-modal>
    <div class="uk-modal-dialog uk-modal-body sq-card" style="width: 700px;">
        <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 12px;">Create New Table</h3>
        <div class="sq-margin-bottom">
            <label style="font-size: 11px; font-weight: 600; color: var(--text-muted);">Table Name:</label>
            <input type="text" id="new-tbl-name" class="sq-input" placeholder="e.g. users">
        </div>
        <div style="background: var(--bg-subtle); padding: 12px; border-radius: 4px; border: 1px solid var(--border-color);">
            <div style="display: grid; grid-template-columns: 2fr 2fr 1fr 40px 40px 40px; gap: 8px; font-weight: 600; font-size: 11px; color: var(--text-muted); margin-bottom: 8px;">
                <div>Column</div><div>Type</div><div>Length</div><div style="text-align:center">AI</div><div style="text-align:center">PK</div><div></div>
            </div>
            <div id="create-cols-container" style="display: flex; flex-direction: column; gap: 6px;"></div>
            <button onclick="addCreateColRow()" class="sq-btn" style="width: 100%; margin-top: 8px; justify-content: center;">+ Add Column</button>
        </div>
        <div style="text-align: right; margin-top: 16px;" class="sq-flex sq-gap-2" style="justify-content: flex-end;">
            <button class="sq-btn uk-modal-close">Cancel</button>
            <button onclick="executeCreateTable()" class="sq-btn sq-btn-primary">Save Table</button>
        </div>
    </div>
</div>

<div id="modal-monitor" uk-modal>
    <div class="uk-modal-dialog uk-modal-body sq-card" style="width: 85vw;">
        <div class="sq-flex sq-flex-between sq-margin-bottom">
            <h3 style="font-size: 15px; font-weight: 600;">Server Process Monitor</h3>
            <button onclick="loadMonitor()" class="sq-btn">Refresh</button>
        </div>
        <div class="sq-table-wrapper" style="max-height: 50vh;">
            <table class="sq-table">
                <thead><tr id="monitor-head"></tr></thead>
                <tbody id="monitor-body"></tbody>
            </table>
        </div>
        <div style="text-align: right; margin-top: 12px;"><button class="sq-btn uk-modal-close">Close</button></div>
    </div>
</div>

<div id="modal-sql" uk-modal>
    <div class="uk-modal-dialog uk-modal-body sq-card" style="width: 750px;">
        <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 8px;">SQL Raw Editor</h3>
        <textarea id="sql-input" rows="4" class="sq-textarea font-mono" placeholder="SELECT * FROM..."></textarea>
        <div style="text-align: right; margin-top: 8px;">
            <button onclick="runRawSql()" class="sq-btn sq-btn-primary">Run Query (Ctrl+Enter)</button>
        </div>
        <div id="sql-result-container" class="sq-table-wrapper" style="margin-top: 12px; max-height: 250px;"></div>
        <div style="text-align: right; margin-top: 12px;"><button class="sq-btn uk-modal-close">Close</button></div>
    </div>
</div>

<div id="modal-log" uk-modal>
    <div class="uk-modal-dialog uk-modal-body sq-card" style="width: 750px;">
        <div class="sq-flex sq-flex-between sq-margin-bottom">
            <h3 style="font-size: 15px; font-weight: 600;">System Logs</h3>
            <div class="sq-flex sq-gap-2">
                <select id="log-file-select" onchange="loadLogContent()" class="sq-select" style="height: 32px;">
                    <option value="sql">sql.log</option>
                    <option value="login">login.log</option>
                </select>
                <button onclick="loadLogContent()" class="sq-btn">Refresh</button>
                <button onclick="clearCurrentLog()" class="sq-btn sq-btn-danger">Clear</button>
            </div>
        </div>
        <pre id="log-content-box" style="background: #09090b; color: #38bdf8; padding: 12px; border-radius: 4px; height: 320px; overflow: auto; font-size: 11px; font-family: monospace;"></pre>
        <div style="text-align: right; margin-top: 12px;"><button class="sq-btn uk-modal-close">Close</button></div>
    </div>
</div>

<div id="modal-insert" uk-modal>
    <div class="uk-modal-dialog uk-modal-body sq-card" style="width: 500px;">
        <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 12px;">Insert Row</h3>
        <div id="insert-form-container" style="max-height: 50vh; overflow-y: auto; display: flex; flex-direction: column; gap: 8px;"></div>
        <div style="text-align: right; margin-top: 16px;" class="sq-flex sq-flex-between">
            <button class="sq-btn uk-modal-close">Cancel</button>
            <button onclick="submitInsert()" class="sq-btn sq-btn-primary">Save Row</button>
        </div>
    </div>
</div>