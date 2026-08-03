/* Employee profile page: admin-only kebab with Edit/Delete, same pattern as employees.js's Directory table.
   Also wires the admin-only "Change Profile Image" / "Remove" controls, independent of the kebab
   (the kebab is hidden when an admin views their own profile, but photo controls still apply). */

(function () {
    const photoBox = document.getElementById('profilePhotoBox');
    const changeBtn = document.getElementById('changePhotoBtn');
    if (photoBox && changeBtn) {
        const id = photoBox.dataset.id;
        const fileInput = document.getElementById('photoInput');
        const removeBtn = document.getElementById('removePhotoBtn');
        const errorBox = document.getElementById('photoError');

        function showPhotoError(message) {
            errorBox.textContent = message;
            errorBox.style.display = 'block';
        }

        changeBtn.addEventListener('click', function () { fileInput.click(); });

        fileInput.addEventListener('change', function () {
            const file = fileInput.files[0];
            if (!file) return;
            errorBox.style.display = 'none';
            apiUpload('api/employees/photo.php?action=upload', { id: id }, file).then(function () {
                location.reload();
            }).catch(function (err) {
                showPhotoError(err.message);
                fileInput.value = '';
            });
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                confirmModal('Remove this profile image?', function () {
                    apiPost('api/employees/photo.php', { action: 'remove', id: id }).then(function () {
                        location.reload();
                    }).catch(function (err) { showPhotoError(err.message); });
                }, { okLabel: 'Remove' });
            });
        }
    }

    const head = document.getElementById('empDetailHead');
    const kebab = document.getElementById('empDetailKebab');
    if (!head || !kebab) return;

    function showError(box, message) {
        box.textContent = message;
        box.style.display = 'block';
    }

    function openEditModal() {
        const id = head.dataset.id;
        const overlay = openModal('Edit ' + head.dataset.name, '' +
            '<div id="editEmpError" class="error-msg" style="display:none;"></div>' +
            '<form id="editEmpForm">' +
            '<div class="field"><label>Full Name</label><input type="text" id="editEmpName" value="' + escapeHtml(head.dataset.name) + '" required></div>' +
            '<div class="field"><label>Telephone</label><input type="text" id="editEmpPhone" value="' + escapeHtml(head.dataset.telephone || '') + '"></div>' +
            '<div class="field"><label>Role</label><select id="editEmpRole">' +
            '<option value="employee">Employee</option><option value="manager">Manager</option><option value="admin">Admin</option>' +
            '</select></div>' +
            '<div class="field"><label>Department</label><input type="text" id="editEmpDept" value="' + escapeHtml(head.dataset.department || '') + '"></div>' +
            '<div class="field"><label>Reports To (Manager)</label><div id="editEmpManagerPicker"></div></div>' +
            '<button type="submit" class="btn">Save Changes</button>' +
            '</form>');

        overlay.querySelector('#editEmpRole').value = head.dataset.role;

        const pickerRoot = overlay.querySelector('#editEmpManagerPicker');
        pickerRoot.innerHTML = empPickerHTML('manager_id', 'Search name or employee ID…');
        initEmpPicker(pickerRoot, {
            roles: ['admin', 'manager'],
            selectedId: head.dataset.managerId || '',
            selectedLabel: head.dataset.managerName || '',
        });

        overlay.querySelector('#editEmpForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            const errorBox = overlay.querySelector('#editEmpError');

            apiPost('api/employees/update.php', {
                id: id,
                name: overlay.querySelector('#editEmpName').value.trim(),
                telephone: overlay.querySelector('#editEmpPhone').value.trim(),
                role: overlay.querySelector('#editEmpRole').value,
                department: overlay.querySelector('#editEmpDept').value.trim(),
                manager_id: pickerRoot.querySelector('.emp-picker-hidden').value,
            }).then(function () {
                location.reload();
            }).catch(function (err) { showError(errorBox, err.message); });
        });
    }

    /* ---------- Delete Employee modal (Deactivate vs. Permanently Delete) — same as employees.js ---------- */

    const COUNT_LABELS = [
        ['projects_managed', 'Projects Managed'],
        ['tasks_assigned', 'Tasks Assigned'],
        ['tasks_created', 'Tasks Created'],
        ['discussion_posts', 'Discussion Posts'],
        ['comments', 'Comments'],
    ];

    function openDeleteModal() {
        const id = head.dataset.id;
        const name = head.dataset.name;

        apiGet('api/employees/delete_info.php?id=' + id).then(function (data) {
            const c = data.counts;
            const total = COUNT_LABELS.reduce((sum, [key]) => sum + c[key], 0);
            const canHardDelete = total === 0;

            const countsHtml = total > 0 ? (
                '<div class="delete-emp-warning">' +
                '<strong>&#9888; This employee has linked records.</strong>' +
                '<ul class="delete-emp-counts">' +
                COUNT_LABELS.map(([key, label]) => '<li><span>' + label + '</span><span>' + c[key] + '</span></li>').join('') +
                '</ul></div>'
            ) : '';

            const overlay = openModal('Delete ' + escapeHtml(name), '' +
                countsHtml +
                '<div id="deleteEmpError" class="error-msg" style="display:none;"></div>' +
                '<form id="deleteEmpForm" class="delete-emp-form">' +
                '<label class="delete-emp-option"><input type="radio" name="deleteAction" value="cancel" checked> Cancel</label>' +
                '<label class="delete-emp-option"><input type="radio" name="deleteAction" value="deactivate"> Deactivate</label>' +
                '<label class="delete-emp-option' + (canHardDelete ? '' : ' disabled') + '">' +
                '<input type="radio" name="deleteAction" value="hard_delete"' + (canHardDelete ? '' : ' disabled') + '> Permanently Delete' +
                (canHardDelete ? '' : '<span class="delete-emp-hint">Only available if there are no linked records.</span>') +
                '</label>' +
                '<button type="submit" class="btn btn-danger">Confirm</button>' +
                '</form>');

            overlay.querySelector('#deleteEmpForm').addEventListener('submit', function (ev) {
                ev.preventDefault();
                const action = overlay.querySelector('input[name="deleteAction"]:checked').value;
                if (action === 'cancel') { closeModal(); return; }

                apiPost('api/employees/delete.php', { id: id, action: action }).then(function () {
                    location.href = 'employees.php';
                }).catch(function (err) {
                    showError(overlay.querySelector('#deleteEmpError'), err.message);
                });
            });
        }).catch(function (err) { alert(err.message); });
    }

    function openResetPasswordModal() {
        const id = head.dataset.id;
        const overlay = openModal('Reset Password — ' + head.dataset.name, '' +
            '<div id="resetPwError" class="error-msg" style="display:none;"></div>' +
            '<form id="resetPwForm">' +
            '<div class="field"><label>New Temporary Password</label>' +
            '<input type="password" id="resetPwInput" required>' +
            '<div id="resetPwChecklist"></div></div>' +
            '<button type="submit" class="btn">Reset Password</button>' +
            '</form>');

        const pwInput = overlay.querySelector('#resetPwInput');
        const checklistBox = overlay.querySelector('#resetPwChecklist');
        checklistBox.innerHTML = passwordChecklistHTML();
        bindPasswordChecklist(pwInput, checklistBox);

        overlay.querySelector('#resetPwForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            const errorBox = overlay.querySelector('#resetPwError');
            if (!isPasswordValid(pwInput.value)) {
                showError(errorBox, 'Password does not meet the requirements above.');
                return;
            }
            apiPost('api/employees/reset_password.php', { id: id, new_password: pwInput.value }).then(function () {
                closeModal();
            }).catch(function (err) { showError(errorBox, err.message); });
        });
    }

    const items = [
        { label: 'Edit', onClick: openEditModal },
        { label: 'Reset Password', onClick: openResetPasswordModal },
        { label: 'Delete', danger: true, onClick: openDeleteModal },
    ];
    initOverflowMenu(kebab, items);
})();
