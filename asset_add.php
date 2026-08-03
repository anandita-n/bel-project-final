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

$suggested_code = $_POST['asset_code'] ?? $assets->nextSuggestedCode();

require 'includes/layout_top.php';
?>

<div class="breadcrumb"><a href="assets.php">Asset Management</a> / Add Asset</div>

<div class="panel" style="max-width:760px;">
    <div class="panel-head"><h3>Asset Details</h3></div>
    <div class="panel-body">
        <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" action="asset_add.php">
            <div class="form-grid">
                <div class="field">
                    <label>Asset ID</label>
                    <input type="text" name="asset_code" required value="<?= htmlspecialchars($suggested_code) ?>">
                </div>
                <div class="field">
                    <label>Asset Name</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Category</label>
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
                    <input type="text" name="department" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Purchase Date</label>
                    <input type="date" name="purchase_date" value="<?= htmlspecialchars($_POST['purchase_date'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Warranty Expiry</label>
                    <input type="date" name="warranty_expiry" value="<?= htmlspecialchars($_POST['warranty_expiry'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn">Add Asset</button>
            <a href="assets.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php require 'includes/layout_bottom.php'; ?>
