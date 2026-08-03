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
        refreshMemberStats();
        if (typeof ProjectUI.onDataChanged === 'function') ProjectUI.onDataChanged();
    }

    /* ---------- Tabs ---------- */

    initTabs(document.getElementById('projectTabs'), { defaultTab: 'list' });

    function refreshHeaderStats() {
        document.querySelector('.tab-btn[data-tab="members"]').textContent = 'Members (' + state.members.length + ')';
    }
    ProjectUI.refreshHeaderStats = refreshHeaderStats;

    /** Recomputes each member's Assigned/Completed counts from the current state.tasks
     *  (checking the full multi-assignee list, not just the primary assignee) and patches
     *  the numbers + workload bar directly into their card — the cards themselves are only
     *  rendered once server-side, so nothing else keeps them in sync after a task's
     *  assignees change. */
    function refreshMemberStats() {
        state.members.forEach(function (m) {
            const card = document.querySelector('.member-card[data-user-id="' + m.id + '"]');
            if (!card) return;
            const mine = state.tasks.filter(function (t) {
                return (t.assignees || []).some(function (a) { return a.id === m.id; });
            });
            const assigned = mine.length;
            const completed = mine.filter(function (t) { return t.status === 'done'; }).length;

            const nums = card.querySelectorAll('.member-card-stat-num');
            if (nums[0]) nums[0].textContent = assigned;
            if (nums[1]) nums[1].textContent = completed;

            let track = card.querySelector('.workload-bar-track');
            if (assigned > 0) {
                const pct = Math.round(completed / assigned * 100);
                if (!track) {
                    track = document.createElement('div');
                    track.className = 'workload-bar-track';
                    track.innerHTML = '<span class="workload-bar-fill"></span>';
                    card.appendChild(track);
                }
                track.querySelector('.workload-bar-fill').style.width = pct + '%';
            } else if (track) {
                track.remove();
            }
        });
    }
    ProjectUI.refreshMemberStats = refreshMemberStats;

    /* ---------- Task card + drawer ---------- */

    function labelChipsHTML(labels) {
        if (!labels || !labels.length) return '';
        return '<div class="task-label-row">' + labels.map(l =>
            '<span class="task-label task-label-' + l.color + '">' + escapeHtml(l.name) + '</span>'
        ).join('') + '</div>';
    }

    function avatarStackHTML(people, max) {
        max = max || 4;
        if (!people || !people.length) return '<span class="avatar-stack-empty">Unassigned</span>';
        const shown = people.slice(0, max);
        const overflow = people.length - shown.length;
        const names = people.map(function (p) { return p.name; }).join(', ');
        let html = '<span class="avatar-stack" title="' + escapeHtml(names) + '">';
        shown.forEach(function (p) {
            html += avatarHTML(p, 'avatar-sm');
        });
        if (overflow > 0) html += '<span class="avatar avatar-sm avatar-stack-more">+' + overflow + '</span>';
        html += '</span>';
        return html;
    }
    ProjectUI.avatarStackHTML = avatarStackHTML;

    function dueBadgeHTML(dateStr, status) {
        if (!dateStr) return '<span class="due-badge due-badge-none">No date</span>';
        const t0 = new Date().toISOString().slice(0, 10);
        let cls = 'due-badge-upcoming';
        if (status !== 'done' && dateStr < t0) cls = 'due-badge-late';
        else if (dateStr === t0) cls = 'due-badge-today';
        return '<span class="due-badge ' + cls + '">' + fmtDate(dateStr) + '</span>';
    }
    ProjectUI.dueBadgeHTML = dueBadgeHTML;

    function taskCardHTML(t) {
        let statusOptions = '';
        for (const key in cfg.statusLabels) {
            statusOptions += '<option value="' + key + '"' + (key === t.status ? ' selected' : '') + '>' + cfg.statusLabels[key] + '</option>';
        }
        const assignees = t.assignees || [];
        const canUpdate = cfg.canManage || assignees.some(function (a) { return a.id === cfg.currentUserId; });
        const assigneeHtml = avatarStackHTML(assignees);
        const subtasks = t.subtasks || [];
        const doneCount = subtasks.filter(s => s.is_done).length;
        const commentCount = t.comment_count || 0;
        const attachmentCount = t.attachment_count || 0;

        let badgesHtml = '';
        if (subtasks.length || commentCount || attachmentCount) {
            badgesHtml = '<div class="task-meta-badges">';
            if (subtasks.length) badgesHtml += '<span class="task-meta-badge">' + doneCount + '/' + subtasks.length + ' subtasks</span>';
            if (commentCount) badgesHtml += '<span class="task-meta-badge"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> ' + commentCount + '</span>';
            if (attachmentCount) badgesHtml += '<span class="task-meta-badge"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg> ' + attachmentCount + '</span>';
            badgesHtml += '</div>';
        }

        return '' +
            '<div class="task-card priority-' + t.priority + '" data-task-id="' + t.id + '" data-current-status="' + t.status + '">' +
            (cfg.canManage ? '<button type="button" class="task-kebab" title="More actions">&#8942;</button>' : '') +
            '<span class="task-title">' + escapeHtml(t.title) + '</span>' +
            labelChipsHTML(t.labels) +
            '<span class="tag tag-' + t.priority + ' task-priority-chip">' + cap(t.priority) + '</span>' +
            '<div class="task-meta"><span>' + assigneeHtml + '</span>' +
            dueBadgeHTML(t.due_date, t.status) + '</div>' +
            badgesHtml +
            (canUpdate ? '<select class="task-status-select">' + statusOptions + '</select>' : '') +
            '</div>';
    }
    ProjectUI.taskCardHTML = taskCardHTML;

    // No-op now that the Board (Kanban) view has been removed — Grid re-renders itself via
    // notifyDataChanged() -> refreshAll() -> renderList(), which is what every caller relies on.
    // Kept (rather than touching every call site) since this is still called from several
    // task-mutation flows below (drawer save, subtasks, labels, comments, attachments, delete).
    function refreshTaskVisibility() {
        const board = document.getElementById('kanbanBoard');
        if (!board) return;
        const anyTasks = board.querySelectorAll('.task-card').length > 0;
        board.style.display = anyTasks ? '' : 'none';
        const emptyEl = document.getElementById('tasksEmpty');
        if (emptyEl) emptyEl.style.display = anyTasks ? 'none' : '';
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

    function subtaskRowHTML(s, canEdit) {
        return '<div class="subtask-row" data-subtask-id="' + s.id + '">' +
            '<label class="subtask-check"><input type="checkbox" class="subtask-toggle"' + (s.is_done ? ' checked' : '') + (canEdit ? '' : ' disabled') + '>' +
            '<span' + (s.is_done ? ' class="subtask-done"' : '') + '>' + escapeHtml(s.title) + '</span></label>' +
            (canEdit ? '<button type="button" class="subtask-delete" title="Remove">&times;</button>' : '') +
            '</div>';
    }

    function refreshSubtaskProgress(overlay, subtasks) {
        const done = subtasks.filter(s => s.is_done).length;
        const el = overlay.querySelector('#tdSubtaskProgress');
        if (el) el.textContent = done + ' / ' + subtasks.length + ' Completed';
    }

    function openTaskDrawer(task) {
        const canEdit = cfg.canManage;
        const canUpdateStatus = cfg.canManage || (task.assignees || []).some(function (a) { return a.id === cfg.currentUserId; });
        let statusOptions = '';
        for (const key in cfg.statusLabels) {
            statusOptions += '<option value="' + key + '"' + (key === task.status ? ' selected' : '') + '>' + cfg.statusLabels[key] + '</option>';
        }
        const subtasks = (task.subtasks || []).slice();

        const overlay = openDrawer(escapeHtml(task.title), '' +
            '<div id="taskDrawerError" class="error-msg" style="display:none;"></div>' +

            '<div class="drawer-section">' +
            '<form id="taskDrawerForm">' +
            '<div class="field"><label>Task Title</label><input type="text" id="tdTitle" value="' + escapeHtml(task.title) + '"' + (canEdit ? '' : ' disabled') + ' required></div>' +
            '<div class="field"><label>Description</label><textarea id="tdDescription" rows="3"' + (canEdit ? '' : ' disabled') + '>' + escapeHtml(task.description || '') + '</textarea></div>' +
            '<div class="field-grid-2">' +
            (canEdit ? '<div class="field"><label>Assignees</label><div id="tdAssigneePicker"></div></div>' : '<div></div>') +
            '<div class="field"><label>Priority</label><span class="pill-select tag-' + task.priority + '" id="tdPriorityPill"><select id="tdPriority"' + (canEdit ? '' : ' disabled') + '>' +
            '<option value="low"' + (task.priority === 'low' ? ' selected' : '') + '>Low</option>' +
            '<option value="medium"' + (task.priority === 'medium' ? ' selected' : '') + '>Medium</option>' +
            '<option value="high"' + (task.priority === 'high' ? ' selected' : '') + '>High</option>' +
            '</select></span></div>' +
            '<div class="field"><label>Status</label><span class="pill-select tag-' + task.status + '" id="tdStatusPill"><select id="tdStatus"' + (canUpdateStatus ? '' : ' disabled') + '>' + statusOptions + '</select></span></div>' +
            '<div class="field"><label>Start Date</label><input type="date" id="tdStartDate" value="' + (task.start_date || '') + '"' + (canEdit ? '' : ' disabled') + '></div>' +
            '<div class="field"><label>Due Date</label><input type="date" id="tdDueDate" value="' + (task.due_date || '') + '"' + (canEdit ? '' : ' disabled') + '></div>' +
            '</div>' +
            (canEdit ? '<button type="submit" class="btn">Save Changes</button> <button type="button" id="tdDelete" class="btn btn-danger">Delete Task</button>' : '') +
            '</form>' +
            '</div>' +

            '<div class="drawer-section">' +
            '<div class="drawer-section-head"><h3 class="drawer-section-title">Subtasks</h3><span id="tdSubtaskProgress" class="drawer-section-meta"></span></div>' +
            '<div id="tdSubtaskList">' + subtasks.map(s => subtaskRowHTML(s, canEdit)).join('') + '</div>' +
            (canEdit ? '<input type="text" id="tdAddSubtask" class="quick-add-input" placeholder="+ Add subtask…">' : '') +
            '</div>' +

            '<div class="drawer-section">' +
            '<h3 class="drawer-section-title">Dependencies</h3>' +
            '<div id="tdDependencies">Loading…</div>' +
            (canEdit ? '' +
                '<div class="dependency-add-row">' +
                '<select id="tdDepType">' +
                '<option value="blocked_by">Blocked By</option>' +
                '<option value="depends_on">Depends On</option>' +
                '<option value="related">Related Task</option>' +
                '</select>' +
                '<select id="tdDepTask"><option value="">Select task…</option>' +
                state.tasks.filter(t => t.id !== task.id).map(t => '<option value="' + t.id + '">' + escapeHtml(t.title) + '</option>').join('') +
                '</select>' +
                '<button type="button" id="tdDepAdd" class="btn btn-sm">Add</button>' +
                '</div>'
                : '') +
            '</div>' +

            '<div class="drawer-section">' +
            '<h3 class="drawer-section-title">Comments</h3>' +
            '<div id="tdComments">Loading…</div>' +
            '<div class="comment-composer">' +
            '<textarea id="tdCommentText" rows="2" placeholder="Add a comment… type @ to mention someone"></textarea>' +
            '<div id="tdMentionDropdown" class="mention-dropdown" style="display:none;"></div>' +
            '<button type="button" id="tdCommentPost" class="btn btn-sm">Post</button>' +
            '</div>' +
            '</div>' +

            '<div class="drawer-section">' +
            '<h3 class="drawer-section-title">Attachments</h3>' +
            '<div id="tdAttachments">Loading…</div>' +
            '<input type="file" id="tdFileInput">' +
            '</div>');

        refreshSubtaskProgress(overlay, subtasks);
        loadDependencies(overlay, task);
        loadComments(overlay, task);
        loadAttachments(overlay, task);

        if (canEdit) {
            const pickerRoot = overlay.querySelector('#tdAssigneePicker');
            pickerRoot.innerHTML = empPickerMultiHTML('assignees', 'Search name or employee ID…');
            let currentAssigneeIds = (task.assignees || []).map(function (a) { return a.id; });
            initEmpPickerMulti(pickerRoot, {
                projectId: cfg.projectId,
                mode: 'members',
                selected: task.assignees || [],
                onChange: function (ids) { currentAssigneeIds = ids; },
            });

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
                    assignees: currentAssigneeIds,
                    priority: overlay.querySelector('#tdPriority').value,
                    start_date: overlay.querySelector('#tdStartDate').value,
                    due_date: overlay.querySelector('#tdDueDate').value,
                }).then(function (data) {
                    data.task.labels = task.labels;
                    data.task.subtasks = task.subtasks;
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

            const addSubtaskInput = overlay.querySelector('#tdAddSubtask');
            if (addSubtaskInput) {
                addSubtaskInput.addEventListener('keydown', function (ev) {
                    if (ev.key !== 'Enter') return;
                    const title = addSubtaskInput.value.trim();
                    if (!title) return;
                    apiPost('api/projects/tasks.php', { action: 'add_subtask', project_id: cfg.projectId, task_id: task.id, title: title })
                        .then(function (data) {
                            subtasks.push(data.subtask);
                            task.subtasks = subtasks;
                            const idx = state.tasks.findIndex(t => t.id === task.id);
                            if (idx !== -1) state.tasks[idx].subtasks = subtasks;
                            overlay.querySelector('#tdSubtaskList').insertAdjacentHTML('beforeend', subtaskRowHTML(data.subtask, true));
                            refreshSubtaskProgress(overlay, subtasks);
                            replaceCardInBoard(task);
                            addSubtaskInput.value = '';
                        })
                        .catch(function (err) { showError(overlay.querySelector('#taskDrawerError'), err.message); });
                });
            }

            overlay.querySelector('#tdSubtaskList').addEventListener('change', function (ev) {
                if (!ev.target.classList.contains('subtask-toggle')) return;
                const row = ev.target.closest('.subtask-row');
                const subtaskId = parseInt(row.dataset.subtaskId, 10);
                const isDone = ev.target.checked;
                apiPost('api/projects/tasks.php', { action: 'toggle_subtask', project_id: cfg.projectId, task_id: task.id, subtask_id: subtaskId, is_done: isDone })
                    .then(function () {
                        const s = subtasks.find(x => x.id === subtaskId);
                        if (s) s.is_done = isDone;
                        row.querySelector('span').classList.toggle('subtask-done', isDone);
                        refreshSubtaskProgress(overlay, subtasks);
                        replaceCardInBoard(task);
                    })
                    .catch(function (err) { showError(overlay.querySelector('#taskDrawerError'), err.message); ev.target.checked = !isDone; });
            });

            overlay.querySelector('#tdSubtaskList').addEventListener('click', function (ev) {
                if (!ev.target.classList.contains('subtask-delete')) return;
                const row = ev.target.closest('.subtask-row');
                const subtaskId = parseInt(row.dataset.subtaskId, 10);
                apiPost('api/projects/tasks.php', { action: 'delete_subtask', project_id: cfg.projectId, task_id: task.id, subtask_id: subtaskId })
                    .then(function () {
                        const idx = subtasks.findIndex(x => x.id === subtaskId);
                        if (idx !== -1) subtasks.splice(idx, 1);
                        row.remove();
                        refreshSubtaskProgress(overlay, subtasks);
                        replaceCardInBoard(task);
                    })
                    .catch(function (err) { showError(overlay.querySelector('#taskDrawerError'), err.message); });
            });
        }

        if (canUpdateStatus) {
            overlay.querySelector('#tdStatus').addEventListener('change', function () {
                const select = this;
                apiPost('api/projects/tasks.php', { action: 'update_status', project_id: cfg.projectId, task_id: task.id, status: select.value })
                    .then(function (data) {
                        data.task.labels = task.labels;
                        data.task.subtasks = task.subtasks;
                        patchTaskInState(data.task);
                        replaceCardInBoard(data.task);
                        notifyDataChanged();
                        overlay.querySelector('#tdStatusPill').className = 'pill-select tag-' + data.task.status;
                    })
                    .catch(function (err) { showError(overlay.querySelector('#taskDrawerError'), err.message); select.value = task.status; });
            });
        }

        const priorityPill = overlay.querySelector('#tdPriority');
        if (priorityPill) {
            priorityPill.addEventListener('change', function () {
                overlay.querySelector('#tdPriorityPill').className = 'pill-select tag-' + priorityPill.value;
            });
        }
    }
    ProjectUI.openTaskDrawer = openTaskDrawer;

    /* ---------- Dependencies ---------- */

    function dependencyRowHTML(type, dep, canEdit) {
        return '<div class="dependency-row" data-dep-type="' + type + '" data-dep-id="' + dep.id + '">' +
            '<span class="tag tag-' + dep.status + '">' + escapeHtml(dep.title) + '</span>' +
            (canEdit ? '<button type="button" class="dependency-remove" title="Remove">&times;</button>' : '') +
            '</div>';
    }

    function renderDependencies(overlay, task, deps) {
        const box = overlay.querySelector('#tdDependencies');
        if (!box) return;
        const canEdit = cfg.canManage;
        const labels = { blocked_by: 'Blocked By', depends_on: 'Depends On', related: 'Related' };
        let html = '';
        ['blocked_by', 'depends_on', 'related'].forEach(function (type) {
            const items = deps[type] || [];
            if (!items.length) return;
            html += '<div class="dependency-group"><span class="dependency-group-label">' + labels[type] + '</span>' +
                items.map(d => dependencyRowHTML(type, d, canEdit)).join('') + '</div>';
        });
        box.innerHTML = html || '<div class="drawer-empty">No dependencies linked.</div>';

        box.querySelectorAll('.dependency-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const row = btn.closest('.dependency-row');
                apiPost('api/projects/dependencies.php', {
                    action: 'remove', project_id: cfg.projectId, task_id: task.id,
                    related_task_id: row.dataset.depId, type: row.dataset.depType,
                }).then(function (data) { renderDependencies(overlay, task, data.dependencies); })
                    .catch(function (err) { showError(overlay.querySelector('#taskDrawerError'), err.message); });
            });
        });
    }

    function loadDependencies(overlay, task) {
        apiPost('api/projects/dependencies.php', { action: 'list_for_task', project_id: cfg.projectId, task_id: task.id })
            .then(function (data) { renderDependencies(overlay, task, data.dependencies); })
            .catch(function () { overlay.querySelector('#tdDependencies').textContent = 'Could not load dependencies.'; });

        const addBtn = overlay.querySelector('#tdDepAdd');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                const type = overlay.querySelector('#tdDepType').value;
                const relatedId = overlay.querySelector('#tdDepTask').value;
                if (!relatedId) return;
                apiPost('api/projects/dependencies.php', {
                    action: 'add', project_id: cfg.projectId, task_id: task.id,
                    related_task_id: relatedId, type: type,
                }).then(function (data) {
                    renderDependencies(overlay, task, data.dependencies);
                    overlay.querySelector('#tdDepTask').value = '';
                }).catch(function (err) { showError(overlay.querySelector('#taskDrawerError'), err.message); });
            });
        }
    }

    /* ---------- Comments + @mentions ---------- */

    function commentRowHTML(c) {
        return '<div class="comment-row">' +
            avatarHTML({ id: c.author_id, name: c.author_name, role: c.author_role, has_photo: c.has_photo }, 'avatar-sm') +
            '<div class="comment-body">' +
            '<div class="comment-meta"><strong>' + escapeHtml(c.author_name) + '</strong> <span class="comment-date">' + fmtDate(c.created_at.slice(0, 10)) + '</span></div>' +
            '<div class="comment-text">' + escapeHtml(c.comment) + '</div>' +
            '</div></div>';
    }

    function refreshCommentsList(overlay, task) {
        const listBox = overlay.querySelector('#tdComments');
        if (!listBox) return;
        apiPost('api/projects/comments.php', { action: 'list_for_task', project_id: cfg.projectId, task_id: task.id })
            .then(function (data) {
                listBox.innerHTML = data.comments.length ? data.comments.map(commentRowHTML).join('') : '<div class="drawer-empty">No comments yet.</div>';
            })
            .catch(function () { listBox.textContent = 'Could not load comments.'; });
    }

    function loadComments(overlay, task) {
        refreshCommentsList(overlay, task);

        const textarea = overlay.querySelector('#tdCommentText');
        const mentionBox = overlay.querySelector('#tdMentionDropdown');
        let mentionIds = [];

        textarea.addEventListener('input', function () {
            const value = textarea.value;
            const caret = textarea.selectionStart;
            const match = value.slice(0, caret).match(/@([a-zA-Z]*)$/);
            if (!match) { mentionBox.style.display = 'none'; return; }
            const query = match[1].toLowerCase();
            const matches = state.members.filter(m => m.name.toLowerCase().indexOf(query) !== -1);
            if (!matches.length) { mentionBox.style.display = 'none'; return; }
            mentionBox.innerHTML = matches.slice(0, 6).map(m =>
                '<div class="mention-item" data-user-id="' + m.id + '" data-user-name="' + escapeHtml(m.name) + '">' + escapeHtml(m.name) + '</div>'
            ).join('');
            mentionBox.style.display = 'block';
            mentionBox.dataset.matchStart = caret - match[0].length;
            mentionBox.dataset.matchEnd = caret;
        });

        mentionBox.addEventListener('mousedown', function (ev) {
            const item = ev.target.closest('.mention-item');
            if (!item) return;
            ev.preventDefault();
            const start = parseInt(mentionBox.dataset.matchStart, 10);
            const end = parseInt(mentionBox.dataset.matchEnd, 10);
            const name = item.dataset.userName;
            const userId = parseInt(item.dataset.userId, 10);
            textarea.value = textarea.value.slice(0, start) + '@' + name + ' ' + textarea.value.slice(end);
            if (mentionIds.indexOf(userId) === -1) mentionIds.push(userId);
            mentionBox.style.display = 'none';
            textarea.focus();
        });

        overlay.querySelector('#tdCommentPost').addEventListener('click', function () {
            const text = textarea.value.trim();
            if (!text) return;
            const errorBox = overlay.querySelector('#taskDrawerError');
            apiPost('api/projects/comments.php', {
                action: 'create', project_id: cfg.projectId, task_id: task.id,
                comment: text, mention_ids: mentionIds,
            }).then(function () {
                textarea.value = '';
                mentionIds = [];
                refreshCommentsList(overlay, task);
                task.comment_count = (task.comment_count || 0) + 1;
                replaceCardInBoard(task);
            }).catch(function (err) { showError(errorBox, err.message); });
        });
    }

    /* ---------- Attachments ---------- */

    function fileSizeLabel(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
        return (bytes / 1024 / 1024).toFixed(1) + ' MB';
    }

    function attachmentRowHTML(a) {
        const canRemove = cfg.canManage || a.uploader_id === cfg.currentUserId;
        const downloadUrl = 'api/projects/attachments.php?action=download&id=' + a.id + '&project_id=' + cfg.projectId;
        return '<div class="attachment-row" data-attachment-id="' + a.id + '">' +
            '<svg class="attachment-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
            '<div class="attachment-info">' +
            '<a href="' + downloadUrl + '" class="attachment-name">' + escapeHtml(a.original_filename) + '</a>' +
            '<div class="attachment-meta">' + escapeHtml(a.uploader_name) + ' &middot; ' + fmtDate(a.created_at.slice(0, 10)) + ' &middot; ' + fileSizeLabel(a.size_bytes) + '</div>' +
            '</div>' +
            (canRemove ? '<button type="button" class="attachment-remove" title="Remove">&times;</button>' : '') +
            '</div>';
    }

    function refreshAttachmentsList(overlay, task) {
        const box = overlay.querySelector('#tdAttachments');
        if (!box) return;
        apiPost('api/projects/attachments.php', { action: 'list_for_task', project_id: cfg.projectId, task_id: task.id })
            .then(function (data) {
                box.innerHTML = data.attachments.length ? data.attachments.map(attachmentRowHTML).join('') : '<div class="drawer-empty">No attachments yet.</div>';
                box.querySelectorAll('.attachment-remove').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const row = btn.closest('.attachment-row');
                        confirmModal('Remove this attachment?', function () {
                            apiPost('api/projects/attachments.php', { action: 'delete', project_id: cfg.projectId, id: row.dataset.attachmentId })
                                .then(function () {
                                    refreshAttachmentsList(overlay, task);
                                    task.attachment_count = Math.max(0, (task.attachment_count || 0) - 1);
                                    replaceCardInBoard(task);
                                })
                                .catch(function (err) { showError(overlay.querySelector('#taskDrawerError'), err.message); });
                        }, { okLabel: 'Remove' });
                    });
                });
            })
            .catch(function () { box.textContent = 'Could not load attachments.'; });
    }

    function loadAttachments(overlay, task) {
        refreshAttachmentsList(overlay, task);
        const fileInput = overlay.querySelector('#tdFileInput');
        fileInput.addEventListener('change', function () {
            const file = fileInput.files[0];
            if (!file) return;
            apiUpload('api/projects/attachments.php', { action: 'upload', project_id: cfg.projectId, task_id: task.id }, file)
                .then(function () {
                    refreshAttachmentsList(overlay, task);
                    task.attachment_count = (task.attachment_count || 0) + 1;
                    replaceCardInBoard(task);
                    fileInput.value = '';
                })
                .catch(function (err) { showError(overlay.querySelector('#taskDrawerError'), err.message); fileInput.value = ''; });
        });
    }

    // No-op now that the Board (Kanban) view has been removed (see refreshTaskVisibility above
    // for why this is kept rather than edited out of every caller).
    function replaceCardInBoard(task) {
        if (!document.getElementById('kanbanBoard')) return;
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

    /* ---------- Quick task creation (column input + Enter) ---------- */
    /* Bound by the board renderer (project-detail-views.js) after every (re)render, in both
       the default status-column layout and any grouping mode that still shows quick-add inputs. */

    function bindQuickAddInput(input) {
        input.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Enter') return;
            const title = input.value.trim();
            if (!title) return;
            const status = input.dataset.status;
            input.disabled = true;

            apiPost('api/projects/tasks.php', {
                action: 'create',
                project_id: cfg.projectId,
                title: title,
                status: status,
            }).then(function (data) {
                state.tasks.push(data.task);
                refreshTaskVisibility();
                notifyDataChanged();
            }).catch(function (err) { showError(pageErrorBox, err.message); input.disabled = false; input.focus(); });
        });
    }
    ProjectUI.bindQuickAddInput = bindQuickAddInput;

    /* ---------- Header: More Actions (Change Status) ---------- */

    const moreActionsBtn = document.getElementById('projectMoreActions');
    if (moreActionsBtn) {
        const menuItems = [
            {
                label: 'Change Status', onClick: function () {
                    const overlay = openModal('Change Project Status', '' +
                        '<div id="statusModalError" class="error-msg" style="display:none;"></div>' +
                        '<form id="statusModalForm">' +
                        '<div class="field"><label>Status</label><select id="newProjectStatus">' +
                        '<option value="active"' + (cfg.projectStatus === 'active' ? ' selected' : '') + '>Active</option>' +
                        '<option value="on_hold"' + (cfg.projectStatus === 'on_hold' ? ' selected' : '') + '>On Hold</option>' +
                        '<option value="completed"' + (cfg.projectStatus === 'completed' ? ' selected' : '') + '>Completed</option>' +
                        '</select></div>' +
                        '<button type="submit" class="btn">Save</button>' +
                        '</form>');
                    overlay.querySelector('#statusModalForm').addEventListener('submit', function (ev) {
                        ev.preventDefault();
                        const status = overlay.querySelector('#newProjectStatus').value;
                        apiPost('api/projects/status.php', { action: 'update', project_id: cfg.projectId, status: status })
                            .then(function () {
                                cfg.projectStatus = status;
                                const tag = document.getElementById('phStatusTag');
                                tag.className = 'dir-badge dir-badge-' + status;
                                tag.textContent = status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ');
                                closeModal();
                            })
                            .catch(function (err) { showError(overlay.querySelector('#statusModalError'), err.message); });
                    });
                },
            },
        ];
        if (cfg.isAdmin) {
            menuItems.push({
                label: 'Delete Project', danger: true, onClick: function () {
                    confirmModal(
                        'Delete "' + cfg.projectName + '"? This permanently deletes all of its tasks, documents, and team assignments. This cannot be undone.',
                        function () {
                            apiPost('api/projects/status.php', { action: 'delete', project_id: cfg.projectId })
                                .then(function () { window.location.href = 'projects.php'; })
                                .catch(function (err) { showError(pageErrorBox, err.message); });
                        },
                        { okLabel: 'Delete' }
                    );
                },
            });
        }
        initOverflowMenu(moreActionsBtn, menuItems);
    }

    const openAddTaskBtn = document.getElementById('openAddTaskModal');
    if (openAddTaskBtn) {
        openAddTaskBtn.addEventListener('click', function () {
            const overlay = openModal('Add Task', '' +
                '<div id="addTaskModalError" class="error-msg" style="display:none;"></div>' +
                '<form id="addTaskForm">' +
                '<div class="field"><label>Task Title</label><input type="text" id="taskTitle" required></div>' +
                '<div class="field"><label>Assignees</label><div id="assigneePicker"></div></div>' +
                '<div class="field"><label>Priority</label><select id="taskPriority">' +
                '<option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option>' +
                '</select></div>' +
                '<div class="field"><label>Start Date</label><input type="date" id="taskStartDate"></div>' +
                '<div class="field"><label>Due Date</label><input type="date" id="taskDueDate"></div>' +
                '<div class="field"><label>Description</label><textarea id="taskDescription" rows="2"></textarea></div>' +
                '<button type="submit" class="btn">Add Task</button>' +
                '</form>');

            const assigneePickerRoot = overlay.querySelector('#assigneePicker');
            assigneePickerRoot.innerHTML = empPickerMultiHTML('assignees', 'Search name or employee ID…');
            let newTaskAssigneeIds = [];
            initEmpPickerMulti(assigneePickerRoot, {
                projectId: cfg.projectId,
                mode: 'members',
                onChange: function (ids) { newTaskAssigneeIds = ids; },
            });

            overlay.querySelector('#addTaskForm').addEventListener('submit', function (ev) {
                ev.preventDefault();
                const errorBox = overlay.querySelector('#addTaskModalError');
                const title = overlay.querySelector('#taskTitle').value.trim();
                if (!title) { showError(errorBox, 'Task title is required.'); return; }

                apiPost('api/projects/tasks.php', {
                    action: 'create',
                    project_id: cfg.projectId,
                    title: title,
                    assignees: newTaskAssigneeIds,
                    priority: overlay.querySelector('#taskPriority').value,
                    start_date: overlay.querySelector('#taskStartDate').value,
                    due_date: overlay.querySelector('#taskDueDate').value,
                    description: overlay.querySelector('#taskDescription').value.trim(),
                }).then(function (data) {
                    state.tasks.push(data.task);
                    notifyDataChanged();
                    closeModal();
                }).catch(function (err) { showError(errorBox, err.message); });
            });
        });
    }

    /* ---------- Member card + drawer ---------- */

    function memberCardHTML(m) {
        const mine = state.tasks.filter(t => t.assigned_to === m.id);
        const assigned = mine.length;
        const completed = mine.filter(t => t.status === 'done').length;
        const pct = assigned > 0 ? Math.round(completed / assigned * 100) : 0;

        return '' +
            '<div class="member-card" data-user-id="' + m.id + '" data-user-name="' + escapeHtml(m.name) + '">' +
            (cfg.canManage ? '<button type="button" class="task-kebab" title="More actions">&#8942;</button>' : '') +
            '<div class="member-card-head">' + avatarHTML(m) +
            '<div class="row-name"><strong>' + escapeHtml(m.name) + '</strong> ' +
            '<span class="tag tag-' + m.system_role + '">' + escapeHtml(cap(m.system_role)) + '</span></div></div>' +
            '<div class="member-card-sub">' + escapeHtml(m.email) + '</div>' +
            '<div class="member-card-sub">' + escapeHtml(m.department || '—') + '</div>' +
            '<span class="member-card-role">' + escapeHtml(m.role_in_project) + '</span>' +
            (m.permission_level && m.permission_level !== 'member' ? ' <span class="member-card-permission">' + cap(m.permission_level) + '</span>' : '') +
            '<div class="member-card-stats">' +
            '<div class="member-card-stat"><span class="member-card-stat-num">' + assigned + '</span><span class="member-card-stat-lbl">Assigned</span></div>' +
            '<div class="member-card-stat"><span class="member-card-stat-num">' + completed + '</span><span class="member-card-stat-lbl">Completed</span></div>' +
            '</div>' +
            (assigned > 0 ? '<div class="workload-bar-track"><span class="workload-bar-fill" style="width:' + pct + '%"></span></div>' : '') +
            '</div>';
    }
    ProjectUI.memberCardHTML = memberCardHTML;

    function bindMemberCard(card) {
        const kebab = card.querySelector('.task-kebab');
        // The project manager's card is synthesized from the project record, not a real
        // project_members row — there's nothing to "remove" them from here.
        const isManagerCard = parseInt(card.dataset.userId, 10) === cfg.managerId;
        if (kebab) {
            const menuItems = [
                { label: 'Comment', onClick: function () { openMemberDrawer(findMember(card.dataset.userId), true); } },
            ];
            if (!isManagerCard) {
                menuItems.push({
                    label: 'Remove', danger: true, onClick: function () {
                        confirmModal('Remove this member from the project?', function () { removeMember(card); }, { okLabel: 'Remove' });
                    },
                });
            }
            initOverflowMenu(kebab, menuItems);
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
            '<div class="row-name" style="margin-bottom:10px;">' + avatarHTML(member) +
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

    /* ---------- Project Documents ---------- */

    function bindDocumentRemove(row) {
        const btn = row.querySelector('.document-remove');
        if (!btn) return;
        btn.addEventListener('click', function () {
            confirmModal('Remove this document?', function () {
                apiPost('api/projects/documents.php', { action: 'delete', project_id: cfg.projectId, id: row.dataset.documentId })
                    .then(function () {
                        row.remove();
                        const list = document.getElementById('documentsList');
                        if (!list.children.length) {
                            list.style.display = 'none';
                            document.getElementById('documentsEmpty').style.display = '';
                        }
                    })
                    .catch(function (err) { showError(pageErrorBox, err.message); });
            }, { okLabel: 'Remove' });
        });
    }
    document.querySelectorAll('#documentsList .attachment-row').forEach(bindDocumentRemove);

    const documentFileInput = document.getElementById('documentFileInput');
    if (documentFileInput) {
        documentFileInput.addEventListener('change', function () {
            const file = documentFileInput.files[0];
            if (!file) return;
            const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'zip'];
            const ext = file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(ext)) {
                showError(pageErrorBox, 'Only PDF, Word, Excel, PowerPoint, TXT, CSV, PNG, JPG, or ZIP files are allowed.');
                documentFileInput.value = '';
                return;
            }
            if (file.size > 50 * 1024 * 1024) {
                showError(pageErrorBox, '"' + file.name + '" is too large (50MB max).');
                documentFileInput.value = '';
                return;
            }
            apiUpload('api/projects/documents.php', { action: 'upload', project_id: cfg.projectId }, file)
                .then(function () {
                    documentFileInput.value = '';
                    window.location.reload();
                })
                .catch(function (err) { showError(pageErrorBox, err.message); documentFileInput.value = ''; });
        });
    }
})();
