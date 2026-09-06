<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<!-- print styles: @media print exception approved per AGENTS.md -->
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
			<h2 class="u-style-1169661891"><?= e(t('module.production_plans.print_title')) ?></h2>
			<div class="muted u-style-fe7b4979fe">
				<?= e(sprintf((string)t('module.production_plans.print_window'), (string)$start_date, (string)$end_date)) ?>
			</div>
			<div class="muted u-style-96ad6099e2">
				<?= e(sprintf((string)t('module.production_plans.print_total'), (int)$total_rows)) ?>
			</div>
		</div>
		<div class="row no-print u-style-33fcd4c359">
			<a class="btn" href="/production-plans"><?= e(t('common.back_to_list')) ?></a>
			<button class="btn ok" type="button" onclick="window.print()"><?= e(t('module.production_plans.print_print')) ?></button>
		</div>
	</div>
</div>

<?php if (empty($groups ?? [])): ?>
	<div class="card">
		<div class="muted"><?= e(t('module.production_plans.print_none')) ?></div>
	</div>
<?php else: ?>
	<?php foreach ($groups as $group): ?>
		<div class="card machine-section">
			<h3 class="machine-title"><?= e((string)$group['machine_label']) ?></h3>
			<div class="table-wrap">
				<table class="print-table">
					<thead>
						<tr>
							<th><?= e(t('common.id')) ?></th>
							<th><?= e(t('module.production_plans.plan_date')) ?></th>
							<th><?= e(t('common.sequence')) ?></th>
							<th><?= e(t('common.part')) ?></th>
							<th><?= e(t('common.qty')) ?></th>
							<th><?= e(t('common.status')) ?></th>
							<th><?= e(t('module.production_plans.added_by')) ?></th>
							<th><?= e(t('module.production_plans.reference_doctype')) ?></th>
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
