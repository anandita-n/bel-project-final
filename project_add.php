<?php
require_once 'includes/bootstrap.php';
require_role(['admin','manager']);

use App\Repositories\ProjectRepository;
use App\Repositories\ProjectDocumentRepository;
use App\Repositories\UserRepository;

$page_title = 'New Project';
$u = current_user();
$error = '';
$projects = new ProjectRepository();
$documentsRepo = new ProjectDocumentRepository();
$users = new UserRepository();
$departments = $users->distinctDepartments();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_code = trim($_POST['project_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $manager_id = (int)($_POST['manager_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? null;
    $due_date = $_POST['due_date'] ?? null;
    $priority = $_POST['priority'] ?? 'medium';

    // Documents are entirely optional — an empty/unused file input still shows up in $_FILES
    // with error === UPLOAD_ERR_NO_FILE, which this filters out.
    $uploadedFiles = [];
    if (!empty($_FILES['documents'])) {
        $fileCount = count(array_filter($_FILES['documents']['error'], fn($e) => $e !== UPLOAD_ERR_NO_FILE));
        if ($fileCount > project_document_max_files_per_upload()) {
            $error = 'You can attach up to ' . project_document_max_files_per_upload() . ' files per upload.';
        } else {
            foreach ($_FILES['documents']['error'] as $i => $err) {
                if ($err === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                    $error = 'One of the attached files is too large (50MB max).';
                    break;
                }
                if ($err !== UPLOAD_ERR_OK) {
                    $error = 'One of the attached files failed to upload.';
                    break;
                }
                $origName = $_FILES['documents']['name'][$i];
                $tmpName = $_FILES['documents']['tmp_name'][$i];
                $size = (int)$_FILES['documents']['size'][$i];
                if ($size > project_document_max_upload_bytes()) {
                    $error = '"' . $origName . '" is too large (50MB max).';
                    break;
                }
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                if (!in_array($ext, allowed_document_extensions(), true) || !in_array($mime, allowed_document_mime_types(), true)) {
                    $error = '"' . $origName . '" is not an allowed file type.';
                    break;
                }
                $uploadedFiles[] = ['name' => $origName, 'tmp_name' => $tmpName, 'size' => $size, 'ext' => $ext, 'mime' => $mime];
            }
        }
    }

    if ($error) {
        // fall through to re-render the form with $error
    } elseif ($project_code === '' || $name === '' || !$manager_id) {
        $error = 'Project code, name and manager are required.';
    } elseif ($department === '' || !in_array($department, $departments, true)) {
        $error = 'Please select a valid department.';
    } elseif (!$users->findActiveById($manager_id)) {
        $error = 'Select an active employee as the project manager.';
    } elseif ($projects->codeExists($project_code)) {
        $error = 'A project with this code already exists.';
    } elseif ($start_date && $start_date < date('Y-m-d')) {
        $error = 'Start date cannot be in the past.';
    } elseif ($start_date && $due_date && $due_date < $start_date) {
        $error = 'End date must be after the project start date.';
    } else {
        $project_id = $projects->create([
            'project_code' => $project_code,
            'name' => $name,
            'department' => $department,
            'description' => $description,
            'manager_id' => $manager_id,
            'start_date' => $start_date,
            'due_date' => $due_date,
            'priority' => $priority,
        ]);

        if ($uploadedFiles) {
            $dir = project_document_upload_dir();
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            foreach ($uploadedFiles as $f) {
                $storedFilename = bin2hex(random_bytes(16)) . '.' . $f['ext'];
                if (move_uploaded_file($f['tmp_name'], $dir . $storedFilename)) {
                    $documentsRepo->create($project_id, (int)$u['id'], $f['name'], $storedFilename, $f['size'], $f['mime']);
                }
            }
        }

        header('Location: project_detail.php?id=' . $project_id);
        exit;
    }
}

$suggested_code = $_POST['project_code'] ?? $projects->nextSuggestedCode();

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/emp-picker.js?v=<?= filemtime(__DIR__ . '/assets/js/emp-picker.js') ?>"></script>

<div class="pa-page-wrap">
<div class="breadcrumb"><a href="projects.php">Projects</a> / Create New Project</div>

<div class="pa-header">
    <h2>Create New Project</h2>
</div>

<?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" action="project_add.php" id="projectForm" enctype="multipart/form-data">
<div class="pa-page">

        <div class="pa-section pa-section-plain">
            <div class="pa-section-head">
                <svg class="pa-section-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <div>
                    <h3>Project Information</h3>
                </div>
            </div>
            <div class="pa-section-body form-grid">
                <div class="field">
                    <label>Project Name <span class="required-mark">*</span></label>
                    <input type="text" name="name" id="nameInput" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>ID <span class="required-mark">*</span></label>
                    <input type="text" name="project_code" id="codeInput" value="<?= htmlspecialchars($suggested_code) ?>" required>
                </div>
                <div class="field">
                    <label>Department <span class="required-mark">*</span></label>
                    <select name="department" id="departmentInput" required>
                        <option value="">Select</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept) ?>" <?= ($_POST['department'] ?? '') === $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Reports To <span class="required-mark">*</span></label>
                    <div id="managerPicker"></div>
                </div>
            </div>
        </div>

        <div class="pa-section pa-section-plain">
            <div class="pa-section-head">
                <svg class="pa-section-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <div>
                    <h3>Project Timeline</h3>
                </div>
            </div>
            <div class="pa-section-body form-grid">
                <div class="field">
                    <label>Start Date <span class="required-mark">*</span></label>
                    <input type="date" name="start_date" id="startDateInput" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Target Completion Date <span class="required-mark">*</span></label>
                    <input type="date" name="due_date" id="dueDateInput" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="pa-section pa-section-plain">
            <div class="pa-section-head">
                <svg class="pa-section-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
                <div>
                    <h3>Project Description</h3>
                </div>
            </div>
            <div class="pa-section-body">
                <div class="field">
                    <textarea name="description" id="descriptionInput" rows="4" maxlength="1000"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    <span class="pa-char-count" id="descriptionCount">0 / 1000</span>
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label>Attachments <span class="pa-optional">(Optional)</span></label>
                    <div class="pa-dropzone" id="docDropzone">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <p>Drag and drop files here or <button type="button" class="link-btn" id="addDocumentsBtn">browse</button></p>
                        <p class="field-hint">PDF, Word, Excel, PowerPoint, TXT, CSV, PNG, JPG, or ZIP — up to 10 files, 50MB each.</p>
                    </div>
                    <input type="file" id="documentsInput" name="documents[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.png,.jpg,.jpeg,.zip" style="display:none;">
                    <div id="documentUploadError" class="error-msg" style="display:none;"></div>
                    <div id="selectedDocumentsList" class="selected-files-list"></div>
                </div>
            </div>
        </div>

        <div class="pa-actions">
            <a href="projects.php" class="pill-btn pill-btn-lg pill-btn-secondary">Cancel</a>
            <button type="submit" class="pill-btn pill-btn-lg">Create Project</button>
        </div>
