<?php
$conn = new mysqli("localhost","root","","fincore");
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);

$msg = ''; $type = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
  if ($_POST['action']==='add') {
    $stmt = $conn->prepare("INSERT INTO payments (Sale_ID,Payment_date,Payment_method,Amount_paid,Payment_status) VALUES (?,?,?,?,?)");
    $stmt->bind_param("issds",$_POST['sale_id'],$_POST['payment_date'],$_POST['method'],$_POST['amount'],$_POST['status']);
    if ($stmt->execute()) { $msg='Payment recorded.'; $type='success'; }
    else { $msg='Error: '.$conn->error; $type='error'; }
    $stmt->close();
  }
  if ($_POST['action']==='delete') {
    $stmt = $conn->prepare("DELETE FROM payments WHERE Payment_ID=?");
    $stmt->bind_param("i",$_POST['id']);
    if ($stmt->execute()) { $msg='Payment deleted.'; $type='success'; }
    else { $msg='Error: '.$conn->error; $type='error'; }
    $stmt->close();
  }
}

$sales = $conn->query("SELECT Sale_ID, Total_amount, Date FROM sales ORDER BY Sale_ID DESC");

$filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$sql = "SELECT p.*, s.Total_amount AS sale_total FROM payments p LEFT JOIN sales s ON p.Sale_ID = s.Sale_ID";
if ($filter_status) $sql .= " WHERE p.Payment_status = '$filter_status'";
$sql .= " ORDER BY p.Payment_date DESC";
$result = $conn->query($sql);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FinCore – Payments</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
  <header class="page-header">
    <div>
      <h1>Payments</h1>
      <p class="page-sub">Payment records and statuses</p>
    </div>
    <div class="header-actions">
      <button class="btn btn-primary" onclick="openModal('addModal')">+ Record Payment</button>
    </div>
  </header>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $type ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="page-card">
    <div class="filter-bar">
      <a href="payments.php" class="btn <?= !$filter_status?'btn-primary':'btn-secondary' ?>">All</a>
      <a href="payments.php?status=Paid"    class="btn <?= $filter_status==='Paid'?'btn-primary':'btn-secondary' ?>">Paid</a>
      <a href="payments.php?status=Pending" class="btn <?= $filter_status==='Pending'?'btn-primary':'btn-secondary' ?>">Pending</a>
      <a href="payments.php?status=Failed"  class="btn <?= $filter_status==='Failed'?'btn-primary':'btn-secondary' ?>">Failed</a>
    </div>

    <table class="data-table">
      <thead>
        <tr><th>#</th><th>Sale #</th><th>Date</th><th>Method</th><th>Amount</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="7" class="empty-msg">No payments found.</td></tr>
      <?php else: ?>
        <?php while ($r = $result->fetch_assoc()): ?>
        <?php
          $badge = 'badge-neutral';
          if ($r['Payment_status']==='Paid')    $badge = 'badge-success';
          if ($r['Payment_status']==='Pending') $badge = 'badge-warning';
          if ($r['Payment_status']==='Failed')  $badge = 'badge-danger';
        ?>
        <tr>
          <td class="mono"><?= $r['Payment_ID'] ?></td>
          <td class="mono">#<?= $r['Sale_ID'] ?></td>
          <td class="mono muted"><?= $r['Payment_date'] ?></td>
          <td class="muted"><?= htmlspecialchars($r['Payment_method'] ?? '—') ?></td>
          <td class="mono green-text">$<?= number_format($r['Amount_paid'],2) ?></td>
          <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($r['Payment_status'] ?? '—') ?></span></td>
          <td>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this payment?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $r['Payment_ID'] ?>">
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
      <span class="modal-title">Record Payment</span>
      <button class="modal-close" onclick="closeModal('addModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label class="form-label">Sale</label>
        <select class="form-control" name="sale_id" required>
          <option value="">— Select Sale —</option>
          <?php while ($s = $sales->fetch_assoc()): ?>
            <option value="<?= $s['Sale_ID'] ?>">Sale #<?= $s['Sale_ID'] ?> — $<?= number_format($s['Total_amount'],2) ?> (<?= $s['Date'] ?>)</option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Amount Paid ($)</label>
          <input class="form-control" type="number" name="amount" step="0.01" min="0" required placeholder="0.00">
        </div>
        <div class="form-group">
          <label class="form-label">Date</label>
          <input class="form-control" type="date" name="payment_date" required value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Method</label>
          <select class="form-control" name="method">
            <option>Cash</option>
            <option>Credit Card</option>
            <option>Debit Card</option>
            <option>Bank Transfer</option>
            <option>Check</option>
            <option>Other</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select class="form-control" name="status">
            <option>Paid</option>
            <option>Pending</option>
            <option>Failed</option>
          </select>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Payment</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/app.js"></script>
</body>
</html>
