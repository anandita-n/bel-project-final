/* Asset Management list: live search+filter, and (admin only) Edit/Assign/Change Status
   modals via api/assets/*.php. Expects window.PAGE_CONFIG = { isAdmin, categories, statuses }. */

(function () {
    const cfg = window.PAGE_CONFIG;
    const statusTagClass = { available: 'dir-badge-available', assigned: 'dir-badge-assigned', under_repair: 'dir-badge-under_repair', retired: 'dir-badge-retired', lost: 'dir-badge-lost' };

    function assetRowHTML(a) {
        const actions = cfg.isAdmin ? '<td class="actions"><button type="button" class="row-kebab asset-row-kebab" title="More actions">&#8942;</button></td>' : '';
        return '' +
            '<tr data-id="' + a.id + '" data-name="' + escapeHtml(a.name) + '" data-category="' + a.category + '" ' +
            'data-serial="' + escapeHtml(a.serial_number || '') + '" data-department="' + escapeHtml(a.department || '') + '" ' +
            'data-purchase-date="' + (a.purchase_date || '') + '" data-warranty-expiry="' + (a.warranty_expiry || '') + '" ' +
            'data-assigned-to="' + (a.assigned_to || '') + '" data-assignee-name="' + escapeHtml(a.assignee_name || '') + '" data-status="' + a.status + '">' +
            '<td>' + escapeHtml(a.asset_code) + '</td>' +
            '<td>' + escapeHtml(a.name) + '</td>' +
            '<td>' + escapeHtml(cfg.categories[a.category] || a.category) + '</td>' +
            '<td>' + escapeHtml(a.serial_number || '—') + '</td>' +
            '<td>' + escapeHtml(a.assignee_name || '—') + '</td>' +
            '<td>' + escapeHtml(a.department || '—') + '</td>' +
            '<td><span class="dir-badge ' + (statusTagClass[a.status] || 'dir-badge-retired') + '">' + escapeHtml(cfg.statuses[a.status] || a.status) + '</span></td>' +
            actions +
            '</tr>';
    }

    function categoryOptionsHTML(selected) {
        return Object.keys(cfg.categories).map(function (key) {
            return '<option value="' + key + '"' + (key === selected ? ' selected' : '') + '>' + escapeHtml(cfg.categories[key]) + '</option>';
        }).join('');
    }

    function statusOptionsHTML(selected) {
        return Object.keys(cfg.statuses).map(function (key) {
            return '<option value="' + key + '"' + (key === selected ? ' selected' : '') + '>' + escapeHtml(cfg.statuses[key]) + '</option>';
        }).join('');
    }

    function renderRows(rows) {
        const tbody = document.getElementById('assetsTbody');
        tbody.innerHTML = rows.map(assetRowHTML).join('');
        document.getElementById('assetsTable').style.display = rows.length ? '' : 'none';
        document.getElementById('assetsEmpty').style.display = rows.length ? 'none' : '';
        tbody.querySelectorAll('tr').forEach(bindAssetRow);
    }

    const searchInput = document.getElementById('assetSearchInput');
    const categoryFilter = document.getElementById('assetCategoryFilter');
    const statusFilter = document.getElementById('assetStatusFilter');

    const runSearch = debounce(function () {
        const params = new URLSearchParams({
            q: searchInput.value.trim(),
            category: categoryFilter.value,
            status: statusFilter.value,
        });
        apiGet('api/assets/list.php?' + params.toString()).then(function (data) {
            renderRows(data.results);
        });
    }, 200);

    searchInput.addEventListener('input', runSearch);
    categoryFilter.addEventListener('change', runSearch);
    statusFilter.addEventListener('change', runSearch);

    function bindAssetRow(row) {
        const kebab = row.querySelector('.asset-row-kebab');
        if (!kebab) return;
        initOverflowMenu(kebab, [
            { label: 'Edit', onClick: function () { openEditModal(row); } },
            { label: 'Assign', onClick: function () { openAssignModal(row); } },
            { label: 'Change Status', onClick: function () { openStatusModal(row); } },
        ]);
    }
    document.querySelectorAll('#assetsTbody tr').forEach(bindAssetRow);

    function showError(box, message) {
        box.textContent = message;
        box.style.display = 'block';
    }

    function patchRow(row, asset) {
        row.outerHTML = assetRowHTML({
            id: asset.id, name: asset.name, category: asset.category, serial_number: asset.serial_number,
            department: asset.department, purchase_date: asset.purchase_date, warranty_expiry: asset.warranty_expiry,
            assigned_to: asset.assigned_to, assignee_name: asset.assignee_name, status: asset.status,
            asset_code: asset.asset_code,
        });
        bindAssetRow(document.querySelector('#assetsTbody tr[data-id="' + asset.id + '"]'));
    }

    if (!cfg.isAdmin) return;

    function openEditModal(row) {
        const id = row.dataset.id;
        const overlay = openModal('Edit Asset — ' + row.dataset.name, '' +
            '<div id="assetEditError" class="error-msg" style="display:none;"></div>' +
            '<form id="assetEditForm">' +
            '<div class="field"><label>Asset Name</label><input type="text" id="editAssetName" value="' + escapeHtml(row.dataset.name) + '" required></div>' +
            '<div class="field"><label>Category</label><select id="editAssetCategory">' + categoryOptionsHTML(row.dataset.category) + '</select></div>' +
            '<div class="field"><label>Serial Number</label><input type="text" id="editAssetSerial" value="' + escapeHtml(row.dataset.serial || '') + '"></div>' +
            '<div class="field"><label>Department</label><input type="text" id="editAssetDept" value="' + escapeHtml(row.dataset.department || '') + '"></div>' +
            '<div class="field"><label>Purchase Date</label><input type="date" id="editAssetPurchase" value="' + (row.dataset.purchaseDate || '') + '"></div>' +
            '<div class="field"><label>Warranty Expiry</label><input type="date" id="editAssetWarranty" value="' + (row.dataset.warrantyExpiry || '') + '"></div>' +
            '<button type="submit" class="btn">Save Changes</button>' +
            '</form>');

        overlay.querySelector('#assetEditForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            const errorBox = overlay.querySelector('#assetEditError');
            apiPost('api/assets/update.php', {
                id: id,
                name: overlay.querySelector('#editAssetName').value.trim(),
                category: overlay.querySelector('#editAssetCategory').value,
                serial_number: overlay.querySelector('#editAssetSerial').value.trim(),
                department: overlay.querySelector('#editAssetDept').value.trim(),
                purchase_date: overlay.querySelector('#editAssetPurchase').value,
                warranty_expiry: overlay.querySelector('#editAssetWarranty').value,
            }).then(function (data) {
                patchRow(row, data.asset);
                closeModal();
            }).catch(function (err) { showError(errorBox, err.message); });
        });
    }

    function openAssignModal(row) {
        const id = row.dataset.id;
        const overlay = openModal('Assign Asset — ' + row.dataset.name, '' +
            '<div id="assetAssignError" class="error-msg" style="display:none;"></div>' +
            '<form id="assetAssignForm">' +
            '<div class="field"><label>Assign To</label><div id="assetAssignPicker"></div></div>' +
            '<button type="submit" class="btn">Save</button>' +
            (row.dataset.assignedTo ? ' <button type="button" class="btn btn-secondary" id="assetUnassignBtn">Unassign</button>' : '') +
            '</form>');

        const pickerRoot = overlay.querySelector('#assetAssignPicker');
        pickerRoot.innerHTML = empPickerHTML('user_id', 'Search name or employee ID…');
        initEmpPicker(pickerRoot, {
            selectedId: row.dataset.assignedTo || '',
            selectedLabel: row.dataset.assigneeName || '',
        });

        function doAssign(userId) {
            const errorBox = overlay.querySelector('#assetAssignError');
            apiPost('api/assets/assign.php', { id: id, user_id: userId })
                .then(function (data) {
                    patchRow(row, data.asset);
                    closeModal();
                }).catch(function (err) { showError(errorBox, err.message); });
        }

        overlay.querySelector('#assetAssignForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            const userId = pickerRoot.querySelector('.emp-picker-hidden').value;
            if (!userId) { showError(overlay.querySelector('#assetAssignError'), 'Please select an employee.'); return; }
            doAssign(userId);
        });
        const unassignBtn = overlay.querySelector('#assetUnassignBtn');
        if (unassignBtn) unassignBtn.addEventListener('click', function () { doAssign(''); });
    }

    function openStatusModal(row) {
        const id = row.dataset.id;
        const overlay = openModal('Change Status — ' + row.dataset.name, '' +
            '<div id="assetStatusError" class="error-msg" style="display:none;"></div>' +
            '<form id="assetStatusForm">' +
            '<div class="field"><label>Status</label><select id="editAssetStatus">' + statusOptionsHTML(row.dataset.status) + '</select></div>' +
            '<button type="submit" class="btn">Save</button>' +
            '</form>');

        overlay.querySelector('#assetStatusForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            const errorBox = overlay.querySelector('#assetStatusError');
            apiPost('api/assets/status.php', { id: id, status: overlay.querySelector('#editAssetStatus').value })
                .then(function (data) {
                    patchRow(row, data.asset);
                    closeModal();
                }).catch(function (err) { showError(errorBox, err.message); });
        });
    }
})();
