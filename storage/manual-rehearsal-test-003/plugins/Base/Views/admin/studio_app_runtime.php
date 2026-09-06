<?php
require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';

if (!function_exists('e')) {
  function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$studioApp = is_array($studioApp ?? null) ? $studioApp : [];
$runtimeError = trim((string)($runtimeError ?? ''));
$runtimeHtml = (string)($runtimeHtml ?? '');
$locale = (string)(session_id() !== '' ? ($_SESSION['locale'] ?? 'en') : 'en');
$tr = static function (string $key) use ($locale): string {
  $dict = [
    'en' => [
      'title' => 'Studio Runtime App',
      'subtitle' => 'Safe runtime reflection from Studio registry (no dynamic PHP execution).',
      'app_key' => 'App Key',
      'route' => 'Route',
      'status' => 'Status',
      'version' => 'Version',
      'snapshot' => 'Snapshot ID',
      'source' => 'Source',
      'installed' => 'Installed At',
      'back' => 'Back to Apps Management',
      'runtime_notice' => 'This surface is rendered by static template + registry metadata only.',
      'rendered_view' => 'Rendered View',
      'manifest' => 'View Manifest',
      'manifest.view_key' => 'View Key',
      'manifest.view_kind' => 'View Kind',
      'manifest.data_source' => 'Data Source',
      'manifest.data_provider' => 'Data Provider',
      'manifest.adapter' => 'Adapter',
      'error.not_found' => 'Studio app not found.',
      'error.disabled' => 'Studio app is disabled.',
    ],
    'ja' => [
      'title' => 'Studio Runtimeアプリ',
      'subtitle' => 'Studioレジストリからの安全なランタイム反映（動的PHP実行なし）。',
      'app_key' => 'App Key',
      'route' => 'ルート',
      'status' => 'ステータス',
      'version' => 'バージョン',
      'snapshot' => 'Snapshot ID',
      'source' => 'ソース',
      'installed' => '登録日時',
      'back' => 'Apps管理へ戻る',
      'runtime_notice' => 'この画面は静的テンプレートとレジストリメタデータのみで描画されます。',
      'rendered_view' => '描画ビュー',
      'manifest' => 'ビューマニフェスト',
      'manifest.view_key' => 'ビューキー',
      'manifest.view_kind' => 'ビュー種別',
      'manifest.data_source' => 'データソース',
      'manifest.data_provider' => 'データプロバイダー',
      'manifest.adapter' => 'アダプター',
      'error.not_found' => 'Studioアプリが見つかりません。',
      'error.disabled' => 'Studioアプリは無効です。',
    ],
    'ne' => [
      'title' => 'Studio Runtime एप',
      'subtitle' => 'Studio रजिस्ट्रीको सुरक्षित रनटाइम प्रतिविम्ब (डाइनामिक PHP कार्यान्वयन बिना)।',
      'app_key' => 'App Key',
      'route' => 'मार्ग',
      'status' => 'स्थिति',
      'version' => 'संस्करण',
      'snapshot' => 'Snapshot ID',
      'source' => 'स्रोत',
      'installed' => 'स्थापित समय',
      'back' => 'Apps व्यवस्थापनमा फर्कनुहोस्',
      'runtime_notice' => 'यो सतह स्थिर टेम्प्लेट र रजिस्ट्री मेटाडाटा मात्रबाट रेन्डर हुन्छ।',
      'rendered_view' => 'रेन्डर गरिएको दृश्य',
      'manifest' => 'दृश्य मेनिफेस्ट',
      'manifest.view_key' => 'दृश्य कुञ्जी',
      'manifest.view_kind' => 'दृश्य प्रकार',
      'manifest.data_source' => 'डाटा स्रोत',
      'manifest.data_provider' => 'डाटा प्रोभाइडर',
      'manifest.adapter' => 'एडेप्टर',
      'error.not_found' => 'Studio एप फेला परेन।',
      'error.disabled' => 'Studio एप अक्षम छ।',
    ],
  ];
  return (string)($dict[$locale][$key] ?? $dict['en'][$key] ?? $key);
};
?>

<div class="card">
  <h2 class="u-style-df671843ff"><?= e($tr('title')) ?></h2>
  <div class="muted"><?= e($tr('subtitle')) ?></div>
  <div class="u-style-d2c171b18b"><a class="btn" href="/admin/apps"><?= e($tr('back')) ?></a></div>
</div>

<?php if ($runtimeError !== ''): ?>
  <div class="card"><div class="muted u-style-002a0ee665"><?= e($tr($runtimeError)) ?></div></div>
<?php else: ?>
  <div class="card">
    <div class="muted u-style-761d3addb2"><?= e($tr('runtime_notice')) ?></div>
    <h3 class="u-style-759e41d83d"><?= e($tr('rendered_view')) ?></h3>
    <?php if ($runtimeHtml !== ''): ?>
      <?= $runtimeHtml ?>
    <?php else: ?>
      <div class="note warning"><?= e($tr('error.not_found')) ?></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3 class="u-style-759e41d83d"><?= e($tr('manifest')) ?></h3>
    <div class="table-wrap">
      <table>
        <tbody>
          <tr><th><?= e($tr('app_key')) ?></th><td><code><?= e((string)($studioApp['app_key'] ?? '')) ?></code></td></tr>
          <tr><th><?= e($tr('source')) ?></th><td><?= e((string)($studioApp['source'] ?? 'studio')) ?></td></tr>
          <tr><th><?= e($tr('status')) ?></th><td><?= e((string)($studioApp['status'] ?? 'disabled')) ?></td></tr>
          <tr><th><?= e($tr('route')) ?></th><td><code><?= e((string)($studioApp['route_path'] ?? '')) ?></code></td></tr>
          <tr><th><?= e($tr('snapshot')) ?></th><td><code><?= e((string)($studioApp['snapshot_id'] ?? '')) ?></code></td></tr>
          <tr><th><?= e($tr('version')) ?></th><td><?= e((string)($studioApp['version'] ?? '')) ?></td></tr>
          <tr><th><?= e($tr('installed')) ?></th><td><?= e((string)($studioApp['installed_at'] ?? '')) ?></td></tr>
          <tr><th><?= e($tr('manifest.view_key')) ?></th><td><code><?= e((string)(($studioApp['view_manifest'] ?? [])['view_key'] ?? '')) ?></code></td></tr>
          <tr><th><?= e($tr('manifest.view_kind')) ?></th><td><code><?= e((string)(($studioApp['view_manifest'] ?? [])['view_kind'] ?? '')) ?></code></td></tr>
          <tr><th><?= e($tr('manifest.data_source')) ?></th><td><code><?= e((string)(($studioApp['view_manifest'] ?? [])['data_source'] ?? '')) ?></code></td></tr>
          <tr><th><?= e($tr('manifest.data_provider')) ?></th><td><code><?= e((string)(($studioApp['view_manifest'] ?? [])['data_provider'] ?? '')) ?></code></td></tr>
          <tr><th><?= e($tr('manifest.adapter')) ?></th><td><code><?= e((string)(($studioApp['view_manifest'] ?? [])['adapter'] ?? '')) ?></code></td></tr>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php';
