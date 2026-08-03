/* Projects list: live inline search. */

(function () {
    function projectRowHTML(p) {
        return '' +
            '<tr>' +
            '<td><a href="project_detail.php?id=' + p.id + '">' + escapeHtml(p.project_code) + '</a></td>' +
            '<td><a href="project_detail.php?id=' + p.id + '">' + escapeHtml(p.name) + '</a></td>' +
            '<td><div class="row-name">' + avatarHTML({ id: p.manager_id, name: p.manager_name, role: p.manager_role, has_photo: p.manager_has_photo }, 'avatar-sm') + escapeHtml(p.manager_name) + '</div></td>' +
            '<td>' + p.member_count + '</td>' +
            '<td><span class="dir-badge dir-badge-' + p.status + '">' + escapeHtml(cap(p.status.replace('_', ' '))) + '</span></td>' +
            '</tr>';
    }

    function renderRows(rows) {
        const tbody = document.getElementById('projectsTbody');
        tbody.innerHTML = rows.map(projectRowHTML).join('');
        document.getElementById('projectsTable').style.display = rows.length ? '' : 'none';
        document.getElementById('projectsEmpty').style.display = rows.length ? 'none' : '';
    }

    const searchInput = document.getElementById('searchInput');
    const searchMeta = document.getElementById('searchMeta');
    const clearLink = document.getElementById('clearSearch');
    const statusFilter = document.getElementById('statusFilter');

    const runSearch = debounce(function (q) {
        const status = statusFilter ? statusFilter.value : '';
        apiGet('api/projects/list.php?q=' + encodeURIComponent(q) + '&status=' + encodeURIComponent(status)).then(function (data) {
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
    if (statusFilter) {
        statusFilter.addEventListener('change', function () { runSearch(searchInput.value.trim()); });
    }
})();
