<?php
$conn = new mysqli("localhost","root","","fincore");
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);

$msg = ''; $type = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
  if ($_POST['action']==='add') {
    // Check if product already has inventory entry
    $check = $conn->prepare("SELECT Inventory_ID FROM inventory WHERE Product_ID=?");
    $check->bind_param("i",$_POST['product_id']);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
      $msg='This product already has an inventory entry. Edit it instead.'; $type='error';
    } else {
      $stmt = $conn->prepare("INSERT INTO inventory (Product_ID,Quantity) VALUES (?,?)");
      $stmt->bind_param("ii",$_POST['product_id'],$_POST['quantity']);
      if ($stmt->execute()) { $msg='Inventory entry added.'; $type='success'; }
      else { $msg='Error: '.$conn->error; $type='error'; }
      $stmt->close();
    }
    $check->close();
  }
  if ($_POST['action']==='edit') {
    $stmt = $conn->prepare("UPDATE inventory SET Quantity=? WHERE Inventory_ID=?");
    $stmt->bind_param("ii",$_POST['quantity'],$_POST['id']);
    if ($stmt->execute()) { $msg='Inventory updated.'; $type='success'; }
    else { $msg='Error: '.$conn->error; $type='error'; }
    $stmt->close();
  }
  if ($_POST['action']==='delete') {
    $stmt = $conn->prepare("DELETE FROM inventory WHERE Inventory_ID=?");
    $stmt->bind_param("i",$_POST['id']);
    if ($stmt->execute()) { $msg='Entry removed.'; $type='success'; }
    else { $msg='Error: '.$conn->error; $type='error'; }
    $stmt->close();
  }
}

$products = $conn->query("SELECT Product_ID, Product_name FROM product ORDER BY Product_name");
$filter = isset($_GET['status']) ? $_GET['status'] : '';
$sql = "SELECT i.Inventory_ID, p.Product_name, p.Price, i.Quantity
        FROM inventory i JOIN product p ON i.Product_ID = p.Product_ID";
if ($filter === 'low')  $sql .= " WHERE i.Quantity < 10 AND i.Quantity > 0";
if ($filter === 'out')  $sql .= " WHERE i.Quantity = 0";
if ($filter === 'good') $sql .= " WHERE i.Quantity >= 10";
$sql .= " ORDER BY i.Quantity ASC";
$result = $conn->query($sql);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FinCore – Inventory</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
  <header class="page-header">
    <div>
      <h1>Inventory</h1>
      <p class="page-sub">Monitor product stock levels</p>
    </div>
    <div class="header-actions">
      <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Entry</button>
    </div>
  </header>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $type ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="page-card">
    <div class="filter-bar">
      <a href="inventory.php" class="btn <?= !$filter?'btn-primary':'btn-secondary' ?>">All</a>
      <a href="inventory.php?status=good" class="btn <?= $filter==='good'?'btn-primary':'btn-secondary' ?>">In Stock</a>
      <a href="inventory.php?status=low"  class="btn <?= $filter==='low'?'btn-primary':'btn-secondary' ?>">Low Stock</a>
      <a href="inventory.php?status=out"  class="btn <?= $filter==='out'?'btn-primary':'btn-secondary' ?>">Out of Stock</a>
    </div>

    <table class="data-table">
      <thead>
        <tr><th>ID</th><th>Product</th><th>Unit Price</th><th>Quantity</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="6" class="empty-msg">No inventory records found.</td></tr>
      <?php else: ?>
        <?php while ($r = $result->fetch_assoc()): ?>
        <tr>
          <td class="mono"><?= $r['Inventory_ID'] ?></td>
          <td><?= htmlspecialchars($r['Product_name']) ?></td>
          <td class="mono muted">$<?= number_format($r['Price'],2) ?></td>
          <td class="mono"><?= $r['Quantity'] ?></td>
          <td>
            <?php if ($r['Quantity'] == 0): ?>
              <span class="badge badge-danger">Out of stock</span>
            <?php elseif ($r['Quantity'] < 10): ?>
              <span class="badge badge-warning">Low stock</span>
            <?php else: ?>
              <span class="badge badge-success">In stock</span>
            <?php endif; ?>
          </td>
          <td>
            <button class="btn btn-secondary" style="padding:.3rem .65rem;font-size:.78rem;"
              onclick="openEdit(<?= $r['Inventory_ID'] ?>,<?= $r['Quantity'] ?>)">
              Update Qty
            </button>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove inventory entry?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $r['Inventory_ID'] ?>">
              <button type="submit" class="btn btn-danger" style="padding:.3rem .65rem;font-size:.78rem;">Remove</button>
            </form>
          </td>
        </tr>
        <?php endwhile; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Inventory Entry</span>
      <button class="modal-close" onclick="closeModal('addModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label class="form-label">Product</label>
        <select class="form-control" name="product_id" required>
          <option value="">— Select Product —</option>
          <?php while ($p = $products->fetch_assoc()): ?>
            <option value="<?= $p['Product_ID'] ?>"><?= htmlspecialchars($p['Product_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Quantity</label>
        <input class="form-control" type="number" name="quantity" min="0" required placeholder="0">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Add Entry</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Qty Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Update Quantity</span>
      <button class="modal-close" onclick="closeModal('editModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-group">
        <label class="form-label">New Quantity</label>
        <input class="form-control" type="number" name="quantity" id="edit-qty" min="0" required>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Update</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/app.js"></script>
<script>
function openEdit(id, qty) {
  document.getElementById('edit-id').value = id;
  document.getElementById('edit-qty').value = qty;
  openModal('editModal');
}
</script>
</body>
</html>
