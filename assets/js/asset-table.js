/**
 * Asset Management - Full-screen table view
 *
 * Client-side filter/sort/search/export for snappy interaction. All driven
 * by a single COLUMNS catalogue — adding a new column later is just:
 *   1. Append to COLUMNS below (with key + label + optional type/formatter)
 *   2. Ensure get_assets.php returns the field
 * Sort, distinct-value filter, search, CSV and PDF export will all "just work".
 *
 * Persistence: column visibility, order and sort live in user_preferences
 * (key = "asset_table_v1") via api/system/get_user_preference.php and
 * api/system/set_user_preference.php. Search and active filters are
 * transient session state — they don't persist across page loads.
 */

(function () {
    'use strict';

    const PREF_KEY = 'asset_table_v1';

    // --- Column catalogue ---------------------------------------------
    // Single source of truth. type = 'string' | 'number' (drives sort
    // comparison and PDF/CSV formatting). format() optionally massages
    // the raw value into a display string (also used for filter
    // distinct-values and export — so the user sees consistent text
    // everywhere).
    const COLUMNS = [
        { key: 'hostname',          label: 'Hostname',        type: 'string', defaultVisible: true,  defaultOrder: 0  },
        { key: 'asset_type_name',   label: 'Type',            type: 'string', defaultVisible: true,  defaultOrder: 1  },
        { key: 'asset_status_name', label: 'Status',          type: 'string', defaultVisible: true,  defaultOrder: 2  },
        { key: 'manufacturer',      label: 'Manufacturer',    type: 'string', defaultVisible: true,  defaultOrder: 3  },
        { key: 'model',             label: 'Model',           type: 'string', defaultVisible: true,  defaultOrder: 4  },
        { key: 'operating_system',  label: 'OS',              type: 'string', defaultVisible: true,  defaultOrder: 5  },
        { key: 'feature_release',   label: 'Feature release', type: 'string', defaultVisible: false, defaultOrder: 6  },
        { key: 'build_number',      label: 'Build',           type: 'string', defaultVisible: false, defaultOrder: 7  },
        { key: 'service_tag',       label: 'Service tag',     type: 'string', defaultVisible: false, defaultOrder: 8  },
        { key: 'cpu_name',          label: 'CPU',             type: 'string', defaultVisible: false, defaultOrder: 9  },
        { key: 'speed',             label: 'CPU speed',       type: 'number', defaultVisible: false, defaultOrder: 10 },
        { key: 'memory',            label: 'Memory',          type: 'number', defaultVisible: false, defaultOrder: 11 },
        { key: 'bios_version',      label: 'BIOS',            type: 'string', defaultVisible: false, defaultOrder: 12 },
        { key: 'user_count',        label: 'Assigned users',  type: 'number', defaultVisible: true,  defaultOrder: 13 },
    ];

    const COL_BY_KEY = Object.fromEntries(COLUMNS.map(c => [c.key, c]));

    // --- State --------------------------------------------------------
    let allAssets = [];                 // everything from the server
    let columnState = [];               // [{ key, visible }] in display order
    let sort = { key: 'hostname', dir: 'asc' };
    let filters = {};                   // { col_key: Set([allowed values...]) }
    let searchTerm = '';
    let openPopover = null;             // currently-open filter/columns dropdown

    // --- Boot ---------------------------------------------------------
    document.addEventListener('DOMContentLoaded', async () => {
        // Default column state
        columnState = COLUMNS.slice().sort((a, b) => a.defaultOrder - b.defaultOrder)
            .map(c => ({ key: c.key, visible: c.defaultVisible }));

        await loadPreferences();
        await loadAssets();

        renderTable();
        wireToolbar();
    });

    function wireToolbar() {
        document.getElementById('atSearch').addEventListener('input', e => {
            searchTerm = e.target.value.trim().toLowerCase();
            renderBody();
        });
        document.getElementById('atColumnsBtn').addEventListener('click', e => {
            e.stopPropagation();
            openColumnsDrawer(e.currentTarget);
        });
        document.getElementById('atResetBtn').addEventListener('click', () => {
            filters = {};
            searchTerm = '';
            sort = { key: 'hostname', dir: 'asc' };
            document.getElementById('atSearch').value = '';
            closeOpenPopover();
            renderTable();
            savePreferences();
        });
        // Close any open popover on outside click / Esc
        document.addEventListener('click', e => {
            if (openPopover && !openPopover.contains(e.target)) closeOpenPopover();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeOpenPopover();
        });
    }

    // --- Data loading -------------------------------------------------
    async function loadAssets() {
        try {
            const res = await fetch('../api/assets/get_assets.php');
            const data = await res.json();
            if (data.success) {
                allAssets = data.assets || [];
            } else {
                console.error('get_assets:', data.error);
                allAssets = [];
            }
        } catch (e) {
            console.error('get_assets:', e);
            allAssets = [];
        }
    }

    // --- Preferences (persisted) --------------------------------------
    async function loadPreferences() {
        try {
            const res = await fetch(`../api/system/get_user_preference.php?key=${encodeURIComponent(PREF_KEY)}`);
            const data = await res.json();
            if (!data.success || !data.value) return;
            const parsed = JSON.parse(data.value);

            // Merge saved column order/visibility with the catalogue so that
            // newly-added columns appear (with their defaults) without nuking
            // the saved layout.
            if (Array.isArray(parsed.cols)) {
                const known = new Set(COLUMNS.map(c => c.key));
                const savedKeys = new Set();
                const merged = [];
                parsed.cols.forEach(c => {
                    if (known.has(c.k)) {
                        merged.push({ key: c.k, visible: c.v !== 0 });
                        savedKeys.add(c.k);
                    }
                });
                // Append any columns the user has never seen (defaults).
                COLUMNS.slice().sort((a, b) => a.defaultOrder - b.defaultOrder)
                    .forEach(c => {
                        if (!savedKeys.has(c.key)) {
                            merged.push({ key: c.key, visible: c.defaultVisible });
                        }
                    });
                columnState = merged;
            }
            if (parsed.sort && COL_BY_KEY[parsed.sort.k]) {
                sort = { key: parsed.sort.k, dir: parsed.sort.d === 'desc' ? 'desc' : 'asc' };
            }
        } catch (e) {
            // No saved prefs or parse error — silently use defaults
        }
    }

    let saveTimer = null;
    function savePreferences() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            const payload = JSON.stringify({
                cols: columnState.map(c => ({ k: c.key, v: c.visible ? 1 : 0 })),
                sort: { k: sort.key, d: sort.dir }
            });
            fetch('../api/system/set_user_preference.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ key: PREF_KEY, value: payload })
            }).catch(e => console.error('save prefs:', e));
        }, 400);
    }

    // --- Rendering ----------------------------------------------------
    function visibleColumns() {
        return columnState.filter(c => c.visible).map(c => COL_BY_KEY[c.key]).filter(Boolean);
    }

    function renderTable() {
        renderHead();
        renderBody();
    }

    function renderHead() {
        const head = document.getElementById('atHead');
        const cols = visibleColumns();
        const tr = cols.map(col => {
            const isSorted = sort.key === col.key;
            const arrow = isSorted ? (sort.dir === 'asc' ? '▲' : '▼') : '↕';
            const sortedClass = isSorted ? ' sorted' : '';
            const hasFilter = filters[col.key] && filters[col.key].size > 0;
            const filterClass = hasFilter ? ' active' : '';
            return `
                <th data-col-key="${esc(col.key)}" draggable="true">
                    <div class="at-th-content${sortedClass}">
                        <span class="at-th-label">${esc(col.label)}</span>
                        <span class="at-sort-arrow">${arrow}</span>
                        <button type="button" class="at-filter-btn${filterClass}" title="Filter ${esc(col.label)}" data-filter-key="${esc(col.key)}" aria-label="Filter">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                            </svg>
                        </button>
                    </div>
                </th>`;
        }).join('');
        head.innerHTML = `<tr>${tr}</tr>`;

        // Wire header click → sort, filter button → popover, drag-reorder
        head.querySelectorAll('th').forEach(th => {
            const key = th.dataset.colKey;
            th.querySelector('.at-th-content').addEventListener('click', e => {
                // Ignore clicks that originated inside the filter button
                if (e.target.closest('.at-filter-btn')) return;
                toggleSort(key);
            });
            th.querySelector('.at-filter-btn').addEventListener('click', e => {
                e.stopPropagation();
                openFilterDropdown(key, e.currentTarget);
            });
            // Drag and drop to reorder columns
            th.addEventListener('dragstart', e => {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', key);
                th.classList.add('at-dragging');
            });
            th.addEventListener('dragend', () => {
                th.classList.remove('at-dragging');
                head.querySelectorAll('th').forEach(t => t.classList.remove('at-drag-over'));
            });
            th.addEventListener('dragover', e => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                head.querySelectorAll('th').forEach(t => t.classList.remove('at-drag-over'));
                th.classList.add('at-drag-over');
            });
            th.addEventListener('drop', e => {
                e.preventDefault();
                const from = e.dataTransfer.getData('text/plain');
                if (from && from !== key) reorderColumn(from, key);
            });
        });
    }

    function renderBody() {
        const body = document.getElementById('atBody');
        const cols = visibleColumns();
        const rows = applyFiltersAndSort();

        document.getElementById('atCount').textContent =
            rows.length === allAssets.length
                ? `${rows.length} asset${rows.length === 1 ? '' : 's'}`
                : `${rows.length} of ${allAssets.length}`;

        if (rows.length === 0) {
            body.innerHTML = `<tr><td colspan="${cols.length || 1}" class="at-empty">No assets match the current filters.</td></tr>`;
            return;
        }

        body.innerHTML = rows.map(row => {
            const tds = cols.map(col => {
                const display = formatCell(col, row[col.key]);
                return `<td title="${esc(display)}">${esc(display)}</td>`;
            }).join('');
            return `<tr class="at-clickable" data-asset-id="${row.id}">${tds}</tr>`;
        }).join('');

        // Click a row → jump to the split-pane view for that asset (deep-link).
        body.querySelectorAll('tr.at-clickable').forEach(tr => {
            tr.addEventListener('click', () => {
                window.location.href = `index.php?asset=${tr.dataset.assetId}`;
            });
        });
    }

    function formatCell(col, raw) {
        if (raw === null || raw === undefined) return '';
        if (typeof col.format === 'function') return col.format(raw) || '';
        return String(raw);
    }

    // --- Filtering / sorting -----------------------------------------
    function applyFiltersAndSort() {
        const cols = visibleColumns();
        const search = searchTerm;
        let rows = allAssets;

        // Per-column tickbox filter: row passes if its column's display value
        // is in the allowed set. An empty (cleared) set means "no filter".
        rows = rows.filter(row => {
            for (const colKey in filters) {
                const allowed = filters[colKey];
                if (!allowed || allowed.size === 0) continue;
                const col = COL_BY_KEY[colKey];
                if (!col) continue;
                const display = formatCell(col, row[colKey]);
                if (!allowed.has(display)) return false;
            }
            return true;
        });

        // Global search: substring match against any VISIBLE column's display.
        if (search) {
            rows = rows.filter(row => {
                for (const col of cols) {
                    const display = formatCell(col, row[col.key]).toLowerCase();
                    if (display.indexOf(search) !== -1) return true;
                }
                return false;
            });
        }

        // Sort.
        const sortCol = COL_BY_KEY[sort.key];
        if (sortCol) {
            const dir = sort.dir === 'desc' ? -1 : 1;
            const isNum = sortCol.type === 'number';
            rows = rows.slice().sort((a, b) => {
                const va = a[sort.key], vb = b[sort.key];
                if (va === null || va === undefined) return 1;
                if (vb === null || vb === undefined) return -1;
                if (isNum) return (Number(va) - Number(vb)) * dir;
                return String(va).localeCompare(String(vb), undefined, { sensitivity: 'base' }) * dir;
            });
        }

        return rows;
    }

    function toggleSort(key) {
        if (sort.key === key) {
            sort.dir = sort.dir === 'asc' ? 'desc' : 'asc';
        } else {
            sort = { key, dir: 'asc' };
        }
        renderTable();
        savePreferences();
    }

    function reorderColumn(fromKey, toKey) {
        const fromIdx = columnState.findIndex(c => c.key === fromKey);
        const toIdx = columnState.findIndex(c => c.key === toKey);
        if (fromIdx < 0 || toIdx < 0) return;
        const [moved] = columnState.splice(fromIdx, 1);
        columnState.splice(toIdx, 0, moved);
        renderTable();
        savePreferences();
    }

    // --- Per-column filter dropdown ----------------------------------
    function openFilterDropdown(colKey, anchorEl) {
        closeOpenPopover();
        const col = COL_BY_KEY[colKey];
        if (!col) return;

        // Build distinct values from the rows that pass OTHER filters + search,
        // matching Excel behaviour — narrowing one column shouldn't hide
        // values that would re-appear if you cleared this column's filter.
        const otherFilters = Object.assign({}, filters);
        delete otherFilters[colKey];
        const distinctRows = allAssets.filter(row => {
            for (const k in otherFilters) {
                const allowed = otherFilters[k];
                if (!allowed || allowed.size === 0) continue;
                const c = COL_BY_KEY[k];
                if (!c) continue;
                const display = formatCell(c, row[k]);
                if (!allowed.has(display)) return false;
            }
            return true;
        });
        const distinct = new Map(); // display → count
        distinctRows.forEach(row => {
            const display = formatCell(col, row[colKey]);
            const key = display === '' ? '(empty)' : display;
            distinct.set(key, (distinct.get(key) || 0) + 1);
        });
        const sortedValues = [...distinct.keys()].sort((a, b) =>
            a === '(empty)' ? -1 : b === '(empty)' ? 1 :
            String(a).localeCompare(String(b), undefined, { sensitivity: 'base' })
        );

        // Selected set — when no filter yet, everything is selected.
        const currentFilter = filters[colKey];
        const selected = new Set(
            currentFilter && currentFilter.size > 0 ? [...currentFilter] : sortedValues
        );

        const pop = document.createElement('div');
        pop.className = 'at-filter-pop';
        pop.innerHTML = `
            <input type="text" class="at-pop-search" placeholder="Search values..." autocomplete="off">
            <div class="at-pop-actions">
                <a class="at-pop-select-all">Select all</a>
                <a class="at-pop-clear">Clear</a>
            </div>
            <div class="at-pop-list">
                ${sortedValues.map(v => `
                    <label class="at-pop-item">
                        <input type="checkbox" value="${esc(v)}" ${selected.has(v) ? 'checked' : ''}>
                        <span class="at-pop-value">${esc(v === '(empty)' ? '(empty)' : v)}</span>
                        <span style="color:#999;font-size:11px;">${distinct.get(v)}</span>
                    </label>`).join('')}
            </div>
            <div class="at-pop-buttons">
                <button type="button" class="at-pop-cancel">Cancel</button>
                <button type="button" class="at-pop-apply">Apply</button>
            </div>`;
        document.body.appendChild(pop);
        positionPopover(pop, anchorEl);
        openPopover = pop;

        const listEl = pop.querySelector('.at-pop-list');
        const searchEl = pop.querySelector('.at-pop-search');
        searchEl.focus();
        searchEl.addEventListener('input', () => {
            const term = searchEl.value.trim().toLowerCase();
            listEl.querySelectorAll('.at-pop-item').forEach(item => {
                const txt = item.querySelector('.at-pop-value').textContent.toLowerCase();
                item.style.display = term === '' || txt.indexOf(term) !== -1 ? '' : 'none';
            });
        });
        pop.querySelector('.at-pop-select-all').addEventListener('click', () => {
            listEl.querySelectorAll('.at-pop-item:not([style*="display: none"]) input').forEach(cb => cb.checked = true);
        });
        pop.querySelector('.at-pop-clear').addEventListener('click', () => {
            listEl.querySelectorAll('.at-pop-item:not([style*="display: none"]) input').forEach(cb => cb.checked = false);
        });
        pop.querySelector('.at-pop-cancel').addEventListener('click', closeOpenPopover);
        pop.querySelector('.at-pop-apply').addEventListener('click', () => {
            const checked = new Set();
            listEl.querySelectorAll('input:checked').forEach(cb => checked.add(cb.value));
            // If everything is ticked, that means "no filter" → drop it
            if (checked.size === sortedValues.length) {
                delete filters[colKey];
            } else {
                filters[colKey] = checked;
            }
            closeOpenPopover();
            renderTable();
        });
    }

    // --- Columns drawer (show/hide + drag-reorder) -------------------
    function openColumnsDrawer(anchorEl) {
        closeOpenPopover();
        const pop = document.createElement('div');
        pop.className = 'at-cols-pop';
        pop.innerHTML = `
            <h4>Columns</h4>
            <div class="at-cols-hint">Drag to reorder. Tick to show.</div>
            <div class="at-cols-list">
                ${columnState.map(c => {
                    const col = COL_BY_KEY[c.key];
                    if (!col) return '';
                    return `
                        <div class="at-cols-item" draggable="true" data-col-key="${esc(c.key)}">
                            <span class="at-cols-drag">⋮⋮</span>
                            <input type="checkbox" ${c.visible ? 'checked' : ''} data-toggle-key="${esc(c.key)}">
                            <span>${esc(col.label)}</span>
                        </div>`;
                }).join('')}
            </div>`;
        document.body.appendChild(pop);
        positionPopover(pop, anchorEl);
        openPopover = pop;

        const list = pop.querySelector('.at-cols-list');
        list.querySelectorAll('.at-cols-item').forEach(item => {
            const key = item.dataset.colKey;
            item.querySelector('input').addEventListener('change', e => {
                const colEntry = columnState.find(c => c.key === key);
                if (colEntry) {
                    colEntry.visible = e.target.checked;
                    renderTable();
                    savePreferences();
                }
            });
            item.addEventListener('dragstart', e => {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', key);
                item.classList.add('dragging');
            });
            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
                list.querySelectorAll('.at-cols-item').forEach(i => i.classList.remove('drag-over'));
            });
            item.addEventListener('dragover', e => {
                e.preventDefault();
                list.querySelectorAll('.at-cols-item').forEach(i => i.classList.remove('drag-over'));
                item.classList.add('drag-over');
            });
            item.addEventListener('drop', e => {
                e.preventDefault();
                const from = e.dataTransfer.getData('text/plain');
                if (from && from !== key) {
                    reorderColumn(from, key);
                    // Re-render the drawer too so the order matches
                    closeOpenPopover();
                    openColumnsDrawer(anchorEl);
                }
            });
        });
    }

    // --- Popover positioning + cleanup -------------------------------
    function positionPopover(pop, anchorEl) {
        const r = anchorEl.getBoundingClientRect();
        // Anchor under the trigger; nudge left if it'd overflow.
        const vw = window.innerWidth;
        pop.style.visibility = 'hidden';
        pop.style.left = '0px';
        pop.style.top = '0px';
        // Force layout to measure
        const pw = pop.offsetWidth || 240;
        const left = Math.max(8, Math.min(r.left, vw - pw - 8));
        pop.style.left = `${left}px`;
        pop.style.top = `${r.bottom + 4 + window.scrollY}px`;
        pop.style.visibility = 'visible';
    }

    function closeOpenPopover() {
        if (openPopover && openPopover.parentNode) openPopover.parentNode.removeChild(openPopover);
        openPopover = null;
    }

    // --- Exports ------------------------------------------------------
    // Both exports use the CURRENT view: visible columns, current filters,
    // current sort, current search. "What you see is what you export."

    window.atExportCSV = function () {
        const cols = visibleColumns();
        const rows = applyFiltersAndSort();
        const header = cols.map(c => csvCell(c.label)).join(',');
        const body = rows.map(row =>
            cols.map(c => csvCell(formatCell(c, row[c.key]))).join(',')
        ).join('\n');
        const csv = '﻿' + header + '\n' + body; // BOM so Excel opens UTF-8 correctly

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `assets-${formatDateStr()}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        if (window.showToast) showToast(`Exported ${rows.length} asset${rows.length === 1 ? '' : 's'} to CSV`, 'success');
    };

    function csvCell(v) {
        const s = String(v == null ? '' : v);
        if (s.indexOf('"') !== -1 || s.indexOf(',') !== -1 || s.indexOf('\n') !== -1) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }

    window.atExportPDF = async function () {
        if (!window.jspdf) {
            if (window.showToast) showToast('PDF library not loaded', 'error');
            return;
        }
        const { jsPDF } = window.jspdf;
        const cols = visibleColumns();
        const rows = applyFiltersAndSort();
        // Landscape A4 so the wider tables fit
        const doc = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'landscape' });
        let startY = 10;

        // Optional logo (same path morning-checks uses, fail-soft)
        try {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            await new Promise((resolve, reject) => {
                img.onload = resolve;
                img.onerror = reject;
                img.src = '../assets/images/CompanyLogo.png';
            });
            const maxH = 12;
            const w = maxH * (img.width / img.height);
            doc.addImage(img, 'PNG', 10, startY, w, maxH);
            startY += maxH + 5;
        } catch (e) {
            // Continue without logo
        }

        doc.setFontSize(14);
        doc.setTextColor(44, 62, 80);
        doc.text('Assets', 10, startY + 5);
        doc.setFontSize(10);
        doc.setTextColor(120, 120, 120);
        doc.text(`${rows.length} of ${allAssets.length} — ${new Date().toLocaleString()}`, 10, startY + 11);
        startY += 18;

        doc.autoTable({
            startY: startY,
            head: [cols.map(c => c.label)],
            body: rows.map(row => cols.map(c => formatCell(c, row[c.key]))),
            styles: { fontSize: 8, cellPadding: 2, overflow: 'linebreak' },
            headStyles: { fillColor: [0, 120, 212], textColor: [255, 255, 255], fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [248, 250, 252] },
            margin: { left: 10, right: 10 }
        });

        doc.save(`assets-${formatDateStr()}.pdf`);
        if (window.showToast) showToast(`Exported ${rows.length} asset${rows.length === 1 ? '' : 's'} to PDF`, 'success');
    };

    function formatDateStr() {
        const d = new Date();
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    // --- Utilities ----------------------------------------------------
    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
