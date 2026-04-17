<?php
$conn = new mysqli("localhost","root","","fincore");
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);

$msg = ''; $type = '';

// Add
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
  if ($_POST['action']==='add') {
    $stmt = $conn->prepare("INSERT INTO customer (Name,Email,Phone) VALUES (?,?,?)");
    $stmt->bind_param("sss",$_POST['name'],$_POST['email'],$_POST['phone']);
    if ($stmt->execute()) { $msg='Customer added successfully.'; $type='success'; }
    else { $msg='Error: '.$conn->error; $type='error'; }
    $stmt->close();
  }
  if ($_POST['action']==='edit') {
    $stmt = $conn->prepare("UPDATE customer SET Name=?,Email=?,Phone=? WHERE Customer_ID=?");
    $stmt->bind_param("sssi",$_POST['name'],$_POST['email'],$_POST['phone'],$_POST['id']);
    if ($stmt->execute()) { $msg='Customer updated.'; $type='success'; }
    else { $msg='Error: '.$conn->error; $type='error'; }
    $stmt->close();
  }
  if ($_POST['action']==='delete') {
    $stmt = $conn->prepare("DELETE FROM customer WHERE Customer_ID=?");
    $stmt->bind_param("i",$_POST['id']);
    if ($stmt->execute()) { $msg='Customer deleted.'; $type='success'; }
    else { $msg='Cannot delete — customer has linked sales.'; $type='error'; }
    $stmt->close();
  }
}

$search = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$sql = "SELECT * FROM customer";
if ($search) $sql .= " WHERE Name LIKE '%$search%' OR Email LIKE '%$search%' OR Phone LIKE '%$search%'";
$sql .= " ORDER BY Customer_ID DESC";
$result = $conn->query($sql);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FinCore – Customers</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
  <header class="page-header">
    <div>
      <h1>Customers</h1>
      <p class="page-sub">Manage your customer database</p>
    </div>
    <div class="header-actions">
      <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Customer</button>
    </div>
  </header>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $type ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="page-card">
    <div class="filter-bar">
      <form method="GET" style="display:flex;gap:.75rem;flex:1;flex-wrap:wrap;">
        <input class="form-control search-input" name="q" placeholder="Search name, email, phone…" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-secondary">Search</button>
        <?php if ($search): ?><a href="customers.php" class="btn btn-secondary">Clear</a><?php endif; ?>
      </form>
    </div>

    <table class="data-table">
      <thead>
        <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="5" class="empty-msg">No customers found.</td></tr>
      <?php else: ?>
        <?php while ($r = $result->fetch_assoc()): ?>
        <tr>
          <td class="mono"><?= $r['Customer_ID'] ?></td>
          <td><?= htmlspecialchars($r['Name'] ?? '—') ?></td>
          <td class="muted"><?= htmlspecialchars($r['Email'] ?? '—') ?></td>
          <td class="mono muted"><?= htmlspecialchars($r['Phone'] ?? '—') ?></td>
          <td>
            <button class="btn btn-secondary" style="padding:.3rem .65rem;font-size:.78rem;"
              onclick="openEdit(<?= $r['Customer_ID'] ?>,'<?= addslashes($r['Name']) ?>','<?= addslashes($r['Email']) ?>','<?= addslashes($r['Phone']) ?>')">
              Edit
            </button>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this customer?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $r['Customer_ID'] ?>">
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
      <span class="modal-title">Add Customer</span>
      <button class="modal-close" onclick="closeModal('addModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input class="form-control" name="name" required placeholder="John Smith">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-control" name="email" type="email" placeholder="john@example.com">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input class="form-control" name="phone" placeholder="555-0100">
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Customer</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Customer</span>
      <button class="modal-close" onclick="closeModal('editModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input class="form-control" name="name" id="edit-name" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-control" name="email" id="edit-email">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input class="form-control" name="phone" id="edit-phone">
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Update</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/app.js"></script>
</body>
</html>
