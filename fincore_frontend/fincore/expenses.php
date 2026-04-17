<?php
$conn = new mysqli("localhost","root","","fincore");
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);

$msg = ''; $type = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
  if ($_POST['action']==='add') {
    $stmt = $conn->prepare("INSERT INTO expenses (Employee_ID,Category,Amount,Date) VALUES (?,?,?,?)");
    $stmt->bind_param("isds",$_POST['employee_id'],$_POST['category'],$_POST['amount'],$_POST['date']);
    if ($stmt->execute()) { $msg='Expense recorded.'; $type='success'; }
    else { $msg='Error: '.$conn->error; $type='error'; }
    $stmt->close();
  }
  if ($_POST['action']==='delete') {
    $stmt = $conn->prepare("DELETE FROM expenses WHERE Expense_ID=?");
    $stmt->bind_param("i",$_POST['id']);
    if ($stmt->execute()) { $msg='Expense deleted.'; $type='success'; }
    else { $msg='Error: '.$conn->error; $type='error'; }
    $stmt->close();
  }
}

$employees = $conn->query("SELECT Employee_ID, Employee_name FROM employee ORDER BY Employee_name");

$filter_cat = isset($_GET['cat']) ? $conn->real_escape_string($_GET['cat']) : '';
$sql = "SELECT ex.*, e.Employee_name FROM expenses ex
        LEFT JOIN employee e ON ex.Employee_ID = e.Employee_ID";
if ($filter_cat) $sql .= " WHERE ex.Category = '$filter_cat'";
$sql .= " ORDER BY ex.Date DESC";
$result = $conn->query($sql);

$categories = $conn->query("SELECT DISTINCT Category FROM expenses ORDER BY Category");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FinCore – Expenses</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
  <header class="page-header">
    <div>
      <h1>Expenses</h1>
      <p class="page-sub">Track business expenses by category</p>
    </div>
    <div class="header-actions">
      <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Expense</button>
    </div>
  </header>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $type ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="page-card">
    <div class="filter-bar">
      <form method="GET" style="display:flex;gap:.75rem;flex:1;flex-wrap:wrap;align-items:center;">
        <label class="form-label" style="margin:0;white-space:nowrap;">Filter by category:</label>
        <select class="form-control" name="cat" style="width:auto;" onchange="this.form.submit()">
          <option value="">All Categories</option>
          <?php while ($c = $categories->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($c['Category']) ?>" <?= $filter_cat===$c['Category']?'selected':'' ?>>
              <?= htmlspecialchars($c['Category']) ?>
            </option>
          <?php endwhile; ?>
        </select>
        <?php if ($filter_cat): ?><a href="expenses.php" class="btn btn-secondary">Clear</a><?php endif; ?>
      </form>
    </div>

    <table class="data-table">
      <thead>
        <tr><th>#</th><th>Employee</th><th>Category</th><th>Date</th><th>Amount</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="6" class="empty-msg">No expenses found.</td></tr>
      <?php else: ?>
        <?php while ($r = $result->fetch_assoc()): ?>
        <tr>
          <td class="mono"><?= $r['Expense_ID'] ?></td>
          <td><?= htmlspecialchars($r['Employee_name'] ?? '—') ?></td>
          <td><span class="badge badge-neutral"><?= htmlspecialchars($r['Category']) ?></span></td>
          <td class="mono muted"><?= $r['Date'] ?></td>
          <td class="mono red-text">$<?= number_format($r['Amount'],2) ?></td>
          <td>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this expense?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $r['Expense_ID'] ?>">
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
      <span class="modal-title">Add Expense</span>
      <button class="modal-close" onclick="closeModal('addModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label class="form-label">Employee</label>
        <select class="form-control" name="employee_id" required>
          <option value="">— Select —</option>
          <?php while ($e = $employees->fetch_assoc()): ?>
            <option value="<?= $e['Employee_ID'] ?>"><?= htmlspecialchars($e['Employee_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Category</label>
          <input class="form-control" name="category" required placeholder="e.g. Supplies, Travel">
        </div>
        <div class="form-group">
          <label class="form-label">Amount ($)</label>
          <input class="form-control" type="number" name="amount" step="0.01" min="0" required placeholder="0.00">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Date</label>
        <input class="form-control" type="date" name="date" required value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Expense</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/app.js"></script>
</body>
</html>
