/* Employees list: live inline search, AJAX delete, and Jira-style modals for Add/Edit
   (Edit folds in what used to be the standalone "Assign Manager" page — role, department, manager).
   Expects window.PAGE_CONFIG = { isAdmin, currentUserId } to be set by the page. */

(function () {
    const cfg = window.PAGE_CONFIG;

    function employeeRowHTML(e) {
        const actions = cfg.isAdmin ? '<td class="actions"><button type="button" class="row-kebab emp-row-kebab" title="More actions">&#8942;</button></td>' : '';
        return '' +
            '<tr data-id="' + e.id + '" data-name="' + escapeHtml(e.name) + '" data-role="' + e.role + '" ' +
            'data-department="' + escapeHtml(e.department || '') + '" data-telephone="' + escapeHtml(e.telephone || '') + '" ' +
            'data-manager-id="' + (e.manager_id || '') + '" data-manager-name="' + escapeHtml(e.manager_name || '') + '">' +
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
        tbody.querySelectorAll('tr').forEach(bindEmployeeRow);
    }

    function bindEmployeeRow(row) {
        const kebab = row.querySelector('.emp-row-kebab');
        if (!kebab) return;
        const items = [{ label: 'Edit', onClick: function () { openEditModal(row); } }];
        if (parseInt(row.dataset.id, 10) !== cfg.currentUserId) {
            items.push({
                label: 'Delete', danger: true, onClick: function () {
                    confirmModal('Remove ' + escapeHtml(row.dataset.name) + ' from the system?', function () {
                        apiPost('api/employees/delete.php', { id: row.dataset.id }).then(function () {
                            row.remove();
                            const tbody = document.getElementById('employeesTbody');
                            if (!tbody.children.length) {
                                document.getElementById('employeesTable').style.display = 'none';
                                document.getElementById('employeesEmpty').style.display = '';
                            }
                        }).catch(function (err) { alert(err.message); });
                    }, { okLabel: 'Remove' });
                },
            });
        }
        initOverflowMenu(kebab, items);
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

    document.querySelectorAll('#employeesTbody tr').forEach(bindEmployeeRow);

    function showError(box, message) {
        box.textContent = message;
        box.style.display = 'block';
    }

    /* ---------- Edit Employee modal (role / department / manager) ---------- */

    function openEditModal(row) {
        const id = row.dataset.id;
        const overlay = openModal('Edit ' + row.dataset.name, '' +
            '<div id="editEmpError" class="error-msg" style="display:none;"></div>' +
            '<form id="editEmpForm">' +
            '<div class="field"><label>Full Name</label><input type="text" id="editEmpName" value="' + escapeHtml(row.dataset.name) + '" required></div>' +
            '<div class="field"><label>Telephone</label><input type="text" id="editEmpPhone" value="' + escapeHtml(row.dataset.telephone || '') + '"></div>' +
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
                name: overlay.querySelector('#editEmpName').value.trim(),
                telephone: overlay.querySelector('#editEmpPhone').value.trim(),
                role: overlay.querySelector('#editEmpRole').value,
                department: overlay.querySelector('#editEmpDept').value.trim(),
                manager_id: pickerRoot.querySelector('.emp-picker-hidden').value,
            }).then(function (data) {
                row.dataset.name = data.employee.name;
                row.dataset.telephone = data.employee.telephone || '';
                row.dataset.role = data.employee.role;
                row.dataset.department = data.employee.department || '';
                row.querySelector('.row-name a').textContent = data.employee.name;
                row.querySelector('.row-name .avatar').textContent = initials(data.employee.name);
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
