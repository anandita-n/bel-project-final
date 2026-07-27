/* Generic client-side tab controller. Pass the root element containing
   .tab-btn[data-tab] triggers and sibling .tab-panel[data-panel] targets.
   Reads/writes location.hash so tabs are deep-linkable and survive reload. */

function initTabs(rootEl, opts) {
    opts = opts || {};
    const btns = Array.prototype.slice.call(rootEl.querySelectorAll('.tab-btn'));
    const panels = Array.prototype.slice.call(rootEl.querySelectorAll(':scope > .tab-panel'));
    const validTabs = btns.map(function (b) { return b.dataset.tab; });

    function activate(tab, skipHash) {
        if (validTabs.indexOf(tab) === -1) tab = opts.defaultTab || validTabs[0];
        btns.forEach(function (b) { b.classList.toggle('active', b.dataset.tab === tab); });
        panels.forEach(function (p) { p.classList.toggle('active', p.dataset.panel === tab); });
        if (!skipHash && opts.useHash !== false) {
            history.replaceState(null, '', '#' + tab);
        }
        if (opts.onChange) opts.onChange(tab);
    }

    btns.forEach(function (b) {
        b.addEventListener('click', function () { activate(b.dataset.tab); });
    });

    const initial = opts.useHash === false ? (opts.defaultTab || validTabs[0]) : (location.hash || '').replace('#', '');
    activate(initial, true);

    return { activate: activate };
}
