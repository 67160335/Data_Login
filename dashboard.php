<?php
// dashboard.php
// Sales Dashboard (Chart.js + Bootstrap) using mysqli (no PDO)

// ===== DB connection =====
$DB_HOST = 'localhost';
$DB_USER = 's67160335';      // <- your DB user
$DB_PASS = 'F5TASXvT';  // <- your DB password
$DB_NAME = 's67160335';      // <- change to your db name

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  die('Database connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

function fetch_all($mysqli, $sql) {
  $res = $mysqli->query($sql);
  if (!$res) { return []; }
  $rows = [];
  while ($row = $res->fetch_assoc()) { $rows[] = $row; }
  $res->free();
  return $rows;
}

// 1. Monthly sales
$monthly = fetch_all($mysqli, "
  SELECT CONCAT(d.y,'-',LPAD(d.m,2,'0')) AS ym,
         SUM(f.net_amount) AS net_sales
  FROM fact_sales f
  JOIN dim_date d ON d.date_key = f.date_key
  GROUP BY d.y, d.m
  ORDER BY d.y, d.m
");

// 2. Sales by category
$category = fetch_all($mysqli, "
  SELECT p.category,
         SUM(f.net_amount) AS net_sales
  FROM fact_sales f
  JOIN dim_product p ON p.product_id = f.product_id
  GROUP BY p.category
  ORDER BY net_sales DESC
");

// 3. Sales by region
$region = fetch_all($mysqli, "
  SELECT s.region,
         SUM(f.net_amount) AS net_sales
  FROM fact_sales f
  JOIN dim_store s ON s.store_id = f.store_id
  GROUP BY s.region
  ORDER BY net_sales DESC
");

// 4. Top 10 products
$topProducts = fetch_all($mysqli, "
  SELECT p.product_name,
         SUM(f.quantity) AS qty_sold,
         SUM(f.net_amount) AS net_sales
  FROM fact_sales f
  JOIN dim_product p ON p.product_id = f.product_id
  GROUP BY p.product_name
  ORDER BY net_sales DESC
  LIMIT 10
");

// 5. Payment share
$payment = fetch_all($mysqli, "
  SELECT payment_method,
         SUM(net_amount) AS net_sales
  FROM fact_sales
  GROUP BY payment_method
");

// 6. Hourly sales
$hourly = fetch_all($mysqli, "
  SELECT hour_of_day,
         SUM(net_amount) AS net_sales
  FROM fact_sales
  GROUP BY hour_of_day
  ORDER BY hour_of_day
");

// 7. New vs returning customers
$newReturning = fetch_all($mysqli, "
  SELECT
    f.date_key,
    SUM(CASE WHEN c.sign_up_date = f.date_key THEN f.net_amount ELSE 0 END) AS new_customer_sales,
    SUM(CASE WHEN c.sign_up_date < f.date_key THEN f.net_amount ELSE 0 END) AS returning_sales
  FROM fact_sales f
  JOIN dim_customer c ON c.customer_id = f.customer_id
  GROUP BY f.date_key
  ORDER BY f.date_key
");

// 8. KPI 30d
$kpis = fetch_all($mysqli, "
  SELECT
    (SELECT COALESCE(SUM(net_amount),0)
     FROM fact_sales
     WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS sales_30d,
    (SELECT COALESCE(SUM(quantity),0)
     FROM fact_sales
     WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS qty_30d,
    (SELECT COALESCE(COUNT(DISTINCT customer_id),0)
     FROM fact_sales
     WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS buyers_30d
");
$kpi = $kpis ? $kpis[0] : ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0];

function nf($n) { return number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Retail DW Sales Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    body { background: #0f172a; color: #e2e8f0; }
    .card { background: #111827; border: 1px solid rgba(255,255,255,0.06); border-radius: 1rem; }
    .card h5 { color: #e5e7eb; }
    .kpi { font-size: 1.4rem; font-weight: 700; }
    .sub { color: #93c5fd; font-size: .9rem; }
    .grid { display: grid; gap: 1rem; grid-template-columns: repeat(12, 1fr); }
    .col-12 { grid-column: span 12; }
    .col-6 { grid-column: span 6; }
    .col-4 { grid-column: span 4; }
    .col-8 { grid-column: span 8; }
    @media (max-width: 991px) {
      .col-6, .col-4, .col-8 { grid-column: span 12; }
    }
    canvas { max-height: 360px; }
  </style>
</head>
<body class="p-3 p-md-4">
  <div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="mb-0">Retail DW Sales Dashboard</h2>
      <span class="sub">Source: MySQL tables (no VIEW)</span>
    </div>

    <!-- KPI -->
    <div class="grid mb-3">
      <div class="card p-3 col-4">
        <h5>Sales (last 30 days)</h5>
        <div class="kpi">?<?= nf($kpi['sales_30d']) ?></div>
      </div>
      <div class="card p-3 col-4">
        <h5>Units sold (last 30 days)</h5>
        <div class="kpi"><?= number_format((int)$kpi['qty_30d']) ?> items</div>
      </div>
      <div class="card p-3 col-4">
        <h5>Unique buyers (last 30 days)</h5>
        <div class="kpi"><?= number_format((int)$kpi['buyers_30d']) ?> customers</div>
      </div>
    </div>

    <!-- Charts grid -->
    <div class="grid">

      <div class="card p-3 col-8">
        <h5 class="mb-2">Monthly sales (from fact_sales)</h5>
        <canvas id="chartMonthly"></canvas>
      </div>

      <div class="card p-3 col-4">
        <h5 class="mb-2">Sales by category</h5>
        <canvas id="chartCategory"></canvas>
      </div>

      <div class="card p-3 col-6">
        <h5 class="mb-2">Top 10 products</h5>
        <canvas id="chartTopProducts"></canvas>
      </div>

      <div class="card p-3 col-6">
        <h5 class="mb-2">Sales by region</h5>
        <canvas id="chartRegion"></canvas>
      </div>

      <div class="card p-3 col-6">
        <h5 class="mb-2">Payment methods</h5>
        <canvas id="chartPayment"></canvas>
      </div>

      <div class="card p-3 col-6">
        <h5 class="mb-2">Hourly sales</h5>
        <canvas id="chartHourly"></canvas>
      </div>

      <div class="card p-3 col-12">
        <h5 class="mb-2">New vs returning customers (daily)</h5>
        <canvas id="chartNewReturning"></canvas>
      </div>

    </div>
  </div>

<script>
// data from PHP
const monthly = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const category = <?= json_encode($category, JSON_UNESCAPED_UNICODE) ?>;
const region = <?= json_encode($region, JSON_UNESCAPED_UNICODE) ?>;
const topProducts = <?= json_encode($topProducts, JSON_UNESCAPED_UNICODE) ?>;
const payment = <?= json_encode($payment, JSON_UNESCAPED_UNICODE) ?>;
const hourly = <?= json_encode($hourly, JSON_UNESCAPED_UNICODE) ?>;
const newReturning = <?= json_encode($newReturning, JSON_UNESCAPED_UNICODE) ?>;

const toXY = (arr, x, y) => ({ labels: arr.map(o => o[x]), values: arr.map(o => parseFloat(o[y] || 0)) });

// Monthly
(() => {
  const {labels, values} = toXY(monthly, 'ym', 'net_sales');
  new Chart(document.getElementById('chartMonthly'), {
    type: 'line',
    data: { labels, datasets: [{ label: 'Sales (THB)', data: values, tension: .25, fill: true }] },
    options: { plugins: { legend: { labels: { color: '#e5e7eb' } } }, scales: {
      x: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } },
      y: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } }
    }}
  });
})();

// Category
(() => {
  const {labels, values} = toXY(category, 'category', 'net_sales');
  new Chart(document.getElementById('chartCategory'), {
    type: 'doughnut',
    data: { labels, datasets: [{ data: values }] },
    options: { plugins: { legend: { position: 'bottom', labels: { color: '#e5e7eb' } } } }
  });
})();

// Top products
(() => {
  const labels = topProducts.map(o => o.product_name);
  const qty = topProducts.map(o => parseInt(o.qty_sold));
  new Chart(document.getElementById('chartTopProducts'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Units sold', data: qty }] },
    options: {
      indexAxis: 'y',
      plugins: { legend: { labels: { color: '#e5e7eb' } } },
      scales: {
        x: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } },
        y: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } }
      }
    }
  });
})();

// Region
(() => {
  const {labels, values} = toXY(region, 'region', 'net_sales');
  new Chart(document.getElementById('chartRegion'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Sales (THB)', data: values }] },
    options: { plugins: { legend: { labels: { color: '#e5e7eb' } } }, scales: {
      x: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } },
      y: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } }
    }}
  });
})();

// Payment
(() => {
  const {labels, values} = toXY(payment, 'payment_method', 'net_sales');
  new Chart(document.getElementById('chartPayment'), {
    type: 'pie',
    data: { labels, datasets: [{ data: values }] },
    options: { plugins: { legend: { position: 'bottom', labels: { color: '#e5e7eb' } } } }
  });
})();

// Hourly
(() => {
  const {labels, values} = toXY(hourly, 'hour_of_day', 'net_sales');
  new Chart(document.getElementById('chartHourly'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Sales (THB)', data: values }] },
    options: { plugins: { legend: { labels: { color: '#e5e7eb' } } }, scales: {
      x: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } },
      y: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } }
    }}
  });
})();

// New vs Returning
(() => {
  const labels = newReturning.map(o => o.date_key);
  const newC = newReturning.map(o => parseFloat(o.new_customer_sales || 0));
  const retC = newReturning.map(o => parseFloat(o.returning_sales || 0));
  new Chart(document.getElementById('chartNewReturning'), {
    type: 'line',
    data: { labels,
      datasets: [
        { label: 'New customers (THB)', data: newC, tension: .25, fill: false },
        { label: 'Returning customers (THB)', data: retC, tension: .25, fill: false }
      ]
    },
    options: { plugins: { legend: { labels: { color: '#e5e7eb' } } }, scales: {
      x: { ticks: { color: '#c7d2fe', maxTicksLimit: 12 }, grid: { color: 'rgba(255,255,255,.08)' } },
      y: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } }
    }}
  });
})();
</script>

</body>
</html>
