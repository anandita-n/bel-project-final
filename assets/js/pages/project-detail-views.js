/* Board filtering + List renderer for the project detail page.
   Reads/writes window.ProjectUI.state (populated by project-detail.js). Both views
   render from the same in-memory tasks array — no extra AJAX calls. */

(function () {
    const ProjectUI = window.ProjectUI;
    const cfg = ProjectUI.cfg;
    const state = ProjectUI.state;

    const filters = { search: '', status: 'all', priority: 'all', assignee: 'all', overdueOnly: false, dateRange: 'all' };
    const sortState = { key: 'due_date', dir: 'asc' };
    const selectedIds = new Set();

    /* ---------- Filters ---------- */

    function populateFilterSelects() {
        const statusHtml = '<option value="all">All statuses</option>' +
            Object.keys(cfg.statusLabels).map(k => '<option value="' + k + '">' + cfg.statusLabels[k] + '</option>').join('');
        document.querySelectorAll('.task-filter-status').forEach(sel => {
            const cur = sel.value;
            sel.innerHTML = statusHtml;
            sel.value = cur || 'all';
        });

        let assigneeHtml = '<option value="all">All assignees</option><option value="unassigned">Unassigned</option>';
        state.members.forEach(m => { assigneeHtml += '<option value="' + m.id + '">' + escapeHtml(m.name) + '</option>'; });
        document.querySelectorAll('.task-filter-assignee').forEach(sel => {
            const cur = sel.value;
            sel.innerHTML = assigneeHtml;
            sel.value = cur || 'all';
        });
    }

    function syncToolbars() {
        document.querySelectorAll('.task-filter-search').forEach(el => { if (el.value !== filters.search) el.value = filters.search; });
        document.querySelectorAll('.task-filter-status').forEach(el => { el.value = filters.status; });
        document.querySelectorAll('.task-filter-priority').forEach(el => { el.value = filters.priority; });
        document.querySelectorAll('.task-filter-assignee').forEach(el => { el.value = filters.assignee; });
        document.querySelectorAll('.task-filter-mine').forEach(el => { el.value = filters.assignee === String(cfg.currentUserId) ? 'mine' : 'all'; });
        document.querySelectorAll('.task-filter-date').forEach(el => { el.value = filters.dateRange; });
    }

    const today = () => new Date().toISOString().slice(0, 10);

    function applyFilters(tasks) {
        const q = filters.search.trim().toLowerCase();
        return tasks.filter(t => {
            if (q && t.title.toLowerCase().indexOf(q) === -1) return false;
            if (filters.status !== 'all' && t.status !== filters.status) return false;
            if (filters.priority !== 'all' && t.priority !== filters.priority) return false;
            const taskAssigneeIds = (t.assignees || []).map(a => a.id);
            if (filters.assignee === 'unassigned' && taskAssigneeIds.length) return false;
            if (filters.assignee !== 'all' && filters.assignee !== 'unassigned' && taskAssigneeIds.indexOf(parseInt(filters.assignee, 10)) === -1) return false;
            if (filters.overdueOnly && !(t.due_date && t.due_date < today() && t.status !== 'done')) return false;
            if (filters.dateRange !== 'all') {
                const cutoff = new Date();
                cutoff.setDate(cutoff.getDate() - parseInt(filters.dateRange, 10));
                if (new Date(t.created_at) < cutoff) return false;
            }
            return true;
        });
    }


    const debouncedSearch = debounce(function (value) {
        filters.search = value;
        refreshAll();
    }, 150);

    document.addEventListener('input', function (ev) {
        if (ev.target.classList.contains('task-filter-search')) debouncedSearch(ev.target.value);
    });
    document.addEventListener('change', function (ev) {
        if (ev.target.classList.contains('task-filter-status')) { filters.status = ev.target.value; refreshAll(); }
        if (ev.target.classList.contains('task-filter-priority')) { filters.priority = ev.target.value; refreshAll(); }
        if (ev.target.classList.contains('task-filter-assignee')) { filters.assignee = ev.target.value; refreshAll(); }
        if (ev.target.classList.contains('task-filter-mine')) { filters.assignee = ev.target.value === 'mine' ? String(cfg.currentUserId) : 'all'; refreshAll(); }
        if (ev.target.classList.contains('task-filter-date')) { filters.dateRange = ev.target.value; refreshAll(); }
    });

    /* ---------- List view ---------- */

    function sortTasks(tasks) {
        const key = sortState.key, dir = sortState.dir === 'asc' ? 1 : -1;
        return tasks.slice().sort(function (a, b) {
            let av = a[key], bv = b[key];
            if (av === null || av === undefined || av === '') return 1;
            if (bv === null || bv === undefined || bv === '') return -1;
            if (av < bv) return -1 * dir;
            if (av > bv) return 1 * dir;
            return 0;
        });
    }

    function taskListHeadHTML(includeSelectAll) {
        const headers = [
            { label: 'Title' }, { label: 'Assignee' }, { label: 'Status' }, { label: 'Priority' },
            { label: 'Created' }, { label: 'Updated' }, { label: 'Due' },
        ];
        let html = (cfg.canManage && includeSelectAll ? '<label class="task-list-check"><input type="checkbox" id="taskSelectAll"></label>' : (cfg.canManage ? '<span></span>' : ''));
        headers.forEach(h => { html += '<span>' + h.label + '</span>'; });
        return html;
    }

    const PRIORITY_ICONS = {
        high: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>',
        medium: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="9" x2="19" y2="9"/><line x1="5" y1="15" x2="19" y2="15"/></svg>',
        low: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>',
    };
    function priorityCellHTML(priority) {
        return '<span class="grid-priority grid-priority-' + priority + '">' + (PRIORITY_ICONS[priority] || '') + escapeHtml(cap(priority)) + '</span>';
    }

    function statusCellHTML(status, label) {
        return '<span class="grid-status-badge grid-status-' + status + '">' + escapeHtml(label) + '</span>';
    }

    /** True if this user currently has a project_members row (or is the manager) — state.members
     *  only ever holds current members, so this reflects live membership, not history. */
    function isCurrentMember(userId) {
        return state.members.some(function (m) { return m.id === userId; });
    }

    /** Assignee column for the Tasks list. Removing someone from Members never touches a task's
     *  assignee history (see confirmRemoveMember/removeMember in project-detail.js) — so a task
     *  can still be assigned to someone who isn't a current member anymore. For an already-done
     *  task that's the correct, permanent historical record and gets no special treatment. For a
     *  still-open task it's flagged here so a manager notices and can reassign — clicking anywhere
     *  in the row (including this badge) already opens the task drawer, whose Assignees picker is
     *  scoped to current members only, so that's where the actual reassignment happens. */
    function assigneeCellHTML(t) {
        const assignees = t.assignees || [];
        if (!assignees.length) return '<span class="avatar-stack-empty">Unassigned</span>';
        const flagStale = t.status !== 'done';
        return '<div class="assignee-name-list">' + assignees.map(function (p) {
            const stale = flagStale && !isCurrentMember(p.id);
            return '<span class="assignee-name-row' + (stale ? ' assignee-name-row-stale' : '') + '">' +
                avatarHTML(p, 'avatar-sm') +
                '<span class="assignee-name-col">' +
                '<span class="assignee-name-text">' + escapeHtml(p.name) + '</span>' +
                (stale ? '<span class="assignee-stale-badge" title="No longer an active project member">Not on team · Reassign</span>' : '') +
                '</span>' +
                '</span>';
        }).join('') + '</div>';
    }

    function taskListRowHTML(t) {
        const assignees = t.assignees || [];
        return '<div class="task-list-row" data-task-id="' + t.id + '">' +
            (cfg.canManage ? '<label class="task-list-check"><input type="checkbox" class="task-select-checkbox" data-task-id="' + t.id + '"' + (selectedIds.has(t.id) ? ' checked' : '') + '></label>' : '') +
            '<span class="task-list-title">' + escapeHtml(t.title) + '</span>' +
            '<span>' + assigneeCellHTML(t) + '</span>' +
            '<span>' + statusCellHTML(t.status, cfg.statusLabels[t.status] || t.status) + '</span>' +
            '<span>' + priorityCellHTML(t.priority) + '</span>' +
            '<span class="task-list-date">' + escapeHtml(fmtDate(t.created_at.slice(0, 10))) + '</span>' +
            '<span class="task-list-date">' + escapeHtml(fmtDate(t.updated_at.slice(0, 10))) + '</span>' +
            '<span>' + ProjectUI.dueBadgeHTML(t.due_date, t.status) + '</span>' +
            '</div>';
    }

    function renderList() {
        const container = document.getElementById('listViewContainer');
        if (!container) return;
        const tasks = sortTasks(applyFilters(state.tasks));
        const visibleIds = new Set(tasks.map(t => t.id));
        Array.from(selectedIds).forEach(id => { if (!visibleIds.has(id)) selectedIds.delete(id); });

        if (!tasks.length) {
            container.innerHTML = '<div class="empty-state">No tasks match the current filters.</div>';
            updateBulkBar();
            return;
        }

        container.innerHTML = '<div class="task-list' + (cfg.canManage ? ' has-select' : '') + '">' +
            '<div class="task-list-head">' + taskListHeadHTML(true) + '</div>' +
            tasks.map(taskListRowHTML).join('') + '</div>';

        const selectAll = container.querySelector('#taskSelectAll');
        if (selectAll) {
            selectAll.checked = tasks.length > 0 && tasks.every(t => selectedIds.has(t.id));
            selectAll.addEventListener('change', function () {
                if (selectAll.checked) tasks.forEach(t => selectedIds.add(t.id));
                else tasks.forEach(t => selectedIds.delete(t.id));
                renderList();
            });
        }

        container.querySelectorAll('.task-select-checkbox').forEach(function (cb) {
            cb.addEventListener('click', function (ev) { ev.stopPropagation(); });
            cb.addEventListener('change', function () {
                const id = parseInt(cb.dataset.taskId, 10);
                if (cb.checked) selectedIds.add(id); else selectedIds.delete(id);
                updateBulkBar();
                const sa = container.querySelector('#taskSelectAll');
                if (sa) sa.checked = tasks.length > 0 && tasks.every(t => selectedIds.has(t.id));
            });
        });

        container.querySelectorAll('.task-list-row').forEach(row => {
            const task = state.tasks.find(t => t.id === parseInt(row.dataset.taskId, 10));
            row.addEventListener('click', function (ev) {
                if (ev.target.closest('.task-list-check')) return;
                if (task) ProjectUI.openTaskDrawer(task);
            });
        });

        updateBulkBar();
    }

    /* ---------- Bulk actions ---------- */

    function updateBulkBar() {
        const bar = document.getElementById('listBulkBar');
        if (!bar) return;
        const count = selectedIds.size;
        bar.style.display = count > 0 ? '' : 'none';
        const countEl = document.getElementById('listBulkCount');
        if (countEl) countEl.textContent = count + ' selected';
    }

    const bulkStatusSelect = document.getElementById('listBulkStatus');
    if (bulkStatusSelect) {
        bulkStatusSelect.innerHTML = '<option value="">Change status…</option>' +
            Object.keys(cfg.statusLabels).map(k => '<option value="' + k + '">' + cfg.statusLabels[k] + '</option>').join('');
    }

    function applyBulk(fields) {
        if (!selectedIds.size) return;
        apiPost('api/projects/tasks.php', Object.assign({
            action: 'bulk_update',
            project_id: cfg.projectId,
            task_ids: Array.from(selectedIds),
        }, fields)).then(function (data) {
            data.tasks.forEach(function (t) {
                const idx = state.tasks.findIndex(x => x.id === t.id);
                if (idx !== -1) state.tasks[idx] = t;
                ProjectUI.replaceCardInBoard(t);
            });
            if (ProjectUI.refreshHeaderStats) ProjectUI.refreshHeaderStats();
            if (ProjectUI.refreshMemberStats) ProjectUI.refreshMemberStats();
            refreshAll();
        }).catch(function (err) { alert(err.message); });
    }

    if (bulkStatusSelect) {
        bulkStatusSelect.addEventListener('change', function () {
            if (!bulkStatusSelect.value) return;
            applyBulk({ status: bulkStatusSelect.value });
            bulkStatusSelect.value = '';
        });
    }
    const bulkPrioritySelect = document.getElementById('listBulkPriority');
    if (bulkPrioritySelect) {
        bulkPrioritySelect.addEventListener('change', function () {
            if (!bulkPrioritySelect.value) return;
            applyBulk({ priority: bulkPrioritySelect.value });
            bulkPrioritySelect.value = '';
        });
    }
    const bulkAssigneePickerRoot = document.getElementById('listBulkAssigneePicker');
    if (bulkAssigneePickerRoot && typeof empPickerHTML === 'function') {
        bulkAssigneePickerRoot.innerHTML = empPickerHTML('bulk_assignee', 'Assign to…');
        initEmpPicker(bulkAssigneePickerRoot, {
            projectId: cfg.projectId, mode: 'members',
            onSelect: function (employee) {
                applyBulk({ assigned_to: employee.id });
                bulkAssigneePickerRoot.querySelector('.emp-picker-search').value = '';
                bulkAssigneePickerRoot.querySelector('.emp-picker-hidden').value = '';
            },
        });
    }
    const bulkDeleteBtn = document.getElementById('listBulkDelete');
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function () {
            if (!selectedIds.size) return;
            const count = selectedIds.size;
            confirmModal('Delete ' + count + ' selected task' + (count === 1 ? '' : 's') + '? This cannot be undone.', function () {
                const ids = Array.from(selectedIds);
                apiPost('api/projects/tasks.php', {
                    action: 'bulk_delete',
                    project_id: cfg.projectId,
                    task_ids: ids,
                }).then(function () {
                    state.tasks = state.tasks.filter(t => ids.indexOf(t.id) === -1);
                    ids.forEach(function (id) {
                        const card = document.querySelector('.task-card[data-task-id="' + id + '"]');
                        if (card) card.remove();
                    });
                    selectedIds.clear();
                    if (ProjectUI.refreshHeaderStats) ProjectUI.refreshHeaderStats();
                    if (ProjectUI.refreshMemberStats) ProjectUI.refreshMemberStats();
                    ProjectUI.refreshTaskVisibility();
                    refreshAll();
                }).catch(function (err) { alert(err.message); });
            }, { okLabel: 'Delete' });
        });
    }
    const bulkClear = document.getElementById('listBulkClear');
    if (bulkClear) {
        bulkClear.addEventListener('click', function (ev) {
            ev.preventDefault();
            selectedIds.clear();
            renderList();
        });
    }

    /* ---------- Orchestration ---------- */

    function refreshAll() {
        populateFilterSelects();
        syncToolbars();
        renderList();
    }

    ProjectUI.onDataChanged = refreshAll;
    refreshAll();
})();
