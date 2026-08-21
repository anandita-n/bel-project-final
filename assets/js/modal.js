/* Minimal modal: openModal(title, bodyHtml) returns the overlay element so callers can
   wire up their own form handlers against it. Only one modal can be open at a time. */

function closeModal() {
    const el = document.getElementById('activeModalOverlay');
    if (el) el.remove();
    document.removeEventListener('keydown', modalEscHandler);
}

function modalEscHandler(ev) {
    if (ev.key === 'Escape') closeModal();
}

function openModal(title, bodyHtml) {
    closeModal();
    if (typeof closeDrawer === 'function') closeDrawer();

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.id = 'activeModalOverlay';
    overlay.innerHTML =
        '<div class="modal-box">' +
        '<div class="modal-head"><h3>' + title + '</h3><button type="button" class="modal-close" aria-label="Close">&times;</button></div>' +
        '<div class="modal-body">' + bodyHtml + '</div>' +
        '</div>';
    document.body.appendChild(overlay);

    overlay.addEventListener('mousedown', function (ev) {
        if (ev.target === overlay) closeModal();
    });
    overlay.querySelector('.modal-close').addEventListener('click', closeModal);
    document.addEventListener('keydown', modalEscHandler);

    return overlay;
}

/* In-app replacement for window.confirm() — consistent with the rest of the UI
   and not subject to browsers suppressing/blocking native dialogs. */
function confirmModal(message, onConfirm, opts) {
    opts = opts || {};
    const okClass = 'okClass' in opts ? opts.okClass : 'pill-btn-danger';
    const overlay = openModal(opts.title || 'Confirm', '' +
        '<p style="margin:0 0 18px;font-size:13px;color:var(--text);">' + message + '</p>' +
        '<div style="text-align:right;">' +
        '<button type="button" class="pill-btn pill-btn-secondary" id="confirmModalCancel">Cancel</button> ' +
        '<button type="button" class="pill-btn ' + okClass + '" id="confirmModalOk">' + (opts.okLabel || 'Confirm') + '</button>' +
        '</div>');
    overlay.querySelector('#confirmModalCancel').addEventListener('click', closeModal);
    overlay.querySelector('#confirmModalOk').addEventListener('click', function () {
        closeModal();
        onConfirm();
    });
}
