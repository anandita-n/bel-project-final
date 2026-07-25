/* Project detail page: team members + kanban task board, all AJAX with Jira-style modal popups
   for the "add" actions instead of stacked inline forms.
   Expects window.PAGE_CONFIG = { projectId, canManage, currentUserId, statusLabels } to be set by the page. */

(function () {
    const cfg = window.PAGE_CONFIG;
    const pageErrorBox = document.getElementById('pageError');

    function showError(box, message) {
        box.textContent = message;
        box.style.display = 'block';
        setTimeout(() => { box.style.display = 'none'; }, 4000);
    }

    /* ---------- Team members ---------- */

    function addMemberRow(m) {
        const tbody = document.getElementById('membersTbody');
        const tr = document.createElement('tr');
        tr.dataset.userId = m.id;
        tr.innerHTML =
            '<td><div class="row-name"><span class="avatar avatar-' + m.system_role + '">' + initials(m.name) + '</span>' +
            '<a href="employee_detail.php?id=' + m.id + '">' + escapeHtml(m.name) + '</a></div></td>' +
            '<td>' + escapeHtml(m.email) + '</td>' +
            '<td><span class="tag tag-' + m.system_role + '">' + escapeHtml(cap(m.system_role)) + '</span></td>' +
            '<td>' + escapeHtml(m.role_in_project) + '</td>' +
            '<td>' + escapeHtml(m.department || '—') + '</td>' +
            '<td class="actions"><button type="button" class="btn btn-danger btn-sm remove-member-btn">Remove</button></td>';
        tbody.appendChild(tr);
        document.getElementById('membersTable').style.display = '';
        document.getElementById('membersEmpty').style.display = 'none';
        document.getElementById('memberCount').textContent = tbody.children.length;
    }

    const openAddMemberBtn = document.getElementById('openAddMemberModal');
    if (openAddMemberBtn) {
        openAddMemberBtn.addEventListener('click', function () {
            const overlay = openModal('Add Team Member', '' +
                '<div id="addMemberModalError" class="error-msg" style="display:none;"></div>' +
                '<form id="addMemberForm">' +
                '<div class="field"><label>Employee</label><div id="addMemberPicker"></div></div>' +
                '<div class="field"><label>Role in Project</label><input type="text" id="addMemberRole" placeholder="e.g. Developer, Tester"></div>' +
                '<button type="submit" class="btn">Add Member</button>' +
                '</form>');

            const pickerRoot = overlay.querySelector('#addMemberPicker');
            pickerRoot.innerHTML = empPickerHTML('member_id', 'Search name or employee ID…');
            initEmpPicker(pickerRoot, { projectId: cfg.projectId, mode: 'available' });

            overlay.querySelector('#addMemberForm').addEventListener('submit', function (ev) {
                ev.preventDefault();
                const errorBox = overlay.querySelector('#addMemberModalError');
                const userId = pickerRoot.querySelector('.emp-picker-hidden').value;
                const role = overlay.querySelector('#addMemberRole').value.trim();
                if (!userId) { showError(errorBox, 'Please select an employee.'); return; }

                apiPost('api/projects/members.php', { action: 'add', project_id: cfg.projectId, user_id: userId, role_in_project: role })
                    .then(function (data) { addMemberRow(data.member); closeModal(); })
                    .catch(function (err) { showError(errorBox, err.message); });
            });
        });
    }

    const membersTbody = document.getElementById('membersTbody');
    if (membersTbody) {
        membersTbody.addEventListener('click', function (ev) {
            if (!ev.target.classList.contains('remove-member-btn')) return;
            const row = ev.target.closest('tr');
            if (!confirm('Remove this member from the project?')) return;

            apiPost('api/projects/members.php', { action: 'remove', project_id: cfg.projectId, user_id: row.dataset.userId })
                .then(function () {
                    row.remove();
                    document.getElementById('memberCount').textContent = membersTbody.children.length;
                    if (!membersTbody.children.length) {
                        document.getElementById('membersTable').style.display = 'none';
                        document.getElementById('membersEmpty').style.display = '';
                    }
                })
                .catch(function (err) { showError(pageErrorBox, err.message); });
        });
    }

    /* ---------- Tasks / kanban ---------- */

    function taskCardHTML(t) {
        const canUpdate = cfg.canManage || t.assigned_to === cfg.currentUserId;
        const overdue = t.due_date && t.due_date < new Date().toISOString().slice(0, 10) && t.status !== 'done';
        let statusOptions = '';
        for (const key in cfg.statusLabels) {
            statusOptions += '<option value="' + key + '"' + (key === t.status ? ' selected' : '') + '>' + cfg.statusLabels[key] + '</option>';
        }
        const assigneeHtml = t.assignee_name
            ? '<span class="avatar avatar-sm avatar-' + t.assignee_role + '" style="vertical-align:middle;">' + initials(t.assignee_name) + '</span> ' + escapeHtml(t.assignee_name)
            : 'Unassigned';

        return '' +
            '<div class="task-card priority-' + t.priority + '" data-task-id="' + t.id + '" data-current-status="' + t.status + '">' +
            '<span class="task-title">' + escapeHtml(t.title) + '</span>' +
            '<div class="task-meta"><span>' + assigneeHtml + '</span>' +
            '<span class="task-due' + (overdue ? ' overdue' : '') + '">' + fmtDate(t.due_date) + '</span></div>' +
            (canUpdate ? '<select class="task-status-select">' + statusOptions + '</select>' : '') +
            (cfg.canManage ? '<div class="task-actions"><button type="button" class="delete-task-btn">Delete</button></div>' : '') +
            '</div>';
    }

    function refreshTaskVisibility() {
        const board = document.getElementById('kanbanBoard');
        const anyTasks = board.querySelectorAll('.task-card').length > 0;
        board.style.display = anyTasks ? '' : 'none';
        document.getElementById('tasksEmpty').style.display = anyTasks ? 'none' : '';
        document.querySelectorAll('.kanban-col').forEach(function (col) {
            col.querySelector('.col-count').textContent = col.querySelectorAll('.task-card').length;
        });
        document.getElementById('taskCount').textContent = board.querySelectorAll('.task-card').length;
    }

    function bindTaskCard(card) {
        const select = card.querySelector('.task-status-select');
        if (select) {
            select.addEventListener('change', function () {
                apiPost('api/projects/tasks.php', { action: 'update_status', project_id: cfg.projectId, task_id: card.dataset.taskId, status: select.value })
                    .then(function (data) {
                        card.dataset.currentStatus = data.task.status;
                        const targetBody = document.querySelector('[data-status-body="' + data.task.status + '"]');
                        targetBody.appendChild(card);
                        refreshTaskVisibility();
                    })
                    .catch(function (err) { showError(pageErrorBox, err.message); select.value = card.dataset.currentStatus; });
            });
        }
        const del = card.querySelector('.delete-task-btn');
        if (del) {
            del.addEventListener('click', function () {
                if (!confirm('Delete this task?')) return;
                apiPost('api/projects/tasks.php', { action: 'delete', project_id: cfg.projectId, task_id: card.dataset.taskId })
                    .then(function () { card.remove(); refreshTaskVisibility(); })
                    .catch(function (err) { showError(pageErrorBox, err.message); });
            });
        }
    }

    document.querySelectorAll('.task-card').forEach(bindTaskCard);

    const openAddTaskBtn = document.getElementById('openAddTaskModal');
    if (openAddTaskBtn) {
        openAddTaskBtn.addEventListener('click', function () {
            const overlay = openModal('Add Task', '' +
                '<div id="addTaskModalError" class="error-msg" style="display:none;"></div>' +
                '<form id="addTaskForm">' +
                '<div class="field"><label>Task Title</label><input type="text" id="taskTitle" required></div>' +
                '<div class="field"><label>Assign To</label><div id="assigneePicker"></div></div>' +
                '<div class="field"><label>Priority</label><select id="taskPriority">' +
                '<option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option>' +
                '</select></div>' +
                '<div class="field"><label>Due Date</label><input type="date" id="taskDueDate"></div>' +
                '<div class="field"><label>Description</label><textarea id="taskDescription" rows="2"></textarea></div>' +
                '<button type="submit" class="btn">Add Task</button>' +
                '</form>');

            const assigneePickerRoot = overlay.querySelector('#assigneePicker');
            assigneePickerRoot.innerHTML = empPickerHTML('assigned_to', 'Search name or employee ID…');
            initEmpPicker(assigneePickerRoot, { projectId: cfg.projectId, mode: 'members' });

            overlay.querySelector('#addTaskForm').addEventListener('submit', function (ev) {
                ev.preventDefault();
                const errorBox = overlay.querySelector('#addTaskModalError');
                const title = overlay.querySelector('#taskTitle').value.trim();
                if (!title) { showError(errorBox, 'Task title is required.'); return; }

                apiPost('api/projects/tasks.php', {
                    action: 'create',
                    project_id: cfg.projectId,
                    title: title,
                    assigned_to: assigneePickerRoot.querySelector('.emp-picker-hidden').value,
                    priority: overlay.querySelector('#taskPriority').value,
                    due_date: overlay.querySelector('#taskDueDate').value,
                    description: overlay.querySelector('#taskDescription').value.trim(),
                }).then(function (data) {
                    const body = document.querySelector('[data-status-body="' + data.task.status + '"]');
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = taskCardHTML(data.task);
                    const card = wrapper.firstElementChild;
                    body.appendChild(card);
                    bindTaskCard(card);
                    refreshTaskVisibility();
                    closeModal();
                }).catch(function (err) { showError(errorBox, err.message); });
            });
        });
    }
})();
