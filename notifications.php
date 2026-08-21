<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\MemberReviewRepository;
use App\Repositories\NotificationRepository;

$page_title = 'Notifications';
$u = current_user();

$reviewRepo = new MemberReviewRepository();
$reviews = $reviewRepo->forUser((int)$u['id'], 100);

$notifRepo = new NotificationRepository();
// Forum-comment notifications are intentionally excluded — this page is for project/task activity.
$notifs = array_filter($notifRepo->forUser((int)$u['id'], 100), fn($n) => $n['type'] !== 'forum_comment');

/** Buckets the underlying notification "type" into the four sidebar categories. */
function notif_category(string $type): string {
    return match ($type) {
        'mentioned' => 'mentions',
        'task_assigned', 'due_date_changed', 'task_completed', 'attachment_uploaded', 'comment_added' => 'tasks',
        default => 'system',
    };
}

/** Messages are freeform sentences built at notification-creation time elsewhere in the app
 *  (e.g. `X changed the due date on "Hello"`) — rather than restructure every call site to pass
 *  actor/action/subject separately, split on the first quoted segment so the UI can still show
 *  "action text" on one line and "subject" on the next, matching the two-line row design. */
function split_notif_message(string $msg): array {
    if (preg_match('/^(.*?)"([^"]+)"(.*)$/', $msg, $m)) {
        $title = trim($m[1] . $m[3]);
        return ['title' => $title !== '' ? $title : $msg, 'subject' => $m[2]];
    }
    return ['title' => $msg, 'subject' => null];
}

/** The title text either already opens with the actor's name (system notifications, whose
 *  message is a full pre-built sentence) or doesn't (raw review-comment text) — bold the name
 *  in place for the former rather than prefixing it again, which would show it twice. */
function notif_title_html(string $title, string $from): string {
    if (stripos($title, $from) === 0) {
        $rest = substr($title, strlen($from));
        return '<strong>' . htmlspecialchars($from) . '</strong>' . htmlspecialchars($rest);
    }
    return '<strong>' . htmlspecialchars($from) . '</strong> ' . htmlspecialchars($title);
}

