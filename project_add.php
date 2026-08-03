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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_code = trim($_POST['project_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $manager_id = (int)($_POST['manager_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? null;
    $due_date = $_POST['due_date'] ?? null;

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

        header('Location: project_add_team.php?id=' . $project_id);
        exit;
    }
}

$suggested_code = $_POST['project_code'] ?? $projects->nextSuggestedCode();

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/emp-picker.js"></script>

<div class="breadcrumb"><a href="projects.php">Projects</a> / New Project</div>

<div class="pa-page">
    <div class="pa-header">
        <h2>Create Project</h2>
        <p class="pa-header-sub">Step 1 of 5 — start with the project details. You'll add your team and tasks next.</p>
    </div>

    <?= render_wizard_stepper(1) ?>

    <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" action="project_add.php" id="projectForm" enctype="multipart/form-data">
        <div class="panel pa-panel">
            <div class="panel-head"><h3>Project Details</h3></div>
            <div class="panel-body">
                <div class="form-grid">
                    <div class="field">
                        <label>Project Name</label>
                        <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label>Project Code</label>
                        <input type="text" name="project_code" value="<?= htmlspecialchars($suggested_code) ?>" required>
                    </div>
                    <div class="field">
                        <label>Department</label>
                        <input type="text" name="department" placeholder="e.g. Engineering" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label>Project Manager</label>
                        <div id="managerPicker"></div>
                    </div>
                    <div class="field">
                        <label>Start Date</label>
                        <input type="date" name="start_date" id="startDateInput" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label>Target Completion Date</label>
                        <input type="date" name="due_date" id="dueDateInput" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>">
                    </div>
                    <div class="field" style="grid-column: 1 / -1;">
                        <label>Description</label>
                        <textarea name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    <div class="field" style="grid-column: 1 / -1;">
                        <label>Project Documents (Optional)</label>
                        <input type="file" id="documentsInput" name="documents[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.png,.jpg,.jpeg,.zip" style="display:none;">
                        <button type="button" class="btn btn-secondary" id="addDocumentsBtn">+ Add Files</button>
                        <div id="documentUploadError" class="error-msg" style="display:none;"></div>
                        <div id="selectedDocumentsList" class="selected-files-list"></div>
                        <p class="field-hint">PDF, Word, Excel, PowerPoint, TXT, CSV, PNG, JPG, or ZIP — up to 10 files, 50MB each.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="pa-actions">
            <button type="submit" class="btn btn-lg">Continue</button>
            <a href="projects.php" class="btn btn-lg btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const managerRoot = document.getElementById('managerPicker');
    managerRoot.innerHTML = empPickerHTML('manager_id', 'Search name or employee ID…');
    initEmpPicker(managerRoot, { roles: ['admin', 'manager'] });

    const startInput = document.getElementById('startDateInput');
    const dueInput = document.getElementById('dueDateInput');
    startInput.addEventListener('change', function () {
        dueInput.min = startInput.value || '<?= date('Y-m-d') ?>';
        if (dueInput.value && startInput.value && dueInput.value < startInput.value) {
            dueInput.value = startInput.value;
        }
    });

    // Accumulates files across multiple picks (a plain <input multiple> replaces its
    // selection every time the dialog reopens), so users can build up a list one pick at a time.
    const documentsInput = document.getElementById('documentsInput');
    const addDocumentsBtn = document.getElementById('addDocumentsBtn');
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

    addDocumentsBtn.addEventListener('click', function () { documentsInput.click(); });
    documentsInput.addEventListener('change', function () {
        clearDocumentError();
        Array.from(documentsInput.files).forEach(function (file) {
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
    });
});
</script>

<?php require 'includes/layout_bottom.php'; ?>
