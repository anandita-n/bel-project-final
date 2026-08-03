/* Organisation Structure: the tree stays hidden until a search matches an employee (by name or
   staff ID). On a match, renders a small scoped tree — that employee's manager (or "Top Level
   Manager" if none), the employee highlighted, and their direct reports (if any) — via
   api/employees/hierarchy.php. Admins additionally get a kebab on each node to reassign that
   employee's manager (api/employees/update.php). */

(function () {
    const cfg = window.PAGE_CONFIG || {};
    const filterInput = document.getElementById('orgFilterInput');
    const resultBox = document.getElementById('orgSearchResult');
    const infoBox = document.getElementById('orgEmployeeInfo');
    const promptBox = document.getElementById('orgChartPrompt');
    const chartWrap = document.getElementById('orgChartWrap');
    const rootsEl = chartWrap ? chartWrap.querySelector('.org-roots') : null;

    function showChart(show) {
        if (chartWrap) chartWrap.style.display = show ? '' : 'none';
        if (promptBox) promptBox.style.display = show ? 'none' : '';
        if (!show && infoBox) infoBox.style.display = 'none';
    }

    function employeeInfoHTML(employee, manager) {
        let html = '<div class="panel-head panel-head-compact">';
        html += '<h3><span class="row-name">' + escapeHtml(employee.name).toUpperCase() + '</span></h3>';
        html += '<div class="panel-head-tools"><span class="dir-badge dir-badge-' + employee.role + '">' + escapeHtml(cap(employee.role)) + '</span></div>';
        html += '</div>';
        html += '<div class="panel-body">';
        html += '<div class="profile-photo-box">' + (employee.has_photo
            ? '<img class="avatar-photo" src="api/employees/photo.php?action=view&id=' + employee.id + '" alt="' + escapeHtml(employee.name) + '">'
            : '<span class="avatar avatar-photo avatar-' + employee.role + '">' + initials(employee.name) + '</span>') + '</div>';
        html += '<div class="form-grid">';
        html += '<div><strong>Employee Code:</strong> ' + escapeHtml(employee.employee_code) + '</div>';
        html += '<div><strong>Email:</strong> ' + escapeHtml(employee.email) + '</div>';
        html += '<div><strong>Telephone:</strong> ' + escapeHtml(employee.telephone || '—') + '</div>';
        html += '<div><strong>Department:</strong> ' + escapeHtml(employee.department || '—') + '</div>';
        html += '<div><strong>Stream:</strong> ' + escapeHtml(employee.stream || '—') + '</div>';
        html += '<div><strong>Group:</strong> ' + escapeHtml(employee.user_group || '—') + '</div>';
        html += '<div><strong>Reports To:</strong> ' + (manager ? escapeHtml(manager.name) : '—') + '</div>';
        html += '</div></div>';
        return html;
    }

    function nodeBoxHTML(person, highlight) {
        const cls = 'org-node' + (highlight ? ' org-highlight' : '');
        const roleLine = escapeHtml(person.employee_code) + ' &middot; ' + escapeHtml(cap(person.role))
            + (person.department ? ' &middot; ' + escapeHtml(person.department) : '');
        let html = '<div class="org-node-box">';
        html += '<a class="' + cls + '" href="employee_detail.php?id=' + person.id + '"'
            + ' data-id="' + person.id + '"'
            + ' data-name="' + escapeHtml(person.name) + '"'
            + ' data-role="' + escapeHtml(person.role) + '"'
            + ' data-department="' + escapeHtml(person.department || '') + '"'
            + ' data-telephone="' + escapeHtml(person.telephone || '') + '"'
            + ' data-manager-id="' + (person.manager_id || '') + '">';
        html += person.has_photo
            ? '<img class="avatar org-avatar org-avatar-photo" src="api/employees/photo.php?action=view&id=' + person.id + '" alt="' + escapeHtml(person.name) + '">'
            : '<span class="avatar org-avatar avatar-' + person.role + '">' + initials(person.name) + '</span>';
        html += '<span class="org-info"><span class="org-name">' + escapeHtml(person.name) + '</span><span class="org-role">' + roleLine + '</span></span>';
        html += '</a>';
        if (cfg.isAdmin) {
            html += '<button type="button" class="org-node-kebab" title="Reassign manager">&#8942;</button>';
        }
        html += '</div>';
        return html;
    }

    function directReportsHTML(reports) {
        if (!reports.length) return '';
        let html = '<div class="org-stem"></div><div class="org-children">';
        reports.forEach(function (r) {
            html += '<div class="org-child-col">' + nodeBoxHTML(r, false) + '</div>';
        });
        html += '</div>';
        return html;
    }

    function buildTreeHTML(data) {
        if (data.manager) {
            return '' +
                '<div class="org-root-col">' +
                nodeBoxHTML(data.manager, false) +
                '<div class="org-stem"></div>' +
                '<div class="org-children"><div class="org-child-col">' +
                nodeBoxHTML(data.employee, true) +
                directReportsHTML(data.direct_reports) +
                '</div></div>' +
                '</div>';
        }
        return '' +
            '<div class="org-root-col">' +
            '<div class="org-top-label">Top Level Manager</div>' +
            nodeBoxHTML(data.employee, true) +
            directReportsHTML(data.direct_reports) +
            '</div>';
    }

    function bindKebabs() {
        if (!cfg.isAdmin || !rootsEl) return;
        rootsEl.querySelectorAll('.org-node-kebab').forEach(function (btn) {
            btn.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                openReassignModal(btn.previousElementSibling);
            });
        });
    }

    function renderResult(data) {
        if (!data.found) {
            resultBox.style.display = 'block';
            resultBox.innerHTML = '<div class="empty-state">No employee found.</div>';
            showChart(false);
            return;
        }

        resultBox.style.display = 'none';
        infoBox.innerHTML = employeeInfoHTML(data.employee, data.manager);
        infoBox.style.display = 'block';
        rootsEl.innerHTML = buildTreeHTML(data);
        showChart(true);
        bindKebabs();
    }

    if (filterInput) {
        const runSearch = debounce(function (q) {
            if (!q) {
                resultBox.style.display = 'none';
                showChart(false);
                return;
            }
            apiGet('api/employees/hierarchy.php?q=' + encodeURIComponent(q)).then(renderResult).catch(function () {
                resultBox.style.display = 'block';
                resultBox.innerHTML = '<div class="empty-state">No employee found.</div>';
                showChart(false);
            });
        }, 250);
        filterInput.addEventListener('input', function () { runSearch(filterInput.value.trim()); });
    }

    if (!cfg.isAdmin) return;

    function openReassignModal(nodeLink) {
        const id = nodeLink.dataset.id;
        const name = nodeLink.dataset.name;
        const overlay = openModal('Reassign Manager — ' + name, '' +
            '<div id="reassignError" class="error-msg" style="display:none;"></div>' +
            '<form id="reassignForm">' +
            '<div class="field"><label>Reports To (Manager)</label><div id="reassignPicker"></div></div>' +
            '<button type="submit" class="btn">Save</button>' +
            '</form>');

        const pickerRoot = overlay.querySelector('#reassignPicker');
        pickerRoot.innerHTML = empPickerHTML('manager_id', 'Search name or employee ID…');
        initEmpPicker(pickerRoot, {
            roles: ['admin', 'manager'],
            selectedId: nodeLink.dataset.managerId || '',
        });

        overlay.querySelector('#reassignForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            const newManagerId = pickerRoot.querySelector('.emp-picker-hidden').value;
            if (newManagerId && newManagerId === id) {
                overlay.querySelector('#reassignError').textContent = 'An employee cannot be their own manager.';
                overlay.querySelector('#reassignError').style.display = 'block';
                return;
            }
            apiPost('api/employees/update.php', {
                id: id,
                name: name,
                role: nodeLink.dataset.role,
                department: nodeLink.dataset.department,
                telephone: nodeLink.dataset.telephone,
                manager_id: newManagerId,
            }).then(function () {
                filterInput.dispatchEvent(new Event('input', { bubbles: true }));
                closeModal();
            }).catch(function (err) {
                const box = overlay.querySelector('#reassignError');
                box.textContent = err.message;
                box.style.display = 'block';
            });
        });
    }
})();