$notifications = array_merge(
    array_map(fn($r) => [
        'id' => (int)$r['id'],
        'type' => 'review',
        'category' => 'system',
        'from' => $r['author_name'],
        'from_avatar' => ['id' => $r['author_id'], 'name' => $r['author_name'], 'role' => $r['author_role'], 'photo_filename' => $r['author_photo_filename']],
        'project_id' => $r['project_id'],
        'project_code' => $r['project_code'],
        'message' => $r['comment'],
        'created_at' => $r['created_at'],
        'is_read' => (bool)$r['is_read'],
    ], $reviews),
    array_map(fn($n) => [
        'id' => (int)$n['id'],
        'type' => 'notification',
        'category' => notif_category($n['type']),
        'from' => $n['actor_name'] ?? 'System',
        'from_avatar' => ['id' => $n['actor_id'], 'name' => $n['actor_name'] ?? 'System', 'role' => $n['actor_role'], 'photo_filename' => $n['actor_photo_filename']],
        'project_id' => $n['project_id'],
        'project_code' => $n['project_code'],
        'message' => $n['message'],
        'created_at' => $n['created_at'],
        'is_read' => (bool)$n['is_read'],
    ], $notifs)
);
usort($notifications, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

$count_all = count($notifications);
$count_unread = count(array_filter($notifications, fn($n) => !$n['is_read']));

// Grouped by calendar day so the page reads like an activity feed ("Today", "Yesterday", …)
// instead of one long flat list.
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$groups = [];
foreach ($notifications as $n) {
    $day = substr($n['created_at'], 0, 10);
    $label = $day === $today ? 'Today' : ($day === $yesterday ? 'Yesterday' : date('F j, Y', strtotime($day)));
    $groups[$label][] = $n;
}

require 'includes/layout_top.php';
?>

<script src="assets/js/modal.js?v=<?= filemtime(__DIR__ . '/assets/js/modal.js') ?>"></script>

<div class="notif-page-layout">
    <div class="notif-sidebar">
        <button type="button" class="notif-sidebar-link active" data-filter="all">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
            <span class="notif-sidebar-label">All</span>
        </button>
        <button type="button" class="notif-sidebar-link" data-filter="unread">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3" fill="currentColor" stroke="none"/></svg>
            <span class="notif-sidebar-label">Unread</span>
        </button>
    </div>

    <div class="notif-page">
        <div class="notif-page-head">
            <h3 class="notif-page-head-title" id="notifHeadTitle"><?= htmlspecialchars(array_key_first($groups) ?? date('F j, Y')) ?></h3>
            <div class="notif-page-actions">
                <label class="notif-select-all-label">
                    <input type="checkbox" id="notifSelectAll"> Select all
                </label>
                <button type="button" class="link-btn link-btn-danger" id="notifBulkDelete" style="display:none;">Delete</button>
                <select class="filter-select" id="notifDateRange">
                    <option value="all">All time</option>
                    <option value="1">Past day</option>
                    <option value="7">Past week</option>
                    <option value="30">Past month</option>
                </select>
                <select class="filter-select" id="notifSort">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                </select>
                <button type="button" class="link-btn" id="notifMarkAllRead">Mark all as read</button>
            </div>
        </div>

        <?php if (empty($notifications)): ?>
        <div class="empty-state" id="notifEmpty">No notifications yet.</div>
        <?php else: ?>
        <div id="notifGroups">
        <?php $isFirstGroup = true; foreach ($groups as $label => $rows): ?>
        <div class="notif-group">
            <?php if (!$isFirstGroup): ?><h3 class="notif-group-heading"><?= htmlspecialchars($label) ?></h3><?php endif; $isFirstGroup = false; ?>
            <?php foreach ($rows as $n): ?>
            <?php $parts = split_notif_message($n['message']); ?>
            <div class="notif-page-row<?= $n['is_read'] ? '' : ' unread' ?>"
               data-id="<?= $n['id'] ?>" data-type="<?= htmlspecialchars($n['type']) ?>" data-created="<?= htmlspecialchars($n['created_at']) ?>"
               data-group-label="<?= htmlspecialchars($label) ?>" data-category="<?= htmlspecialchars($n['category']) ?>">
                <label class="notif-row-check">
                    <input type="checkbox" class="notif-select-checkbox">
                </label>
                <a class="notif-page-row-link" href="<?= $n['project_id'] ? 'project_detail.php?id=' . (int)$n['project_id'] : '#' ?>">
                    <span class="notif-icon-badge"><?= render_avatar($n['from_avatar']) ?></span>
                    <div class="notif-page-row-body">
                        <div class="notif-page-row-message">
                            <?= notif_title_html($parts['title'], $n['from']) ?>
                            <?php if ($parts['subject']): ?><br>&quot;<?= htmlspecialchars($parts['subject']) ?>&quot;<?php endif; ?>
                        </div>
                        <div class="notif-page-row-meta"><?= htmlspecialchars(time_ago($n['created_at'])) ?></div>
                    </div>
                    <?php if (!$n['is_read']): ?><span class="nav-bell-item-dot"></span><?php endif; ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const list = document.querySelector('.notif-page');
    const groupsWrap = document.getElementById('notifGroups');
    const headTitle = document.getElementById('notifHeadTitle');
    const markAllBtn = document.getElementById('notifMarkAllRead');
    const sortSelect = document.getElementById('notifSort');
    const dateRangeSelect = document.getElementById('notifDateRange');
    const selectAllBox = document.getElementById('notifSelectAll');
    const bulkDeleteBtn = document.getElementById('notifBulkDelete');
    const sidebarLinks = Array.prototype.slice.call(document.querySelectorAll('.notif-sidebar-link'));
    let activeFilter = 'all';

    function withinDateRange(row) {
        if (dateRangeSelect.value === 'all') return true;
        const days = parseInt(dateRangeSelect.value, 10);
        const created = new Date(row.dataset.created.replace(' ', 'T'));
        return (Date.now() - created.getTime()) <= days * 24 * 60 * 60 * 1000;
    }

    function applyFilter() {
        document.querySelectorAll('.notif-page-row').forEach(function (row) {
            const show = (activeFilter === 'all'
                || (activeFilter === 'unread' && row.classList.contains('unread'))
                || row.dataset.category === activeFilter) && withinDateRange(row);
            row.style.display = show ? '' : 'none';
        });
        document.querySelectorAll('.notif-group').forEach(function (group) {
            const anyVisible = Array.prototype.slice.call(group.querySelectorAll('.notif-page-row')).some(function (r) { return r.style.display !== 'none'; });
            group.style.display = anyVisible ? '' : 'none';
        });
        updateBulkUI();
    }

    sidebarLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            sidebarLinks.forEach(function (l) { l.classList.remove('active'); });
            link.classList.add('active');
            activeFilter = link.dataset.filter;
            applyFilter();
        });
    });

    dateRangeSelect.addEventListener('change', applyFilter);

    if (list) {
        list.addEventListener('click', function (ev) {
            if (ev.target.closest('.notif-row-check')) return;
            const row = ev.target.closest('.notif-page-row');
            if (!row || !row.classList.contains('unread')) return;
            fetch('api/notifications/mark_read.php', {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(row.dataset.id, 10), type: row.dataset.type }),
            }).catch(function () {});
            row.classList.remove('unread');
            const dot = row.querySelector('.nav-bell-item-dot');
            if (dot) dot.remove();
        });
    }

    /* Bulk select + delete. Selection is keyed by "type:id" since ids aren't unique across
       the two underlying tables (reviews vs. notifications). */
    const selected = new Set();

    function rowKey(row) { return row.dataset.type + ':' + row.dataset.id; }

    function updateBulkUI() {
        bulkDeleteBtn.style.display = selected.size ? '' : 'none';
        bulkDeleteBtn.textContent = 'Delete (' + selected.size + ')';
        const visibleRows = Array.prototype.slice.call(document.querySelectorAll('.notif-page-row')).filter(function (r) { return r.style.display !== 'none'; });
        const visibleChecked = visibleRows.filter(function (r) { return selected.has(rowKey(r)); });
        selectAllBox.checked = visibleRows.length > 0 && visibleChecked.length === visibleRows.length;
        selectAllBox.indeterminate = visibleChecked.length > 0 && visibleChecked.length < visibleRows.length;
    }

    if (list) {
        list.addEventListener('change', function (ev) {
            const checkbox = ev.target.closest('.notif-select-checkbox');
            if (!checkbox) return;
            const row = checkbox.closest('.notif-page-row');
            if (checkbox.checked) selected.add(rowKey(row)); else selected.delete(rowKey(row));
            updateBulkUI();
        });
    }

    selectAllBox.addEventListener('change', function () {
        const visibleRows = Array.prototype.slice.call(document.querySelectorAll('.notif-page-row')).filter(function (r) { return r.style.display !== 'none'; });
        visibleRows.forEach(function (row) {
            const checkbox = row.querySelector('.notif-select-checkbox');
            checkbox.checked = selectAllBox.checked;
            if (selectAllBox.checked) selected.add(rowKey(row)); else selected.delete(rowKey(row));
        });
        updateBulkUI();
    });

    bulkDeleteBtn.addEventListener('click', function () {
        if (!selected.size) return;
        const count = selected.size;
        confirmModal('Delete ' + count + ' selected notification' + (count === 1 ? '' : 's') + '? This cannot be undone.', function () {
            const items = Array.from(selected).map(function (key) {
                const idx = key.indexOf(':');
                return { type: key.slice(0, idx), id: parseInt(key.slice(idx + 1), 10) };
            });
            apiPost('api/notifications/delete.php', { items: items }).then(function () {
                items.forEach(function (it) {
                    const row = document.querySelector('.notif-page-row[data-type="' + it.type + '"][data-id="' + it.id + '"]');
                    if (row) row.remove();
                });
                selected.clear();
                document.querySelectorAll('.notif-group').forEach(function (group) {
                    if (!group.querySelector('.notif-page-row')) group.remove();
                });
                if (!document.querySelector('.notif-page-row')) {
                    groupsWrap.innerHTML = '';
                    const empty = document.createElement('div');
                    empty.className = 'empty-state';
                    empty.id = 'notifEmpty';
                    empty.textContent = 'No notifications yet.';
                    list.appendChild(empty);
                }
                updateBulkUI();
            }).catch(function (err) { alert(err.message); });
        }, { okLabel: 'Delete' });
    });

    markAllBtn.addEventListener('click', function () {
        fetch('api/notifications/mark_all_read.php', { method: 'POST', credentials: 'same-origin' })
            .then(function () {
                document.querySelectorAll('.notif-page-row.unread').forEach(function (row) {
                    row.classList.remove('unread');
                    const dot = row.querySelector('.nav-bell-item-dot');
                    if (dot) dot.remove();
                });
            });
    });

    /* Re-groups by the day label each row already carries (assigned server-side, so it stays
       in sync with the server's "Today"/"Yesterday" cutoffs) rather than recomputing dates in JS. */
    if (groupsWrap) {
        sortSelect.addEventListener('change', function () {
            const rows = Array.prototype.slice.call(groupsWrap.querySelectorAll('.notif-page-row'));
            const dir = sortSelect.value === 'oldest' ? 1 : -1;
            rows.sort(function (a, b) { return dir * a.dataset.created.localeCompare(b.dataset.created); });

            groupsWrap.innerHTML = '';
            let currentLabel = null;
            let currentGroup = null;
            let first = true;
            rows.forEach(function (row) {
                if (row.dataset.groupLabel !== currentLabel) {
                    currentLabel = row.dataset.groupLabel;
                    currentGroup = document.createElement('div');
                    currentGroup.className = 'notif-group';
                    if (!first) {
                        const heading = document.createElement('h3');
                        heading.className = 'notif-group-heading';
                        heading.textContent = currentLabel;
                        currentGroup.appendChild(heading);
                    } else if (headTitle) {
                        headTitle.textContent = currentLabel;
                    }
                    first = false;
                    groupsWrap.appendChild(currentGroup);
                }
                currentGroup.appendChild(row);
            });
            applyFilter();
        });
    }
})();
</script>

<?php require 'includes/layout_bottom.php'; ?>
