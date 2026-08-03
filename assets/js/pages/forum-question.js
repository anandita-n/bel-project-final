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

    /* ---------- Accept answer (question author or admin) ---------- */

    function bindAcceptBtn(btn) {
        btn.addEventListener('click', function () {
            apiPost('api/forum/accept.php', { question_id: btn.dataset.questionId, answer_id: btn.dataset.answerId })
                .then(function () { window.location.reload(); })
                .catch(function (err) { showError(err.message); });
        });
    }
    document.querySelectorAll('.forum-accept-btn').forEach(bindAcceptBtn);

    /* ---------- Comments (on the question, and on each answer) ---------- */

    function bindCommentDelete(btn) {
        btn.addEventListener('click', function () {
            const commentEl = btn.closest('.forum-comment');
            const container = btn.closest('.forum-comments');
            const type = container.dataset.type === 'question' ? 'question_comment' : 'answer_comment';
            confirmModal('Delete this comment?', function () {
                apiPost('api/forum/delete.php', { type: type, id: commentEl.dataset.commentId })
                    .then(function () { commentEl.remove(); })
                    .catch(function (err) { showError(err.message); });
            }, { okLabel: 'Delete' });
        });
    }

    function commentRowHTML(c, canDelete) {
        return '<div class="forum-comment" data-comment-id="' + c.id + '">' +
            '<span class="forum-comment-body">' + escapeHtml(c.body) + '</span>' +
            ' &mdash; <span class="forum-comment-author">' + escapeHtml(c.author_name) + '</span>' +
            (canDelete ? ' <button type="button" class="forum-comment-delete" title="Delete comment">&times;</button>' : '') +
            '</div>';
    }

    function bindCommentsBlock(container) {
        const type = container.dataset.type;
        const id = container.dataset.id;
        const list = container.querySelector('.forum-comment-list');
        const addLink = container.querySelector('.forum-add-comment-link');
        const form = container.querySelector('.forum-comment-form');
        const input = container.querySelector('.forum-comment-input');
        const submitBtn = container.querySelector('.forum-comment-submit');

        container.querySelectorAll('.forum-comment-delete').forEach(bindCommentDelete);

        addLink.addEventListener('click', function () {
            addLink.style.display = 'none';
            form.style.display = '';
            input.focus();
        });

        function submit() {
            const text = input.value.trim();
            if (!text) return;
            apiPost('api/forum/comment.php', { type: type, id: id, body: text })
                .then(function (data) {
                    const row = document.createElement('div');
                    row.innerHTML = commentRowHTML(data.comment, true);
                    const commentEl = row.firstElementChild;
                    list.appendChild(commentEl);
                    bindCommentDelete(commentEl.querySelector('.forum-comment-delete'));
                    input.value = '';
                    form.style.display = 'none';
                    addLink.style.display = '';
                })
                .catch(function (err) { showError(err.message); });
        }
        submitBtn.addEventListener('click', submit);
        input.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') submit(); });
    }
    document.querySelectorAll('.forum-comments').forEach(bindCommentsBlock);

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
