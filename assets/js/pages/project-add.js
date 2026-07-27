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

    function addTaskRow() {
        const wrap = document.getElementById('taskRows');
        const row = document.createElement('div');
        row.className = 'task-row-card';

        row.innerHTML =
            '<div class="task-row-remove"><span class="remove-row">Remove</span></div>' +
            '<div class="task-row-grid">' +
            '<div class="field"><label>Task Title</label><input type="text" name="task_title[]" placeholder="Task title"></div>' +
            '<div class="field"><label>Assignee</label><div class="task-row-picker"></div></div>' +
            '<div class="field"><label>Priority</label><select name="task_priority[]">' +
            '<option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option>' +
            '</select></div>' +
            '<div class="field"><label>Start Date</label><input type="date" name="task_start_date[]"></div>' +
            '<div class="field"><label>Due Date</label><input type="date" name="task_due_date[]"></div>' +
            '</div>';

        row.querySelector('.remove-row').onclick = () => row.remove();

        const pickerWrap = row.querySelector('.task-row-picker');
        pickerWrap.innerHTML = empPickerHTML('task_assignee[]', 'Search name or employee ID…');
        wrap.appendChild(row);
        initEmpPicker(pickerWrap, {});
    }

    document.getElementById('addTaskRow').addEventListener('click', addTaskRow);
    addTaskRow();
})();
