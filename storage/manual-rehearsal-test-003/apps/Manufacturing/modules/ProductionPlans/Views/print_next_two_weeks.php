<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<style>
	@page {
		size: A4 landscape;
		margin: 10mm;
	}

	@media print {
		html,
		body {
			width: 297mm;
			height: 210mm;
		}

		.no-print {
			display: none !important;
		}

		.card {
			border-color: #ddd !important;
			box-shadow: none !important;
			break-inside: avoid;
		}

		.table-wrap {
			overflow: visible !important;
		}
	}

	.print-header {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 12px;
	}

	.machine-section {
		margin-top: 14px;
	}

	.machine-title {
		font-size: 16px;
		font-weight: 700;
		margin: 0 0 8px;
	}

	.print-table th,
	.print-table td {
		font-size: 12px;
		padding: 8px;
		vertical-align: top;
	}

	.print-table {
		width: 100%;
		border-collapse: collapse;
	}

	.print-table th {
		text-align: left;
		background: #f5f6f8;
	}
</style>

<div class="card">
	<div class="print-header">
		<div class="ui-block">
			<h2 class="u-style-1169661891">Production Plans by Machine</h2>
			<div class="muted u-style-fe7b4979fe">
				Window: <?= e((string)$start_date) ?> to <?= e((string)$end_date) ?> (next 2 weeks)
			</div>
			<div class="muted u-style-96ad6099e2">
				Total Plans: <?= (int)$total_rows ?>
			</div>
		</div>
		<div class="row no-print u-style-33fcd4c359">
			<a class="btn" href="/production-plans">Back</a>
			<button class="btn ok" type="button" onclick="window.print()">Print</button>
		</div>
	</div>
</div>

<?php if (empty($groups ?? [])): ?>
	<div class="card">
		<div class="muted">No production plans found for the next 2 weeks.</div>
	</div>
<?php else: ?>
	<?php foreach ($groups as $group): ?>
		<div class="card machine-section">
			<h3 class="machine-title"><?= e((string)$group['machine_label']) ?></h3>
			<div class="table-wrap">
				<table class="print-table">
					<thead>
						<tr>
							<th>ID</th>
							<th>Plan Date</th>
							<th>Sequence</th>
							<th>Part</th>
							<th>Qty</th>
							<th>Status</th>
							<th>Added by</th>
							<th>Reference</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach (($group['rows'] ?? []) as $row): ?>
							<tr>
								<td><?= (int)$row['id'] ?></td>
								<td><?= e((string)$row['plan_date']) ?></td>
								<td><?= (int)$row['sequence_no'] ?></td>
								<td>
									<?= e((string)$row['parts_name']) ?>
									<div class="muted"><?= e((string)$row['parts_number']) ?></div>
								</td>
								<td><?= e((string)$row['planned_qty']) ?></td>
								<td><?= e((string)$row['status']) ?></td>
								<td><?= e((string)($row['added_by'] ?? '-')) ?></td>
								<td>
									<?= e((string)($row['reference_doctype'] ?? '')) ?>
									<?= e((string)($row['reference_name'] ?? '')) ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
