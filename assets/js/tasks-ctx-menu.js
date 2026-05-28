/**
 * FreeITSM Tasks — Shared right-click context menu
 *
 * One menu used by the dashboard (right-click a board / list card) and the
 * timeline (right-click a Gantt bar). Each page provides:
 *   - targetSelector: CSS selector that identifies a clickable task element
 *   - getTaskId(el):  return the numeric task id from that element
 *   - getTask(id):    return the task object (so submenus know the current
 *                     analyst/team/status/priority for the ✓ marker)
 *   - getLookups():   return { analysts, teams, statuses, priorities }
 *   - onUpdate():     called after a successful save (typically loadTasks())
 *   - apiBase:        base URL for save.php (e.g. '../api/tasks/')
 *   - onCreateSubtask(id) [optional]: dashboard wires this to open the detail
 *                     panel and focus the add-subtask input. Omit on pages
 *                     that don't have a detail panel — the menu hides the
 *                     "Create subtask" item when this is missing.
 *
 * Markup contract: a single .ctx-menu with id="ctxMenu" containing four
 * submenu containers (#ctxAnalyst / #ctxTeam / #ctxStatus / #ctxPriority).
 * The optional "Create subtask" item carries data-action="subtask".
 */
(function() {
    let cfg = null;
    let ctxTaskId = null;
    let wired = false;

    function init(config) {
        cfg = Object.assign({
            targetSelector: '.task-card',
            menuId: 'ctxMenu',
            apiBase: '',
            onUpdate: () => {},
            onCreateSubtask: null,
        }, config);

        if (!wired) {
            document.addEventListener('contextmenu', onDocCtx);
            document.addEventListener('click', closeCtx);
            document.addEventListener('scroll', closeCtx, true);
            window.addEventListener('resize', closeCtx);

            const menu = document.getElementById(cfg.menuId);
            if (menu) menu.addEventListener('click', onMenuClick);
            wired = true;
        }

        // Hide the Create-subtask item (and its preceding separator) on pages
        // that don't provide an onCreateSubtask callback.
        const subtaskItem = document.querySelector(`#${cfg.menuId} [data-action="subtask"]`);
        if (subtaskItem) {
            const show = !!cfg.onCreateSubtask;
            subtaskItem.style.display = show ? '' : 'none';
            const sep = subtaskItem.previousElementSibling;
            if (sep && sep.classList.contains('ctx-sep')) sep.style.display = show ? '' : 'none';
        }
    }

    function close() { closeCtx(); }
    function isOpen() {
        const menu = cfg && document.getElementById(cfg.menuId);
        return !!menu && menu.style.display === 'block';
    }

    function onDocCtx(e) {
        if (!cfg) return;
        const target = e.target.closest(cfg.targetSelector);
        if (target) openCtx(e, cfg.getTaskId(target));
        else closeCtx();
    }

    function openCtx(e, taskId) {
        e.preventDefault();
        const task = cfg.getTask(taskId);
        if (!task) return;
        ctxTaskId = taskId;
        buildSubmenus(task);

        const menu = document.getElementById(cfg.menuId);
        menu.style.display = 'block';
        const mw = menu.offsetWidth, mh = menu.offsetHeight;
        const x = Math.min(e.clientX, window.innerWidth - mw - 6);
        const y = Math.min(e.clientY, window.innerHeight - mh - 6);
        menu.style.left = Math.max(6, x) + 'px';
        menu.style.top  = Math.max(6, y) + 'px';
        menu.classList.toggle('flip-sub',   e.clientX + mw + 190 > window.innerWidth);
        menu.classList.toggle('flip-sub-v', e.clientY > window.innerHeight * 0.55);
    }

    function closeCtx() {
        if (!cfg) return;
        const menu = document.getElementById(cfg.menuId);
        if (menu) menu.style.display = 'none';
        ctxTaskId = null;
    }

    function buildSubmenus(task) {
        const lookups = cfg.getLookups();
        const T = (k) => window.t('tasks.' + k);

        const opt = (field, value, label, current, swatch) =>
            `<div class="ctx-sub-item${current ? ' current' : ''}" data-field="${field}" data-value="${escAttr(value)}">
                ${swatch || ''}<span class="ctx-sub-label">${esc(label)}</span>
                ${current ? '<span class="ctx-check">✓</span>' : ''}
            </div>`;
        const sw = c => `<span class="ctx-swatch" style="background:${escAttr(c || '#888')}"></span>`;

        const elA = document.getElementById('ctxAnalyst');
        if (elA) elA.innerHTML =
            opt('assigned_analyst_id', '', T('detail.unassigned'), !task.assigned_analyst_id) +
            (lookups.analysts || []).map(a =>
                opt('assigned_analyst_id', a.id, a.name, task.assigned_analyst_id == a.id)).join('');

        const elT = document.getElementById('ctxTeam');
        if (elT) elT.innerHTML =
            opt('assigned_team_id', '', T('detail.no_team'), !task.assigned_team_id) +
            (lookups.teams || []).map(tm =>
                opt('assigned_team_id', tm.id, tm.name, task.assigned_team_id == tm.id)).join('');

        const elS = document.getElementById('ctxStatus');
        if (elS) elS.innerHTML =
            (lookups.statuses || []).map(s =>
                opt('status', s.name, s.name, task.status === s.name, sw(s.colour))).join('')
            || `<div class="ctx-sub-empty">${esc(T('context.no_statuses'))}</div>`;

        const elP = document.getElementById('ctxPriority');
        if (elP) elP.innerHTML =
            (lookups.priorities || []).map(p =>
                opt('priority', p.name, p.name, task.priority === p.name, sw(p.colour))).join('')
            || `<div class="ctx-sub-empty">${esc(T('context.no_priorities'))}</div>`;
    }

    function onMenuClick(e) {
        const subOpt = e.target.closest('.ctx-sub-item');
        if (subOpt) {
            const field = subOpt.dataset.field;
            let value = subOpt.dataset.value;
            if (field === 'assigned_analyst_id' || field === 'assigned_team_id') {
                value = value === '' ? null : parseInt(value, 10);
            }
            setField(field, value);
            return;
        }
        if (e.target.closest('[data-action="subtask"]')) {
            const id = ctxTaskId;
            closeCtx();
            if (id && cfg.onCreateSubtask) cfg.onCreateSubtask(id);
        }
    }

    async function setField(field, value) {
        const id = ctxTaskId;
        closeCtx();
        if (!id) return;
        try {
            const data = await fetch(cfg.apiBase + 'save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, [field]: value })
            }).then(r => r.json());
            if (data.success) {
                if (typeof showToast === 'function') {
                    showToast(window.t('tasks.toast.task_updated'), 'success');
                }
                if (cfg.onUpdate) cfg.onUpdate();
            } else if (typeof showToast === 'function') {
                showToast(window.t('tasks.toast.error_prefix', {
                    message: data.error || window.t('tasks.toast.update_failed')
                }), 'error');
            }
        } catch (e) {
            if (typeof showToast === 'function') {
                showToast(window.t('tasks.toast.update_failed'), 'error');
            }
        }
    }

    // ── Local helpers (mirrors of esc/escAttr in tasks.js + tasks-timeline.js) ──
    function esc(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }
    function escAttr(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    window.TasksCtxMenu = { init, close, isOpen };
})();
