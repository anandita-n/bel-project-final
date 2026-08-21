/* Forum thread page: Helpful toggle, post answer, accept answer (author or admin), delete,
   comments, and answer attachments — all AJAX, patching the DOM in place.
   Expects window.PAGE_CONFIG = { questionId, isAuthor, isAdmin, currentUserId }. */

(function () {
    const cfg = window.PAGE_CONFIG;
    const errorBox = document.getElementById('pageError');

    function showError(message) {
        errorBox.textContent = message;
        errorBox.style.display = 'block';
        setTimeout(() => { errorBox.style.display = 'none'; }, 4000);
    }

    /* ---------- Helpful toggle ---------- */

    function bindHelpfulBtn(btn) {
        btn.addEventListener('click', function () {
            apiPost('api/forum/helpful.php', { answer_id: btn.dataset.answerId })
                .then(function (data) {
                    btn.classList.toggle('active', data.helpful);
                    btn.querySelector('.forum-helpful-count').textContent = '(' + data.count + ')';
                })
                .catch(function (err) { showError(err.message); });
        });
    }
    document.querySelectorAll('.forum-helpful-btn').forEach(bindHelpfulBtn);

    /* ---------- Delete (question / answer / comments, shared handler) ---------- */

    function bindDeleteLink(link) {
        link.addEventListener('click', function () {
            const type = link.dataset.type;
            const id = link.dataset.id;
            confirmModal('Delete this ' + type + '? This cannot be undone.', function () {
                apiPost('api/forum/delete.php', { type: type, id: id })
                    .then(function () {
                        if (type === 'question') {
                            window.location.href = 'forum.php';
                        } else {
                            link.closest('.forum-answer-row').remove();
                        }
                    })
                    .catch(function (err) { showError(err.message); });
            }, { okLabel: 'Delete' });
        });
    }
    document.querySelectorAll('.forum-delete-link').forEach(bindDeleteLink);

    /* ---------- Accept answer (question author) — one toggle button: click to accept,
       click again to undo, right next to Helpful instead of a separate button below the card. */

    function bindAcceptToggle(btn) {
        btn.addEventListener('click', function () {
            const accepted = btn.dataset.accepted === '1';
            const body = accepted
                ? { action: 'unaccept', question_id: btn.dataset.questionId }
                : { question_id: btn.dataset.questionId, answer_id: btn.dataset.answerId };
            apiPost('api/forum/accept.php', body)
                .then(function () { window.location.reload(); })
                .catch(function (err) { showError(err.message); });
        });
    }
    document.querySelectorAll('.forum-accept-toggle').forEach(bindAcceptToggle);

    /* ---------- Edit question (author only) ---------- */

    const editBtn = document.getElementById('forumEditQuestionBtn');
    if (editBtn) {
        editBtn.addEventListener('click', function () {
            const overlay = openModal('Edit Question', '' +
                '<div id="forumEditError" class="error-msg" style="display:none;"></div>' +
                '<form id="forumEditForm">' +
                '<div class="field"><label>Title</label><input type="text" id="forumEditTitle" required></div>' +
                '<div class="field"><label>Body</label><textarea id="forumEditBody" rows="6" required></textarea></div>' +
                '<div class="field"><label>Tags</label><input type="text" id="forumEditTags" placeholder="comma, separated, tags"></div>' +
                '<button type="submit" class="pill-btn">Save Changes</button>' +
                '</form>');
            overlay.querySelector('#forumEditTitle').value = cfg.questionTitle;
            overlay.querySelector('#forumEditBody').value = cfg.questionBody;
            overlay.querySelector('#forumEditTags').value = cfg.questionTags;

            const modalErrorBox = overlay.querySelector('#forumEditError');
            function showModalError(message) {
                modalErrorBox.textContent = message;
                modalErrorBox.style.display = 'block';
            }

            overlay.querySelector('#forumEditForm').addEventListener('submit', function (ev) {
                ev.preventDefault();
                const title = overlay.querySelector('#forumEditTitle').value.trim();
                const body = overlay.querySelector('#forumEditBody').value.trim();
                if (!title || !body) { showModalError('Title and body are required.'); return; }
                apiPost('api/forum/update.php', {
                    question_id: cfg.questionId,
                    title: title,
                    body: body,
                    tags: overlay.querySelector('#forumEditTags').value.trim(),
                }).then(function () {
                    window.location.reload();
                }).catch(function (err) { showModalError(err.message); });
            });
        });
    }

    /* ---------- Sort answers ---------- */

    const sortSelect = document.getElementById('forumAnswerSort');
    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            const url = new URL(window.location.href);
            url.searchParams.set('sort', sortSelect.value);
            window.location.href = url.toString();
        });
    }

    /* ---------- Replies (on each answer) ---------- */

    function bindReplyDelete(btn) {
        btn.addEventListener('click', function () {
            const replyEl = btn.closest('.forum-reply');
            confirmModal('Delete this reply?', function () {
                apiPost('api/forum/delete.php', { type: 'answer_comment', id: replyEl.dataset.commentId })
                    .then(function () { replyEl.remove(); })
                    .catch(function (err) { showError(err.message); });
            }, { okLabel: 'Delete' });
        });
    }

    function formatReplyTime(iso) {
        const d = new Date(iso.replace(' ', 'T'));
        const datePart = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        const timePart = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        return datePart + ' at ' + timePart;
    }

    function replyRowHTML(c, canDelete) {
        const initial = escapeHtml(c.author_name.charAt(0).toUpperCase());
        return '<div class="forum-reply" data-comment-id="' + c.id + '">' +
            '<span class="forum-reply-avatar">' + initial + '</span>' +
            '<div class="forum-reply-content">' +
            '<div class="forum-reply-bubble">' +
            '<span class="forum-reply-author">' + escapeHtml(c.author_name) + '</span>' +
            '<span class="forum-reply-body">' + escapeHtml(c.body) + '</span>' +
            '</div>' +
            '<span class="forum-reply-time">' + escapeHtml(formatReplyTime(c.created_at)) + '</span>' +
            '</div>' +
            (canDelete ? '<button type="button" class="forum-reply-delete" title="Delete reply">&times;</button>' : '') +
            '</div>';
    }

    function bindRepliesBlock(container) {
        const answerId = container.dataset.answerId;
        const list = container.querySelector('.forum-reply-list');
        const form = container.querySelector('.forum-reply-form');
        const input = container.querySelector('.forum-reply-input');
        const submitBtn = container.querySelector('.forum-reply-submit');
        const cancelBtn = container.querySelector('.forum-reply-cancel');

        container.querySelectorAll('.forum-reply-delete').forEach(bindReplyDelete);

        function closeForm() {
            form.style.display = 'none';
            input.value = '';
        }

        function submit() {
            const text = input.value.trim();
            if (!text) return;
            apiPost('api/forum/comment.php', { type: 'answer', id: answerId, body: text })
                .then(function (data) {
                    const row = document.createElement('div');
                    row.innerHTML = replyRowHTML(data.comment, true);
                    const replyEl = row.firstElementChild;
                    list.appendChild(replyEl);
                    bindReplyDelete(replyEl.querySelector('.forum-reply-delete'));
                    closeForm();
                })
                .catch(function (err) { showError(err.message); });
        }
        submitBtn.addEventListener('click', submit);
        cancelBtn.addEventListener('click', closeForm);
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') submit();
            if (ev.key === 'Escape') closeForm();
        });

        // Clicking anywhere outside this answer's reply area closes an open composer
        // without submitting — same idea as dismissing a popover.
        document.addEventListener('mousedown', function (ev) {
            if (form.style.display !== 'none' && !container.contains(ev.target)) {
                closeForm();
            }
        });

        const replyLink = document.querySelector('.forum-reply-link[data-answer-id="' + answerId + '"]');
        if (replyLink) {
            replyLink.addEventListener('click', function () {
                if (form.style.display === 'none') {
                    form.style.display = 'flex';
                    input.focus();
                } else {
                    closeForm();
                }
            });
        }
    }
    document.querySelectorAll('.forum-replies').forEach(bindRepliesBlock);

    /* ---------- Answer attachments ---------- */

    function fileSizeLabel(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
        return (bytes / 1024 / 1024).toFixed(1) + ' MB';
    }

    function attachmentRowHTML(a) {
        const downloadUrl = 'api/forum/attachments.php?action=download&id=' + a.id;
        return '<div class="attachment-row" data-attachment-id="' + a.id + '">' +
            '<svg class="attachment-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
            '<div class="attachment-info">' +
            '<a href="' + downloadUrl + '" target="_blank" class="attachment-name">' + escapeHtml(a.original_filename) + '</a>' +
            '<div class="attachment-meta">' + escapeHtml(a.uploader_name) + ' &middot; ' + fileSizeLabel(a.size_bytes) + '</div>' +
            '</div>' +
            '<button type="button" class="attachment-remove forum-attachment-remove" title="Remove">&times;</button>' +
            '</div>';
    }

    function bindAttachmentRemove(btn) {
        btn.addEventListener('click', function () {
            const row = btn.closest('.attachment-row');
            confirmModal('Remove this attachment?', function () {
                apiPost('api/forum/attachments.php', { action: 'delete', id: row.dataset.attachmentId })
                    .then(function () { row.remove(); })
                    .catch(function (err) { showError(err.message); });
            }, { okLabel: 'Remove' });
        });
    }

    function bindAttachmentsBlock(container) {
        container.querySelectorAll('.forum-attachment-remove').forEach(bindAttachmentRemove);
        const fileInput = container.querySelector('.forum-attach-input');
        if (!fileInput) return;
        fileInput.addEventListener('change', function () {
            const file = fileInput.files[0];
            if (!file) return;
            apiUpload('api/forum/attachments.php', { action: 'upload', answer_id: container.dataset.answerId }, file)
                .then(function (data) {
                    const row = document.createElement('div');
                    row.innerHTML = attachmentRowHTML(data.attachment);
                    const attachEl = row.firstElementChild;
                    container.insertBefore(attachEl, container.querySelector('.forum-attach-link'));
                    bindAttachmentRemove(attachEl.querySelector('.forum-attachment-remove'));
                    fileInput.value = '';
                })
                .catch(function (err) { showError(err.message); fileInput.value = ''; });
        });
    }
    document.querySelectorAll('.forum-attachments').forEach(bindAttachmentsBlock);

    /* ---------- Post a new answer ---------- */

    const postBtn = document.getElementById('forumPostAnswerBtn');
    const bodyInput = document.getElementById('forumAnswerBody');
    postBtn.addEventListener('click', function () {
        const body = bodyInput.value.trim();
        if (!body) { showError('Please write an answer before posting.'); return; }
        apiPost('api/forum/answer.php', { question_id: cfg.questionId, body: body })
            .then(function () {
                window.location.reload();
            })
            .catch(function (err) { showError(err.message); });
    });
})();
