/* Employees list: live inline search, AJAX delete, and Jira-style modals for Add/Edit
   (Edit folds in what used to be the standalone "Assign Manager" page — role, department, manager).
   Expects window.PAGE_CONFIG = { isAdmin, currentUserId } to be set by the page. */

(function () {
    const cfg = window.PAGE_CONFIG;

    function employeeRowHTML(e) {
        let actions = '';
        if (cfg.isAdmin) {
            actions = '<td class="actions"><button type="button" class="btn btn-secondary edit-emp-btn" style="padding:4px 10px;font-size:11px;">Edit</button>';
            if (e.id !== cfg.currentUserId) {
                actions += ' <button type="button" class="btn btn-danger delete-emp-btn" style="padding:4px 10px;font-size:11px;">Delete</button>';
            }
            actions += '</td>';
        }
        return '' +
            '<tr data-id="' + e.id + '" data-name="' + escapeHtml(e.name) + '" data-role="' + e.role + '" ' +
            'data-department="' + escapeHtml(e.department || '') + '" data-manager-id="' + (e.manager_id || '') + '" data-manager-name="' + escapeHtml(e.manager_name || '') + '">' +
            '<td><div class="row-name"><span class="avatar avatar-' + e.role + '">' + initials(e.name) + '</span>' +
            '<a href="employee_detail.php?id=' + e.id + '">' + escapeHtml(e.name) + '</a></div></td>' +
            '<td>' + escapeHtml(e.employee_code) + '</td>' +
            '<td>' + escapeHtml(e.email) + '</td>' +
            '<td><span class="tag tag-' + e.role + '">' + escapeHtml(cap(e.role)) + '</span></td>' +
            '<td class="dept-cell">' + escapeHtml(e.department || '—') + '</td>' +
            '<td class="manager-cell">' + escapeHtml(e.manager_name || '—') + '</td>' +
            actions +
            '</tr>';
    }

    function renderRows(rows) {
        const tbody = document.getElementById('employeesTbody');
        tbody.innerHTML = rows.map(employeeRowHTML).join('');
        document.getElementById('employeesTable').style.display = rows.length ? '' : 'none';
        document.getElementById('employeesEmpty').style.display = rows.length ? 'none' : '';
    }

    const searchInput = document.getElementById('searchInput');
    const searchMeta = document.getElementById('searchMeta');
    const clearLink = document.getElementById('clearSearch');

    const runSearch = debounce(function (q) {
        apiGet('api/employees/list.php?q=' + encodeURIComponent(q)).then(function (data) {
            renderRows(data.results);
            if (q) {
                searchMeta.style.display = '';
                searchMeta.textContent = data.results.length + ' result' + (data.results.length === 1 ? '' : 's') + ' for "' + q + '"';
                clearLink.style.display = '';
            } else {
                searchMeta.style.display = 'none';
                clearLink.style.display = 'none';
            }
        });
    }, 200);

    searchInput.addEventListener('input', function () { runSearch(searchInput.value.trim()); });
    clearLink.addEventListener('click', function (ev) { ev.preventDefault(); searchInput.value = ''; runSearch(''); });

    document.getElementById('employeesTbody').addEventListener('click', function (ev) {
        if (ev.target.classList.contains('delete-emp-btn')) {
            const row = ev.target.closest('tr');
            if (!confirm('Remove ' + row.dataset.name + ' from the system?')) return;
            apiPost('api/employees/delete.php', { id: row.dataset.id }).then(function () {
                row.remove();
                const tbody = document.getElementById('employeesTbody');
                if (!tbody.children.length) {
                    document.getElementById('employeesTable').style.display = 'none';
                    document.getElementById('employeesEmpty').style.display = '';
                }
            }).catch(function (err) { alert(err.message); });
        }

        if (ev.target.classList.contains('edit-emp-btn')) {
            openEditModal(ev.target.closest('tr'));
        }
    });

    function showError(box, message) {
        box.textContent = message;
        box.style.display = 'block';
    }

    /* ---------- Add Employee modal ---------- */

    const openAddBtn = document.getElementById('openAddEmployeeModal');
    if (openAddBtn) {
        openAddBtn.addEventListener('click', function () {
            const overlay = openModal('Add Employee', '' +
                '<div id="addEmpError" class="error-msg" style="display:none;"></div>' +
                '<form id="addEmpForm">' +
                '<div class="field"><label>Employee Code</label><input type="text" id="addEmpCode" placeholder="BEL0002" required></div>' +
                '<div class="field"><label>Full Name</label><input type="text" id="addEmpName" required></div>' +
                '<div class="field"><label>Email</label><input type="email" id="addEmpEmail" required></div>' +
                '<div class="field"><label>Temporary Password</label><input type="password" id="addEmpPassword" required></div>' +
                '<div class="field"><label>Role</label><select id="addEmpRole"><option value="employee">Employee</option><option value="manager">Manager</option><option value="admin">Admin</option></select></div>' +
                '<div class="field"><label>Department</label><input type="text" id="addEmpDept"></div>' +
                '<div class="field"><label>Reports To (Manager)</label><div id="addEmpManagerPicker"></div></div>' +
                '<button type="submit" class="btn">Create Employee</button>' +
                '</form>');

            const pickerRoot = overlay.querySelector('#addEmpManagerPicker');
            pickerRoot.innerHTML = empPickerHTML('manager_id', 'Search name or employee ID…');
            initEmpPicker(pickerRoot, { roles: ['admin', 'manager'] });

            overlay.querySelector('#addEmpForm').addEventListener('submit', function (ev) {
                ev.preventDefault();
                const errorBox = overlay.querySelector('#addEmpError');

                apiPost('api/employees/create.php', {
                    employee_code: overlay.querySelector('#addEmpCode').value.trim(),
                    name: overlay.querySelector('#addEmpName').value.trim(),
                    email: overlay.querySelector('#addEmpEmail').value.trim(),
                    password: overlay.querySelector('#addEmpPassword').value,
                    role: overlay.querySelector('#addEmpRole').value,
                    department: overlay.querySelector('#addEmpDept').value.trim(),
                    manager_id: pickerRoot.querySelector('.emp-picker-hidden').value,
                }).then(function (data) {
                    const tbody = document.getElementById('employeesTbody');
                    tbody.insertAdjacentHTML('beforeend', employeeRowHTML(data.employee));
                    document.getElementById('employeesTable').style.display = '';
                    document.getElementById('employeesEmpty').style.display = 'none';
                    closeModal();
                }).catch(function (err) { showError(errorBox, err.message); });
            });
        });
    }

    /* ---------- Edit Employee modal (role / department / manager) ---------- */

    function openEditModal(row) {
        const id = row.dataset.id;
        const overlay = openModal('Edit ' + row.dataset.name, '' +
            '<div id="editEmpError" class="error-msg" style="display:none;"></div>' +
            '<form id="editEmpForm">' +
            '<div class="field"><label>Role</label><select id="editEmpRole">' +
            '<option value="employee">Employee</option><option value="manager">Manager</option><option value="admin">Admin</option>' +
            '</select></div>' +
            '<div class="field"><label>Department</label><input type="text" id="editEmpDept" value="' + escapeHtml(row.dataset.department) + '"></div>' +
            '<div class="field"><label>Reports To (Manager)</label><div id="editEmpManagerPicker"></div></div>' +
            '<button type="submit" class="btn">Save Changes</button>' +
            '</form>');

        overlay.querySelector('#editEmpRole').value = row.dataset.role;

        const pickerRoot = overlay.querySelector('#editEmpManagerPicker');
        pickerRoot.innerHTML = empPickerHTML('manager_id', 'Search name or employee ID…');
        initEmpPicker(pickerRoot, {
            roles: ['admin', 'manager'],
            selectedId: row.dataset.managerId || '',
            selectedLabel: row.dataset.managerName || '',
        });

        overlay.querySelector('#editEmpForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            const errorBox = overlay.querySelector('#editEmpError');

            apiPost('api/employees/update.php', {
                id: id,
                role: overlay.querySelector('#editEmpRole').value,
                department: overlay.querySelector('#editEmpDept').value.trim(),
                manager_id: pickerRoot.querySelector('.emp-picker-hidden').value,
            }).then(function (data) {
                row.dataset.role = data.employee.role;
                row.dataset.department = data.employee.department || '';
                row.querySelector('.tag').className = 'tag tag-' + data.employee.role;
                row.querySelector('.tag').textContent = cap(data.employee.role);
                row.querySelector('.dept-cell').textContent = data.employee.department || '—';
                row.querySelector('.manager-cell').textContent = data.employee.manager_name || '—';
                row.dataset.managerName = data.employee.manager_name || '';
                closeModal();
            }).catch(function (err) { showError(errorBox, err.message); });
        });
    }
})();
