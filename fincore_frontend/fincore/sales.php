<?php
$conn = new mysqli("localhost","root","","fincore");
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);

$msg = ''; $type = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
  if ($_POST['action']==='add') {
    $stmt = $conn->prepare("INSERT INTO sales (Customer_ID,Employee_ID,Date,Total_amount) VALUES (?,?,?,?)");
    $stmt->bind_param("iisd",$_POST['customer_id'],$_POST['employee_id'],$_POST['date'],$_POST['total']);
    if ($stmt->execute()) { $msg='Sale recorded.'; $type='success'; }
    else { $msg='Error: '.$conn->error; $type='error'; }
    $stmt->close();
  }
  if ($_POST['action']==='delete') {
    $stmt = $conn->prepare("DELETE FROM sales WHERE Sale_ID=?");
    $stmt->bind_param("i",$_POST['id']);
    if ($stmt->execute()) { $msg='Sale deleted.'; $type='success'; }
    else { $msg='Cannot delete — has linked payments.'; $type='error'; }
    $stmt->close();
  }
}

$customers = $conn->query("SELECT Customer_ID, Name FROM customer ORDER BY Name");
$employees = $conn->query("SELECT Employee_ID, Employee_name FROM employee ORDER BY Employee_name");

$search = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$sql = "SELECT s.Sale_ID, c.Name AS customer, e.Employee_name AS employee,
               s.Date, s.Total_amount
        FROM sales s
        LEFT JOIN customer c ON s.Customer_ID = c.Customer_ID
        LEFT JOIN employee e ON s.Employee_ID = e.Employee_ID";
if ($search) $sql .= " WHERE c.Name LIKE '%$search%' OR e.Employee_name LIKE '%$search%'";
$sql .= " ORDER BY s.Date DESC";
$result = $conn->query($sql);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FinCore – Sales</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
  <header class="page-header">
    <div>
      <h1>Sales</h1>
      <p class="page-sub">Track and manage all sales transactions</p>
    </div>
    <div class="header-actions">
      <button class="btn btn-primary" onclick="openModal('addModal')">+ Record Sale</button>
    </div>
  </header>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $type ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="page-card">
    <div class="filter-bar">
      <form method="GET" style="display:flex;gap:.75rem;flex:1;flex-wrap:wrap;">
        <input class="form-control search-input" name="q" placeholder="Search by customer or employee…" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-secondary">Search</button>
        <?php if ($search): ?><a href="sales.php" class="btn btn-secondary">Clear</a><?php endif; ?>
      </form>
    </div>

    <table class="data-table">
      <thead>
        <tr><th>Sale #</th><th>Customer</th><th>Employee</th><th>Date</th><th>Total</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="6" class="empty-msg">No sales records found.</td></tr>
      <?php else: ?>
        <?php while ($r = $result->fetch_assoc()): ?>
        <tr>
          <td class="mono">#<?= $r['Sale_ID'] ?></td>
          <td><?= htmlspecialchars($r['customer'] ?? '—') ?></td>
          <td class="muted"><?= htmlspecialchars($r['employee'] ?? '—') ?></td>
          <td class="mono muted"><?= $r['Date'] ?></td>
          <td class="mono green-text">$<?= number_format($r['Total_amount'],2) ?></td>
          <td>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete sale #<?= $r['Sale_ID'] ?>?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $r['Sale_ID'] ?>">
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

<!-- Add Sale Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Record Sale</span>
      <button class="modal-close" onclick="closeModal('addModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Customer</label>
          <select class="form-control" name="customer_id" required>
            <option value="">— Select —</option>
            <?php while ($c = $customers->fetch_assoc()): ?>
              <option value="<?= $c['Customer_ID'] ?>"><?= htmlspecialchars($c['Name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Employee</label>
          <select class="form-control" name="employee_id" required>
            <option value="">— Select —</option>
            <?php while ($e = $employees->fetch_assoc()): ?>
              <option value="<?= $e['Employee_ID'] ?>"><?= htmlspecialchars($e['Employee_name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date</label>
          <input class="form-control" type="date" name="date" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Total Amount ($)</label>
          <input class="form-control" type="number" name="total" step="0.01" min="0" required placeholder="0.00">
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Sale</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/app.js"></script>
</body>
</html>
