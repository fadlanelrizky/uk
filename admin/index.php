<?php
$role_allowed = 'admin';
$title = "Dashboard";
require_once '../includes/header.php';

// Stats
$total_users = $conn->query("SELECT COUNT(*) as t FROM users WHERE role='user'")->fetch_assoc()['t'];
$total_event = $conn->query("SELECT COUNT(*) as t FROM event")->fetch_assoc()['t'];
$total_income = $conn->query("SELECT SUM(total) as t FROM orders WHERE status='paid'")->fetch_assoc()['t'];
$total_terjual = $conn->query("SELECT SUM(qty) as t FROM order_detail od JOIN orders o ON od.id_order=o.id_order WHERE o.status='paid'")->fetch_assoc()['t'];

// Data Grafik: Hari Ini, Minggu Ini, Bulan Ini
$today_date = date('Y-m-d');
$week_start = date('Y-m-d', strtotime("-6 days"));
$month_start = date('Y-m-01');

$chart_today = ['labels' => [], 'revenue' => array_fill(0, 24, 0)];
for($i=0; $i<24; $i++) $chart_today['labels'][] = str_pad($i, 2, '0', STR_PAD_LEFT).':00';
$total_today = 0;
$q_today = $conn->query("SELECT HOUR(tanggal_order) as h, SUM(total) as t FROM orders WHERE status='paid' AND DATE(tanggal_order) = '$today_date' GROUP BY HOUR(tanggal_order)");
while($r = $q_today->fetch_assoc()) {
    $chart_today['revenue'][(int)$r['h']] = (int)$r['t'];
    $total_today += (int)$r['t'];
}

$chart_week = ['labels' => [], 'revenue' => []];
$week_dates = [];
for($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_week['labels'][] = date('d M', strtotime($d));
    $chart_week['revenue'][] = 0;
    $week_dates[$d] = 6 - $i;
}
$total_week = 0;
$q_week = $conn->query("SELECT DATE(tanggal_order) as d, SUM(total) as t FROM orders WHERE status='paid' AND DATE(tanggal_order) >= '$week_start' GROUP BY DATE(tanggal_order)");
while($r = $q_week->fetch_assoc()) {
    if(isset($week_dates[$r['d']])) {
        $chart_week['revenue'][$week_dates[$r['d']]] = (int)$r['t'];
        $total_week += (int)$r['t'];
    }
}

$days_in_month = (int)date('t');
$chart_month = ['labels' => [], 'revenue' => array_fill(0, $days_in_month, 0)];
for($i=1; $i<=$days_in_month; $i++) $chart_month['labels'][] = str_pad($i, 2, '0', STR_PAD_LEFT) . ' ' . date('M');
$total_month = 0;
$q_month = $conn->query("SELECT DAY(tanggal_order) as d, SUM(total) as t FROM orders WHERE status='paid' AND DATE(tanggal_order) >= '$month_start' GROUP BY DAY(tanggal_order)");
while($r = $q_month->fetch_assoc()) {
    $idx = (int)$r['d'] - 1;
    if(isset($chart_month['revenue'][$idx])) {
        $chart_month['revenue'][$idx] = (int)$r['t'];
        $total_month += (int)$r['t'];
    }
}

$chart_data_js = [
    'today' => ['labels' => $chart_today['labels'], 'revenue' => $chart_today['revenue'], 'total' => $total_today],
    'week' => ['labels' => $chart_week['labels'], 'revenue' => $chart_week['revenue'], 'total' => $total_week],
    'month' => ['labels' => $chart_month['labels'], 'revenue' => $chart_month['revenue'], 'total' => $total_month]
];

