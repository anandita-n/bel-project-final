/* Employee search picker — queries /api/employees/search.php as you type (debounced).
   Does not hold the employee roster client-side, so it scales to any headcount. */

function empPickerEscape(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function empPickerHTML(name, placeholder) {
    return '' +
        '<div class="emp-picker">' +
        '<input type="text" class="emp-picker-search" placeholder="' + empPickerEscape(placeholder || 'Search name or employee ID…') + '" autocomplete="off">' +
        '<input type="hidden" name="' + name + '" class="emp-picker-hidden">' +
        '<div class="emp-picker-list"></div>' +
        '</div>';
}

/**
 * opts:
 *   roles: array of role names to restrict results to (e.g. ['admin','manager'])
 *   selectedId / selectedLabel: pre-fill the field (label can be async-resolved by caller)
 *   onSelect(employee): called with {id, name, code, role} on pick
 */
function initEmpPicker(root, opts) {
    opts = opts || {};
    var input = root.querySelector('.emp-picker-search');
    var hidden = root.querySelector('.emp-picker-hidden');
    var listEl = root.querySelector('.emp-picker-list');
    var baseUrl = 'api/employees/search.php';

    if (opts.placeholder) input.placeholder = opts.placeholder;
    if (opts.selectedId) {
        hidden.value = opts.selectedId;
        input.value = opts.selectedLabel || '';
    }

    function render(items) {
        listEl.innerHTML = '';
        if (!items.length) {
            listEl.style.display = 'none';
            return;
        }
        items.forEach(function (e) {
            var row = document.createElement('div');
            row.className = 'emp-picker-item';
            row.innerHTML =
                '<span class="emp-picker-item-name">' + empPickerEscape(e.name) + '</span>' +
                '<span class="emp-picker-item-meta">' + empPickerEscape(e.code) + ' · ' + empPickerEscape(e.role) + '</span>';
            row.addEventListener('mousedown', function (ev) {
                ev.preventDefault();
                select(e);
            });
            listEl.appendChild(row);
        });
        listEl.style.display = 'block';
    }

    function select(e) {
        hidden.value = e.id;
        input.value = e.name + ' (' + e.code + ')';
        listEl.style.display = 'none';
        listEl.innerHTML = '';
        if (opts.onSelect) opts.onSelect(e);
    }

    var runSearch = debounce(function (q) {
        var url = baseUrl + '?q=' + encodeURIComponent(q);
        if (opts.roles && opts.roles.length) url += '&roles=' + encodeURIComponent(opts.roles.join(','));
        if (opts.projectId && opts.mode) url += '&project_id=' + encodeURIComponent(opts.projectId) + '&mode=' + encodeURIComponent(opts.mode);
        apiGet(url).then(function (data) {
            if (input.value.trim().toLowerCase() !== q.toLowerCase()) return; // stale response
            render(data.results || []);
        }).catch(function () {
            listEl.style.display = 'none';
        });
    }, 220);

    input.addEventListener('input', function () {
        hidden.value = '';
        var q = input.value.trim();
        if (!q) {
            listEl.style.display = 'none';
            return;
        }
        runSearch(q);
    });

    input.addEventListener('focus', function () {
        if (input.value.trim() && !hidden.value) runSearch(input.value.trim());
    });

    document.addEventListener('click', function (ev) {
        if (!root.contains(ev.target)) listEl.style.display = 'none';
    });
}
