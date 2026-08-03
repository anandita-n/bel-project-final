/* Small display helpers shared by every page-level script. */

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function cap(s) {
    return s.charAt(0).toUpperCase() + s.slice(1);
}

function initials(name) {
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function fmtDate(iso) {
    if (!iso) return '';
    const d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
}

/* A person's avatar: their uploaded photo if they have one, otherwise initials.
   person needs id, name, role (system_role also accepted), and has_photo. */
function avatarHTML(person, sizeClass) {
    const role = person.role || person.system_role || 'employee';
    const cls = ('avatar ' + (sizeClass || '') + ' avatar-' + role).replace(/\s+/g, ' ').trim();
    if (person.has_photo && person.id) {
        return '<img class="' + cls + ' avatar-img" src="api/employees/photo.php?action=view&id=' + person.id + '" alt="' + escapeHtml(person.name || '') + '">';
    }
    return '<span class="' + cls + '">' + initials(person.name || '') + '</span>';
}
