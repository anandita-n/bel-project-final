/* Step 3 of the New Project wizard: add/edit/remove team members via the existing
   api/projects/members.php endpoint, same one project_detail.php's Members tab uses. */

(function () {
    const cfg = window.PAGE_CONFIG;
    const errorBox = document.getElementById('pageError');

    function showError(message) {
        errorBox.textContent = message;
        errorBox.style.display = 'block';
        setTimeout(() => { errorBox.style.display = 'none'; }, 4000);
    }

    function bindRow(row) {
        const userId = row.dataset.userId;
        const roleInput = row.querySelector('.team-role-input');
        const permSelect = row.querySelector('.team-permission-select');
        const removeBtn = row.querySelector('.team-remove-btn');

        function saveUpdate() {
            apiPost('api/projects/members.php', {
                action: 'update',
                project_id: cfg.projectId,
                user_id: userId,
                role_in_project: roleInput.value.trim(),
                permission_level: permSelect.value,
            }).catch(function (err) { showError(err.message); });
        }

        roleInput.addEventListener('blur', saveUpdate);
        roleInput.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') roleInput.blur(); });
        permSelect.addEventListener('change', saveUpdate);

        removeBtn.addEventListener('click', function () {
            confirmModal('Remove this member from the project?', function () {
                apiPost('api/projects/members.php', { action: 'remove', project_id: cfg.projectId, user_id: userId })
                    .then(function () { window.location.reload(); })
                    .catch(function (err) { showError(err.message); });
            }, { okLabel: 'Remove' });
        });
    }
    document.querySelectorAll('#teamTable tbody tr').forEach(bindRow);

    const pickerRoot = document.getElementById('addMemberPicker');
    pickerRoot.innerHTML = empPickerHTML('member_id', 'Search name or employee ID…');
    initEmpPicker(pickerRoot, { projectId: cfg.projectId, mode: 'available' });

    document.getElementById('addMemberBtn').addEventListener('click', function () {
        const userId = pickerRoot.querySelector('.emp-picker-hidden').value;
        const role = document.getElementById('addMemberRole').value.trim();
        const permission = document.getElementById('addMemberPermission').value;
        if (!userId) { showError('Please select an employee.'); return; }

        apiPost('api/projects/members.php', { action: 'add', project_id: cfg.projectId, user_id: userId, role_in_project: role, permission_level: permission })
            .then(function () { window.location.reload(); })
            .catch(function (err) { showError(err.message); });
    });
})();
