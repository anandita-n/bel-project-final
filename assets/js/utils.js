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

/* Formats an ISO date ("2026-08-21") or MySQL datetime ("2026-08-21 14:59:00") as
   "Aug 21, 2026" — or "Aug 21, 2026, 2:59 PM" when a time component is present. */
function fmtDate(iso) {
    if (!iso) return '';
    const hasTime = iso.length > 10;
    const d = new Date(iso.replace(' ', 'T') + (hasTime ? '' : 'T00:00:00'));
    const datePart = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    if (!hasTime) return datePart;
    const timePart = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    return datePart + ', ' + timePart;
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
