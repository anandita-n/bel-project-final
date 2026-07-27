/* Board filtering + List renderer for the project detail page.
   Reads/writes window.ProjectUI.state (populated by project-detail.js). Both views
   render from the same in-memory tasks array — no extra AJAX calls. */

(function () {
    const ProjectUI = window.ProjectUI;
    const cfg = ProjectUI.cfg;
    const state = ProjectUI.state;

    const filters = { search: '', status: 'all', priority: 'all', assignee: 'all' };
    const sortState = { key: 'due_date', dir: 'asc' };

    /* ---------- Filters ---------- */

    function populateFilterSelects() {
        const statusHtml = '<option value="all">All statuses</option>' +
            Object.keys(cfg.statusLabels).map(k => '<option value="' + k + '">' + cfg.statusLabels[k] + '</option>').join('');
        document.querySelectorAll('.task-filter-status').forEach(sel => {
            const cur = sel.value;
            sel.innerHTML = statusHtml;
            sel.value = cur || 'all';
        });

        const assignees = {};
        state.tasks.forEach(t => { if (t.assigned_to) assignees[t.assigned_to] = t.assignee_name; });
        let assigneeHtml = '<option value="all">All assignees</option><option value="unassigned">Unassigned</option>';
        Object.keys(assignees).forEach(id => { assigneeHtml += '<option value="' + id + '">' + escapeHtml(assignees[id]) + '</option>'; });
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
    }

    function applyFilters(tasks) {
        const q = filters.search.trim().toLowerCase();
        return tasks.filter(t => {
            if (q && t.title.toLowerCase().indexOf(q) === -1) return false;
            if (filters.status !== 'all' && t.status !== filters.status) return false;
            if (filters.priority !== 'all' && t.priority !== filters.priority) return false;
            if (filters.assignee === 'unassigned' && t.assigned_to) return false;
            if (filters.assignee !== 'all' && filters.assignee !== 'unassigned' && t.assigned_to !== parseInt(filters.assignee, 10)) return false;
            return true;
        });
    }

    function applyBoardFiltering() {
        const filtered = new Set(applyFilters(state.tasks).map(t => t.id));
        document.querySelectorAll('#kanbanBoard .task-card').forEach(card => {
            const id = parseInt(card.dataset.taskId, 10);
            card.style.display = filtered.has(id) ? '' : 'none';
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

    function renderList() {
        const container = document.getElementById('listViewContainer');
        if (!container) return;
        const tasks = sortTasks(applyFilters(state.tasks));

        if (!tasks.length) {
            container.innerHTML = '<div class="empty-state">No tasks match the current filters.</div>';
            return;
        }

        const headers = [
            { key: 'title', label: 'Title' },
            { key: 'assignee_name', label: 'Assignee' },
            { key: 'status', label: 'Status' },
            { key: 'priority', label: 'Priority' },
            { key: 'due_date', label: 'Due' },
        ];
        let html = '<div class="task-list"><div class="task-list-head">';
        headers.forEach(h => {
            html += '<button type="button" data-sort="' + h.key + '">' + h.label + (sortState.key === h.key ? (sortState.dir === 'asc' ? ' ↑' : ' ↓') : '') + '</button>';
        });
        html += '<span></span></div>';
        tasks.forEach(t => {
            const overdue = t.due_date && t.due_date < new Date().toISOString().slice(0, 10) && t.status !== 'done';
            html += '<div class="task-list-row priority-' + t.priority + '" data-task-id="' + t.id + '">' +
                '<span class="task-list-title">' + escapeHtml(t.title) + '</span>' +
                '<span>' + (t.assignee_name ? escapeHtml(t.assignee_name) : 'Unassigned') + '</span>' +
                '<span class="tag tag-' + t.status + '">' + escapeHtml(cfg.statusLabels[t.status] || t.status) + '</span>' +
                '<span class="tag tag-' + t.priority + '">' + escapeHtml(cap(t.priority)) + '</span>' +
                '<span class="task-list-due' + (overdue ? ' overdue' : '') + '">' + fmtDate(t.due_date) + '</span>' +
                (cfg.canManage ? '<button type="button" class="task-kebab" title="More actions">&#8942;</button>' : '<span></span>') +
                '</div>';
        });
        html += '</div>';
        container.innerHTML = html;

        container.querySelectorAll('[data-sort]').forEach(btn => {
            btn.addEventListener('click', function () {
                const key = btn.dataset.sort;
                if (sortState.key === key) sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
                else { sortState.key = key; sortState.dir = 'asc'; }
                renderList();
            });
        });
        container.querySelectorAll('.task-list-row').forEach(row => {
            const task = state.tasks.find(t => t.id === parseInt(row.dataset.taskId, 10));
            const kebab = row.querySelector('.task-kebab');
            if (kebab) {
                initOverflowMenu(kebab, [{
                    label: 'Delete', danger: true, onClick: function () {
                        confirmModal('Delete this task?', function () {
                            apiPost('api/projects/tasks.php', { action: 'delete', project_id: cfg.projectId, task_id: task.id })
                                .then(function () {
                                    state.tasks = state.tasks.filter(t => t.id !== task.id);
                                    const card = document.querySelector('.task-card[data-task-id="' + task.id + '"]');
                                    if (card) card.remove();
                                    ProjectUI.refreshTaskVisibility();
                                    refreshAll();
                                }).catch(function (err) { alert(err.message); });
                        }, { okLabel: 'Delete' });
                    },
                }]);
            }
            row.addEventListener('click', function (ev) {
                if (ev.target.closest('.task-kebab') || ev.target.closest('.overflow-menu')) return;
                if (task) ProjectUI.openTaskDrawer(task);
            });
        });
    }

    /* ---------- Orchestration ---------- */

    function refreshAll() {
        populateFilterSelects();
        syncToolbars();
        applyBoardFiltering();
        renderList();
    }

    ProjectUI.onDataChanged = refreshAll;
    refreshAll();
})();
