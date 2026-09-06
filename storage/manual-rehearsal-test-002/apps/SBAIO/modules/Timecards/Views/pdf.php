<?php
declare(strict_types=1);

$isPdfMode = true;
$isPrintMode = true;
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
  <meta charset="utf-8">
  <title><?= e((string)($pageTitle ?? t('timecards.title'))) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="u-style-42d394df21">
  <?php require __DIR__ . '/_report.php'; ?>
</body>
</html>
