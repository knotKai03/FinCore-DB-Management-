<?php
$conn = new mysqli("localhost", "root", "", "fincore");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Stats
$total_sales     = $conn->query("SELECT COALESCE(SUM(Total_amount),0) AS v FROM sales")->fetch_assoc()['v'];
$total_expenses  = $conn->query("SELECT COALESCE(SUM(Amount),0) AS v FROM expenses")->fetch_assoc()['v'];
$total_customers = $conn->query("SELECT COUNT(*) AS v FROM customer")->fetch_assoc()['v'];
$total_products  = $conn->query("SELECT COUNT(*) AS v FROM product")->fetch_assoc()['v'];
$net_profit      = $total_sales - $total_expenses;

// Recent Sales
$recent_sales = $conn->query("
    SELECT s.Sale_ID, c.Name AS customer, e.Employee_name AS employee,
           s.Date, s.Total_amount
    FROM sales s
    LEFT JOIN customer c ON s.Customer_ID = c.Customer_ID
    LEFT JOIN employee e ON s.Employee_ID = e.Employee_ID
    ORDER BY s.Date DESC LIMIT 5
");

// Recent Expenses
$recent_expenses = $conn->query("
    SELECT ex.Expense_ID, e.Employee_name, ex.Category, ex.Amount, ex.Date
    FROM expenses ex
    LEFT JOIN employee e ON ex.Employee_ID = e.Employee_ID
    ORDER BY ex.Date DESC LIMIT 5
");

// Monthly sales for chart
$monthly = $conn->query("
    SELECT DATE_FORMAT(Date,'%b') AS month, SUM(Total_amount) AS total
    FROM sales
    GROUP BY YEAR(Date), MONTH(Date)
    ORDER BY YEAR(Date), MONTH(Date) DESC
    LIMIT 6
");
$months = []; $amounts = [];
while ($row = $monthly->fetch_assoc()) {
    array_unshift($months, $row['month']);
    array_unshift($amounts, floatval($row['total']));
}

// Low inventory
$low_stock = $conn->query("
    SELECT p.Product_name, i.Quantity
    FROM inventory i JOIN product p ON i.Product_ID = p.Product_ID
    WHERE i.Quantity < 10
    ORDER BY i.Quantity ASC LIMIT 5
");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FinCore – Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
  <header class="page-header">
    <div>
      <h1>Dashboard</h1>
      <p class="page-sub">Welcome back — here's your business overview</p>
    </div>
    <div class="header-actions">
      <span class="date-badge"><?= date('F j, Y') ?></span>
    </div>
  </header>

  <!-- KPI Cards -->
  <div class="kpi-grid">
    <div class="kpi-card kpi-green">
      <div class="kpi-icon">$</div>
      <div class="kpi-info">
        <span class="kpi-label">Total Revenue</span>
        <span class="kpi-value">$<?= number_format($total_sales,2) ?></span>
      </div>
    </div>
    <div class="kpi-card kpi-red">
      <div class="kpi-icon">↓</div>
      <div class="kpi-info">
        <span class="kpi-label">Total Expenses</span>
        <span class="kpi-value">$<?= number_format($total_expenses,2) ?></span>
      </div>
    </div>
    <div class="kpi-card <?= $net_profit >= 0 ? 'kpi-blue' : 'kpi-red' ?>">
      <div class="kpi-icon"><?= $net_profit >= 0 ? '↑' : '↓' ?></div>
      <div class="kpi-info">
        <span class="kpi-label">Net Profit</span>
        <span class="kpi-value">$<?= number_format(abs($net_profit),2) ?></span>
      </div>
    </div>
    <div class="kpi-card kpi-amber">
      <div class="kpi-icon">#</div>
      <div class="kpi-info">
        <span class="kpi-label">Customers</span>
        <span class="kpi-value"><?= $total_customers ?></span>
      </div>
    </div>
  </div>

  <!-- Chart + Low Stock -->
  <div class="grid-2col">
    <div class="card">
      <div class="card-header">
        <h2>Revenue Trend</h2>
        <span class="card-badge">Last 6 months</span>
      </div>
      <div class="chart-wrap">
        <canvas id="salesChart"></canvas>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Low Stock Alerts</h2>
        <a href="inventory.php" class="card-link">View all →</a>
      </div>
      <?php if ($low_stock->num_rows === 0): ?>
        <p class="empty-msg">All products are well-stocked.</p>
      <?php else: ?>
        <table class="data-table">
          <thead><tr><th>Product</th><th>Qty</th><th>Status</th></tr></thead>
          <tbody>
          <?php while ($r = $low_stock->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($r['Product_name']) ?></td>
              <td><?= $r['Quantity'] ?></td>
              <td><span class="badge badge-danger">Low</span></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent tables -->
  <div class="grid-2col">
    <div class="card">
      <div class="card-header">
        <h2>Recent Sales</h2>
        <a href="sales.php" class="card-link">View all →</a>
      </div>
      <table class="data-table">
        <thead><tr><th>#</th><th>Customer</th><th>Employee</th><th>Date</th><th>Amount</th></tr></thead>
        <tbody>
        <?php while ($r = $recent_sales->fetch_assoc()): ?>
          <tr>
            <td class="mono"><?= $r['Sale_ID'] ?></td>
            <td><?= htmlspecialchars($r['customer'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['employee'] ?? '—') ?></td>
            <td class="mono"><?= $r['Date'] ?></td>
            <td class="mono green-text">$<?= number_format($r['Total_amount'],2) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Recent Expenses</h2>
        <a href="expenses.php" class="card-link">View all →</a>
      </div>
      <table class="data-table">
        <thead><tr><th>#</th><th>Employee</th><th>Category</th><th>Date</th><th>Amount</th></tr></thead>
        <tbody>
        <?php while ($r = $recent_expenses->fetch_assoc()): ?>
          <tr>
            <td class="mono"><?= $r['Expense_ID'] ?></td>
            <td><?= htmlspecialchars($r['Employee_name'] ?? '—') ?></td>
            <td><span class="badge badge-neutral"><?= htmlspecialchars($r['Category']) ?></span></td>
            <td class="mono"><?= $r['Date'] ?></td>
            <td class="mono red-text">$<?= number_format($r['Amount'],2) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<script>
const ctx = document.getElementById('salesChart').getContext('2d');
const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
const gridColor = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)';
const textColor = isDark ? '#9a998f' : '#6b6a63';
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($months) ?>,
    datasets: [{
      label: 'Revenue',
      data: <?= json_encode($amounts) ?>,
      backgroundColor: 'rgba(30,180,120,0.75)',
      borderRadius: 6,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: gridColor }, ticks: { color: textColor, font: { family: 'Sora' } } },
      y: {
        grid: { color: gridColor },
        ticks: {
          color: textColor,
          font: { family: 'Sora' },
          callback: v => '$' + v.toLocaleString()
        }
      }
    }
  }
});
</script>
</body>
</html>
