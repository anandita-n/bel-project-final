/* Project detail page: compact header, tabbed layout, drawer-based task/member detail,
   kebab menus for secondary actions. Board/List/Calendar/Timeline rendering + filters
   live in project-detail-views.js (loaded after this file); this file owns the shared
   state object (window.ProjectUI) and all create/update/delete AJAX + the drawers.
   Expects window.PAGE_CONFIG = {projectId, canManage, currentUserId, statusLabels}
   and window.PAGE_STATE = {members, tasks} to be set by the page. */

(function () {
    const cfg = window.PAGE_CONFIG;
    const pageErrorBox = document.getElementById('pageError');

    const state = {
        tasks: window.PAGE_STATE.tasks.slice(),
        members: window.PAGE_STATE.members.slice(),
    };

    const ProjectUI = window.ProjectUI = {
        cfg: cfg,
        state: state,
        onDataChanged: null, // set by project-detail-views.js
    };

    function showError(box, message) {
        box.textContent = message;
        box.style.display = 'block';
        setTimeout(() => { box.style.display = 'none'; }, 4000);
    }

    function notifyDataChanged() {
        refreshHeaderStats();
        if (typeof ProjectUI.onDataChanged === 'function') ProjectUI.onDataChanged();
    }

    /* ---------- Tabs ---------- */

    initTabs(document.getElementById('projectTabs'), { defaultTab: 'board' });

    function refreshHeaderStats() {
        document.querySelector('.tab-btn[data-tab="members"]').textContent = 'Members (' + state.members.length + ')';
    }
    ProjectUI.refreshHeaderStats = refreshHeaderStats;

    /* ---------- Task card + drawer ---------- */

    function taskCardHTML(t) {
        const overdue = t.due_date && t.due_date < new Date().toISOString().slice(0, 10) && t.status !== 'done';
        let statusOptions = '';
        for (const key in cfg.statusLabels) {
            statusOptions += '<option value="' + key + '"' + (key === t.status ? ' selected' : '') + '>' + cfg.statusLabels[key] + '</option>';
        }
        const canUpdate = cfg.canManage || t.assigned_to === cfg.currentUserId;
        const assigneeHtml = t.assignee_name
            ? '<span class="avatar avatar-sm avatar-' + t.assignee_role + '" style="vertical-align:middle;">' + initials(t.assignee_name) + '</span> ' + escapeHtml(t.assignee_name)
            : 'Unassigned';
        const dateLabel = t.start_date ? (fmtDate(t.start_date) + ' → ' + fmtDate(t.due_date)) : fmtDate(t.due_date);

        return '' +
            '<div class="task-card priority-' + t.priority + '" data-task-id="' + t.id + '" data-current-status="' + t.status + '">' +
            (cfg.canManage ? '<button type="button" class="task-kebab" title="More actions">&#8942;</button>' : '') +
            '<span class="task-title">' + escapeHtml(t.title) + '</span>' +
            '<div class="task-meta"><span>' + assigneeHtml + '</span>' +
            '<span class="task-due' + (overdue ? ' overdue' : '') + '">' + dateLabel + '</span></div>' +
            (canUpdate ? '<select class="task-status-select">' + statusOptions + '</select>' : '') +
            '</div>';
    }
    ProjectUI.taskCardHTML = taskCardHTML;

    function refreshTaskVisibility() {
        const board = document.getElementById('kanbanBoard');
        const anyTasks = board.querySelectorAll('.task-card').length > 0;
        board.style.display = anyTasks ? '' : 'none';
        document.getElementById('tasksEmpty').style.display = anyTasks ? 'none' : '';
        document.querySelectorAll('.kanban-col').forEach(function (col) {
            col.querySelector('.col-count').textContent = col.querySelectorAll('.task-card').length;
        });
    }
    ProjectUI.refreshTaskVisibility = refreshTaskVisibility;

    function bindTaskCard(card) {
        const select = card.querySelector('.task-status-select');
        if (select) {
            select.addEventListener('click', function (ev) { ev.stopPropagation(); });
            select.addEventListener('change', function () {
                apiPost('api/projects/tasks.php', { action: 'update_status', project_id: cfg.projectId, task_id: card.dataset.taskId, status: select.value })
                    .then(function (data) {
                        patchTaskInState(data.task);
                        card.dataset.currentStatus = data.task.status;
                        const targetBody = document.querySelector('[data-status-body="' + data.task.status + '"]');
                        if (targetBody) targetBody.appendChild(card);
                        refreshTaskVisibility();
                        notifyDataChanged();
                    })
                    .catch(function (err) { showError(pageErrorBox, err.message); select.value = card.dataset.currentStatus; });
            });
        }
        const kebab = card.querySelector('.task-kebab');
        if (kebab) {
            initOverflowMenu(kebab, [{
                label: 'Delete', danger: true, onClick: function () {
                    confirmModal('Delete this task?', function () {
                        apiPost('api/projects/tasks.php', { action: 'delete', project_id: cfg.projectId, task_id: card.dataset.taskId })
                            .then(function () {
                                state.tasks = state.tasks.filter(t => t.id !== parseInt(card.dataset.taskId, 10));
                                card.remove();
                                refreshTaskVisibility();
                                notifyDataChanged();
                            })
                            .catch(function (err) { showError(pageErrorBox, err.message); });
                    }, { okLabel: 'Delete' });
                },
            }]);
        }
        card.addEventListener('click', function (ev) {
            if (ev.target.closest('.task-kebab') || ev.target.closest('.overflow-menu') || ev.target.closest('select')) return;
            const task = state.tasks.find(t => t.id === parseInt(card.dataset.taskId, 10));
            if (task) openTaskDrawer(task);
        });
    }
    ProjectUI.bindTaskCard = bindTaskCard;

    function patchTaskInState(task) {
        const idx = state.tasks.findIndex(t => t.id === task.id);
        if (idx !== -1) state.tasks[idx] = task; else state.tasks.push(task);
    }

    function openTaskDrawer(task) {
        const canEdit = cfg.canManage;
        let statusOptions = '';
        for (const key in cfg.statusLabels) {
            statusOptions += '<option value="' + key + '"' + (key === task.status ? ' selected' : '') + '>' + cfg.statusLabels[key] + '</option>';
        }
        const overlay = openDrawer(escapeHtml(task.title), '' +
            '<div id="taskDrawerError" class="error-msg" style="display:none;"></div>' +
            '<form id="taskDrawerForm">' +
            '<div class="field"><label>Task Title</label><input type="text" id="tdTitle" value="' + escapeHtml(task.title) + '"' + (canEdit ? '' : ' disabled') + ' required></div>' +
            '<div class="field"><label>Description</label><textarea id="tdDescription" rows="3"' + (canEdit ? '' : ' disabled') + '>' + escapeHtml(task.description || '') + '</textarea></div>' +
            (canEdit ? '<div class="field"><label>Assignee</label><div id="tdAssigneePicker"></div></div>' : '') +
            '<div class="field"><label>Priority</label><select id="tdPriority"' + (canEdit ? '' : ' disabled') + '>' +
            '<option value="low"' + (task.priority === 'low' ? ' selected' : '') + '>Low</option>' +
            '<option value="medium"' + (task.priority === 'medium' ? ' selected' : '') + '>Medium</option>' +
            '<option value="high"' + (task.priority === 'high' ? ' selected' : '') + '>High</option>' +
            '</select></div>' +
            '<div class="field"><label>Start Date</label><input type="date" id="tdStartDate" value="' + (task.start_date || '') + '"' + (canEdit ? '' : ' disabled') + '></div>' +
            '<div class="field"><label>Due Date</label><input type="date" id="tdDueDate" value="' + (task.due_date || '') + '"' + (canEdit ? '' : ' disabled') + '></div>' +
            '<div class="field"><label>Status</label><select id="tdStatus">' + statusOptions + '</select></div>' +
            (canEdit ? '<button type="submit" class="btn">Save Changes</button> <button type="button" id="tdDelete" class="btn btn-danger">Delete Task</button>' : '') +
            '</form>');

        if (canEdit) {
            const pickerRoot = overlay.querySelector('#tdAssigneePicker');
            pickerRoot.innerHTML = empPickerHTML('assigned_to', 'Search name or employee ID…');
            initEmpPicker(pickerRoot, { projectId: cfg.projectId, mode: 'members', selectedId: task.assigned_to || '', selectedLabel: task.assignee_name || '' });

            overlay.querySelector('#taskDrawerForm').addEventListener('submit', function (ev) {
                ev.preventDefault();
                const errorBox = overlay.querySelector('#taskDrawerError');
                const title = overlay.querySelector('#tdTitle').value.trim();
                if (!title) { showError(errorBox, 'Task title is required.'); return; }

                apiPost('api/projects/tasks.php', {
                    action: 'update',
                    project_id: cfg.projectId,
                    task_id: task.id,
                    title: title,
                    description: overlay.querySelector('#tdDescription').value.trim(),
                    assigned_to: pickerRoot.querySelector('.emp-picker-hidden').value,
                    priority: overlay.querySelector('#tdPriority').value,
                    start_date: overlay.querySelector('#tdStartDate').value,
                    due_date: overlay.querySelector('#tdDueDate').value,
                }).then(function (data) {
                    patchTaskInState(data.task);
                    replaceCardInBoard(data.task);
                    notifyDataChanged();
                    closeDrawer();
                }).catch(function (err) { showError(errorBox, err.message); });
            });

            overlay.querySelector('#tdDelete').addEventListener('click', function () {
                confirmModal('Delete this task?', function () {
                    apiPost('api/projects/tasks.php', { action: 'delete', project_id: cfg.projectId, task_id: task.id })
                        .then(function () {
                            state.tasks = state.tasks.filter(t => t.id !== task.id);
                            const card = document.querySelector('.task-card[data-task-id="' + task.id + '"]');
                            if (card) card.remove();
                            refreshTaskVisibility();
                            notifyDataChanged();
                            closeDrawer();
                        })
                        .catch(function (err) { showError(overlay.querySelector('#taskDrawerError'), err.message); });
                }, { okLabel: 'Delete' });
            });
        }

        overlay.querySelector('#tdStatus').addEventListener('change', function () {
            const select = this;
            apiPost('api/projects/tasks.php', { action: 'update_status', project_id: cfg.projectId, task_id: task.id, status: select.value })
                .then(function (data) {
                    patchTaskInState(data.task);
                    replaceCardInBoard(data.task);
                    notifyDataChanged();
                })
                .catch(function (err) { showError(overlay.querySelector('#taskDrawerError'), err.message); select.value = task.status; });
        });
    }
    ProjectUI.openTaskDrawer = openTaskDrawer;

    function replaceCardInBoard(task) {
        const old = document.querySelector('.task-card[data-task-id="' + task.id + '"]');
        const wrapper = document.createElement('div');
        wrapper.innerHTML = taskCardHTML(task);
        const card = wrapper.firstElementChild;
        if (old) {
            const targetBody = document.querySelector('[data-status-body="' + task.status + '"]');
            old.replaceWith(card);
            if (targetBody && card.parentElement !== targetBody) targetBody.appendChild(card);
        } else {
            const targetBody = document.querySelector('[data-status-body="' + task.status + '"]');
            if (targetBody) targetBody.appendChild(card);
        }
        bindTaskCard(card);
        refreshTaskVisibility();
    }
    ProjectUI.replaceCardInBoard = replaceCardInBoard;

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
                '<div class="field"><label>Start Date</label><input type="date" id="taskStartDate"></div>' +
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
                    start_date: overlay.querySelector('#taskStartDate').value,
                    due_date: overlay.querySelector('#taskDueDate').value,
                    description: overlay.querySelector('#taskDescription').value.trim(),
                }).then(function (data) {
                    state.tasks.push(data.task);
                    const body = document.querySelector('[data-status-body="' + data.task.status + '"]');
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = taskCardHTML(data.task);
                    const card = wrapper.firstElementChild;
                    body.appendChild(card);
                    bindTaskCard(card);
                    refreshTaskVisibility();
                    notifyDataChanged();
                    closeModal();
                }).catch(function (err) { showError(errorBox, err.message); });
            });
        });
    }

    /* ---------- Member card + drawer ---------- */

    function memberCardHTML(m) {
        return '' +
            '<div class="member-card" data-user-id="' + m.id + '" data-user-name="' + escapeHtml(m.name) + '">' +
            (cfg.canManage ? '<button type="button" class="task-kebab" title="More actions">&#8942;</button>' : '') +
            '<div class="member-card-head"><span class="avatar avatar-' + m.system_role + '">' + initials(m.name) + '</span>' +
            '<div class="row-name"><strong>' + escapeHtml(m.name) + '</strong> ' +
            '<span class="tag tag-' + m.system_role + '">' + escapeHtml(cap(m.system_role)) + '</span></div></div>' +
            '<div class="member-card-sub">' + escapeHtml(m.email) + '</div>' +
            '<div class="member-card-sub">' + escapeHtml(m.department || '—') + '</div>' +
            '<span class="member-card-role">' + escapeHtml(m.role_in_project) + '</span>' +
            '</div>';
    }
    ProjectUI.memberCardHTML = memberCardHTML;

    function bindMemberCard(card) {
        const kebab = card.querySelector('.task-kebab');
        if (kebab) {
            initOverflowMenu(kebab, [
                { label: 'Comment', onClick: function () { openMemberDrawer(findMember(card.dataset.userId), true); } },
                {
                    label: 'Remove', danger: true, onClick: function () {
                        confirmModal('Remove this member from the project?', function () { removeMember(card); }, { okLabel: 'Remove' });
                    },
                },
            ]);
        }
        card.addEventListener('click', function (ev) {
            if (ev.target.closest('.task-kebab') || ev.target.closest('.overflow-menu') || ev.target.closest('a')) return;
            openMemberDrawer(findMember(card.dataset.userId), false);
        });
    }

    function findMember(userId) {
        return state.members.find(m => m.id === parseInt(userId, 10));
    }

    function removeMember(card) {
        apiPost('api/projects/members.php', { action: 'remove', project_id: cfg.projectId, user_id: card.dataset.userId })
            .then(function () {
                state.members = state.members.filter(m => m.id !== parseInt(card.dataset.userId, 10));
                card.remove();
                refreshMemberVisibility();
                notifyDataChanged();
            })
            .catch(function (err) { showError(pageErrorBox, err.message); });
    }

    function refreshMemberVisibility() {
        const grid = document.getElementById('memberCardGrid');
        const any = grid.children.length > 0;
        grid.style.display = any ? '' : 'none';
        document.getElementById('membersEmpty').style.display = any ? 'none' : '';
    }

    function openMemberDrawer(member, focusComposer) {
        if (!member) return;
        const overlay = openDrawer(escapeHtml(member.name), '' +
            '<div id="memberDrawerError" class="error-msg" style="display:none;"></div>' +
            '<div class="row-name" style="margin-bottom:10px;"><span class="avatar avatar-' + member.system_role + '">' + initials(member.name) + '</span>' +
            '<span><b>' + escapeHtml(member.name) + '</b> <span class="tag tag-' + member.system_role + '">' + escapeHtml(cap(member.system_role)) + '</span></span></div>' +
            '<div class="member-card-sub">' + escapeHtml(member.email) + '</div>' +
            '<div class="member-card-sub">' + escapeHtml(member.department || '—') + '</div>' +
            '<div class="member-card-sub" style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border-light);">' +
            '<strong>' + escapeHtml(cfg.projectName) + '</strong> (' + escapeHtml(cfg.projectCode) + ')</div>' +
            '<div class="member-card-sub">Role on this project: ' + escapeHtml(member.role_in_project) + '</div>' +
            (cfg.canManage ? '' +
                '<h3 style="font-size:12px;color:var(--navy);margin:16px 0 8px;text-transform:uppercase;">Your Comments</h3>' +
                '<div id="memberReviewList" style="margin-bottom:12px;font-size:12px;color:var(--text-muted);">Loading…</div>' +
                '<form id="memberCommentForm">' +
                '<div class="field"><label>Add Comment / Review</label><textarea id="memberCommentText" rows="3" required></textarea></div>' +
                '<button type="submit" class="btn btn-sm">Send Comment</button>' +
                '</form>' +
                '<button type="button" id="memberRemoveBtn" class="btn btn-danger btn-sm" style="margin-top:16px;">Remove from Project</button>'
                : ''));

        if (cfg.canManage) {
            apiPost('api/projects/reviews.php', { action: 'list_for_member', project_id: cfg.projectId, user_id: member.id })
                .then(function (data) { renderReviewList(overlay, data.reviews); })
                .catch(function () { overlay.querySelector('#memberReviewList').textContent = 'Could not load comments.'; });

            overlay.querySelector('#memberCommentForm').addEventListener('submit', function (ev) {
                ev.preventDefault();
                const errorBox = overlay.querySelector('#memberDrawerError');
                const textarea = overlay.querySelector('#memberCommentText');
                const comment = textarea.value.trim();
                if (!comment) { showError(errorBox, 'Comment cannot be empty.'); return; }

                apiPost('api/projects/reviews.php', { action: 'create', project_id: cfg.projectId, user_id: member.id, comment: comment })
                    .then(function () {
                        textarea.value = '';
                        return apiPost('api/projects/reviews.php', { action: 'list_for_member', project_id: cfg.projectId, user_id: member.id });
                    })
                    .then(function (data) { renderReviewList(overlay, data.reviews); })
                    .catch(function (err) { showError(errorBox, err.message); });
            });

            overlay.querySelector('#memberRemoveBtn').addEventListener('click', function () {
                confirmModal('Remove this member from the project?', function () {
                    const card = document.querySelector('.member-card[data-user-id="' + member.id + '"]');
                    removeMember(card);
                    closeDrawer();
                }, { okLabel: 'Remove' });
            });

            if (focusComposer) {
                setTimeout(function () {
                    const ta = overlay.querySelector('#memberCommentText');
                    if (ta) ta.focus();
                }, 200);
            }
        }
    }

    function renderReviewList(overlay, reviews) {
        const box = overlay.querySelector('#memberReviewList');
        if (!box) return;
        if (!reviews.length) { box.textContent = 'No comments yet.'; return; }
        box.innerHTML = reviews.map(r =>
            '<div style="padding:8px 0;border-bottom:1px solid var(--border-light);">' +
            '<div>' + escapeHtml(r.comment) + '</div>' +
            '<div style="font-size:10.5px;margin-top:3px;">' + fmtDate(r.created_at.slice(0, 10)) + '</div>' +
            '</div>'
        ).join('');
    }

    document.querySelectorAll('.member-card').forEach(bindMemberCard);
    ProjectUI.bindMemberCard = bindMemberCard;
    ProjectUI.openMemberDrawer = openMemberDrawer;

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
                    .then(function (data) {
                        state.members.push(data.member);
                        const grid = document.getElementById('memberCardGrid');
                        grid.insertAdjacentHTML('beforeend', memberCardHTML(data.member));
                        bindMemberCard(grid.lastElementChild);
                        refreshMemberVisibility();
                        notifyDataChanged();
                        closeModal();
                    })
                    .catch(function (err) { showError(errorBox, err.message); });
            });
        });
    }

    ProjectUI.findMember = findMember;
})();
