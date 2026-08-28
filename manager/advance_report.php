<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
require_once 'db.php';

$advanceResult = $conn->query("SELECT * FROM advances ORDER BY recorded_at DESC");
if ($advanceResult === false) {
	die('Unable to load advance report: ' . htmlspecialchars($conn->error));
}

$advances = $advanceResult->fetch_all(MYSQLI_ASSOC);
$totalAmount = array_sum(array_map('floatval', array_column($advances, 'amount')));
$totalBalance = array_sum(array_map('floatval', array_column($advances, 'balance')));
$recoveredAmount = $totalAmount - $totalBalance;
$reportDate = date('d F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Advance Portfolio Report</title>
	<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
	<style>
		:root {
			--ink: #172331;
			--muted: #687582;
			--line: #dbe3e8;
			--paper: #ffffff;
			--canvas: #f2f5f6;
			--teal: #147d78;
			--gold: #c7973e;
		}

		body {
			background: var(--canvas);
			color: var(--ink);
			font-family: "Trebuchet MS", Arial, sans-serif;
		}

		.report-shell {
			max-width: 1280px;
			margin: 34px auto 60px;
			padding: 0 22px;
		}

		.report-heading {
			background: var(--ink);
			border-top: 4px solid var(--gold);
			color: white;
			padding: 30px 34px 27px;
			position: relative;
		}

		.report-heading h1 {
			font-family: Georgia, serif;
			font-size: clamp(1.8rem, 3vw, 2.6rem);
			font-weight: normal;
			letter-spacing: .02em;
			margin: 0 0 8px;
		}

		.report-heading p { color: #c3d0d6; margin: 0; }
		.report-meta { color: #e5c579; font-size: .8rem; letter-spacing: .12em; text-transform: uppercase; }
		.report-actions { position: absolute; right: 34px; bottom: 27px; }
		.report-actions button, .report-actions a { border: 1px solid #82939c; color: white; background: transparent; padding: 8px 14px; text-decoration: none; }
		.report-actions button:hover, .report-actions a:hover { background: var(--teal); border-color: var(--teal); }
		.metric-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin: 18px 0; }
		.metric { background: var(--paper); border: 1px solid var(--line); border-left: 4px solid var(--teal); padding: 18px 20px; }
		.metric:nth-child(2) { border-left-color: var(--gold); }
		.metric:nth-child(3) { border-left-color: #5b7180; }
		.metric:nth-child(4) { border-left-color: #a95d55; }
		.metric-label { color: var(--muted); font-size: .74rem; letter-spacing: .1em; text-transform: uppercase; }
		.metric-value { display: block; font-family: Georgia, serif; font-size: 1.45rem; margin-top: 8px; }

		.table-panel { background: var(--paper); border: 1px solid var(--line); }
		.panel-heading { border-bottom: 1px solid var(--line); padding: 18px 22px; }
		.panel-heading h2 { font-family: Georgia, serif; font-size: 1.25rem; margin: 0; }
		.panel-heading span { color: var(--muted); font-size: .85rem; }
		.report-table { margin: 0; }
		.report-table thead th { background: #edf2f3; border-bottom: 2px solid var(--teal); color: #425460; font-size: .72rem; letter-spacing: .08em; padding: 14px 18px; text-transform: uppercase; white-space: nowrap; }
		.report-table tbody td { border-color: #e6ecef; padding: 15px 18px; vertical-align: middle; }
		.report-table tbody tr:hover { background: #f7faf9; }
		.officer { font-weight: bold; }
		.amount { font-variant-numeric: tabular-nums; white-space: nowrap; }
		.statement-link { color: var(--teal); font-size: .8rem; font-weight: bold; text-decoration: none; white-space: nowrap; }
		.statement-link:hover { color: var(--ink); text-decoration: underline; }
		.status { border-radius: 20px; display: inline-block; font-size: .72rem; font-weight: bold; letter-spacing: .05em; padding: 5px 10px; text-transform: uppercase; }
		.status-open { background: #e6f3f1; color: #146b67; }
		.status-cleared { background: #edf0f2; color: #53636d; }
		.empty-state { color: var(--muted); padding: 34px 22px; text-align: center; }
		.report-footer { color: var(--muted); font-size: .78rem; margin-top: 14px; }

		@media (max-width: 850px) {
			.report-heading { padding: 24px; }
			.report-actions { margin-top: 20px; position: static; }
			.metric-grid { grid-template-columns: repeat(2, 1fr); }
		}
		@media (max-width: 520px) {
			.report-shell { padding: 0 12px; }
			.metric-grid { grid-template-columns: 1fr; }
			.report-heading h1 { font-size: 1.8rem; }
		}
		@media print {
			body { background: white; }
			.report-shell { margin: 0; max-width: none; }
			.report-actions, #header { display: none !important; }
			.report-heading { color: black; background: white; border: 1px solid #999; }
			.report-heading p, .report-meta { color: #444; }
			.metric, .table-panel { break-inside: avoid; }
		}
	</style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="report-shell" id="mainContent">
	<section class="report-heading">
		<div class="report-meta">Management report · Advance portfolio</div>
		<h1>Advance Portfolio Report</h1>
		<p>A consolidated view of advances booked, recovered, and outstanding.</p>
		<div class="report-actions">
			<a href="index.php">Back to Dashboard</a>
			<button type="button" onclick="window.print()">Print report</button>
			<a href="view_Advance.php">View advances</a>
		</div>
	</section>

	<section class="metric-grid" aria-label="Portfolio summary">
		<div class="metric"><span class="metric-label">Total advances</span><strong class="metric-value"><?php echo count($advances); ?></strong></div>
		<div class="metric"><span class="metric-label">Total disbursed</span><strong class="metric-value"><?php echo number_format($totalAmount, 2); ?> KES</strong></div>
		<div class="metric"><span class="metric-label">Recovered</span><strong class="metric-value"><?php echo number_format($recoveredAmount, 2); ?> KES</strong></div>
		<div class="metric"><span class="metric-label">Outstanding</span><strong class="metric-value"><?php echo number_format($totalBalance, 2); ?> KES</strong></div>
	</section>

	<section class="table-panel">
		<div class="panel-heading d-flex justify-content-between align-items-center gap-3">
			<h2>Advance register</h2>
			<span>Prepared <?php echo $reportDate; ?></span>
		</div>
		<?php if (!empty($advances)): ?>
			<div class="table-responsive">
				<table class="table report-table">
					<thead><tr><th>Officer</th><th>Advance amount</th><th>Balance</th><th>Status</th><th>Start date</th><th>Monthly deduction</th><th>Recorded</th><th>Statement</th></tr></thead>
					<tbody>
						<?php foreach ($advances as $advance): ?>
							<?php $isOpen = (float) $advance['balance'] > 0; ?>
							<tr>
								<td class="officer"><?php echo htmlspecialchars($advance['loan_officer_name']); ?></td>
								<td class="amount"><?php echo number_format((float) $advance['amount'], 2); ?> KES</td>
								<td class="amount"><?php echo number_format((float) $advance['balance'], 2); ?> KES</td>
								<td><span class="status <?php echo $isOpen ? 'status-open' : 'status-cleared'; ?>"><?php echo $isOpen ? 'Outstanding' : 'Cleared'; ?></span></td>
								<td><?php echo htmlspecialchars($advance['start_date']); ?></td>
								<td class="amount"><?php echo number_format((float) $advance['monthly_deduction'], 2); ?> KES</td>
								<td><?php echo htmlspecialchars($advance['recorded_at']); ?></td>
								<td><a class="statement-link" href="advance_statement.php?advance_id=<?php echo (int) $advance['id']; ?>">View statement</a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<div class="empty-state">No advances have been booked yet.</div>
		<?php endif; ?>
	</section>
	<div class="report-footer">Source: advance register · All amounts are presented in Kenyan Shillings (KES).</div>
</main>
</body>
</html>
