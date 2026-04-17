<?php
$conn = new mysqli("localhost","root","","fincore");
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);

$msg = ''; $type = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
  if ($_POST['action']==='add') {
    $stmt = $conn->prepare("INSERT INTO employee (Employee_name,Phone,Role,Hire_date) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss",$_POST['name'],$_POST['phone'],$_POST['role'],$_POST['hire_date']);
    if ($stmt->execute()) { $msg='Employee added.'; $type='success'; }
    else { $msg='Error: '.$conn->error; $type='error'; }
    $stmt->close();
  }
  if ($_POST['action']==='edit') {
    $stmt = $conn->prepare("UPDATE employee SET Employee_name=?,Phone=?,Role=?,Hire_date=? WHERE Employee_ID=?");
    $stmt->bind_param("ssssi",$_POST['name'],$_POST['phone'],$_POST['role'],$_POST['hire_date'],$_POST['id']);
    if ($stmt->execute()) { $msg='Employee updated.'; $type='success'; }
    else { $msg='Error: '.$conn->error; $type='error'; }
    $stmt->close();
  }
  if ($_POST['action']==='delete') {
    $stmt = $conn->prepare("DELETE FROM employee WHERE Employee_ID=?");
    $stmt->bind_param("i",$_POST['id']);
    if ($stmt->execute()) { $msg='Employee removed.'; $type='success'; }
    else { $msg='Cannot delete — employee has linked records.'; $type='error'; }
    $stmt->close();
  }
}

$result = $conn->query("SELECT * FROM employee ORDER BY Hire_date DESC");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FinCore – Employees</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
  <header class="page-header">
    <div>
      <h1>Employees</h1>
      <p class="page-sub">Manage your team members</p>
    </div>
    <div class="header-actions">
      <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Employee</button>
    </div>
  </header>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $type ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="page-card">
    <table class="data-table">
      <thead>
        <tr><th>ID</th><th>Name</th><th>Role</th><th>Phone</th><th>Hire Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="6" class="empty-msg">No employees found.</td></tr>
      <?php else: ?>
        <?php while ($r = $result->fetch_assoc()): ?>
        <tr>
          <td class="mono"><?= $r['Employee_ID'] ?></td>
          <td><?= htmlspecialchars($r['Employee_name'] ?? '—') ?></td>
          <td><span class="badge badge-info"><?= htmlspecialchars($r['Role'] ?? '—') ?></span></td>
          <td class="mono muted"><?= htmlspecialchars($r['Phone'] ?? '—') ?></td>
          <td class="mono muted"><?= $r['Hire_date'] ?></td>
          <td>
            <button class="btn btn-secondary" style="padding:.3rem .65rem;font-size:.78rem;"
              onclick="openEdit(<?= $r['Employee_ID'] ?>,'<?= addslashes($r['Employee_name']) ?>','<?= addslashes($r['Phone']) ?>','<?= addslashes($r['Role']) ?>','<?= $r['Hire_date'] ?>')">
              Edit
            </button>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this employee?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $r['Employee_ID'] ?>">
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
      <span class="modal-title">Add Employee</span>
      <button class="modal-close" onclick="closeModal('addModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input class="form-control" name="name" required placeholder="Jane Doe">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Role</label>
          <input class="form-control" name="role" placeholder="e.g. Sales Manager">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input class="form-control" name="phone" placeholder="555-0100">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Hire Date</label>
        <input class="form-control" type="date" name="hire_date" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Employee</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Employee</span>
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
          <label class="form-label">Role</label>
          <input class="form-control" name="role" id="edit-role">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input class="form-control" name="phone" id="edit-phone">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Hire Date</label>
        <input class="form-control" type="date" name="hire_date" id="edit-hire">
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
function openEdit(id, name, phone, role, hire) {
  document.getElementById('edit-id').value   = id;
  document.getElementById('edit-name').value = name;
  document.getElementById('edit-phone').value= phone;
  document.getElementById('edit-role').value = role;
  document.getElementById('edit-hire').value = hire;
  openModal('editModal');
}
</script>
</body>
</html>
