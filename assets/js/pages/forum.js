/* Discussion Forum list: live search + status/department/tag filters + sort toggle against
   api/forum/list.php. */

(function () {
    const searchInput = document.getElementById('forumSearchInput');
    const statusFilter = document.getElementById('forumStatusFilter');
    const departmentFilter = document.getElementById('forumDepartmentFilter');
    const tagFilter = document.getElementById('forumTagFilter');
    const sortSelect = document.getElementById('forumSortSelect');
    let currentPage = 1;

    function statusBadgeHTML(status) {
        return status === 'solved'
            ? '<span class="forum-status-badge solved">&check; Solved</span>'
            : '<span class="forum-status-badge open">Open</span>';
    }

    function forumRowHTML(q) {
        const tagsHtml = q.tags.length
            ? '<div class="forum-tag-list">' + q.tags.map(function (t) { return '<span class="tag">' + escapeHtml(t.name) + '</span>'; }).join('') + '</div>'
            : '';
        const dept = q.author_department ? ' &middot; ' + escapeHtml(q.author_department) : '';
        return '' +
            '<div class="forum-row">' +
            '<div class="forum-stats">' +
            '<div class="forum-stat' + (q.answer_count > 0 ? ' forum-stat-answered' : '') + '"><span class="forum-stat-num">' + q.answer_count + '</span><span class="forum-stat-lbl">answers</span></div>' +
            '</div>' +
            '<div class="forum-row-content">' +
            '<div class="forum-row-title-line">' +
            '<a class="forum-row-title" href="forum_question.php?id=' + q.id + '">' + escapeHtml(q.title) + '</a>' +
            statusBadgeHTML(q.status) +
            '</div>' +
            tagsHtml +
            '<div class="forum-row-byline">asked by ' + avatarHTML({ id: q.author_id, name: q.author_name, role: q.author_role, has_photo: q.author_has_photo }, 'avatar-sm') + escapeHtml(q.author_name) + dept + ' &middot; ' + fmtDate(q.created_at.slice(0, 10)) + '</div>' +
            '</div>' +
            '</div>';
    }

    function renderRows(rows) {
        const list = document.getElementById('forumList');
        list.innerHTML = rows.map(forumRowHTML).join('');
        list.style.display = rows.length ? '' : 'none';
        document.getElementById('forumEmpty').style.display = rows.length ? 'none' : '';
    }

    function renderPagination(data) {
        const el = document.getElementById('forumPagination');
        if (data.total_pages <= 1) {
            el.style.display = 'none';
            el.innerHTML = '';
            return;
        }
        el.style.display = '';
        el.innerHTML = '' +
            '<button type="button" id="forumPagePrev" class="btn btn-secondary btn-sm"' + (data.page <= 1 ? ' disabled' : '') + '>&larr; Previous</button>' +
            '<span class="forum-pagination-info">Page ' + data.page + ' of ' + data.total_pages + '</span>' +
            '<button type="button" id="forumPageNext" class="btn btn-secondary btn-sm"' + (data.page >= data.total_pages ? ' disabled' : '') + '>Next &rarr;</button>';

        const prevBtn = document.getElementById('forumPagePrev');
        const nextBtn = document.getElementById('forumPageNext');
        if (prevBtn) prevBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage--; fetchAndRender(); } });
        if (nextBtn) nextBtn.addEventListener('click', function () { if (currentPage < data.total_pages) { currentPage++; fetchAndRender(); } });
    }

    function fetchAndRender() {
        const params = new URLSearchParams({
            q: searchInput.value.trim(),
            tag_id: tagFilter.value,
            sort: sortSelect.value,
            status: statusFilter.value,
            department: departmentFilter.value,
            page: currentPage,
        });
        apiGet('api/forum/list.php?' + params.toString()).then(function (data) {
            renderRows(data.results);
            renderPagination(data);
        });
    }
    function runFilterChange() {
        currentPage = 1;
        fetchAndRender();
    }
    const runSearch = debounce(runFilterChange, 200);

    searchInput.addEventListener('input', runSearch);
    tagFilter.addEventListener('change', runFilterChange);
    statusFilter.addEventListener('change', runFilterChange);
    departmentFilter.addEventListener('change', runFilterChange);
    sortSelect.addEventListener('change', runFilterChange);

    /* The browser's back/forward cache can restore this exact page (with stale answer/helpful
       counts) when navigating back from a question thread. Re-fetch on every pageshow —
       including plain fresh loads, where this is just a harmless redundant refresh — rather
       than trying to detect a bfcache restore, since that detection proved unreliable. */
    window.addEventListener('pageshow', function () {
        fetchAndRender();
    });
})();
