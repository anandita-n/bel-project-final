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
        defects: window.PAGE_STATE.defects.slice(),
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

    initTabs(document.getElementById('projectTabs'), { defaultTab: 'overview' });

    function refreshHeaderStats() {
        // Members no longer has its own tab (folded into Overview's "Project roles"), so there's
        // no tab label count to keep in sync here anymore.
    }
    ProjectUI.refreshHeaderStats = refreshHeaderStats;

    /** Recomputes each member's Assigned/Completed counts from the current state.tasks
     *  (checking the full multi-assignee list, not just the primary assignee) and patches
     *  the numbers directly into their card — the cards themselves are only rendered once
     *  server-side, so nothing else keeps them in sync after a task's assignees change. */
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

    /* Grid/List "Assignee" column variant — avatar plus the person's name, one per line,
       instead of an overlapping icon-only stack (that's fine on cards, not in a data table). */
    function avatarStackNamesHTML(people) {
        if (!people || !people.length) return '<span class="avatar-stack-empty">Unassigned</span>';
        return '<div class="assignee-name-list">' + people.map(function (p) {
            return '<span class="assignee-name-row">' + avatarHTML(p, 'avatar-sm') +
                '<span class="assignee-name-text">' + escapeHtml(p.name) + '</span></span>';
        }).join('') + '</div>';
    }
    ProjectUI.avatarStackNamesHTML = avatarStackNamesHTML;

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
        // The project manager/admin controls everything about a task. A plain member who's
        // assigned to it can only view it, comment/attach, and update its status — not edit its
        // info, reassign it, or delete it.
        const canEdit = cfg.canManage;
        const canUpdateStatus = cfg.canManage || (task.assignees || []).some(function (a) { return a.id === cfg.currentUserId; });
        let statusOptions = '';
        for (const key in cfg.statusLabels) {
            statusOptions += '<option value="' + key + '"' + (key === task.status ? ' selected' : '') + '>' + cfg.statusLabels[key] + '</option>';
        }
        const subtasks = (task.subtasks || []).slice();

        // Removing a member never touches their existing task assignments (see removeMember) —
        // for a still-open task that can leave an assignee who's no longer on the team. Surface
        // that here (rather than silently reassigning) so a manager notices and, if they want to,
        // reassigns via the Assignees picker below, which is already scoped to current members.
        const staleAssignees = canEdit && task.status !== 'done'
            ? (task.assignees || []).filter(function (a) { return !state.members.some(function (m) { return m.id === a.id; }); })
            : [];
        const staleNoticeHtml = staleAssignees.length
            ? '<div class="task-drawer-notice">' +
              staleAssignees.map(function (a) { return escapeHtml(a.name); }).join(', ') +
              (staleAssignees.length === 1 ? ' is no longer an active project member.' : ' are no longer active project members.') +
              ' Their task history is preserved — use Assignees below to reassign.</div>'
            : '';

        const overlay = openDrawer(escapeHtml(task.title), '' +
            '<div id="taskDrawerError" class="error-msg" style="display:none;"></div>' +
            staleNoticeHtml +

            '<div class="drawer-section">' +
            '<form id="taskDrawerForm">' +
            '<div class="field"><label>Task Title</label><input type="text" id="tdTitle" value="' + escapeHtml(task.title) + '"' + (canEdit ? '' : ' disabled') + ' required></div>' +
            '<div class="field"><label>Description</label><textarea id="tdDescription" rows="3"' + (canEdit ? '' : ' disabled') + '>' + escapeHtml(task.description || '') + '</textarea></div>' +
            '<div class="field-grid-2">' +
            (canEdit ? '<div class="field"><label>Assignees</label><div id="tdAssigneePicker"></div></div>' : '') +
            '<div class="field"><label>Priority</label><span class="pill-select tag-' + task.priority + '" id="tdPriorityPill"><select id="tdPriority"' + (canEdit ? '' : ' disabled') + '>' +
            '<option value="low"' + (task.priority === 'low' ? ' selected' : '') + '>Low</option>' +
            '<option value="medium"' + (task.priority === 'medium' ? ' selected' : '') + '>Medium</option>' +
            '<option value="high"' + (task.priority === 'high' ? ' selected' : '') + '>High</option>' +
            '</select></span></div>' +
            '<div class="field"><label>Status</label><span class="pill-select tag-' + task.status + '" id="tdStatusPill"><select id="tdStatus"' + (canUpdateStatus ? '' : ' disabled') + '>' + statusOptions + '</select></span></div>' +
            '<div class="field"><label>Start Date</label><input type="date" id="tdStartDate" value="' + (task.start_date || '') + '"' + (canEdit ? '' : ' disabled') + '></div>' +
            '<div class="field"><label>Due Date</label><input type="date" id="tdDueDate" value="' + (task.due_date || '') + '"' + (canEdit ? '' : ' disabled') + '></div>' +
            '</div>' +
            '</form>' +
            '</div>' +

            '<div class="drawer-section">' +
            '<div class="drawer-section-head"><h3 class="drawer-section-title">Subtasks</h3><span id="tdSubtaskProgress" class="drawer-section-meta"></span></div>' +
            '<div id="tdSubtaskList">' + subtasks.map(s => subtaskRowHTML(s, canEdit)).join('') + '</div>' +
            (canEdit ? '<input type="text" id="tdAddSubtask" class="quick-add-input" placeholder="+ Add subtask…">' : '') +
            '</div>' +

            '<div class="drawer-section">' +
            '<h3 class="drawer-section-title">Comments</h3>' +
            '<div id="tdComments">Loading…</div>' +
            '<div class="comment-composer">' +
            '<textarea id="tdCommentText" rows="2" placeholder="Add a comment… type @ to mention someone"></textarea>' +
            '<div id="tdMentionDropdown" class="mention-dropdown" style="display:none;"></div>' +
            '<button type="button" id="tdCommentPost" class="pill-btn">Post</button>' +
            '</div>' +
            '</div>' +

            '<div class="drawer-section">' +
            '<h3 class="drawer-section-title">Attachments</h3>' +
            '<div id="tdAttachments">Loading…</div>' +
            '<input type="file" id="tdFileInput">' +
            '</div>' +

            (canEdit ? '' +
                '<div class="drawer-section drawer-actions-bottom">' +
                '<button type="submit" form="taskDrawerForm" class="pill-btn">Save Changes</button> ' +
                '<button type="button" id="tdDelete" class="pill-btn pill-btn-danger">Delete Task</button>' +
                '</div>'
                : ''));

        refreshSubtaskProgress(overlay, subtasks);
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

    /* ---------- Comments + @mentions ---------- */

    function commentRowHTML(c) {
        return '<div class="comment-row">' +
            avatarHTML({ id: c.author_id, name: c.author_name, role: c.author_role, has_photo: c.has_photo }, 'avatar-sm') +
            '<div class="comment-body">' +
            '<div class="comment-meta"><strong>' + escapeHtml(c.author_name) + '</strong> <span class="comment-date">' + fmtDate(c.created_at) + '</span></div>' +
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
            '<div class="attachment-meta">' + escapeHtml(a.uploader_name) + ' &middot; ' + fmtDate(a.created_at) + ' &middot; ' + fileSizeLabel(a.size_bytes) + '</div>' +
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
        const editItems = [
            {
                label: 'Edit Details', onClick: function () {
                    const canReassign = cfg.isAdmin;
                    const overlay = openModal('Edit Project Details', '' +
                        '<div id="editDetailsError" class="error-msg" style="display:none;"></div>' +
                        '<form id="editDetailsForm">' +
                        '<div class="field"><label>Project Name</label><input type="text" id="editProjectName" required></div>' +
                        '<div class="field">' +
                        '<label>Description</label>' +
                        '<textarea id="editProjectDescription" rows="4"></textarea>' +
                        '<div id="editDetailsDocList">' + cfg.documentsHtml + '</div>' +
                        (cfg.canManage ?
                            '<input type="file" id="editDetailsDocInput" style="display:none;" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.png,.jpg,.jpeg,.zip">' +
                            '<button type="button" class="link-btn" id="editDetailsAddDocBtn">+ Add document</button>'
                            : '') +
                        '</div>' +
                        '<div class="field"><label>Department</label><input type="text" id="editProjectDepartment"></div>' +
                        '<div class="field"><label>Due Date</label><input type="date" id="editProjectDueDate"></div>' +
                        '<div class="field"><label>Status</label><select id="editProjectStatus">' +
                        '<option value="active"' + (cfg.projectStatus === 'active' ? ' selected' : '') + '>Active</option>' +
                        '<option value="on_hold"' + (cfg.projectStatus === 'on_hold' ? ' selected' : '') + '>On Hold</option>' +
                        '<option value="completed"' + (cfg.projectStatus === 'completed' ? ' selected' : '') + '>Completed</option>' +
                        '</select></div>' +
                        (canReassign ? '<div class="field"><label>Project Manager</label><div id="editProjectManagerPicker"></div></div>' : '') +
                        '<div class="modal-actions">' +
                        '<button type="button" class="pill-btn pill-btn-secondary" id="editDetailsCancel">Cancel</button>' +
                        '<button type="submit" class="pill-btn">Save</button>' +
                        '</div>' +
                        '</form>');
                    overlay.querySelector('#editDetailsCancel').addEventListener('click', closeModal);
                    overlay.querySelector('#editProjectName').value = cfg.projectName;
                    overlay.querySelector('#editProjectDescription').value = cfg.projectDescription || '';
                    overlay.querySelector('#editProjectDepartment').value = cfg.projectDepartment || '';
                    overlay.querySelector('#editProjectDueDate').value = cfg.projectDueDate || '';

                    overlay.querySelectorAll('#editDetailsDocList .attachment-row').forEach(bindDocumentRemove);

                    let managerPickerRoot = null;
                    if (canReassign) {
                        managerPickerRoot = overlay.querySelector('#editProjectManagerPicker');
                        managerPickerRoot.innerHTML = empPickerHTML('manager_id', 'Search name or employee ID…');
                        initEmpPicker(managerPickerRoot, { roles: ['admin', 'manager'], selectedId: cfg.managerId, selectedLabel: cfg.managerName });
                    }

                    const editDocInput = overlay.querySelector('#editDetailsDocInput');
                    const editAddDocBtn = overlay.querySelector('#editDetailsAddDocBtn');
                    if (editAddDocBtn) {
                        editAddDocBtn.addEventListener('click', function () { editDocInput.click(); });
                        editDocInput.addEventListener('change', function () {
                            const file = editDocInput.files[0];
                            if (!file) return;
                            const errorBox = overlay.querySelector('#editDetailsError');
                            const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'zip'];
                            const ext = file.name.split('.').pop().toLowerCase();
                            if (!allowedExtensions.includes(ext)) {
                                showError(errorBox, 'Only PDF, Word, Excel, PowerPoint, TXT, CSV, PNG, JPG, or ZIP files are allowed.');
                                editDocInput.value = '';
                                return;
                            }
                            if (file.size > 50 * 1024 * 1024) {
                                showError(errorBox, '"' + file.name + '" is too large (50MB max).');
                                editDocInput.value = '';
                                return;
                            }
                            apiUpload('api/projects/documents.php', { action: 'upload', project_id: cfg.projectId }, file)
                                .then(function () { window.location.reload(); })
                                .catch(function (err) { showError(errorBox, err.message); editDocInput.value = ''; });
                        });
                    }

                    overlay.querySelector('#editDetailsForm').addEventListener('submit', function (ev) {
                        ev.preventDefault();
                        const errorBox = overlay.querySelector('#editDetailsError');
                        const name = overlay.querySelector('#editProjectName').value.trim();
                        if (!name) { showError(errorBox, 'Project name is required.'); return; }
                        const description = overlay.querySelector('#editProjectDescription').value.trim();
                        const department = overlay.querySelector('#editProjectDepartment').value.trim();
                        const dueDate = overlay.querySelector('#editProjectDueDate').value;
                        const status = overlay.querySelector('#editProjectStatus').value;
                        const managerId = managerPickerRoot ? managerPickerRoot.querySelector('.emp-picker-hidden').value : null;

                        apiPost('api/projects/status.php', {
                            action: 'update_details', project_id: cfg.projectId,
                            name: name, description: description, department: department, due_date: dueDate,
                        }).then(function () {
                            const chain = [];
                            if (status !== cfg.projectStatus) {
                                chain.push(apiPost('api/projects/status.php', { action: 'update', project_id: cfg.projectId, status: status }));
                            }
                            if (managerPickerRoot && managerId && Number(managerId) !== Number(cfg.managerId)) {
                                chain.push(apiPost('api/projects/status.php', { action: 'reassign_manager', project_id: cfg.projectId, manager_id: managerId }));
                            }
                            return Promise.all(chain);
                        }).then(function () {
                            window.location.reload();
                        }).catch(function (err) { showError(errorBox, err.message); });
                    });
                },
            },
        ];

        const menuItems = [];
        if (cfg.canEdit) {
            menuItems.push(...editItems);
        }
        if (cfg.canManage) {
            if (!cfg.isArchived) {
                menuItems.push({
                    label: 'Archive Project', onClick: function () {
                        confirmModal(
                            'This project will be removed from active project lists. All project history, tasks, defects, members, documents, and updates will be retained and can be viewed later.',
                            function () {
                                apiPost('api/projects/status.php', { action: 'archive', project_id: cfg.projectId })
                                    .then(function () { window.location.reload(); })
                                    .catch(function (err) { showError(pageErrorBox, err.message); });
                            },
                            { title: 'Archive Project?', okLabel: 'Archive Project' }
                        );
                    },
                });
            } else {
                menuItems.push({
                    label: 'Restore Project', onClick: function () {
                        confirmModal(
                            'This project will become active again and its features will be enabled.',
                            function () {
                                apiPost('api/projects/status.php', { action: 'restore', project_id: cfg.projectId })
                                    .then(function () { window.location.reload(); })
                                    .catch(function (err) { showError(pageErrorBox, err.message); });
                            },
                            { title: 'Restore Project?', okLabel: 'Restore Project' }
                        );
                    },
                });
            }
        }
        if (cfg.isAdmin) {
            menuItems.push({
                label: 'Delete Project', danger: true, onClick: function () {
                    confirmModal(
                        'Permanently delete "' + cfg.projectName + '"? This is only allowed for projects with no tasks, defects, documents, or members, and cannot be undone. Projects with history should be archived instead.',
                        function () {
                            apiPost('api/projects/status.php', { action: 'delete', project_id: cfg.projectId })
                                .then(function () { window.location.href = 'projects.php'; })
                                .catch(function (err) { showError(pageErrorBox, err.message); });
                        },
                        { title: 'Permanently Delete Project?', okLabel: 'Delete Permanently' }
                    );
                },
            });
        }
        initOverflowMenu(moreActionsBtn, menuItems);
    }

    const openAddTaskBtn = document.getElementById('openAddTaskModal');
    if (openAddTaskBtn) {
        openAddTaskBtn.addEventListener('click', function () {
            const overlay = openModal('Add New Task', '' +
                '<div id="addTaskModalError" class="error-msg" style="display:none;"></div>' +
                '<form id="addTaskForm">' +
                '<div class="form-grid">' +
                '<div class="field field-full"><label>Task Title <span class="req-star">*</span></label><input type="text" id="taskTitle" required></div>' +
                '<div class="field field-full"><label>Assignees <span class="req-star">*</span></label><div id="assigneePicker"></div></div>' +
                '<div class="field"><label>Priority <span class="req-star">*</span></label><select id="taskPriority">' +
                '<option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option>' +
                '</select></div>' +
                '<div class="field"><label>Status</label><select id="taskStatus">' +
                '<option value="todo" selected>To Do</option><option value="in_progress">In Progress</option><option value="review">Review</option><option value="done">Done</option>' +
                '</select></div>' +
                '<div class="field"><label>Start Date</label><input type="date" id="taskStartDate"></div>' +
                '<div class="field"><label>Due Date</label><input type="date" id="taskDueDate"></div>' +
                '<div class="field field-full"><label>Description</label><textarea id="taskDescription" rows="2"></textarea></div>' +
                '</div>' +
                '<div class="modal-actions">' +
                '<button type="button" class="pill-btn pill-btn-secondary" id="addTaskCancel">Cancel</button>' +
                '<button type="submit" class="pill-btn">Add Task</button>' +
                '</div>' +
                '</form>');

            overlay.querySelector('#addTaskCancel').addEventListener('click', closeModal);

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
                    status: overlay.querySelector('#taskStatus').value,
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

    /* ---------- Defects tab ---------- */

    const DEFECT_SEVERITY_LABELS = { critical: 'Critical', major: 'Major', minor: 'Minor' };
    const DEFECT_STATUS_LABELS = { open: 'Open', in_progress: 'In Progress', resolved: 'Resolved' };

    function findDefect(id) {
        id = parseInt(id, 10);
        return state.defects.find(d => d.id === id);
    }

    function defectRowHTML(d) {
        const assignee = d.assigned_to ? [{ id: d.assigned_to, name: d.assignee_name, role: d.assignee_role, has_photo: d.assignee_has_photo }] : [];
        return '<div class="task-list-row defect-list-row" data-defect-id="' + d.id + '"' +
            ' data-status="' + d.status + '" data-severity="' + d.severity + '" data-created="' + d.created_at.slice(0, 10) + '">' +
            '<span class="defect-list-code">' + escapeHtml(d.code) + '</span>' +
            '<span class="defect-list-title">' + escapeHtml(d.title) + '</span>' +
            '<span>' + ProjectUI.avatarStackNamesHTML(assignee) + '</span>' +
            '<span><span class="grid-status-badge grid-status-' + d.status + '">' + (DEFECT_STATUS_LABELS[d.status] || d.status) + '</span></span>' +
            '<span><span class="grid-status-badge grid-severity-' + d.severity + '">' + (DEFECT_SEVERITY_LABELS[d.severity] || d.severity) + '</span></span>' +
            '<span class="task-list-date">' + escapeHtml(fmtDate(d.created_at.slice(0, 10))) + '</span>' +
            '<span class="task-list-date">' + escapeHtml(fmtDate(d.updated_at.slice(0, 10))) + '</span>' +
            '</div>';
    }

    function replaceDefectRow(d) {
        const row = document.querySelector('.defect-list-row[data-defect-id="' + d.id + '"]');
        if (!row) return;
        row.outerHTML = defectRowHTML(d);
        bindDefectRow(document.querySelector('.defect-list-row[data-defect-id="' + d.id + '"]'));
    }

    function patchDefectInState(d) {
        const idx = state.defects.findIndex(x => x.id === d.id);
        if (idx !== -1) state.defects[idx] = d; else state.defects.push(d);
    }

    function bindDefectRow(row) {
        row.addEventListener('click', function () {
            const d = findDefect(row.dataset.defectId);
            if (d) openDefectDrawer(d);
        });
    }
    document.querySelectorAll('#defectList .defect-list-row').forEach(bindDefectRow);

    function openDefectDrawer(defect) {
        const canEdit = cfg.canManage;
        const canUpdateStatus = cfg.canManage || defect.assigned_to === cfg.currentUserId;
        let statusOptions = '';
        for (const key in DEFECT_STATUS_LABELS) {
            statusOptions += '<option value="' + key + '"' + (key === defect.status ? ' selected' : '') + '>' + DEFECT_STATUS_LABELS[key] + '</option>';
        }
        let severityOptions = '';
        for (const key in DEFECT_SEVERITY_LABELS) {
            severityOptions += '<option value="' + key + '"' + (key === defect.severity ? ' selected' : '') + '>' + DEFECT_SEVERITY_LABELS[key] + '</option>';
        }

        const overlay = openDrawer(escapeHtml(defect.title), '' +
            '<div id="defectDrawerError" class="error-msg" style="display:none;"></div>' +
            '<div class="drawer-section">' +
            '<form id="defectDrawerForm">' +
            '<div class="field"><label>Title</label><input type="text" id="ddTitle" value="' + escapeHtml(defect.title) + '"' + (canEdit ? '' : ' disabled') + ' required></div>' +
            '<div class="field"><label>Description</label><textarea id="ddDescription" rows="3"' + (canEdit ? '' : ' disabled') + '>' + escapeHtml(defect.description || '') + '</textarea></div>' +
            '<div class="field-grid-2">' +
            (canEdit ? '<div class="field"><label>Assignee</label><div id="ddAssigneePicker"></div></div>' : '') +
            '<div class="field"><label>Severity</label><span class="pill-select tag-' + defect.severity + '" id="ddSeverityPill"><select id="ddSeverity"' + (canEdit ? '' : ' disabled') + '>' + severityOptions + '</select></span></div>' +
            '<div class="field"><label>Status</label><span class="pill-select tag-' + defect.status + '" id="ddStatusPill"><select id="ddStatus"' + (canUpdateStatus ? '' : ' disabled') + '>' + statusOptions + '</select></span></div>' +
            '</div>' +
            '</form>' +
            '</div>' +
            (canEdit ? '' +
                '<div class="drawer-section drawer-actions-bottom">' +
                '<button type="submit" form="defectDrawerForm" class="pill-btn">Save Changes</button> ' +
                '<button type="button" id="ddDelete" class="pill-btn pill-btn-danger">Delete Defect</button>' +
                '</div>'
                : ''));

        if (canEdit) {
            const pickerRoot = overlay.querySelector('#ddAssigneePicker');
            pickerRoot.innerHTML = empPickerHTML('assigned_to', 'Search name or employee ID…');
            initEmpPicker(pickerRoot, {
                projectId: cfg.projectId,
                mode: 'members',
                selectedId: defect.assigned_to || '',
                selectedLabel: defect.assignee_name || '',
            });

            overlay.querySelector('#defectDrawerForm').addEventListener('submit', function (ev) {
                ev.preventDefault();
                const errorBox = overlay.querySelector('#defectDrawerError');
                const title = overlay.querySelector('#ddTitle').value.trim();
                if (!title) { showError(errorBox, 'Defect title is required.'); return; }

                apiPost('api/projects/defects.php', {
                    action: 'update',
                    project_id: cfg.projectId,
                    defect_id: defect.id,
                    title: title,
                    description: overlay.querySelector('#ddDescription').value.trim(),
                    severity: overlay.querySelector('#ddSeverity').value,
                    assigned_to: pickerRoot.querySelector('.emp-picker-hidden').value,
                }).then(function (data) {
                    patchDefectInState(data.defect);
                    replaceDefectRow(data.defect);
                    closeDrawer();
                }).catch(function (err) { showError(errorBox, err.message); });
            });

            overlay.querySelector('#ddDelete').addEventListener('click', function () {
                confirmModal('Delete this defect?', function () {
                    apiPost('api/projects/defects.php', { action: 'delete', project_id: cfg.projectId, defect_id: defect.id })
                        .then(function () {
                            state.defects = state.defects.filter(d => d.id !== defect.id);
                            const row = document.querySelector('.defect-list-row[data-defect-id="' + defect.id + '"]');
                            if (row) row.remove();
                            closeDrawer();
                        })
                        .catch(function (err) { showError(overlay.querySelector('#defectDrawerError'), err.message); });
                }, { okLabel: 'Delete' });
            });
        }

        if (canUpdateStatus) {
            overlay.querySelector('#ddStatus').addEventListener('change', function () {
                const select = this;
                apiPost('api/projects/defects.php', { action: 'update_status', project_id: cfg.projectId, defect_id: defect.id, status: select.value })
                    .then(function () {
                        defect.status = select.value;
                        patchDefectInState(defect);
                        replaceDefectRow(defect);
                        overlay.querySelector('#ddStatusPill').className = 'pill-select tag-' + defect.status;
                    })
                    .catch(function (err) { showError(overlay.querySelector('#defectDrawerError'), err.message); select.value = defect.status; });
            });
        }

        const severitySelect = overlay.querySelector('#ddSeverity');
        if (severitySelect) {
            severitySelect.addEventListener('change', function () {
                overlay.querySelector('#ddSeverityPill').className = 'pill-select tag-' + severitySelect.value;
            });
        }
    }

    const openAddDefectBtn = document.getElementById('openAddDefectModal');
    if (openAddDefectBtn) {
        openAddDefectBtn.addEventListener('click', function () {
            const suggestedCode = 'DEF-' + String(state.defects.length + 1).padStart(3, '0');
            const overlay = openModal('Add Defect', '' +
                '<div id="addDefectModalError" class="error-msg" style="display:none;"></div>' +
                '<form id="addDefectForm">' +
                '<div class="form-grid">' +
                '<div class="field"><label>ID <span class="req-star">*</span></label><input type="text" id="defectCode" value="' + escapeHtml(suggestedCode) + '" required></div>' +
                '<div class="field"><label>Title <span class="req-star">*</span></label><input type="text" id="defectTitle" required></div>' +
                '<div class="field"><label>Severity <span class="req-star">*</span></label><select id="defectSeverity">' +
                '<option value="critical">Critical</option><option value="major">Major</option><option value="minor" selected>Minor</option>' +
                '</select></div>' +
                '<div class="field"><label>Assignee</label><div id="defectAssigneePicker"></div></div>' +
                '<div class="field field-full"><label>Description</label><textarea id="defectDescription" rows="2"></textarea></div>' +
                '</div>' +
                '<div class="modal-actions">' +
                '<button type="button" class="pill-btn pill-btn-secondary" id="addDefectCancel">Cancel</button>' +
                '<button type="submit" class="pill-btn">Add Defect</button>' +
                '</div>' +
                '</form>');

            overlay.querySelector('#addDefectCancel').addEventListener('click', closeModal);

            const pickerRoot = overlay.querySelector('#defectAssigneePicker');
            pickerRoot.innerHTML = empPickerHTML('assigned_to', 'Search name or employee ID…');
            initEmpPicker(pickerRoot, { projectId: cfg.projectId, mode: 'members' });

            overlay.querySelector('#addDefectForm').addEventListener('submit', function (ev) {
                ev.preventDefault();
                const errorBox = overlay.querySelector('#addDefectModalError');
                const code = overlay.querySelector('#defectCode').value.trim();
                const title = overlay.querySelector('#defectTitle').value.trim();
                if (!code || !title) { showError(errorBox, 'Defect ID and title are required.'); return; }

                apiPost('api/projects/defects.php', {
                    action: 'create',
                    project_id: cfg.projectId,
                    code: code,
                    title: title,
                    severity: overlay.querySelector('#defectSeverity').value,
                    assigned_to: pickerRoot.querySelector('.emp-picker-hidden').value,
                    description: overlay.querySelector('#defectDescription').value.trim(),
                }).then(function (data) {
                    const emptyState = document.getElementById('defectsEmpty');
                    let list = document.getElementById('defectList');
                    if (!list) {
                        const wrap = document.getElementById('defectListWrap');
                        wrap.innerHTML = '<div class="task-list defect-list" id="defectList">' +
                            '<div class="task-list-head defect-list-head">' +
                            '<span>ID</span><span>Defect</span><span>Assignee</span><span>Status</span><span>Severity</span>' +
                            '<span>Created</span><span>Updated</span></div></div>';
                        list = document.getElementById('defectList');
                    }
                    if (emptyState) emptyState.remove();
                    state.defects.push(data.defect);
                    list.insertAdjacentHTML('beforeend', defectRowHTML(data.defect));
                    bindDefectRow(list.lastElementChild);
                    closeModal();
                }).catch(function (err) { showError(errorBox, err.message); });
            });
        });
    }

    /* ---------- Member card + drawer ---------- */

    function memberCardHTML(m) {
        const mine = state.tasks.filter(t => t.assigned_to === m.id);
        const completed = mine.filter(t => t.status === 'done').length;
        const active = mine.length - completed;
        const deptIcon = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';

        return '' +
            '<div class="member-card" data-user-id="' + m.id + '" data-user-name="' + escapeHtml(m.name) + '" data-role="' + escapeHtml(m.role_in_project) + '" data-department="' + escapeHtml(m.department || '') + '">' +
            '<button type="button" class="task-kebab" title="More actions">&#8942;</button>' +
            '<div class="member-card-head">' + avatarHTML(m) +
            '<div class="row-name"><strong>' + escapeHtml(m.name) + '</strong>' +
            (m.employee_code ? '<div class="member-card-id">' + escapeHtml(m.employee_code) + '</div>' : '') +
            '</div></div>' +
            '<span class="member-card-role">' + escapeHtml(m.role_in_project) + '</span>' +
            (m.permission_level && m.permission_level !== 'member' ? ' <span class="member-card-permission">' + cap(m.permission_level) + '</span>' : '') +
            '<div class="member-card-dept">' + deptIcon + ' ' + escapeHtml(m.department || '—') + '</div>' +
            '<div class="member-card-stats">' +
            '<div class="member-card-stat member-card-stat-assigned"><span class="member-card-stat-lbl">Active Tasks</span><span class="member-card-stat-num">' + active + '</span></div>' +
            '<div class="member-card-stat member-card-stat-completed"><span class="member-card-stat-lbl">Completed Tasks</span><span class="member-card-stat-num">' + completed + '</span></div>' +
            '</div>' +
            '</div>';
    }
    ProjectUI.memberCardHTML = memberCardHTML;

    /** Admin/manager-only: change a member's role_in_project (free text, e.g. "Tech Lead").
     *  Uses the API's existing 'update' action on members.php — permission_level is left
     *  untouched by passing the member's current value straight through. */
    function openEditRoleModal(member, card) {
        const overlay = openModal('Edit Role — ' + member.name, '' +
            '<div id="editRoleError" class="error-msg" style="display:none;"></div>' +
            '<form id="editRoleForm">' +
            '<div class="form-grid">' +
            '<div class="field field-full"><label>Role in Project</label><input type="text" id="editRoleInput" placeholder="e.g. Developer, Tester"></div>' +
            '</div>' +
            '<div class="modal-actions">' +
            '<button type="button" class="pill-btn pill-btn-secondary" id="editRoleCancel">Cancel</button>' +
            '<button type="submit" class="pill-btn">Save</button>' +
            '</div>' +
            '</form>');
        overlay.querySelector('#editRoleCancel').addEventListener('click', closeModal);
        overlay.querySelector('#editRoleInput').value = member.role_in_project || '';

        overlay.querySelector('#editRoleForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            const errorBox = overlay.querySelector('#editRoleError');
            const role = overlay.querySelector('#editRoleInput').value.trim();

            apiPost('api/projects/members.php', {
                action: 'update', project_id: cfg.projectId, user_id: member.id,
                role_in_project: role, permission_level: member.permission_level || 'member',
            }).then(function () {
                member.role_in_project = role || 'Team Member';
                const newCard = document.createElement('div');
                newCard.innerHTML = memberCardHTML(member);
                const insertedCard = newCard.firstElementChild;
                card.replaceWith(insertedCard);
                bindMemberCard(insertedCard);
                closeModal();
            }).catch(function (err) { showError(errorBox, err.message); });
        });
    }

    function bindMemberCard(card) {
        const kebab = card.querySelector('.task-kebab');
        const cardUserId = parseInt(card.dataset.userId, 10);
        // The project manager's card is synthesized from the project record, not a real
        // project_members row — there's nothing to "remove" them from here.
        const isManagerCard = cardUserId === cfg.managerId;
        const isSelfCard = cardUserId === cfg.currentUserId;
        if (kebab) {
            const menuItems = [];
            // Any project member can leave a comment on a fellow member or the manager — same as
            // the manager always could — but not on their own card (there's nothing to say to
            // yourself); their own card just offers to view what others said about them.
            menuItems.push(isSelfCard
                ? { label: 'View Comments', onClick: function () { openMemberDrawer(findMember(card.dataset.userId), false); } }
                : { label: 'Comment', onClick: function () { openMemberDrawer(findMember(card.dataset.userId), true); } });
            if (cfg.canEdit && !isManagerCard) {
                menuItems.push({
                    label: 'Edit Role', onClick: function () { openEditRoleModal(findMember(card.dataset.userId), card); },
                });
                menuItems.push({
                    label: 'Remove', danger: true, onClick: function () {
                        confirmRemoveMember(findMember(card.dataset.userId), function () { removeMember(card); });
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

    /** Tasks not yet done that this member is currently assigned to — used both to word the
     *  removal confirmation and (after removal) to flag those same rows in the Tasks list. */
    function activeTasksForMember(userId) {
        return state.tasks.filter(function (t) {
            return t.status !== 'done' && (t.assignees || []).some(function (a) { return a.id === userId; });
        });
    }

    /** Removing someone from Members never touches their task assignments or history — a task's
     *  assignee is who did the work, which stays true regardless of current membership. Completed
     *  tasks keep showing them untouched; active ones just get flagged in the Tasks list (see
     *  assigneeCellHTML in project-detail-views.js) so a manager can reassign, since the picker
     *  there only offers current members/manager to begin with. */
    function confirmRemoveMember(member, onConfirm) {
        const activeCount = activeTasksForMember(member.id).length;
        const message = activeCount > 0
            ? escapeHtml(member.name) + ' is currently assigned to ' + activeCount + ' active task' + (activeCount === 1 ? '' : 's') +
              '. Removing them will remove them from the project members list, but existing task history will be retained.'
            : 'Remove ' + escapeHtml(member.name) + ' from this project?';
        confirmModal(message, onConfirm, { title: 'Remove ' + escapeHtml(member.name) + ' from this project?', okLabel: 'Remove Member' });
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
        const isSelf = member.id === cfg.currentUserId;
        // Anyone on the project can comment on anyone else (a fellow member or the manager) —
        // not just the manager. Your own card just shows what others said about you instead.
        const canCompose = !isSelf;
        const canRemove = cfg.canEdit && member.id !== cfg.managerId && !isSelf;
        const overlay = openDrawer(escapeHtml(member.name), '' +
            '<div id="memberDrawerError" class="error-msg" style="display:none;"></div>' +
            '<div class="row-name" style="margin-bottom:10px;">' + avatarHTML(member) +
            '<span><b>' + escapeHtml(member.name) + '</b>' +
            (member.employee_code ? '<div class="member-card-sub" style="margin:2px 0 0;">ID: ' + escapeHtml(member.employee_code) + '</div>' : '') +
            '</span></div>' +
            '<div class="member-card-sub">' + escapeHtml(member.email) + '</div>' +
            '<div class="member-card-sub">' + escapeHtml(member.department || '—') + '</div>' +
            '<h3 style="font-size:12px;color:var(--navy);margin:16px 0 8px;text-transform:uppercase;">' + (canCompose ? 'Your Comments' : 'Comments About You') + '</h3>' +
            '<div id="memberReviewList" style="margin-bottom:12px;font-size:12px;color:var(--text-muted);">Loading…</div>' +
            (canCompose ? '' +
                '<form id="memberCommentForm">' +
                '<div class="field"><label>Add Comment</label><textarea id="memberCommentText" rows="3" required></textarea></div>' +
                '<button type="submit" class="pill-btn pill-btn-sm">Send Comment</button>' +
                '</form>'
                : '') +
            (canRemove ? '<button type="button" id="memberRemoveBtn" class="pill-btn pill-btn-danger pill-btn-sm" style="margin-top:16px;">Remove from Project</button>' : ''));

        apiPost('api/projects/reviews.php', { action: 'list_for_member', project_id: cfg.projectId, user_id: member.id })
            .then(function (data) { renderReviewList(overlay, data.reviews); })
            .catch(function () { overlay.querySelector('#memberReviewList').textContent = 'Could not load comments.'; });

        if (canCompose) {
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

            if (focusComposer) {
                setTimeout(function () {
                    const ta = overlay.querySelector('#memberCommentText');
                    if (ta) ta.focus();
                }, 200);
            }
        }

        if (canRemove) {
            overlay.querySelector('#memberRemoveBtn').addEventListener('click', function () {
                confirmRemoveMember(member, function () {
                    const card = document.querySelector('.member-card[data-user-id="' + member.id + '"]');
                    removeMember(card);
                    closeDrawer();
                });
            });
        }
    }

    function renderReviewList(overlay, reviews) {
        const box = overlay.querySelector('#memberReviewList');
        if (!box) return;
        if (!reviews.length) { box.textContent = 'No comments yet.'; return; }
        box.innerHTML = reviews.map(r =>
            '<div style="padding:8px 0;border-bottom:1px solid var(--border-light);">' +
            (r.author_name ? '<div style="font-size:10.5px;font-weight:700;color:var(--text);">' + escapeHtml(r.author_name) + '</div>' : '') +
            '<div>' + escapeHtml(r.comment) + '</div>' +
            '<div style="font-size:10.5px;margin-top:3px;">' + fmtDate(r.created_at) + '</div>' +
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
                '<div class="form-grid">' +
                '<div class="field field-full"><label>Employee <span class="req-star">*</span></label><div id="addMemberPicker"></div></div>' +
                '<div class="field field-full"><label>Role in Project</label><input type="text" id="addMemberRole" placeholder="e.g. Developer, Tester"></div>' +
                '</div>' +
                '<div class="modal-actions">' +
                '<button type="button" class="pill-btn pill-btn-secondary" id="addMemberCancel">Cancel</button>' +
                '<button type="submit" class="pill-btn">Add Member</button>' +
                '</div>' +
                '</form>');

            overlay.querySelector('#addMemberCancel').addEventListener('click', closeModal);

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
    document.querySelectorAll('#ovDescDocs .attachment-row').forEach(bindDocumentRemove);

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
