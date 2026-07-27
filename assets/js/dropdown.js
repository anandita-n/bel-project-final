/* Generic overflow (kebab) menu. Call initOverflowMenu(triggerEl, items) once per
   trigger button; triggerEl's parent must have position:relative (task/member cards
   and list rows already do via .task-kebab's containing block).
   items: [{label, onClick, danger?}] */

function closeOverflowMenu() {
    const el = document.querySelector('.overflow-menu');
    if (el) el.remove();
    document.removeEventListener('click', overflowOutsideHandler, true);
    document.removeEventListener('keydown', overflowEscHandler);
}

function overflowOutsideHandler(ev) {
    const menu = document.querySelector('.overflow-menu');
    if (menu && !menu.contains(ev.target)) closeOverflowMenu();
}

function overflowEscHandler(ev) {
    if (ev.key === 'Escape') closeOverflowMenu();
}

function initOverflowMenu(triggerEl, items) {
    triggerEl.addEventListener('click', function (ev) {
        ev.stopPropagation();
        const already = triggerEl.parentElement.querySelector('.overflow-menu');
        closeOverflowMenu();
        if (already) return;

        const menu = document.createElement('div');
        menu.className = 'overflow-menu';
        items.forEach(function (item) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'overflow-menu-item' + (item.danger ? ' danger' : '');
            btn.textContent = item.label;
            btn.addEventListener('click', function (ev2) {
                ev2.stopPropagation();
                closeOverflowMenu();
                item.onClick();
            });
            menu.appendChild(btn);
        });
        triggerEl.parentElement.appendChild(menu);

        setTimeout(function () {
            document.addEventListener('click', overflowOutsideHandler, true);
            document.addEventListener('keydown', overflowEscHandler);
        }, 0);
    });
}
