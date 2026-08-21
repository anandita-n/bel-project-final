<?php
require_once 'includes/bootstrap.php';
require_role(['admin']);

use App\Repositories\AssetRepository;

$page_title = 'Add Asset';
$u = current_user();
$error = '';
$assets = new AssetRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_code = trim($_POST['asset_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? '';
    $serial_number = trim($_POST['serial_number'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $purchase_date = $_POST['purchase_date'] ?? '';
    $warranty_expiry = $_POST['warranty_expiry'] ?? '';

    if ($asset_code === '' || $name === '' || !array_key_exists($category, AssetRepository::CATEGORIES)) {
        $error = 'Asset ID, name and a valid category are required.';
    } elseif ($assets->codeExists($asset_code)) {
        $error = 'An asset with this ID already exists.';
    } else {
        $id = $assets->create([
            'asset_code' => $asset_code,
            'name' => $name,
            'category' => $category,
            'serial_number' => $serial_number,
            'department' => $department,
            'purchase_date' => $purchase_date,
            'warranty_expiry' => $warranty_expiry,
        ]);

        header('Location: assets.php');
        exit;
    }
}

$suggested_code = trim($_POST['asset_code'] ?? '') ?: $assets->nextSuggestedCode();

require 'includes/layout_top.php';
?>

<div class="pa-page-wrap">
<div class="breadcrumb"><a href="assets.php">Asset Management</a> / Add Asset</div>

<div class="pa-header">
    <h2>Add New Asset</h2>
</div>

<?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" action="asset_add.php" class="pa-form-spacious">
<div class="pa-page">

        <div class="pa-section pa-section-plain">
            <div class="pa-section-head">
                <svg class="pa-section-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.29 7 12 12l8.71-5"/><line x1="12" y1="22" x2="12" y2="12"/></svg>
                <div>
                    <h3>Asset Information</h3>
                </div>
            </div>
            <div class="pa-section-body form-grid">
                <div class="field">
                    <label>ID <span class="required-mark">*</span></label>
                    <input type="text" value="<?= htmlspecialchars($suggested_code) ?>" readonly>
                    <input type="hidden" name="asset_code" value="<?= htmlspecialchars($suggested_code) ?>">
                </div>
                <div class="field">
                    <label>Asset Name <span class="required-mark">*</span></label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Category <span class="required-mark">*</span></label>
                    <select name="category">
                        <?php foreach (AssetRepository::CATEGORIES as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($_POST['category'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Serial Number</label>
                    <input type="text" name="serial_number" value="<?= htmlspecialchars($_POST['serial_number'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Department</label>
                    <input type="text" name="department" placeholder="e.g. Engineering" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="pa-section pa-section-plain">
            <div class="pa-section-head">
                <svg class="pa-section-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <div>
                    <h3>Asset Dates</h3>
                </div>
            </div>
            <div class="pa-section-body form-grid">
                <div class="field">
                    <label>Purchase Date</label>
                    <input type="date" name="purchase_date" value="<?= htmlspecialchars($_POST['purchase_date'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Warranty Expiry</label>
                    <input type="date" name="warranty_expiry" value="<?= htmlspecialchars($_POST['warranty_expiry'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="pa-actions">
            <a href="assets.php" class="pill-btn pill-btn-lg pill-btn-secondary">Cancel</a>
            <button type="submit" class="pill-btn pill-btn-lg">Add Asset</button>
        </div>
</div>
</form>
</div>

<?php require 'includes/layout_bottom.php'; ?>
