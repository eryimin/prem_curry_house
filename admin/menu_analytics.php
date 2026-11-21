<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','staff'],true)) { header('Location: ../login.php'); exit; }
require_once __DIR__ . '/../config/database.php';
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
try {
 $stats=$pdo->query("SELECT COUNT(*) total, COALESCE(SUM(is_available=1),0) available, COALESCE(SUM(is_available=0),0) unavailable FROM menu_items")->fetch(PDO::FETCH_ASSOC);
 $categories=$pdo->query("SELECT c.category_name name, COUNT(m.item_id) total, COALESCE(SUM(m.is_available=1),0) available FROM categories c LEFT JOIN menu_items m ON m.category_id=c.category_id GROUP BY c.category_id,c.category_name ORDER BY total DESC,c.category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $ex) { error_log('Menu analytics: '.$ex->getMessage()); http_response_code(500); exit('Unable to load Menu Analytics. Check your database connection.'); }
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Menu Analytics | Prem Curry House</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>

:root {--forest:#294e35;--green:#527b35;--cream:#f8f6ef;--muted:#718074;--stroke:#e5e9df;--gold:#c99c51}

body.analytics-page {background:var(--cream);color:#253b2a;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}

.analytics-nav {background:var(--forest)!important;box-shadow:0 4px 16px #203d2918}

.analytics-nav .navbar-brand {color:#fff!important;letter-spacing:.04em;font-size:1rem}

.analytics-nav .nav-link {color:#ebf2e9!important;font-size:.86rem}

.analytics-nav .nav-link:hover {color:#f6d89b!important}

.analytics-container {max-width:1500px;margin:auto;padding:28px 22px 55px}

.eyebrow {text-transform:uppercase;letter-spacing:.13em;color:var(--green);font-weight:800;font-size:.73rem}

.page-title {font-weight:800;font-size:clamp(1.5rem,2.7vw,2.25rem);letter-spacing:-.04em;color:#203e2a}

.subtle {color:#728173;font-size:.88rem}

.panel {background:#fff;border:1px solid var(--stroke);border-radius:16px;box-shadow:0 5px 26px #26472b0b}

.filter-panel {padding:18px 20px}

.filter-panel label {font-size:.77rem;font-weight:700;color:#58715b}

.form-select,.form-control {border-color:#dbe4d7}

.form-select:focus,.form-control:focus {border-color:#85a36e;box-shadow:0 0 0 .2rem #527b3522}

.btn-forest {background:var(--forest);color:white;border:1px solid var(--forest)}

.btn-forest:hover {background:#3b6845;color:white}

.kpi {padding:19px 20px;min-height:122px;display:flex;justify-content:space-between;gap:10px}

.kpi .k-label {font-size:.82rem;color:#738272;font-weight:600;margin-bottom:8px}

.kpi .k-value {font-size:clamp(1.3rem,2vw,1.8rem);font-weight:800;line-height:1.1;color:#24432b;letter-spacing:-.035em}

.kpi .k-hint {font-size:.7rem;color:#89968a;margin-top:6px}

.kpi .k-icon {width:43px;height:43px;border-radius:12px;background:#eff4e9;color:var(--green);display:grid;place-items:center;font-size:1.3rem;flex:none}

.section-heading {font-size:1.13rem;font-weight:800;letter-spacing:-.02em;color:#24432b}

.chart-card {padding:18px;min-height:306px;height:100%;display:flex;flex-direction:column}

.chart-title {font-size:.96rem;font-weight:800;margin:0;color:#28472c}

.chart-subtitle {font-size:.73rem;color:#879586;margin-top:3px}

.zoom-btn {border:1px solid #e1e9dc;background:#f7faf5;color:#527b35;border-radius:8px;width:32px;height:32px;display:grid;place-items:center}

.zoom-btn:hover {background:#edf4e9}

.chart-holder {height:230px;position:relative;min-height:0;flex:1;margin-top:14px}

.chart-holder canvas {width:100%!important;height:100%!important}



/* Compact, filter-aware stacked plan bars; also used in the enlarge modal */





.plan-bars {

    margin-top: 15px;

    display: flex;

    flex-direction: column;

    justify-content: space-evenly;

    gap: 12px;

    flex: 1;

    min-height: 0;

    max-height: none;

    overflow: visible;

    padding-bottom: 10px;

}





.plan-line {min-width:0}

.plan-name {font-weight:700;font-size:.78rem;color:#304a35;line-height:1.25}

.plan-total {font-size:.72rem;font-weight:800;color:#466647;white-space:nowrap}



.plan-track {

    height: 18px;

    border-radius: 30px;

    background: #edf1e9;

    overflow: hidden;

    display: flex;

    margin-top: 8px;

}



.plan-segment {display:block;height:100%;flex:none}

.plan-legend {display:flex;gap:9px;flex-wrap:wrap;margin-top:6px}

.plan-legend span {font-size:.68rem;color:#687969;white-space:nowrap}

.plan-legend i {display:inline-block;vertical-align:middle;width:8px;height:8px;border-radius:50%;margin-right:4px}

 .plan-zero {font-size:.7rem;color:#819083;margin-top:5px}

/* Always-visible status key at the bottom of the popular plans card */

.plan-status-key {margin-top:auto;padding-top:14px;border-top:1px solid #edf1e9;display:flex;flex-wrap:wrap;gap:9px 13px;align-items:center}

.plan-status-key-label {width:100%;font-size:.70rem;font-weight:800;color:#536b56}

.plan-status-key-item {display:inline-flex;align-items:center;gap:5px;font-size:.72rem;color:#536b56}

.plan-status-key-dot {width:10px;height:10px;display:inline-block;border-radius:3px;flex:none}

.plan-bars-large {max-height:65vh;overflow-y:auto;gap:27px;padding:15px 6px}

.plan-bars-large .plan-name {font-size:1rem}

.plan-bars-large .plan-total {font-size:.94rem}

.plan-bars-large .plan-track {height:22px}

.plan-bars-large .plan-legend span {font-size:.83rem}



.data-panel {padding:17px 18px;height:100%}

.data-panel h3 {font-size:1rem;font-weight:800}

.table {font-size:.83rem;margin-bottom:0}

.table thead th {background:#f6f8f4;color:#617062;border-bottom:0;white-space:nowrap;font-weight:700}

.table td {vertical-align:middle;color:#39483c}

.chip {display:inline-block;font-size:.7rem;font-weight:750;padding:5px 9px;border-radius:7px;white-space:nowrap}

.chip-Pending,.chip-New {background:#fff4df;color:#a66e14}

.chip-Confirmed,.chip-In\ Progress {background:#e6f2ff;color:#356ca1}

.chip-Completed,.chip-Resolved {background:#e4f4e9;color:#27804b}

.chip-Rejected {background:#fde8e6;color:#b44c43}

.action-link {color:#426d3a;text-decoration:none;font-weight:700;font-size:.85rem}

.action-link:hover {text-decoration:underline}

.quick-links a {border:1px solid #e1e7dd;background:#fff;border-radius:10px;padding:9px 13px;text-decoration:none;color:#35593b;font-size:.83rem;font-weight:700}

.quick-links a:hover {background:#eef5e9}

/* Permanent left navigation; compact scrollable bar on smaller screens */

.dashboard-layout {display:flex;align-items:stretch;min-height:calc(100vh - 58px)}

.dashboard-sidebar {width:238px;flex:0 0 238px;background:#fff;border-right:1px solid var(--stroke);padding:26px 13px;position:sticky;top:0;height:calc(100vh - 58px);overflow-y:auto}

.sidebar-heading {font-size:.68rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase;color:#8b9a8b;margin:9px 12px 13px}

.sidebar-link {display:flex;align-items:center;gap:11px;padding:12px 13px;margin:3px 0;border-radius:11px;font-size:.85rem;font-weight:650;text-decoration:none;color:#435846;transition:*background* .15s}

.sidebar-link i {font-size:1.05rem;width:20px;text-align:center;color:#527b35}

.sidebar-link:hover,.sidebar-link:focus-visible {background:#eff5eb;color:#24472e}

.sidebar-link.selected {background:#e7f1e4;color:#285b35;font-weight:800}

.dashboard-main {min-width:0;flex:1}

.category-color-guide {display:flex;gap:15px;flex-wrap:wrap;align-items:center;justify-content:center;margin-top:12px;padding:10px 9px;border-top:1px solid #edf1e9;font-size:.72rem;color:#526b56}

.category-color-guide span::before {content:'';display:inline-block;width:9px;height:9px;border-radius:3px;background:var(--dot);margin-right:5px}

@media(max-width:991.98px){.dashboard-layout{display:block}.dashboard-sidebar{width:auto;height:auto;position:static;display:flex;flex-wrap:wrap;gap:3px;padding:10px 12px;border-right:0;border-bottom:1px solid var(--stroke)}.sidebar-heading{width:100%;margin:4px 8px}.sidebar-link{margin:0;padding:9px 11px}.dashboard-main{width:100%}}

@media(max-width:575px){.sidebar-link{font-size:.76rem;padding:8px}.sidebar-link i{font-size:.85rem;width:auto}}



.modal-content {border-radius:17px;border:0}

.large-chart {height:min(67vh,560px);position:relative}

.note {font-size:.76rem;color:#798777}

@media(max-width:575px){.analytics-container{padding:20px 12px}.chart-card{min-height:300px}.kpi{padding:16px}}





/* =========================================

   DASHBOARD VISUAL IMPROVEMENTS

   ========================================= */



/* Make the dashboard use more screen space */

.analytics-container {

    width: 100%;

    max-width: none;

    margin: 0;

    padding: 28px 32px 55px;

}



/* Improve overall text readability */

.analytics-page {

    font-size: 15px;

    -webkit-font-smoothing: antialiased;

}



/* Top navigation */

.analytics-nav .navbar-brand {

    font-size: 17px;

    font-weight: 800;

}



.analytics-nav .nav-link {

    font-size: 14px;

    font-weight: 600;

}



/* Left sidebar */

.dashboard-sidebar {

    width: 240px;

    flex: 0 0 240px;

    padding: 25px 14px;

}



.sidebar-heading {

    font-size: 12px;

    letter-spacing: 1px;

    font-weight: 800;

}



.sidebar-link {

    font-size: 15px;

    font-weight: 600;

    padding: 14px 13px;

    gap: 12px;

}



.sidebar-link i {

    font-size: 18px;

    width: 22px;

}



.sidebar-link.selected {

    font-weight: 800;

}



/* Main dashboard heading */

.page-title {

    font-size: 32px;

    font-weight: 800;

}



.eyebrow {

    font-size: 12px;

}



.subtle {

    font-size: 14px;

}



/* Statistics cards */

.kpi .k-label {

    font-size: 14px;

    font-weight: 600;

}



.kpi .k-value {

    font-size: 28px;

}



.kpi .k-hint {

    font-size: 12px;

}



/* Section and chart headings */

.section-heading {

    font-size: 19px;

}



.chart-title {

    font-size: 16px;

    font-weight: 800;

}



.chart-subtitle {

    font-size: 12px;

    color: #5c705f;

}



/* =========================================

   POPULAR TIFFIN PLANS

   ========================================= */



/* Spread plan information evenly in card */

.plan-bars {

    display: flex;

    flex-direction: column;

    justify-content: space-evenly;

    flex: 1 1 auto;

    gap: 15px;

    max-height: none;

    min-height: 0;

    overflow: visible;

    margin-top: 12px;

    padding-bottom: 8px;

}



.plan-line {

    width: 100%;

}



/* Larger plan names */

.plan-name {

    font-size: 14px;

    font-weight: 700;

}



.plan-total {

    font-size: 13px;

    font-weight: 800;

}



/* Thicker horizontal bars */

.plan-track {

    height: 18px;

    margin-top: 9px;

    border-radius: 20px;

}



/* Make Pending, Confirmed etc. easier to read */

.plan-legend {

    gap: 12px;

    margin-top: 8px;

}



.plan-legend span {

    font-size: 12px;

    color: #526755;

}



.plan-zero {

    font-size: 12px;

}



/* Tables */

.data-panel h3 {

    font-size: 17px;

}



.table {

    font-size: 14px;

}



.table thead th {

    font-size: 13px;

}



/* Keep mobile layout responsive */

@media (max-width: 991.98px) {

    .dashboard-sidebar {

        width: 100%;

        height: auto;

        flex: none;

    }



    .analytics-container {

        padding: 22px 16px;

    }



    .page-title {

        font-size: 26px;

    }



    .sidebar-link {

        font-size: 13px;

        padding: 10px;

    }



    .plan-bars {

        gap: 12px;

    }

}





/* ======================================

   COMPACT PROFESSIONAL DASHBOARD LAYOUT

   ====================================== */



/* 1. TOP NAVIGATION */

.analytics-nav {

    min-height: 52px;

    padding-top: 5px;

    padding-bottom: 5px;

}



.analytics-nav .navbar-brand {

    font-size: 15px;

    font-weight: 750;

}



.analytics-nav .nav-link {

    font-size: 13px;

    font-weight: 600;

    padding: 7px 11px;

}



.analytics-nav .text-white-50 {

    font-size: 12px;

}



.analytics-nav .btn {

    font-size: 12px;

    padding: 5px 11px;

}



/* 2. LEFT SIDEBAR */

.dashboard-sidebar {

    width: 205px;

    flex: 0 0 205px;

    padding: 20px 11px;

}



.sidebar-heading {

    font-size: 10px;

    margin: 7px 10px 10px;

}



.sidebar-link {

    font-size: 13px;

    font-weight: 600;

    padding: 10px 11px;

    margin: 3px 0;

    gap: 9px;

}



.sidebar-link i {

    font-size: 15px;

    width: 19px;

}



/* 3. MAIN DASHBOARD HEADING */

.analytics-container {

    padding: 22px 26px 45px;

}



.page-title {

    font-size: 27px;

    font-weight: 800;

    line-height: 1.2;

}



.eyebrow {

    font-size: 10px;

    margin-bottom: 5px;

}



.subtle {

    font-size: 12px;

}



/* 4. DATE FILTER */

.filter-panel {

    padding: 15px 18px;

}



.filter-panel label {

    font-size: 12px;

}



.filter-panel .form-select,

.filter-panel .form-control {

    font-size: 13px;

}



.filter-panel .btn {

    font-size: 13px;

}



/* 5. DASHBOARD STATISTICS */

.kpi {

    min-height: 108px;

    padding: 16px 17px;

}



.kpi .k-label {

    font-size: 12px;

}



.kpi .k-value {

    font-size: 25px;

}



.kpi .k-hint {

    font-size: 11px;

}



/* 6. CHART TITLES */

.section-heading {

    font-size: 17px;

}



.chart-title {

    font-size: 15px;

}



.chart-subtitle {

    font-size: 11px;

}



/* Maintain mobile responsiveness */

@media (max-width: 991.98px) {

    .dashboard-sidebar {

        width: 100%;

        height: auto;

        flex: none;

    }



    .analytics-container {

        padding: 20px 15px;

    }



    .page-title {

        font-size: 24px;

    }

}





</style>
</head><body class="analytics-page">
<nav class="navbar navbar-expand-lg analytics-nav navbar-dark"><div class="container-fluid px-3 px-lg-4">
<a class="navbar-brand fw-bold" href="dashboard.php"><i class="bi bi-flower1 me-2"></i>PREM CURRY HOUSE</a>
<div class="ms-auto d-flex align-items-center gap-3"><span class="text-white-50 small"><i class="bi bi-person-circle me-1"></i><?= e($_SESSION['full_name'] ?? 'Staff') ?></span><a class="btn btn-outline-light btn-sm" href="../logout.php">Logout</a></div>
</div></nav>
<div class="dashboard-layout">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<div class="dashboard-main"><main class="analytics-container">
<div class="eyebrow mb-1">Inventory overview</div><h1 class="page-title mb-1">Menu Analytics</h1>
<p class="subtle mb-4">Current menu catalogue and availability. Counts are not sales or bestseller rankings.</p>
<div class="row g-3 mb-4">
<?php foreach ([['Total Menu Items',$stats['total'],'journal-text'],['Available Items',$stats['available'],'check-circle'],['Unavailable Items',$stats['unavailable'],'slash-circle'],['Categories',count($categories),'collection']] as $k): ?>
<div class="col-6 col-lg-3"><div class="panel kpi"><div><div class="k-label"><?=e($k[0])?></div><div class="k-value"><?= (int)$k[1] ?></div></div><div class="k-icon"><i class="bi bi-<?=e($k[2])?>"></i></div></div></div>
<?php endforeach; ?></div>
<div class="panel data-panel mb-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="section-heading mb-1">Menu Items by Category</h2><p class="chart-subtitle mb-0">Dark green: 20+ · Light green: 10–19 · Gold: fewer than 10</p></div><a class="btn btn-outline-success btn-sm" href="menu_items.php">Manage Menu</a></div>
<?php $max=max(1,...array_map(fn($c)=>(int)$c['total'],$categories)); ?>
<?php foreach ($categories as $c): $count=(int)$c['total']; $color=$count>=20?'#527B35':($count>=10?'#8cb18b':'#c99c51'); ?>
<div class="mb-3"><div class="d-flex justify-content-between gap-3 mb-1"><strong><?=e($c['name'])?></strong><span class="text-muted small"><?= $count ?> items · <?= (int)$c['available'] ?> available</span></div><div style="height:15px;background:#edf1e9;border-radius:20px;overflow:hidden"><div style="height:100%;width:<?= $count/$max*100 ?>%;background:<?=$color?>;border-radius:20px"></div></div></div>
<?php endforeach; ?>
<?php if (!$categories): ?><p class="text-muted">No menu categories found.</p><?php endif; ?>
</div><p class="note">These are catalogue counts, not food or beverage sales. Bestseller charts require individual food-order records.</p>
</main></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