// Data Tambahan untuk Dashboard
$recent_orders = $conn->query("
    SELECT o.id_order, o.tanggal_order, o.total, o.status, u.nama_lengkap 
    FROM orders o 
    JOIN users u ON o.id_user = u.id_user 
    ORDER BY o.tanggal_order DESC LIMIT 5
");

$bestselling_events = $conn->query("
    SELECT e.nama_event, v.nama_venue, 
        (
            SELECT IFNULL(SUM(od.qty), 0) 
            FROM order_detail od 
            JOIN tiket t ON od.id_tiket = t.id_tiket 
            JOIN orders o ON od.id_order = o.id_order 
            WHERE t.id_event = e.id_event AND o.status = 'paid'
        ) as total_terjual
    FROM event e 
    JOIN venue v ON e.id_venue = v.id_venue 
    ORDER BY total_terjual DESC LIMIT 4
");
?>

<div class="row text-white mb-4">
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-primary text-white text-center p-3 h-100 shadow-sm" style="border:none; border-radius:12px;">
            <h5 class="text-white-50 mb-2 mt-1">Total User</h5>
            <h2 class="mb-1"><?= (int)$total_users ?></h2>
        </div>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-success text-white text-center p-3 h-100 shadow-sm" style="border:none; border-radius:12px;">
            <h5 class="text-white-50 mb-2 mt-1">Pendapatan</h5>
            <h2 class="mb-1">Rp <?= number_format((float)$total_income, 0, ',', '.') ?></h2>
        </div>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="card bg-warning text-dark text-center p-3 h-100 shadow-sm" style="border:none; border-radius:12px;">
            <h5 class="opacity-75 mb-2 mt-1">Tiket Terjual</h5>
            <h2 class="mb-1"><?= (int)$total_terjual ?: 0 ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-dark text-center p-3 h-100 shadow-sm" style="border:none; border-radius:12px;">
            <h5 class="opacity-75 mb-2 mt-1">Total Event</h5>
            <h2 class="mb-1"><?= (int)$total_event ?></h2>
        </div>
    </div>
</div>

<div class="row mt-2 gy-4">
    <div class="col-lg-8">
        <div class="card shadow-lg border-secondary h-100" style="border-radius:12px; overflow:hidden;">
            <div class="card-header bg-dark text-white border-bottom border-secondary d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 fs-6"><i class="bi bi-bar-chart-fill me-2 text-info"></i>Grafik Pendapatan</h5>
                    <select id="chartPeriod" class="form-select form-select-sm bg-dark text-white border-secondary" style="width: auto; font-size: 0.8rem; cursor: pointer;">
                        <option value="today">Hari Ini</option>
                        <option value="week" selected>7 Hari Terakhir</option>
                        <option value="month">Bulan Ini</option>
                    </select>
                </div>
                <span id="chartTotalBadge" class="badge bg-success bg-opacity-25 text-success border border-success fw-normal px-2 py-1">
                    <i class="bi bi-wallet2 me-1"></i>Total: Rp <?= number_format($total_week, 0, ',', '.') ?>
                </span>
            </div>
            <div class="card-body p-3 p-md-4" style="background: rgba(15,23,42,0.6);">
                <canvas id="revenueChart" style="max-height: 300px; width: 100%;"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow-lg border-secondary h-100" style="border-radius:12px; overflow:hidden;">
            <div class="card-header bg-dark text-white border-bottom border-secondary d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-6"><i class="bi bi-star-fill me-2 text-warning"></i>Event Terlaris</h5>
                <a href="event.php" class="btn btn-sm btn-outline-secondary py-0" style="font-size:0.75rem;">Selengkapnya</a>
            </div>
            <div class="card-body p-0" style="background: rgba(15,23,42,0.4);">
                <ul class="list-group list-group-flush">
                    <?php if($bestselling_events->num_rows > 0): ?>
                        <?php while($ev = $bestselling_events->fetch_assoc()): ?>
                            <li class="list-group-item bg-transparent text-white border-secondary py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1 text-truncate" style="max-width: 200px;"><?= htmlspecialchars($ev['nama_event']) ?></h6>
                                        <small class="text-white-50"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($ev['nama_venue']) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge" style="background:rgba(245,158,11,0.15);color:#fbbf24;font-size:0.75rem;"><i class="bi bi-ticket-perforated me-1"></i><?= (int)$ev['total_terjual'] ?> Terjual</span>
                                    </div>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="list-group-item bg-transparent text-white-50 text-center py-4">Belum ada data event.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-lg border-secondary" style="border-radius:12px; overflow:hidden;">
            <div class="card-header bg-dark text-white border-bottom border-secondary d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-6"><i class="bi bi-receipt me-2 text-primary"></i>Transaksi Terbaru</h5>
                <a href="orders.php" class="btn btn-sm btn-outline-secondary py-0" style="font-size:0.75rem;">Lihat Semua</a>
            </div>
            <div class="card-body p-0" style="background: rgba(15,23,42,0.4);">
                <div class="table-responsive">
                    <table class="table table-hover text-white m-0 border-secondary">
                        <thead style="border-bottom: 2px solid #334155;">
                            <tr>
                                <th class="ps-4">ID Order</th>
                                <th>Pelanggan</th>
                                <th>Tanggal</th>
                                <th>Total</th>
                                <th class="pe-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($recent_orders->num_rows > 0): ?>
                                <?php while($ro = $recent_orders->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4 align-middle fw-medium">#<?= str_pad($ro['id_order'], 4, '0', STR_PAD_LEFT) ?></td>
                                        <td class="align-middle"><?= htmlspecialchars($ro['nama_lengkap']) ?></td>
                                        <td class="align-middle text-white-50" style="font-size: 0.85rem;"><?= date('d M Y, H:i', strtotime($ro['tanggal_order'])) ?></td>
                                        <td class="align-middle">Rp <?= number_format($ro['total'], 0, ',', '.') ?></td>
                                        <td class="pe-4 text-center align-middle">
                                            <?php if($ro['status'] == 'paid'): ?>
                                                <span class="badge bg-success bg-opacity-25 text-success border border-success"><i class="bi bi-check-circle me-1"></i>Paid</span>
                                            <?php elseif($ro['status'] == 'pending'): ?>
                                                <span class="badge bg-warning bg-opacity-25 text-warning border border-warning"><i class="bi bi-clock-history me-1"></i>Pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger bg-opacity-25 text-danger border border-danger"><i class="bi bi-x-circle me-1"></i>Batal</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-4 text-white-50">Belum ada transaksi sama sekali.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    // Create modern gradient for bars
    let gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(56, 189, 248, 1)');   // Light blue top
    gradient.addColorStop(1, 'rgba(59, 130, 246, 0.2)'); // Darker blue transparent bottom

    let hoverGradient = ctx.createLinearGradient(0, 0, 0, 300);
    hoverGradient.addColorStop(0, 'rgba(14, 165, 233, 1)'); 
    hoverGradient.addColorStop(1, 'rgba(37, 99, 235, 0.4)');

    const chartData = <?= json_encode($chart_data_js) ?>;

    let revenueChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData['week'].labels,
            datasets: [{
                label: 'Pendapatan',
                data: chartData['week'].revenue,
                backgroundColor: gradient,
                hoverBackgroundColor: hoverGradient,
                borderRadius: 6,
                borderSkipped: false,
                barPercentage: 0.5,
                categoryPercentage: 0.7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(30, 41, 59, 0.95)',
                    titleColor: '#94a3b8',
                    titleFont: { size: 13, family: "'Plus Jakarta Sans', sans-serif", weight: '500' },
                    bodyColor: '#10b981', // Emerald color for revenue
                    bodyFont: { size: 15, family: "'Plus Jakarta Sans', sans-serif", weight: 'bold' },
                    borderColor: 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            let value = context.raw;
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)',
                        borderDash: [5, 5],
                        drawBorder: false,
                        tickLength: 0
                    },
                    ticks: {
                        color: '#94a3b8',
                        font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
                        padding: 10,
                        callback: function(value) {
                            if(value >= 1000000) {
                                return 'Rp ' + (value / 1000000) + ' Jt';
                            } else if(value >= 1000) {
                                return 'Rp ' + (value / 1000) + ' Rb';
                            }
                            return 'Rp ' + value;
                        }
                    }
                },
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        color: '#94a3b8',
                        font: { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
                        padding: 5
                    }
                }
            }
        }
    });

    const periodSelect = document.getElementById('chartPeriod');
    const totalBadge = document.getElementById('chartTotalBadge');

    periodSelect.addEventListener('change', function() {
        const period = this.value;
        const data = chartData[period];
        
        revenueChart.data.labels = data.labels;
        revenueChart.data.datasets[0].data = data.revenue;
        revenueChart.update();

        totalBadge.innerHTML = '<i class="bi bi-wallet2 me-1"></i>Total: Rp ' + new Intl.NumberFormat('id-ID').format(data.total);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
