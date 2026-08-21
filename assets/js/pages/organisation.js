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

    const ORG_ICONS = {
        mail: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/></svg>',
        phone: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
    };

    function employeeInfoHTML(employee) {
        const avatar = employee.has_photo
            ? '<img class="org-profile-avatar" src="api/employees/photo.php?action=view&id=' + employee.id + '" alt="' + escapeHtml(employee.name) + '">'
            : '<span class="org-profile-avatar avatar-' + employee.role + '">' + initials(employee.name) + '</span>';

        let html = '<div class="org-profile-card">';

        html += '<div class="org-profile-left">';
        html += avatar;
        html += '<div class="org-profile-heading">';
        html += '<div class="org-profile-name-line"><span class="org-profile-name">' + escapeHtml(employee.name) + '</span></div>';
        html += '<div class="org-profile-code">' + escapeHtml(employee.employee_code) + '</div>';
        html += '<div class="org-profile-role-line">' + escapeHtml(cap(employee.role))
            + (employee.department ? ' &middot; ' + escapeHtml(employee.department) + ' Department' : '') + '</div>';
        html += '<div class="org-profile-contact">';
        html += '<span>' + ORG_ICONS.mail + escapeHtml(employee.email) + '</span>';
        if (employee.telephone) html += '<span>' + ORG_ICONS.phone + escapeHtml(employee.telephone) + '</span>';
        html += '</div>';
        html += '</div>';
        html += '</div>';

        html += '</div>';
        return html;
    }

    function nodeBoxHTML(person, highlight) {
        // Employees can only browse the tree shape — clicking a node must not leak into
        // that colleague's full profile page, so no navigable link is rendered for them.
        const cls = 'org-node' + (highlight ? ' org-highlight' : '') + (cfg.isEmployee ? ' org-node-static' : '');
        const tag = cfg.isEmployee ? 'div' : 'a';
        const subLine = escapeHtml(cap(person.role)) + ' &middot; <strong>' + escapeHtml(person.employee_code) + '</strong>'
            + (person.department ? ' &middot; ' + escapeHtml(person.department) : '');
        let html = '<div class="org-node-box">';
        html += '<' + tag + ' class="' + cls + '"'
            + (cfg.isEmployee ? '' : ' href="employee_detail.php?id=' + person.id + '"')
            + ' data-id="' + person.id + '"'
            + ' data-name="' + escapeHtml(person.name) + '"'
            + ' data-role="' + escapeHtml(person.role) + '"'
            + ' data-department="' + escapeHtml(person.department || '') + '"'
            + ' data-telephone="' + escapeHtml(person.telephone || '') + '"'
            + ' data-manager-id="' + (person.manager_id || '') + '">';
        html += person.has_photo
            ? '<img class="avatar org-avatar org-avatar-photo" src="api/employees/photo.php?action=view&id=' + person.id + '" alt="' + escapeHtml(person.name) + '">'
            : '<span class="avatar org-avatar avatar-' + person.role + '">' + initials(person.name) + '</span>';
        html += '<span class="org-info">'
            + '<span class="org-name">' + escapeHtml(person.name) + '</span>'
            + '<span class="org-role">' + subLine + '</span>';
        if (person.email) {
            html += '<span class="org-node-contact">' + ORG_ICONS.mail + escapeHtml(person.email) + '</span>';
        }
        if (person.telephone) {
            html += '<span class="org-node-contact">' + ORG_ICONS.phone + escapeHtml(person.telephone) + '</span>';
        }
        html += '</span>';
        html += '</' + tag + '>';
        if (cfg.isAdmin) {
            html += '<button type="button" class="org-node-kebab" title="Reassign manager">&#8942;</button>';
        }
        html += '</div>';
        return html;
    }

    function directReportsHTML(reports) {
        if (!reports.length) return '';
        // The connecting bar across .org-children only makes sense fanning out to 2+ siblings —
        // for a single report it just reads as a stray line, so drop it and keep only the
        // vertical stems for a clean one-to-one connector.
        const singleClass = reports.length === 1 ? ' org-children-single' : '';
        let html = '<div class="org-stem"></div><div class="org-children' + singleClass + '">';
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
                '<div class="org-children org-children-single"><div class="org-child-col">' +
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
        infoBox.innerHTML = employeeInfoHTML(data.employee);
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
