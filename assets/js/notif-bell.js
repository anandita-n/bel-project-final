/* Site-wide bell dropdown (every page, via layout_top.php) — fetches recent notifications
   on open rather than embedding them in every page's initial render. */

(function () {
    const wrap = document.getElementById('navBellWrap');
    const btn = document.getElementById('navBellBtn');
    const panel = document.getElementById('navBellPanel');
    const body = document.getElementById('navBellPanelBody');
    const badge = document.getElementById('navBellBadge');
    if (!wrap || !btn || !panel || !body) return;

    let items = null;
    let open = false;

    function timeAgo(iso) {
        const diff = (Date.now() - new Date(iso.replace(' ', 'T'))) / 1000;
        if (diff < 60) return 'just now';
        const mins = Math.floor(diff / 60);
        if (mins < 60) return mins + ' minute' + (mins === 1 ? '' : 's') + ' ago';
        const hours = Math.floor(mins / 60);
        if (hours < 24) return hours + ' hour' + (hours === 1 ? '' : 's') + ' ago';
        const days = Math.floor(hours / 24);
        if (days < 30) return days + ' day' + (days === 1 ? '' : 's') + ' ago';
        return fmtDate(iso.slice(0, 10));
    }

    /* The message text is already a full sentence for system notifications ("X commented on…"),
       so showing "from" again above it just duplicates the name — it only earns its own line
       for review comments, where the message is the raw comment body with no name in it. */
    function rowHTML(n) {
        const href = n.project_id ? 'project_detail.php?id=' + n.project_id : 'notifications.php';
        const metaBits = [escapeHtml(n.from)];
        if (n.project_code) metaBits.push(escapeHtml(n.project_code));
        metaBits.push(timeAgo(n.created_at));
        return '' +
            '<a class="nav-bell-item' + (n.is_read ? '' : ' unread') + '" href="' + href + '" data-id="' + n.id + '" data-type="' + n.type + '">' +
            (n.is_read ? '' : '<span class="nav-bell-item-dot"></span>') +
            '<div class="nav-bell-item-body">' +
            '<div class="nav-bell-item-message">' + escapeHtml(n.message) + '</div>' +
            '<div class="nav-bell-item-meta">' + metaBits.join(' &middot; ') + '</div>' +
            '</div>' +
            '</a>';
    }

    /* The server already returns only the latest 5 unread notifications, so no client-side
       filtering is needed — items is always the unread-only list to render. */
    function render() {
        if (!items.length) {
            body.innerHTML = '<div class="nav-bell-empty">No unread notifications.</div>';
            return;
        }
        body.innerHTML = items.map(rowHTML).join('');
    }

    function load() {
        body.innerHTML = '<div class="nav-bell-loading">Loading…</div>';
        apiGet('api/notifications/recent.php').then(function (data) {
            items = data.items;
            setBadge(data.unread_count);
            render();
        }).catch(function () {
            body.innerHTML = '<div class="nav-bell-empty">Could not load notifications.</div>';
        });
    }

    function setBadge(count) {
        badge.style.display = count > 0 ? '' : 'none';
        badge.textContent = count > 9 ? '9+' : count;
    }

    function setOpen(next) {
        open = next;
        panel.style.display = open ? '' : 'none';
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) load();
    }

    /* Marks it read and drops it out of the popup's own list (so a reopened popup no longer
       shows it), then lets the click's default navigation proceed to the linked project.
       keepalive so the request survives the page unloading mid-flight. */
    body.addEventListener('click', function (ev) {
        const row = ev.target.closest('.nav-bell-item');
        if (!row || !row.classList.contains('unread')) return;
        const id = parseInt(row.dataset.id, 10);
        const type = row.dataset.type;
        fetch('api/notifications/mark_read.php', {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, type: type }),
        }).catch(function () {});
        // The popup only ever shows unread items, so once read it drops out entirely
        // rather than just losing its unread styling.
        row.remove();
        items = items.filter(function (n) { return !(n.id === id && n.type === type); });
        if (!items.length) render();
        setBadge(Math.max(0, (parseInt(badge.textContent, 10) || 0) - 1));
    });

    btn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        setOpen(!open);
    });
    panel.addEventListener('click', function (ev) { ev.stopPropagation(); });
    document.addEventListener('click', function () { if (open) setOpen(false); });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && open) setOpen(false); });
})();
