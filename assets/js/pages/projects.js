/* Projects — department drill-down: live server-side search + status filter (debounced),
   paginated via api/projects/list.php. Only loaded on projects.php?department=X; the department
   index page itself has no JS (it's just cheap counts + links, nothing to filter/paginate). */

(function () {
    const cfg = window.PAGE_CONFIG || {};
    let currentPage = 1;

    function projectRowHTML(p) {
        return '' +
            '<tr>' +
            '<td><a href="project_detail.php?id=' + p.id + '">' + escapeHtml(p.project_code) + '</a></td>' +
            '<td><a href="project_detail.php?id=' + p.id + '">' + escapeHtml(p.name) + '</a></td>' +
            '<td><div class="row-name">' + avatarHTML({ id: p.manager_id, name: p.manager_name, role: p.manager_role, has_photo: p.manager_has_photo }, 'avatar-sm') + escapeHtml(p.manager_name) + '</div></td>' +
            '<td><span class="dir-badge dir-badge-' + p.status + '">' + escapeHtml(cap(p.status.replace('_', ' '))) + '</span></td>' +
            '</tr>';
    }

    function renderRows(rows) {
        const tbody = document.getElementById('projectsTbody');
        tbody.innerHTML = rows.map(projectRowHTML).join('');
        document.getElementById('projectsPanel').style.display = rows.length ? '' : 'none';
        document.getElementById('projectsEmpty').style.display = rows.length ? 'none' : '';
    }

    function renderPagination(data) {
        const el = document.getElementById('projectsPagination');
        if (data.total_pages <= 1) {
            el.style.display = 'none';
            el.innerHTML = '';
            return;
        }
        el.style.display = '';
        el.innerHTML = '' +
            '<button type="button" id="projectsPagePrev" class="btn btn-secondary btn-sm"' + (data.page <= 1 ? ' disabled' : '') + '>&larr; Previous</button>' +
            '<span class="pagination-info">Page ' + data.page + ' of ' + data.total_pages + ' (' + data.total + ' total)</span>' +
            '<button type="button" id="projectsPageNext" class="btn btn-secondary btn-sm"' + (data.page >= data.total_pages ? ' disabled' : '') + '>Next &rarr;</button>';

        const prevBtn = document.getElementById('projectsPagePrev');
        const nextBtn = document.getElementById('projectsPageNext');
        if (prevBtn) prevBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage--; fetchAndRender(); } });
        if (nextBtn) nextBtn.addEventListener('click', function () { if (currentPage < data.total_pages) { currentPage++; fetchAndRender(); } });
    }

    const searchInput = document.getElementById('searchInput');
    const searchMeta = document.getElementById('searchMeta');
    const clearLink = document.getElementById('clearSearch');
    const statusFilter = document.getElementById('statusFilter');

    function fetchAndRender() {
        const params = new URLSearchParams({
            q: searchInput.value.trim(),
            status: statusFilter.value,
            department: cfg.department || '',
            page: currentPage,
        });
        apiGet('api/projects/list.php?' + params.toString()).then(function (data) {
            renderRows(data.results);
            renderPagination(data);
            if (params.get('q')) {
                searchMeta.style.display = '';
                searchMeta.textContent = data.total + ' result' + (data.total === 1 ? '' : 's') + ' for "' + params.get('q') + '"';
                clearLink.style.display = '';
            } else {
                searchMeta.style.display = 'none';
                clearLink.style.display = 'none';
            }
        });
    }

    const debouncedSearch = debounce(function () { currentPage = 1; fetchAndRender(); }, 300);

    searchInput.addEventListener('input', debouncedSearch);
    clearLink.addEventListener('click', function (ev) { ev.preventDefault(); searchInput.value = ''; currentPage = 1; fetchAndRender(); });
    statusFilter.addEventListener('change', function () { currentPage = 1; fetchAndRender(); });
})();
