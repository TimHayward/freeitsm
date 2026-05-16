/**
 * Network Mapper — editor module (chunks A + B + C).
 *
 * Chunk C adds the drag-to-canvas → bind → place loop:
 *   - dragging a class tile onto the canvas opens a CMDB object picker
 *     scoped to that class
 *   - picking an object places a node at the drop coordinates (snapped
 *     to the 20px grid)
 *   - nodes render as icon + name with planned-object styling preserved
 *     (dashed border + amber tint when is_planned)
 *   - click selects, drag moves (with autosave deferred mid-drag exactly
 *     like Process Mapper), Delete removes (and tears down its connectors
 *     so save_diagram doesn't refuse the payload)
 *   - after every save, get_diagram is re-fetched so temp ids resolve into
 *     real ids — necessary now that connectors will reference nodes in
 *     chunk D, but harmless for chunk C
 *
 * What still ships in later chunks:
 *   - connector drawing + relationships pull-in (chunk D)
 *   - detail panel for selected node + relationships modal (chunk D)
 *   - zoom, PNG export, size variants UI, icon override (phase 2)
 *
 * Convention: every exported entry point goes on window.NM so the inline
 * HTML can call NM.save(), NM.toggleAutosave() etc. without scope concerns.
 */
(function () {
    'use strict';

    const API = '../api/network-mapper/';
    const CMDB_API = '../api/cmdb/';
    const SYSTEM_API = '../api/system/';

    const AUTOSAVE_PREF_KEY = 'network_mapper_autosave';
    const AUTOSAVE_DEBOUNCE_MS = 2000;
    const MIN_SAVING_VISIBLE_MS = 400;
    const GRID = 20;                // snap-to-grid pitch, matches the canvas dot grid
    const NODE_SIZES = {            // pixel icon size per node.size enum value
        small: 40, medium: 56, large: 80
    };

    // ---- state ----
    let diagramId = 0;
    let diagram = null;          // metadata; null while loading
    let classes = [];            // CMDB classes for the palette
    let classById = {};          // id-keyed lookup, rebuilt after loadClasses
    let nodes = [];              // current canvas nodes
    let connectors = [];         // current canvas connectors
    let nextTempId = -1;         // tempIds count down so they never collide with real auto-inc IDs

    let dirty = false;
    let autosaveOn = false;
    let autosaveTimer = null;
    let saveInFlight = false;
    let lastSavingShownAt = 0;

    // ---- selection + drag state ----
    let selectedNodeKey = null;  // nodeKey() of currently selected node, or null
    let nodeDrag = null;         // { key, offsetX, offsetY, startX, startY, moved }

    // ---- picker state ----
    let pickerClassId = null;
    let pickerDropX = 0;
    let pickerDropY = 0;
    let pickerResults = [];      // last fetch's results, for keyboard selection
    let pickerHighlight = 0;
    let pickerSearchTimer = null;

    // ---- DOM refs (filled in init) ----
    let elTitle, elVersionPill, elMetaRow, elMetaAuthor, elMetaCreated, elMetaUpdated;
    let elStatus, elSaveBtn, elSaveVersionBtn, elAutosaveToggle, elAutosaveWrap;
    let elPaletteBody, elCanvas, elReadonlyBanner, elCanvasEmpty;
    let elPickerModal, elPickerClassLabel, elPickerSearch, elPickerResults;

    // =========================================================
    //  Initialisation
    // =========================================================
    function init(id) {
        diagramId = id;

        elTitle           = document.getElementById('diagramTitle');
        elVersionPill     = document.getElementById('versionPill');
        elMetaRow         = document.getElementById('metaRow');
        elMetaAuthor      = document.getElementById('metaAuthor');
        elMetaCreated     = document.getElementById('metaCreated');
        elMetaUpdated     = document.getElementById('metaUpdated');
        elStatus          = document.getElementById('saveStatus');
        elSaveBtn         = document.getElementById('saveBtn');
        elSaveVersionBtn  = document.getElementById('saveVersionBtn');
        elAutosaveToggle  = document.getElementById('nmAutosaveToggle');
        elAutosaveWrap    = document.getElementById('autosaveWrap');
        elPaletteBody     = document.getElementById('paletteBody');
        elCanvas          = document.getElementById('canvas');
        elReadonlyBanner  = document.getElementById('readonlyBanner');
        elCanvasEmpty     = document.getElementById('canvasEmpty');
        elPickerModal     = document.getElementById('objectPickerModal');
        elPickerClassLabel = document.getElementById('pickerClassLabel');
        elPickerSearch    = document.getElementById('pickerSearch');
        elPickerResults   = document.getElementById('pickerResults');

        bindCanvasEvents();

        // Load diagram + palette + autosave preference in parallel
        Promise.all([loadDiagram(), loadClasses(), loadAutosavePreference()]).catch(() => {});
    }

    function bindCanvasEvents() {
        // Drop-target wiring: must preventDefault on dragover to permit drop
        elCanvas.addEventListener('dragover', e => {
            if (!diagram || !diagram.is_current) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
        });
        elCanvas.addEventListener('drop', onCanvasDrop);

        // Click on empty canvas clears selection
        elCanvas.addEventListener('mousedown', e => {
            if (e.target === elCanvas || e.target.classList.contains('nm-canvas-empty')) {
                selectNode(null);
            }
        });

        // Document-level keyboard: Delete/Backspace removes selected node when
        // the canvas (or one of its node children) has focus / nothing else does
        document.addEventListener('keydown', e => {
            if (e.key !== 'Delete' && e.key !== 'Backspace') return;
            const tag = (document.activeElement && document.activeElement.tagName) || '';
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
            // Don't fire if a modal is open (search inputs inside the picker etc.)
            if (document.querySelector('.nm-modal-overlay.active')) return;
            if (selectedNodeKey != null) {
                e.preventDefault();
                deleteSelectedNode();
            }
        });
    }

    // =========================================================
    //  Diagram + palette loading
    // =========================================================
    async function loadDiagram() {
        try {
            const resp = await fetch(API + 'get_diagram.php?id=' + diagramId);
            const data = await resp.json();
            if (!data.success) throw new Error(data.error || 'Failed to load');
            diagram = data.diagram;
            nodes = data.nodes || [];
            connectors = data.connectors || [];
            renderHeader();
            applyReadOnlyState();
            renderNodes();
            setStatus(autosaveOn ? 'saved' : 'off');
        } catch (e) {
            elTitle.textContent = 'Failed to load diagram';
            elStatus.textContent = e.message;
        }
    }

    async function loadClasses() {
        try {
            const resp = await fetch(CMDB_API + 'get_classes.php');
            const data = await resp.json();
            if (!data.success) throw new Error(data.error || 'Failed to load classes');
            classes = (data.classes || []).filter(c => c.is_active);
            classById = {};
            classes.forEach(c => { classById[c.id] = c; });
            renderPalette();
        } catch (e) {
            elPaletteBody.innerHTML = '<div class="nm-palette-empty">Failed to load classes: ' + escapeHtml(e.message) + '</div>';
        }
    }

    function renderHeader() {
        document.title = 'FreeITSM — ' + (diagram.title || 'Network Diagram');
        elTitle.textContent = diagram.title || '(untitled)';

        const label = diagram.version_label || 'v?';
        if (diagram.is_current) {
            elVersionPill.className = 'nm-version-pill';
            elVersionPill.textContent = label + ' (current)';
        } else {
            elVersionPill.className = 'nm-version-pill readonly';
            elVersionPill.textContent = label + ' (read-only)';
        }
        elVersionPill.style.display = '';

        elMetaRow.style.display = '';
        elMetaAuthor.textContent  = diagram.author_name || 'Unknown';
        elMetaCreated.textContent = formatDate(diagram.created_datetime);
        elMetaUpdated.textContent = formatDate(diagram.updated_datetime);
    }

    function renderPalette() {
        if (!classes.length) {
            elPaletteBody.innerHTML = '<div class="nm-palette-empty">No CMDB classes defined yet. <a href="../cmdb/settings/">Create one</a> to start dragging objects onto the diagram.</div>';
            return;
        }
        const html = classes.map(c => {
            const icon = window.nmRenderIcon ? window.nmRenderIcon(c.icon_key || 'box', 28) : '';
            const objCount = c.object_count || 0;
            return `
                <div class="nm-palette-tile" draggable="true" data-class-id="${c.id}" data-icon-key="${escapeAttr(c.icon_key || 'box')}" title="Drag onto the canvas (coming in chunk C)">
                    <div class="nm-palette-tile-icon">${icon}</div>
                    <div class="nm-palette-tile-name">${escapeHtml(c.name)}</div>
                    <div class="nm-palette-tile-count">${objCount} object${objCount === 1 ? '' : 's'}</div>
                </div>`;
        }).join('');
        elPaletteBody.innerHTML = html;

        // Drag-start: stash the class id for the drop handler to pick up in chunk C
        elPaletteBody.querySelectorAll('.nm-palette-tile').forEach(tile => {
            tile.addEventListener('dragstart', onTileDragStart);
        });
    }

    function onTileDragStart(e) {
        const classId = e.currentTarget.dataset.classId;
        e.dataTransfer.setData('text/plain', JSON.stringify({ kind: 'nm-class', class_id: parseInt(classId, 10) }));
        e.dataTransfer.effectAllowed = 'copy';
    }

    // =========================================================
    //  Read-only mode (historical version)
    // =========================================================
    function applyReadOnlyState() {
        if (diagram.is_current) {
            elReadonlyBanner.style.display = 'none';
            return;
        }
        elReadonlyBanner.style.display = '';
        elSaveBtn.disabled = true;
        elSaveBtn.title = 'This is a historical version — read-only';
        elAutosaveWrap.style.display = 'none';
        // Save-as-new-version on a non-leaf is refused by the backend (create_version
        // only forks from the leaf), so disable it here too.
        elSaveVersionBtn.disabled = true;
        elSaveVersionBtn.title = 'Only the current version can be forked into a new version';
    }

    // =========================================================
    //  Drop → CMDB object picker → place node
    // =========================================================
    function snap(v) { return Math.round(v / GRID) * GRID; }

    function onCanvasDrop(e) {
        if (!diagram || !diagram.is_current) return;
        e.preventDefault();
        let payload = null;
        try { payload = JSON.parse(e.dataTransfer.getData('text/plain') || '{}'); } catch (_) { /* ignore */ }
        if (!payload || payload.kind !== 'nm-class') return;
        const cls = classById[payload.class_id];
        if (!cls) return;

        const rect = elCanvas.getBoundingClientRect();
        // Drop point in canvas-local coordinates (account for scroll). We snap
        // the *top-left* of the node bounding box, computed by offsetting the
        // drop point by half the icon size so the drop visually centres.
        const iconPx = NODE_SIZES.medium;
        const dropX = e.clientX - rect.left + elCanvas.scrollLeft;
        const dropY = e.clientY - rect.top + elCanvas.scrollTop;
        const x = Math.max(0, snap(dropX - iconPx / 2));
        const y = Math.max(0, snap(dropY - iconPx / 2));

        openObjectPicker(cls, x, y);
    }

    function openObjectPicker(cls, x, y) {
        pickerClassId = cls.id;
        pickerDropX = x;
        pickerDropY = y;
        pickerResults = [];
        pickerHighlight = 0;
        elPickerClassLabel.textContent = cls.name;
        elPickerSearch.value = '';
        elPickerModal.classList.add('active');
        // Load the class's full object list as the default view; type-ahead
        // narrows it server-side from there.
        fetchPickerResults('');
        setTimeout(() => elPickerSearch.focus(), 50);
    }

    function closeObjectPicker() {
        elPickerModal.classList.remove('active');
        pickerClassId = null;
    }

    async function fetchPickerResults(q) {
        try {
            const url = q.trim()
                ? CMDB_API + 'get_objects.php?class_id=' + pickerClassId + '&search=' + encodeURIComponent(q.trim())
                : CMDB_API + 'get_objects.php?class_id=' + pickerClassId;
            const resp = await fetch(url, { credentials: 'same-origin' });
            const data = await resp.json();
            if (!data.success) throw new Error(data.error || 'Search failed');
            pickerResults = data.objects || [];
            pickerHighlight = 0;
            renderPickerResults();
        } catch (e) {
            elPickerResults.innerHTML = '<div class="nm-picker-empty">Failed: ' + escapeHtml(e.message) + '</div>';
        }
    }

    function renderPickerResults() {
        // Filter out objects already on the canvas — placing the same object
        // twice would be confusing and save_diagram would persist both.
        const onCanvas = new Set(nodes.map(n => n.cmdb_object_id));
        const available = pickerResults.filter(r => !onCanvas.has(r.id));

        if (!available.length) {
            const allInUse = pickerResults.length > 0;
            elPickerResults.innerHTML = '<div class="nm-picker-empty">' +
                (allInUse
                    ? 'Every object in this class is already on the diagram.'
                    : 'No objects in this class yet. <a href="../cmdb/" target="_blank">Create one in CMDB →</a>') +
                '</div>';
            return;
        }
        elPickerResults.innerHTML = available.map((r, i) => {
            const cls = pickerHighlight === i ? ' highlighted' : '';
            const planned = r.is_planned ? '<span class="nm-picker-planned">PLANNED</span>' : '';
            const parent = r.parent_name
                ? '<span class="nm-picker-parent">in ' + escapeHtml(r.parent_name) + '</span>'
                : '';
            return '<div class="nm-picker-row' + cls + '" data-object-id="' + r.id + '">' +
                '<span class="nm-picker-name">' + escapeHtml(r.name) + planned + '</span>' +
                parent +
                '</div>';
        }).join('');
        elPickerResults.querySelectorAll('.nm-picker-row').forEach(row => {
            row.addEventListener('click', () => {
                const id = parseInt(row.dataset.objectId, 10);
                const obj = available.find(o => o.id === id);
                if (obj) commitPickerSelection(obj);
            });
        });
    }

    function onPickerSearchInput(value) {
        clearTimeout(pickerSearchTimer);
        pickerSearchTimer = setTimeout(() => fetchPickerResults(value), 200);
    }

    function onPickerKeyDown(e) {
        const onCanvas = new Set(nodes.map(n => n.cmdb_object_id));
        const available = pickerResults.filter(r => !onCanvas.has(r.id));
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            pickerHighlight = Math.min(available.length - 1, pickerHighlight + 1);
            renderPickerResults();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            pickerHighlight = Math.max(0, pickerHighlight - 1);
            renderPickerResults();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const pick = available[pickerHighlight];
            if (pick) commitPickerSelection(pick);
        } else if (e.key === 'Escape') {
            closeObjectPicker();
        }
    }

    function commitPickerSelection(obj) {
        const cls = classById[obj.class_id];
        const node = {
            id: null,
            tempId: nextTempId--,
            cmdb_object_id: obj.id,
            name: obj.name,
            class_id: obj.class_id,
            class_name: obj.class_name || (cls ? cls.name : ''),
            class_icon: cls ? cls.icon_key : 'box',
            is_planned: !!obj.is_planned,
            x: pickerDropX,
            y: pickerDropY,
            size: 'medium',
            icon_override: null
        };
        nodes.push(node);
        closeObjectPicker();
        renderNodes();
        selectNode(nodeKey(node));
        markDirty();
    }

    // =========================================================
    //  Node rendering + selection + drag + delete
    // =========================================================
    function nodeKey(n) {
        // Stable identifier for selection / drag refs. Real id wins; tempId
        // (negative number) for unsaved nodes. Returned as a string so we can
        // use === safely on Set/Map lookups.
        return n.id ? 'r' + n.id : 't' + n.tempId;
    }

    function findNodeByKey(key) {
        if (key == null) return null;
        return nodes.find(n => nodeKey(n) === key) || null;
    }

    function renderNodes() {
        // Clear existing node DOM; keep the empty-state element + any future
        // SVG layer that chunk D's connectors will live in.
        Array.from(elCanvas.querySelectorAll('.nm-node')).forEach(el => el.remove());

        if (!nodes.length) {
            if (elCanvasEmpty) elCanvasEmpty.style.display = '';
            return;
        }
        if (elCanvasEmpty) elCanvasEmpty.style.display = 'none';

        nodes.forEach(n => elCanvas.appendChild(buildNodeEl(n)));
    }

    function buildNodeEl(n) {
        const iconKey = n.icon_override || n.class_icon || 'box';
        const sizePx = NODE_SIZES[n.size] || NODE_SIZES.medium;
        const iconSvg = window.nmRenderIcon ? window.nmRenderIcon(iconKey, sizePx - 12) : '';

        const el = document.createElement('div');
        el.className = 'nm-node';
        if (n.is_planned) el.classList.add('is-planned');
        if (nodeKey(n) === selectedNodeKey) el.classList.add('selected');
        el.style.left = n.x + 'px';
        el.style.top  = n.y + 'px';
        el.style.width = sizePx + 'px';
        el.dataset.key = nodeKey(n);
        el.innerHTML =
            '<div class="nm-node-icon" style="height:' + sizePx + 'px;">' + iconSvg + '</div>' +
            (n.is_planned ? '<span class="nm-node-planned-pill">PLANNED</span>' : '') +
            '<div class="nm-node-label" title="' + escapeAttr(n.name + ' (' + (n.class_name || '') + ')') + '">' +
                escapeHtml(n.name) +
            '</div>';

        el.addEventListener('mousedown', onNodeMouseDown);
        return el;
    }

    function selectNode(key) {
        if (selectedNodeKey === key) return;
        selectedNodeKey = key;
        // Cheap DOM swap rather than full re-render
        Array.from(elCanvas.querySelectorAll('.nm-node')).forEach(el => {
            el.classList.toggle('selected', el.dataset.key === key);
        });
    }

    function onNodeMouseDown(e) {
        if (e.button !== 0) return;
        const key = e.currentTarget.dataset.key;
        const n = findNodeByKey(key);
        if (!n) return;
        selectNode(key);
        if (!diagram || !diagram.is_current) return; // read-only: select but no drag

        const rect = elCanvas.getBoundingClientRect();
        const cursorX = e.clientX - rect.left + elCanvas.scrollLeft;
        const cursorY = e.clientY - rect.top + elCanvas.scrollTop;
        nodeDrag = {
            key,
            offsetX: cursorX - n.x,
            offsetY: cursorY - n.y,
            startX: n.x,
            startY: n.y,
            moved: false
        };
        document.addEventListener('mousemove', onNodeMouseMove);
        document.addEventListener('mouseup', onNodeMouseUp);
        e.preventDefault();
    }

    function onNodeMouseMove(e) {
        if (!nodeDrag) return;
        const n = findNodeByKey(nodeDrag.key);
        if (!n) return;
        const rect = elCanvas.getBoundingClientRect();
        const cursorX = e.clientX - rect.left + elCanvas.scrollLeft;
        const cursorY = e.clientY - rect.top + elCanvas.scrollTop;
        const newX = Math.max(0, snap(cursorX - nodeDrag.offsetX));
        const newY = Math.max(0, snap(cursorY - nodeDrag.offsetY));
        if (newX === n.x && newY === n.y) return;
        n.x = newX;
        n.y = newY;
        nodeDrag.moved = true;
        const el = elCanvas.querySelector('.nm-node[data-key="' + cssEscape(nodeDrag.key) + '"]');
        if (el) {
            el.style.left = newX + 'px';
            el.style.top  = newY + 'px';
        }
    }

    function onNodeMouseUp() {
        document.removeEventListener('mousemove', onNodeMouseMove);
        document.removeEventListener('mouseup', onNodeMouseUp);
        if (!nodeDrag) return;
        const moved = nodeDrag.moved;
        nodeDrag = null;
        if (moved) markDirty();
    }

    function deleteSelectedNode() {
        if (!diagram || !diagram.is_current) return;
        if (selectedNodeKey == null) return;
        const n = findNodeByKey(selectedNodeKey);
        if (!n) return;
        // Drop any connectors touching this node so save_diagram doesn't reject
        // the payload as having dangling refs. Chunk C has no connectors so this
        // is a no-op now but the wiring is here for chunk D.
        connectors = connectors.filter(c => {
            // Connectors reference nodes by their id (real or temp). Build the
            // node we're deleting's key form on both sides for the comparison.
            const myId = n.id;
            const myTemp = n.tempId;
            return c.from_node_id !== myId && c.from_node_id !== myTemp
                && c.to_node_id   !== myId && c.to_node_id   !== myTemp;
        });
        nodes = nodes.filter(other => other !== n);
        selectedNodeKey = null;
        renderNodes();
        markDirty();
    }

    function cssEscape(s) {
        // Minimal escape for data-key selector use (keys are 'r123' or 't-1' so
        // safe in practice, but be defensive in case the format ever changes)
        return String(s).replace(/(["\\])/g, '\\$1');
    }

    // =========================================================
    //  Autosave: dirty / debounce / status / preference
    // =========================================================

    // Single entry-point for "something changed" — wraps dirty/status/debounce
    // so the call sites (place node, drag, delete, …) don't have to think about
    // any of it. No-op on read-only versions so even programmatic edits can't
    // dirty a historical diagram.
    function markDirty() {
        if (!diagram || !diagram.is_current) return;
        dirty = true;
        setStatus('unsaved');
        if (autosaveOn) scheduleAutosave();
    }

    function scheduleAutosave() {
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(() => {
            if (!autosaveOn || !dirty || saveInFlight) return;
            // Don't save during an active node drag — save() reloads the diagram,
            // which would destroy the in-progress drag DOM and snap the node
            // back to its last-committed position. Reschedule and re-check.
            if (nodeDrag) {
                scheduleAutosave();
                return;
            }
            save(true);
        }, AUTOSAVE_DEBOUNCE_MS);
    }

    // Status states: 'idle' | 'unsaved' | 'saving' | 'saved' | 'failed' | 'off'
    function setStatus(state) {
        if (!elStatus) return;
        const map = {
            idle:    { html: '', cls: '' },
            unsaved: { html: autosaveOn ? 'Unsaved' : 'Unsaved changes', cls: 'nm-status-unsaved' },
            saving:  { html: '<span class="nm-status-spinner"></span> Saving…', cls: 'nm-status-saving' },
            saved:   { html: '<span class="nm-status-tick">✓</span> Saved', cls: 'nm-status-saved' },
            failed:  { html: '<span class="nm-status-warn">⚠</span> Save failed — <a href="#" id="nmRetrySave">retry</a>', cls: 'nm-status-failed' },
            off:     { html: 'Autosave off', cls: 'nm-status-off' }
        };
        const s = map[state] || map.idle;
        elStatus.className = 'nm-status ' + s.cls;
        elStatus.innerHTML = s.html;
        if (state === 'failed') {
            const retry = document.getElementById('nmRetrySave');
            if (retry) retry.onclick = (e) => { e.preventDefault(); save(autosaveOn); };
        }
    }

    async function loadAutosavePreference() {
        try {
            const r = await fetch(SYSTEM_API + 'get_user_preference.php?key=' + encodeURIComponent(AUTOSAVE_PREF_KEY), { credentials: 'same-origin' });
            const d = await r.json();
            applyAutosaveState(d.success && d.value === '1', false);
        } catch (e) {
            applyAutosaveState(false, false);
        }
    }

    function applyAutosaveState(on, persist) {
        autosaveOn = !!on;
        if (elAutosaveToggle) elAutosaveToggle.checked = autosaveOn;
        if (!diagram) {
            setStatus('idle');
        } else if (!diagram.is_current) {
            // Read-only versions don't show a save status — banner does the work
            setStatus('idle');
        } else if (dirty) {
            setStatus('unsaved');
            if (autosaveOn) scheduleAutosave();
        } else {
            setStatus(autosaveOn ? 'saved' : 'off');
        }
        if (persist) {
            fetch(SYSTEM_API + 'set_user_preference.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ key: AUTOSAVE_PREF_KEY, value: autosaveOn ? '1' : '0' })
            }).catch(() => {});
        }
    }

    function toggleAutosave(on) {
        clearTimeout(autosaveTimer);
        applyAutosaveState(on, true);
        if (autosaveOn && dirty && diagram && diagram.is_current) scheduleAutosave();
    }

    // =========================================================
    //  Save
    // =========================================================
    async function save(isAutoSave) {
        if (!diagram || !diagram.is_current || saveInFlight) return;
        saveInFlight = true;
        setStatus('saving');
        lastSavingShownAt = Date.now();

        try {
            const resp = await fetch(API + 'save_diagram.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: diagramId,
                    nodes: nodes.map(n => ({
                        id: n.id,
                        cmdb_object_id: n.cmdb_object_id,
                        x: n.x, y: n.y,
                        size: n.size || 'medium',
                        icon_override: n.icon_override || null
                    })),
                    connectors: connectors.map(c => ({
                        id: c.id,
                        from_node_id: c.from_node_id,
                        to_node_id: c.to_node_id,
                        cmdb_relationship_id: c.cmdb_relationship_id || null,
                        label: c.label || null,
                        line_style: c.line_style || 'solid'
                    }))
                })
            });
            const data = await resp.json();
            if (!data.success) throw new Error(data.error || 'Save failed');

            dirty = false;
            // Reload from the server so temp ids resolve into real ids — vital
            // before any connector references those nodes. Preserve the current
            // selection by cmdb_object_id so the user's focus survives the swap.
            const selectedCmdbId = selectedNodeKey
                ? (findNodeByKey(selectedNodeKey) || {}).cmdb_object_id
                : null;
            const resp2 = await fetch(API + 'get_diagram.php?id=' + diagramId, { credentials: 'same-origin' });
            const data2 = await resp2.json();
            if (data2.success) {
                diagram = data2.diagram;
                nodes = data2.nodes || [];
                connectors = data2.connectors || [];
                renderNodes();
                if (selectedCmdbId) {
                    const reselect = nodes.find(n => n.cmdb_object_id === selectedCmdbId);
                    selectedNodeKey = reselect ? nodeKey(reselect) : null;
                    if (selectedNodeKey) selectNode(selectedNodeKey);
                }
            }

            // Hold "Saving…" on screen for MIN_SAVING_VISIBLE_MS so it doesn't flash
            const elapsed = Date.now() - lastSavingShownAt;
            const wait = Math.max(0, MIN_SAVING_VISIBLE_MS - elapsed);
            setTimeout(() => {
                setStatus('saved');
                if (!isAutoSave && window.showToast) showToast('Saved', 'success');
            }, wait);
        } catch (e) {
            setStatus('failed');
            if (!isAutoSave && window.showToast) showToast('Save failed: ' + e.message, 'error');
        } finally {
            saveInFlight = false;
        }
    }

    // =========================================================
    //  Save as new version
    // =========================================================
    async function openNewVersionModal() {
        if (!diagram || !diagram.is_current) {
            if (window.showToast) showToast('Only the current version can be forked', 'error');
            return;
        }
        // create_version.php clones from the *persisted* state, so any in-memory
        // edits would be silently dropped. Save first so the user gets what
        // they see — they don't need to think about persistence semantics.
        if (dirty) {
            if (window.showToast) showToast('Saving pending changes first…', 'info');
            await save(false);
            if (dirty) return; // save failed; bail and let the user retry
        }
        // Pre-fill with the current diagram's metadata so the user only needs to
        // tweak the version label most of the time
        document.getElementById('nvTitle').value = diagram.title || '';
        document.getElementById('nvDescription').value = diagram.description || '';
        document.getElementById('nvVersionLabel').value = suggestNextVersionLabel(diagram.version_label);
        document.getElementById('newVersionModal').classList.add('active');
        setTimeout(() => document.getElementById('nvVersionLabel').focus(), 50);
    }

    function closeNewVersionModal() {
        document.getElementById('newVersionModal').classList.remove('active');
    }

    function suggestNextVersionLabel(current) {
        if (!current) return 'v2';
        // Try to bump a trailing integer ("v3" -> "v4", "Draft 2" -> "Draft 3")
        const m = String(current).match(/^(.*?)(\d+)\s*$/);
        if (m) return m[1] + (parseInt(m[2], 10) + 1);
        return current + ' (new)';
    }

    async function createNewVersion() {
        const title = document.getElementById('nvTitle').value.trim();
        const description = document.getElementById('nvDescription').value.trim();
        const versionLabel = document.getElementById('nvVersionLabel').value.trim();
        if (!title) {
            if (window.showToast) showToast('Title is required', 'error');
            return;
        }
        const btn = document.getElementById('nvCreateBtn');
        btn.disabled = true;
        try {
            const resp = await fetch(API + 'create_version.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    parent_diagram_id: diagramId,
                    title, description, version_label: versionLabel
                })
            });
            const data = await resp.json();
            if (!data.success) throw new Error(data.error || 'Failed to create version');
            // Navigate to the new (leaf, editable) version
            window.location.href = 'diagram.php?id=' + data.id;
        } catch (e) {
            if (window.showToast) showToast('Failed: ' + e.message, 'error');
            btn.disabled = false;
        }
    }

    // =========================================================
    //  Helpers
    // =========================================================
    function formatDate(s) {
        if (!s) return '—';
        try { return new Date(s.replace(' ', 'T') + 'Z').toLocaleString(); }
        catch (e) { return s; }
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function escapeAttr(s) { return escapeHtml(s).replace(/'/g, "\\'"); }

    // =========================================================
    //  Public surface
    // =========================================================
    window.NM = {
        init,
        save: () => save(false),
        toggleAutosave,
        openNewVersionModal,
        closeNewVersionModal,
        createNewVersion,
        markDirty,
        // picker
        closeObjectPicker,
        onPickerSearchInput,
        onPickerKeyDown
    };
})();
