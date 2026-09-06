<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$labelsPerPage = 8;
$totalPages = (int)ceil($copies / $labelsPerPage);
$labelOptions = is_array($labelOptions ?? null) ? $labelOptions : [];
$productionDate = trim((string)($labelOptions['production_date'] ?? ''));
$serialNumber = trim((string)($labelOptions['serial_number'] ?? ''));
$machineNo = trim((string)($labelOptions['machine_no'] ?? ''));
$caseNumber = trim((string)($labelOptions['case_number'] ?? ''));
$includeDate = !empty($labelOptions['include_date']);
$includeSerialNumber = !empty($labelOptions['include_serial_number']);
$includeMachineNo = !empty($labelOptions['include_machine_no']);
$includeCaseNumber = !empty($labelOptions['include_case_number']);
$includeQtyPerCase = !empty($labelOptions['include_qty_per_case']) && (int)($product['qty_per_case'] ?? 0) > 0;
$includeCaseSpec = !empty($labelOptions['include_case_spec']) && trim((string)($product['case_spec'] ?? '')) !== '';
$includeCasesPerPallet = !empty($labelOptions['include_cases_per_pallet']) && (int)($product['cases_per_pallet'] ?? 0) > 0;
$detailRows = [];
if ($includeDate) {
    $detailRows[] = ['label' => 'Date', 'value' => $productionDate];
}
if ($includeSerialNumber) {
    $detailRows[] = ['label' => 'Serial', 'value' => $serialNumber];
}
if ($includeMachineNo) {
    $detailRows[] = ['label' => 'Machine', 'value' => $machineNo];
}
if ($includeCaseNumber) {
    $detailRows[] = ['label' => 'Case No.', 'value' => $caseNumber];
}
if ($includeQtyPerCase) {
    $detailRows[] = ['label' => 'Qty / Case', 'value' => (string)((int)($product['qty_per_case'] ?? 0))];
}
if ($includeCaseSpec) {
    $detailRows[] = ['label' => 'Case Spec', 'value' => trim((string)($product['case_spec'] ?? ''))];
}
if ($includeCasesPerPallet) {
    $detailRows[] = ['label' => 'Cases / Pallet', 'value' => (string)((int)($product['cases_per_pallet'] ?? 0))];
}
?>
<div class="card qr-screen-only">
  <div class="module-header">
    <div class="module-header-info">
      <h2> <?= e($tt('q_r_code.product_label_sheet_title')) ?> </h2>
      <div class="muted">A4 printable labels with QR code for scan-based workflows.</div>
    </div>
  </div>
</div>
<div class="card qr-sheet-card">
  <div class="qr-print-actions qr-screen-only">
    <div class="u-style-6002dd784e">
      <button class="btn ok" type="button" onclick="window.print()"> <?= e($tt('q_r_code.print_a4_sheet_action')) ?> </button>
      <a class="btn" href="/products">Back to Products</a>
    </div>
    <div class="qr-print-meta"><?= e((string)$copies) ?> <?= e($tt('q_r_code.labels_across_label')) ?> <?= e((string)$totalPages) ?> A4 page<?= $totalPages === 1 ? '' : 's' ?></div>
  </div>
  <div class="qr-preview-stage" data-qr-preview>
    <div class="qr-preview-stack" data-qr-preview-stack>
      <?php for ($page = 0; $page < $totalPages; $page++): ?>
        <?php $start = $page * $labelsPerPage; ?>
        <?php $end = min($copies, $start + $labelsPerPage); ?>
        <div class="qr-page-frame">
          <section class="qr-sheet-page">
            <div class="qr-sheet">
              <?php for ($i = $start; $i < $end; $i++): ?>
                <section class="qr-label">
                  <div class="ui-block">
                    <div class="qr-header">
                      <div class="qr-title"><?= e((string)$product['parts_name']) ?></div>
                      <?php if (!empty($product['model'])): ?>
                        <div class="qr-model"><?= e((string)$product['model']) ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="qr-number"><?= e((string)$product['parts_number']) ?></div>
                    <?php if (!empty($product['producer'])): ?><div class="qr-name">Producer: <?= e((string)$product['producer']) ?></div><?php endif; ?>
                    <?php if ($detailRows !== []): ?>
                      <div class="qr-details">
                        <?php foreach ($detailRows as $detail): ?>
                          <div class="qr-detail">
                            <div class="qr-detail-label"><?= e((string)$detail['label']) ?></div>
                            <div class="qr-detail-line"><?= e((string)$detail['value']) ?></div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="qr-bottom">
                    <div class="qr-side-fields">
                      <div class="qr-detail">
                        <div class="qr-detail-label">Owner</div>
                        <div class="qr-detail-line"></div>
                      </div>
                      <div class="qr-detail">
                        <div class="qr-detail-label">QC</div>
                        <div class="qr-detail-line"></div>
                      </div>
                    </div>
                    <div class="qr-code">
                      <img src="<?= e((string)$qrImageSrc) ?>" alt="QR code for <?= e((string)$product['parts_number']) ?>">
                    </div>
                  </div>
                </section>
              <?php endfor; ?>
            </div>
          </section>
        </div>
      <?php endfor; ?>
    </div>
  </div>
</div>
<script>
  (function () {
    function fitQrPartNumbers() {
      var numbers = document.querySelectorAll('.qr-number');
      var maxSize = 56;
      var minSize = 16;

      for (var i = 0; i < numbers.length; i += 1) {
        var number = numbers[i];
        number.style.fontSize = maxSize + 'px';

        if (number.scrollWidth <= number.clientWidth) {
          continue;
        }

        var low = minSize;
        var high = maxSize;
        while (low < high) {
          var mid = Math.floor((low + high + 1) / 2);
          number.style.fontSize = mid + 'px';
          if (number.scrollWidth <= number.clientWidth) {
            low = mid;
          } else {
            high = mid - 1;
          }
        }

        number.style.fontSize = low + 'px';
      }
    }

    function resizeQrPreview() {
      var viewport = document.querySelector('[data-qr-preview]');
      var stack = document.querySelector('[data-qr-preview-stack]');
      var page = stack ? stack.querySelector('.qr-sheet-page') : null;
      if (!viewport || !stack || !page) {
        return;
      }

      var availableWidth = Math.max(viewport.clientWidth - 8, 0);
      var pageWidth = page.offsetWidth;
      var pageHeight = page.offsetHeight;
      var scale = pageWidth > 0 ? Math.min(1, availableWidth / pageWidth) : 1;

      stack.style.setProperty('--qr-scale', String(scale));
      stack.style.setProperty('--qr-scaled-page-height', (pageHeight * scale) + 'px');
      fitQrPartNumbers();
    }

    window.addEventListener('resize', resizeQrPreview);
    window.addEventListener('load', resizeQrPreview);
    document.addEventListener('DOMContentLoaded', resizeQrPreview);
    window.addEventListener('beforeprint', fitQrPartNumbers);
    resizeQrPreview();
  }());
</script>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
