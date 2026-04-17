<?php
$conn = new mysqli("localhost","root","","fincore");
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);

$msg = ''; $type = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
  if ($_POST['action']==='add') {
    $stmt = $conn->prepare("INSERT INTO product (Product_name,Price) VALUES (?,?)");
    $stmt->bind_param("sd",$_POST['name'],$_POST['price']);
    if ($stmt->execute()) { $msg='Product added.'; $type='success'; }
    else { $msg='Error: '.$conn->error; $type='error'; }
    $stmt->close();
  }
  if ($_POST['action']==='edit') {
    $stmt = $conn->prepare("UPDATE product SET Product_name=?,Price=? WHERE Product_ID=?");
    $stmt->bind_param("sdi",$_POST['name'],$_POST['price'],$_POST['id']);
    if ($stmt->execute()) { $msg='Product updated.'; $type='success'; }
    else { $msg='Error: '.$conn->error; $type='error'; }
    $stmt->close();
  }
  if ($_POST['action']==='delete') {
    $stmt = $conn->prepare("DELETE FROM product WHERE Product_ID=?");
    $stmt->bind_param("i",$_POST['id']);
    if ($stmt->execute()) { $msg='Product deleted.'; $type='success'; }
    else { $msg='Cannot delete — product has linked records.'; $type='error'; }
    $stmt->close();
  }
}

$search = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$sql = "SELECT p.*, COALESCE(i.Quantity,0) AS stock FROM product p LEFT JOIN inventory i ON p.Product_ID = i.Product_ID";
if ($search) $sql .= " WHERE p.Product_name LIKE '%$search%'";
$sql .= " ORDER BY p.Product_ID DESC";
$result = $conn->query($sql);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FinCore – Products</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
  <header class="page-header">
    <div>
      <h1>Products</h1>
      <p class="page-sub">Product catalog and pricing</p>
    </div>
    <div class="header-actions">
      <a href="inventory.php" class="btn btn-secondary">Manage Inventory</a>
      <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Product</button>
    </div>
  </header>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $type ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="page-card">
    <div class="filter-bar">
      <form method="GET" style="display:flex;gap:.75rem;flex:1;flex-wrap:wrap;">
        <input class="form-control search-input" name="q" placeholder="Search products…" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-secondary">Search</button>
        <?php if ($search): ?><a href="products.php" class="btn btn-secondary">Clear</a><?php endif; ?>
      </form>
    </div>

    <table class="data-table">
      <thead>
        <tr><th>ID</th><th>Product Name</th><th>Price</th><th>Stock</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="5" class="empty-msg">No products found.</td></tr>
      <?php else: ?>
        <?php while ($r = $result->fetch_assoc()): ?>
        <tr>
          <td class="mono"><?= $r['Product_ID'] ?></td>
          <td><?= htmlspecialchars($r['Product_name'] ?? '—') ?></td>
          <td class="mono green-text">$<?= number_format($r['Price'],2) ?></td>
          <td>
            <?php if ($r['stock'] == 0): ?>
              <span class="badge badge-danger">Out of stock</span>
            <?php elseif ($r['stock'] < 10): ?>
              <span class="badge badge-warning"><?= $r['stock'] ?> left</span>
            <?php else: ?>
              <span class="badge badge-success"><?= $r['stock'] ?> in stock</span>
            <?php endif; ?>
          </td>
          <td>
            <button class="btn btn-secondary" style="padding:.3rem .65rem;font-size:.78rem;"
              onclick="openEdit(<?= $r['Product_ID'] ?>,'<?= addslashes($r['Product_name']) ?>',<?= $r['Price'] ?>)">
              Edit
            </button>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this product?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $r['Product_ID'] ?>">
              <button type="submit" class="btn btn-danger" style="padding:.3rem .65rem;font-size:.78rem;">Delete</button>
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
      <span class="modal-title">Add Product</span>
      <button class="modal-close" onclick="closeModal('addModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label class="form-label">Product Name</label>
        <input class="form-control" name="name" required placeholder="e.g. Office Chair">
      </div>
      <div class="form-group">
        <label class="form-label">Price ($)</label>
        <input class="form-control" name="price" type="number" step="0.01" min="0" required placeholder="0.00">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Product</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Product</span>
      <button class="modal-close" onclick="closeModal('editModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-group">
        <label class="form-label">Product Name</label>
        <input class="form-control" name="name" id="edit-name" required>
      </div>
      <div class="form-group">
        <label class="form-label">Price ($)</label>
        <input class="form-control" name="price" id="edit-price" type="number" step="0.01" min="0" required>
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
function openEdit(id, name, price) {
  document.getElementById('edit-id').value = id;
  document.getElementById('edit-name').value = name;
  document.getElementById('edit-price').value = price;
  openModal('editModal');
}
</script>
</body>
</html>
