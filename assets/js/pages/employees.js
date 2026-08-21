/* Employees list — department drill-down: live inline search + pagination scoped to
   window.PAGE_CONFIG.department/status. Edit/Reset Password/Delete live on employee_detail.php's
   own kebab now, not here, so this file only renders and searches rows (plus Reactivate, which
   only exists on this list since deactivated employees have no working profile page to host it).
   Expects window.PAGE_CONFIG = { isAdmin, currentUserId, department, status } to be set by the page. */

(function () {
    const cfg = window.PAGE_CONFIG;
    const inactive = cfg.status === 'inactive';

    function employeeRowHTML(e) {
        const nameCell = inactive
            ? '<span>' + escapeHtml(e.name) + '</span>'
            : '<a href="employee_detail.php?id=' + e.id + '">' + escapeHtml(e.name) + '</a>';
        const codeCell = inactive
            ? '<td>' + escapeHtml(e.employee_code) + '</td>'
            : '<td><a class="code-link" href="employee_detail.php?id=' + e.id + '">' + escapeHtml(e.employee_code) + '</a></td>';
        const lastCell = inactive
            ? '<td><button type="button" class="pill-btn pill-btn-sm reactivate-btn">Reactivate</button></td>'
            : '<td class="manager-cell">' + escapeHtml(e.manager_name || '—') + '</td>';
        return '' +
            '<tr data-id="' + e.id + '" data-name="' + escapeHtml(e.name) + '" data-role="' + e.role + '" ' +
            'data-department="' + escapeHtml(e.department || '') + '" data-telephone="' + escapeHtml(e.telephone || '') + '" ' +
            'data-manager-id="' + (e.manager_id || '') + '" data-manager-name="' + escapeHtml(e.manager_name || '') + '">' +
            '<td><div class="row-name">' + avatarHTML(e) + nameCell + '</div></td>' +
            codeCell +
            '<td>' + escapeHtml(e.email) + '</td>' +
            '<td><span class="dir-badge dir-badge-' + e.role + '">' + escapeHtml(cap(e.role)) + '</span></td>' +
            '<td class="dept-cell">' + escapeHtml(e.department || '—') + '</td>' +
            lastCell +
            '</tr>';
    }

    function renderRows(rows) {
        const tbody = document.getElementById('employeesTbody');
        tbody.innerHTML = rows.map(employeeRowHTML).join('');
        document.getElementById('employeesTable').style.display = rows.length ? '' : 'none';
        document.getElementById('employeesEmpty').style.display = rows.length ? 'none' : '';
    }

    function renderPagination(data) {
        const el = document.getElementById('employeesPagination');
        if (!el) return;
        if (data.total_pages <= 1) {
            el.style.display = 'none';
            el.innerHTML = '';
            return;
        }
        el.style.display = '';
        el.innerHTML = '' +
            '<button type="button" id="employeesPagePrev" class="btn btn-secondary btn-sm"' + (data.page <= 1 ? ' disabled' : '') + '>&larr; Previous</button>' +
            '<span class="pagination-info">Page ' + data.page + ' of ' + data.total_pages + ' (' + data.total + ' total)</span>' +
            '<button type="button" id="employeesPageNext" class="btn btn-secondary btn-sm"' + (data.page >= data.total_pages ? ' disabled' : '') + '>Next &rarr;</button>';

        const prevBtn = document.getElementById('employeesPagePrev');
        const nextBtn = document.getElementById('employeesPageNext');
        if (prevBtn) prevBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage--; runSearch(); } });
        if (nextBtn) nextBtn.addEventListener('click', function () { if (currentPage < data.total_pages) { currentPage++; runSearch(); } });
    }

    const searchInput = document.getElementById('searchInput');
    const searchMeta = document.getElementById('searchMeta');
    const clearLink = document.getElementById('clearSearch');
    let currentPage = 1;

    function runSearch() {
        const q = searchInput.value.trim();
        const params = new URLSearchParams({ q: q, department: cfg.department || '', status: cfg.status || 'active', page: currentPage });
        apiGet('api/employees/list.php?' + params.toString()).then(function (data) {
            renderRows(data.results);
            renderPagination(data);
            if (q) {
                searchMeta.style.display = '';
                searchMeta.textContent = data.total + ' result' + (data.total === 1 ? '' : 's') + ' for "' + q + '"';
                clearLink.style.display = '';
            } else {
                searchMeta.style.display = 'none';
                clearLink.style.display = 'none';
            }
        });
    }

    const debouncedSearch = debounce(function () { currentPage = 1; runSearch(); }, 300);

    searchInput.addEventListener('input', debouncedSearch);
    clearLink.addEventListener('click', function (ev) { ev.preventDefault(); searchInput.value = ''; currentPage = 1; runSearch(); });

    const statusFilter = document.getElementById('statusFilter');
    statusFilter.addEventListener('change', function () {
        const params = new URLSearchParams({ department: cfg.department || '', status: statusFilter.value });
        window.location.href = 'employees.php?' + params.toString();
    });

    if (inactive) {
        document.getElementById('employeesTbody').addEventListener('click', function (ev) {
            const btn = ev.target.closest('.reactivate-btn');
            if (!btn) return;
            const row = btn.closest('tr');
            if (!confirm('Reactivate this employee? They will be able to log in again.')) return;

            btn.disabled = true;
            apiPost('api/employees/reactivate.php', { id: row.dataset.id }).then(function () {
                row.remove();
                if (!document.querySelector('#employeesTbody tr')) {
                    document.getElementById('employeesTable').style.display = 'none';
                    document.getElementById('employeesEmpty').style.display = '';
                }
            }).catch(function (err) {
                alert(err.message);
                btn.disabled = false;
            });
        });
    }
})();
