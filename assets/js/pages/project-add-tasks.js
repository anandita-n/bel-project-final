/* Step 4 of the New Project wizard: choose how to start (create now / skip), then
   add/remove tasks via the existing api/projects/tasks.php endpoint, same one the project board uses. */

(function () {
    const cfg = window.PAGE_CONFIG;
    const errorBox = document.getElementById('pageError');
    const choicesEl = document.getElementById('taskStartChoices');
    const createPanelEl = document.getElementById('taskCreatePanel');

    function showError(message) {
        errorBox.textContent = message;
        errorBox.style.display = 'block';
        setTimeout(() => { errorBox.style.display = 'none'; }, 4000);
    }

    const createRadio = document.querySelector('input[name="task_start"][value="create"]');
    const skipRadio = document.querySelector('input[name="task_start"][value="skip"]');

    if (createRadio) {
        createRadio.addEventListener('change', function () {
            choicesEl.style.display = 'none';
            createPanelEl.style.display = '';
        });
    }
    if (skipRadio) {
        skipRadio.addEventListener('change', function () {
            window.location.href = 'project_add_review.php?id=' + cfg.projectId;
        });
    }

    function bindTaskCard(card) {
        const select = card.querySelector('.task-status-select');
        if (select) {
            select.addEventListener('change', function () {
                apiPost('api/projects/tasks.php', { action: 'update_status', project_id: cfg.projectId, task_id: card.dataset.taskId, status: select.value })
                    .catch(function (err) { showError(err.message); select.value = card.dataset.currentStatus; });
            });
        }
        const kebab = card.querySelector('.task-kebab');
        if (kebab) {
            initOverflowMenu(kebab, [{
                label: 'Delete', danger: true, onClick: function () {
                    confirmModal('Delete this task?', function () {
                        apiPost('api/projects/tasks.php', { action: 'delete', project_id: cfg.projectId, task_id: card.dataset.taskId })
                            .then(function () { window.location.reload(); })
                            .catch(function (err) { showError(err.message); });
                    }, { okLabel: 'Delete' });
                },
            }]);
        }
    }
    document.querySelectorAll('.task-card').forEach(bindTaskCard);

    const assigneePickerRoot = document.getElementById('assigneePicker');
    if (assigneePickerRoot) {
        assigneePickerRoot.innerHTML = empPickerHTML('assigned_to', 'Search name or employee ID…');
        initEmpPicker(assigneePickerRoot, { projectId: cfg.projectId, mode: 'members' });
    }

    const addTaskBtn = document.getElementById('addTaskBtn');
    if (addTaskBtn) {
        addTaskBtn.addEventListener('click', function () {
            const title = document.getElementById('taskTitle').value.trim();
            if (!title) { showError('Task title is required.'); return; }

            apiPost('api/projects/tasks.php', {
                action: 'create',
                project_id: cfg.projectId,
                title: title,
                assigned_to: assigneePickerRoot.querySelector('.emp-picker-hidden').value,
                priority: document.getElementById('taskPriority').value,
                start_date: document.getElementById('taskStartDate').value,
                due_date: document.getElementById('taskDueDate').value,
            }).then(function () { window.location.reload(); })
                .catch(function (err) { showError(err.message); });
        });
    }

})();
