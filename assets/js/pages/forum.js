/* Discussion Forum list: live search + status/tag filters + sort toggle against
   api/forum/list.php. */

(function () {
    const searchInput = document.getElementById('forumSearchInput');
    const statusFilter = document.getElementById('forumStatusFilter');
    const tagFilter = document.getElementById('forumTagFilter');
    const sortSelect = document.getElementById('forumSortSelect');
    let currentPage = 1;

    function forumRowHTML(q) {
        const isSolved = q.status === 'solved';
        const isAnswered = !isSolved && q.answer_count > 0;
        const statClass = isSolved ? ' forum-stat-solved' : (isAnswered ? ' forum-stat-answered' : '');
        const tagsHtml = q.tags.length
            ? '<div class="forum-tag-list">' + q.tags.map(function (t) { return '<span class="tag">' + escapeHtml(t.name) + '</span>'; }).join('') + '</div>'
            : '';
        const dept = q.author_department ? ' &middot; ' + escapeHtml(q.author_department) : '';
        return '' +
            '<div class="forum-row">' +
            '<div class="forum-stats">' +
            '<div class="forum-stat' + statClass + '">' +
            '<span class="forum-stat-num">' + q.answer_count + '</span><span class="forum-stat-lbl">answers</span></div>' +
            '</div>' +
            '<div class="forum-row-content">' +
            '<div class="forum-row-title-line">' +
            '<a class="forum-row-title" href="forum_question.php?id=' + q.id + '">' + escapeHtml(q.title) + '</a>' +
            '</div>' +
            '<div class="forum-row-byline">asked by ' + avatarHTML({ id: q.author_id, name: q.author_name, role: q.author_role, has_photo: q.author_has_photo }, 'avatar-sm') + escapeHtml(q.author_name) + dept + ' &middot; ' + fmtDate(q.created_at.slice(0, 10)) + '</div>' +
            tagsHtml +
            '</div>' +
            '</div>';
    }

    function renderRows(rows) {
        const list = document.getElementById('forumList');
        list.innerHTML = rows.map(forumRowHTML).join('');
        list.style.display = rows.length ? '' : 'none';
        document.getElementById('forumEmpty').style.display = rows.length ? 'none' : '';
    }

    /** Compact page-number list with an ellipsis for gaps, e.g. 1 … 4 5 6 … 13. */
    function pageNumbersAround(current, total) {
        const nums = [];
        for (let p = 1; p <= total; p++) {
            if (p === 1 || p === total || (p >= current - 1 && p <= current + 1)) {
                nums.push(p);
            } else if (nums[nums.length - 1] !== '…') {
                nums.push('…');
            }
        }
        return nums;
    }

    function renderPagination(data) {
        const el = document.getElementById('forumPagination');
        if (data.total === 0) {
            el.style.display = 'none';
            el.innerHTML = '';
            return;
        }
        el.style.display = '';

        const startItem = (data.page - 1) * data.per_page + 1;
        const endItem = Math.min(data.page * data.per_page, data.total);

        function pageBtn(page, label, opts) {
            opts = opts || {};
            const cls = 'forum-page-btn' + (opts.active ? ' active' : '');
            return '<button type="button" class="' + cls + '" data-page="' + page + '"' + (opts.disabled ? ' disabled' : '') + '>' + label + '</button>';
        }

        const pagesHtml = pageNumbersAround(data.page, data.total_pages).map(function (p) {
            return p === '…' ? '<span class="forum-page-ellipsis">…</span>' : pageBtn(p, p, { active: p === data.page });
        }).join('');

        el.innerHTML = '' +
            '<div class="forum-pagination-summary">Showing ' + startItem + ' to ' + endItem + ' of ' + data.total + ' question' + (data.total === 1 ? '' : 's') + '</div>' +
            (data.total_pages > 1 ? (
                '<div class="forum-pagination-controls">' +
                pageBtn(data.page - 1, '&laquo;', { disabled: data.page <= 1 }) +
                pagesHtml +
                pageBtn(data.page + 1, '&raquo;', { disabled: data.page >= data.total_pages }) +
                '</div>'
            ) : '');

        el.querySelectorAll('.forum-page-btn:not([disabled])').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const p = parseInt(btn.dataset.page, 10);
                if (p >= 1 && p <= data.total_pages && p !== currentPage) {
                    currentPage = p;
                    fetchAndRender();
                }
            });
        });
    }

    function fetchAndRender() {
        const params = new URLSearchParams({
            q: searchInput.value.trim(),
            tag_id: tagFilter.value,
            sort: sortSelect.value,
            status: statusFilter.value,
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
    sortSelect.addEventListener('change', runFilterChange);

    /* The browser's back/forward cache can restore this exact page (with stale answer/helpful
       counts) when navigating back from a question thread. Re-fetch on every pageshow —
       including plain fresh loads, where this is just a harmless redundant refresh — rather
       than trying to detect a bfcache restore, since that detection proved unreliable. */
    window.addEventListener('pageshow', function () {
        fetchAndRender();
    });
})();
