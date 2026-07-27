/* Right-side slide-in drawer, structurally parallel to modal.js.
   openDrawer(title, bodyHtml) -> asideEl (the .drawer-panel, so callers can wire listeners against it).
   closeDrawer(). Only one of {drawer, modal} may be open at a time. */

function closeDrawer() {
    const overlay = document.getElementById('activeDrawerOverlay');
    const panel = document.getElementById('activeDrawerPanel');
    if (panel) panel.classList.remove('open');
    if (overlay) {
        setTimeout(function () { overlay.remove(); }, 180);
    }
    document.removeEventListener('keydown', drawerEscHandler);
}

function drawerEscHandler(ev) {
    if (ev.key === 'Escape') closeDrawer();
}

function openDrawer(title, bodyHtml) {
    closeDrawer();
    if (typeof closeModal === 'function') closeModal();

    const overlay = document.createElement('div');
    overlay.className = 'drawer-overlay';
    overlay.id = 'activeDrawerOverlay';

    const panel = document.createElement('aside');
    panel.className = 'drawer-panel';
    panel.id = 'activeDrawerPanel';
    panel.innerHTML =
        '<div class="drawer-head"><h3>' + title + '</h3><button type="button" class="drawer-close" aria-label="Close">&times;</button></div>' +
        '<div class="drawer-body">' + bodyHtml + '</div>';

    overlay.appendChild(panel);
    document.body.appendChild(overlay);

    overlay.addEventListener('mousedown', function (ev) {
        if (ev.target === overlay) closeDrawer();
    });
    panel.querySelector('.drawer-close').addEventListener('click', closeDrawer);
    document.addEventListener('keydown', drawerEscHandler);

    requestAnimationFrame(function () {
        requestAnimationFrame(function () { panel.classList.add('open'); });
    });
    setTimeout(function () { panel.classList.add('open'); }, 30);

    return panel;
}
