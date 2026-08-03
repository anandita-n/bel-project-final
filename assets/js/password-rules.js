/* Shared password complexity rules — mirrors password_rule_results() in includes/helpers.php.
   Used for the validation checklist on any form that sets a password (create, reset, change). */

function passwordRuleResults(pw) {
    pw = pw || '';
    return {
        length: pw.length >= 8 && pw.length <= 32,
        upper: /[A-Z]/.test(pw),
        lower: /[a-z]/.test(pw),
        number: /[0-9]/.test(pw),
        special: /[^A-Za-z0-9]/.test(pw),
        no_spaces: pw.length > 0 && !/\s/.test(pw),
    };
}

function isPasswordValid(pw) {
    const r = passwordRuleResults(pw);
    return r.length && r.upper && r.lower && r.number && r.special && r.no_spaces;
}

const PASSWORD_RULE_LABELS = [
    ['length', 'Minimum 8 characters (max 32)'],
    ['upper', 'One uppercase letter'],
    ['lower', 'One lowercase letter'],
    ['number', 'One number'],
    ['special', 'One special character'],
    ['no_spaces', 'No spaces'],
];

function passwordChecklistHTML() {
    return '<ul class="password-checklist">' +
        PASSWORD_RULE_LABELS.map(function (r) {
            return '<li data-rule="' + r[0] + '">' + r[1] + '</li>';
        }).join('') +
        '</ul>';
}

/* Wires a password <input> to a checklist rendered by passwordChecklistHTML() inside checklistEl.
   The checklist stays hidden until something has been typed and it doesn't yet meet every rule. */
function bindPasswordChecklist(inputEl, checklistEl) {
    function update() {
        const pw = inputEl.value;
        const results = passwordRuleResults(pw);
        const valid = isPasswordValid(pw);
        checklistEl.style.display = (pw.length > 0 && !valid) ? '' : 'none';
        checklistEl.querySelectorAll('[data-rule]').forEach(function (li) {
            const ok = results[li.dataset.rule];
            li.classList.toggle('rule-ok', !!ok);
            li.classList.toggle('rule-bad', !ok);
        });
    }
    inputEl.addEventListener('input', update);
    update();
}