</div>
</form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const managerRoot = document.getElementById('managerPicker');
    managerRoot.innerHTML = empPickerHTML('manager_id', 'Search name or employee ID…');
    const departmentSelect = document.getElementById('departmentInput');
    const managerPickerOpts = { roles: ['admin', 'manager'], department: departmentSelect.value };
    initEmpPicker(managerRoot, managerPickerOpts);

    // Reports To narrows to the selected department (admins stay selectable regardless) —
    // switching department invalidates whatever was picked, since it may no longer qualify.
    departmentSelect.addEventListener('change', function () {
        managerPickerOpts.department = departmentSelect.value;
        managerRoot.querySelector('.emp-picker-hidden').value = '';
        managerRoot.querySelector('.emp-picker-search').value = '';
        managerRoot.querySelector('.emp-picker-list').style.display = 'none';
    });

    const startInput = document.getElementById('startDateInput');
    const dueInput = document.getElementById('dueDateInput');
    startInput.addEventListener('change', function () {
        dueInput.min = startInput.value || '<?= date('Y-m-d') ?>';
        if (dueInput.value && startInput.value && dueInput.value < startInput.value) {
            dueInput.value = startInput.value;
        }
    });

    // --- Description character counter ---
    const descriptionInput = document.getElementById('descriptionInput');
    const descriptionCount = document.getElementById('descriptionCount');
    function updateDescriptionCount() {
        descriptionCount.textContent = descriptionInput.value.length + ' / 1000';
    }
    descriptionInput.addEventListener('input', updateDescriptionCount);
    updateDescriptionCount();

    // --- Documents: accumulates files across multiple picks/drops (a plain <input multiple>
    // replaces its selection every time the dialog reopens), plus drag-and-drop onto the zone. ---
    const documentsInput = document.getElementById('documentsInput');
    const addDocumentsBtn = document.getElementById('addDocumentsBtn');
    const docDropzone = document.getElementById('docDropzone');
    const selectedDocumentsList = document.getElementById('selectedDocumentsList');
    const documentUploadError = document.getElementById('documentUploadError');
    const MAX_DOCUMENT_FILES = 10;
    const MAX_DOCUMENT_BYTES = 50 * 1024 * 1024;
    let selectedFiles = [];

    function showDocumentError(message) {
        documentUploadError.textContent = message;
        documentUploadError.style.display = 'block';
    }

    function clearDocumentError() {
        documentUploadError.style.display = 'none';
    }

    function escapeText(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function syncDocumentsInput() {
        const dt = new DataTransfer();
        selectedFiles.forEach(function (file) { dt.items.add(file); });
        documentsInput.files = dt.files;
    }

    function renderSelectedDocuments() {
        selectedDocumentsList.innerHTML = selectedFiles.map(function (file, idx) {
            return '<div class="selected-file-row" data-idx="' + idx + '">' +
                '<span class="selected-file-name">' + escapeText(file.name) + '</span>' +
                '<button type="button" class="selected-file-remove" data-idx="' + idx + '" aria-label="Remove">&times;</button>' +
                '</div>';
        }).join('');
        selectedDocumentsList.querySelectorAll('.selected-file-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectedFiles.splice(parseInt(btn.dataset.idx, 10), 1);
                syncDocumentsInput();
                renderSelectedDocuments();
            });
        });
    }

    function addFiles(fileList) {
        clearDocumentError();
        Array.from(fileList).forEach(function (file) {
            if (selectedFiles.length >= MAX_DOCUMENT_FILES) {
                showDocumentError('You can attach up to ' + MAX_DOCUMENT_FILES + ' files per upload.');
                return;
            }
            if (file.size > MAX_DOCUMENT_BYTES) {
                showDocumentError('"' + file.name + '" is too large (50MB max).');
                return;
            }
            const isDuplicate = selectedFiles.some(function (f) { return f.name === file.name && f.size === file.size; });
            if (!isDuplicate) { selectedFiles.push(file); }
        });
        syncDocumentsInput();
        renderSelectedDocuments();
    }

    addDocumentsBtn.addEventListener('click', function () { documentsInput.click(); });
    documentsInput.addEventListener('change', function () { addFiles(documentsInput.files); });

    ['dragenter', 'dragover'].forEach(function (evt) {
        docDropzone.addEventListener(evt, function (e) {
            e.preventDefault();
            docDropzone.classList.add('dragover');
        });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
        docDropzone.addEventListener(evt, function (e) {
            e.preventDefault();
            docDropzone.classList.remove('dragover');
        });
    });
    docDropzone.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files.length) { addFiles(e.dataTransfer.files); }
    });
});
</script>

<?php require 'includes/layout_bottom.php'; ?>
