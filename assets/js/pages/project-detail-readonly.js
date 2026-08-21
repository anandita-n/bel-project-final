/* Same-department read-only project view: client-side search/filter over the server-rendered
   .task-list-row elements (there is no edit capability here, so this never touches the network —
   everything needed is already in the DOM via data-* attributes). */

(function () {
    const wrap = document.getElementById('readonlyTaskWrap');
    const flatList = document.getElementById('readonlyTaskList');
    if (!wrap || !flatList) return;

    const searchInput = document.getElementById('roTaskSearch');
    const statusSelect = document.getElementById('roTaskStatus');
    const prioritySelect = document.getElementById('roTaskPriority');
    const mineSelect = document.getElementById('roTaskMine');
    const dateSelect = document.getElementById('roTaskDate');
    if (!searchInput || !statusSelect || !prioritySelect) return;

    const currentUserId = (window.ROJcfg && window.ROJcfg.currentUserId) || 0;
    const allRows = Array.from(flatList.querySelectorAll('.task-list-row'));

    function matches(row) {
        const q = searchInput.value.trim().toLowerCase();
        if (q && row.dataset.title.indexOf(q) === -1) return false;
        if (statusSelect.value !== 'all' && row.dataset.status !== statusSelect.value) return false;
        if (prioritySelect.value !== 'all' && row.dataset.priority !== prioritySelect.value) return false;
        if (mineSelect && mineSelect.value === 'mine') {
            const assigneeIds = (row.dataset.assignees || '').split(',').filter(Boolean).map(Number);
            if (assigneeIds.indexOf(currentUserId) === -1) return false;
        }
        if (dateSelect && dateSelect.value !== 'all') {
            const cutoff = new Date();
            cutoff.setDate(cutoff.getDate() - parseInt(dateSelect.value, 10));
            if (!row.dataset.created || new Date(row.dataset.created) < cutoff) return false;
        }
        return true;
    }

    function showEmptyIfNeeded(visibleCount) {
        wrap.querySelectorAll('.task-list-empty-msg').forEach(function (el) { el.remove(); });
        if (visibleCount) return;
        const msg = document.createElement('div');
        msg.className = 'empty-state task-list-empty-msg';
        msg.textContent = 'No tasks match the current filters.';
        wrap.appendChild(msg);
    }

    function apply() {
        let visibleCount = 0;
        allRows.forEach(function (row) {
            const show = matches(row);
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });
        showEmptyIfNeeded(visibleCount);
    }

    [searchInput, statusSelect, prioritySelect, mineSelect, dateSelect].filter(Boolean).forEach(function (el) {
        el.addEventListener('input', apply);
        el.addEventListener('change', apply);
    });
})();
