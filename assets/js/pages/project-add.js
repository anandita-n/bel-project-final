/* New Project form: manager picker + dynamically-added team-member picker rows. */

(function () {
    const managerRoot = document.getElementById('managerPicker');
    managerRoot.innerHTML = empPickerHTML('manager_id', 'Search name or employee ID…');
    initEmpPicker(managerRoot, { roles: ['admin', 'manager'] });

    function addMemberRow() {
        const wrap = document.getElementById('memberRows');
        const row = document.createElement('div');
        row.className = 'member-row';

        const pickerWrap = document.createElement('div');
        pickerWrap.style.flex = '1';
        pickerWrap.innerHTML = empPickerHTML('member_id[]', 'Search name or employee ID…');

        const roleInput = document.createElement('input');
        roleInput.type = 'text';
        roleInput.name = 'member_role[]';
        roleInput.placeholder = 'Role in project (e.g. Developer, Tester)';

        const removeBtn = document.createElement('span');
        removeBtn.className = 'remove-row';
        removeBtn.textContent = 'Remove';
        removeBtn.onclick = () => row.remove();

        row.appendChild(pickerWrap);
        row.appendChild(roleInput);
        row.appendChild(removeBtn);
        wrap.appendChild(row);

        initEmpPicker(pickerWrap, {});
    }

    document.getElementById('addMemberRow').addEventListener('click', addMemberRow);
    addMemberRow();
})();
