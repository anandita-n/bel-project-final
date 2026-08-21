/* Employees list — department drill-down: live inline search + pagination scoped to
   window.PAGE_CONFIG.department. Edit/Reset Password/Delete live on employee_detail.php's
   own kebab now, not here, so this file only renders and searches rows.
   Expects window.PAGE_CONFIG = { isAdmin, currentUserId, department } to be set by the page. */

(function () {
    const cfg = window.PAGE_CONFIG;

    function employeeRowHTML(e) {
        return '' +
            '<tr data-id="' + e.id + '" data-name="' + escapeHtml(e.name) + '" data-role="' + e.role + '" ' +
            'data-department="' + escapeHtml(e.department || '') + '" data-telephone="' + escapeHtml(e.telephone || '') + '" ' +
            'data-manager-id="' + (e.manager_id || '') + '" data-manager-name="' + escapeHtml(e.manager_name || '') + '">' +
            '<td><div class="row-name">' + avatarHTML(e) +
            '<a href="employee_detail.php?id=' + e.id + '">' + escapeHtml(e.name) + '</a></div></td>' +
            '<td><a class="code-link" href="employee_detail.php?id=' + e.id + '">' + escapeHtml(e.employee_code) + '</a></td>' +
            '<td>' + escapeHtml(e.email) + '</td>' +
            '<td><span class="dir-badge dir-badge-' + e.role + '">' + escapeHtml(cap(e.role)) + '</span></td>' +
            '<td class="dept-cell">' + escapeHtml(e.department || '—') + '</td>' +
            '<td class="manager-cell">' + escapeHtml(e.manager_name || '—') + '</td>' +
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
        const params = new URLSearchParams({ q: q, department: cfg.department || '', page: currentPage });
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
})();
