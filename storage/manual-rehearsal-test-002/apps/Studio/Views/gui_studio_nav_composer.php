<?php
declare(strict_types=1);

$tt = static function (string $key): string {
  return t($key);
};
/**
 * Phase 1: Nav Composer read-only shell.
 *
 * Variables provided by route:
 * - $pageTitle string
 * - $candidateFields array<int,string>
 * - $candidates array<int,array<string,mixed>>
 * - $candidateCounts array<string,int>
 * - $candidateSourceCounts array<string,int>
 */

$candidateFields = is_array($candidateFields ?? null) ? $candidateFields : [];
$candidates = is_array($candidates ?? null) ? $candidates : [];
$candidateCounts = is_array($candidateCounts ?? null) ? $candidateCounts : [];
$candidateSourceCounts = is_array($candidateSourceCounts ?? null) ? $candidateSourceCounts : [];
$lang = (string)(session_id() !== '' ? ($_SESSION['locale'] ?? 'en') : 'en');

$copy = [
    'en' => [
        'title' => 'Nav Composer',
        'state' => 'Read-only planning surface',
        'owner_note' => 'Apps and modules own navigation truth. Studio only composes owner-owned navigation resources.',
        'empty' => 'No real candidates were found from owner-owned navigation/route sources.',
        'fields_title' => 'Planned Candidate Contract Fields',
        'fields_subtitle' => 'Candidate rows are read-only. No save/apply/mutation is available in this phase.',
        'candidates_title' => 'Read-only Candidate Preview',
        'candidates_subtitle' => 'Candidates come from owner-owned navigation declarations and route metadata only.',
        'source_count' => 'Source rows',
        'candidate_count' => 'Candidate rows',
        'status_count' => 'Status counts',
        'back_studio' => 'Back to Studio',
        'open_library' => 'Open Library',
        'open_history' => 'Open History',
        'read_only_badge' => 'Read-only',
        'no_mutation_badge' => 'No mutation/apply',
        'phase_badge' => 'Phase 1 shell',
    ],
    'ja' => [
        'title' => 'Nav Composer',
        'state' => 'Read-only planning surface',
        'owner_note' => 'ナビゲーションの正本は各アプリ/モジュールが所有します。Studio は owner-owned リソースを構成するのみです。',
        'empty' => 'owner-owned な navigation/route ソースから候補が見つかりませんでした。',
        'fields_title' => 'Planned Candidate Contract Fields',
        'fields_subtitle' => '候補行は read-only です。このフェーズでは save/apply/mutation はありません。',
        'candidates_title' => 'Read-only Candidate Preview',
        'candidates_subtitle' => '候補は owner-owned な navigation 宣言と route metadata のみから読み取ります。',
        'source_count' => 'ソース行',
        'candidate_count' => '候補行',
        'status_count' => 'ステータス件数',
        'back_studio' => 'Studioに戻る',
        'open_library' => 'ライブラリを開く',
        'open_history' => '履歴を開く',
        'read_only_badge' => 'Read-only',
        'no_mutation_badge' => 'No mutation/apply',
        'phase_badge' => 'Phase 1 shell',
    ],
    'ne' => [
        'title' => 'Nav Composer',
        'state' => 'Read-only planning surface',
        'owner_note' => 'Navigation सत्यको स्वामित्व app/module मै रहन्छ। Studio ले owner-owned navigation resources मात्र compose गर्छ।',
        'empty' => 'owner-owned navigation/route स्रोतबाट कुनै वास्तविक candidates भेटिएनन्।',
        'fields_title' => 'Planned Candidate Contract Fields',
        'fields_subtitle' => 'Candidate rows read-only छन्। यो चरणमा save/apply/mutation हुँदैन।',
        'candidates_title' => 'Read-only Candidate Preview',
        'candidates_subtitle' => 'Candidates केवल owner-owned navigation declarations र route metadata बाट पढिन्छ।',
        'source_count' => 'Source rows',
        'candidate_count' => 'Candidate rows',
        'status_count' => 'Status counts',
        'back_studio' => 'Studio मा फर्कनुहोस्',
        'open_library' => 'Library खोल्नुहोस्',
        'open_history' => 'History खोल्नुहोस्',
        'read_only_badge' => 'Read-only',
        'no_mutation_badge' => 'No mutation/apply',
        'phase_badge' => 'Phase 1 shell',
    ],
];

$ui = $copy[$lang] ?? $copy['en'];

$statusOrder = ['linked', 'missing_nav', 'mismatch', 'ambiguous', 'blocked'];
$statusLabels = [
  'linked' => 'linked',
  'missing_nav' => 'missing_nav',
  'mismatch' => 'mismatch',
  'ambiguous' => 'ambiguous',
  'blocked' => 'blocked',
];

require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';
?>

<section class="card">
  <div class="section-head">
    <h2><?= e((string)$ui['title']) ?></h2>
    <div class="row">
      <span class="status-chip"><?= e((string)$ui['phase_badge']) ?></span>
      <span class="status-chip"><?= e((string)$ui['read_only_badge']) ?></span>
      <span class="status-chip"><?= e((string)$ui['no_mutation_badge']) ?></span>
    </div>
  </div>
  <p class="muted"><?= e((string)$ui['state']) ?></p>
  <p class="muted"><?= e((string)$ui['owner_note']) ?></p>
  <div class="row">
    <span class="status-chip"><?= e((string)$ui['source_count']) ?>: <?= (int)($candidateSourceCounts['linked_routes'] ?? 0) + (int)($candidateSourceCounts['declared_routes'] ?? 0) + (int)($candidateSourceCounts['route_contracts'] ?? 0) ?></span>
    <span class="status-chip"><?= e((string)$ui['candidate_count']) ?>: <?= count($candidates) ?></span>
  </div>
</section>

<section class="card">
  <div class="section-head">
    <h3><?= e((string)$ui['candidates_title']) ?></h3>
    <p class="muted"><?= e((string)$ui['candidates_subtitle']) ?></p>
  </div>

  <div class="row">
    <span class="muted"><?= e((string)$ui['status_count']) ?>:</span>
    <?php foreach ($statusOrder as $statusKey): ?>
      <span class="status-chip"><?= e((string)$statusLabels[$statusKey]) ?>: <?= (int)($candidateCounts[$statusKey] ?? 0) ?></span>
    <?php endforeach; ?>
  </div>

  <?php if ($candidates === []): ?>
    <div class="note warning"><?= e((string)$ui['empty']) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <?php foreach ($candidateFields as $field): ?>
              <th><?= e((string)$field) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($candidates as $candidate): ?>
          <tr>
            <?php foreach ($candidateFields as $field): ?>
              <?php if ($field === 'status'): ?>
                <td><span class="status-chip"><?= e((string)($candidate[$field] ?? '')) ?></span></td>
              <?php elseif ($field === 'diagnostics'): ?>
                <?php $diag = array_values(array_filter(array_map('strval', (array)($candidate[$field] ?? [])), static fn(string $v): bool => trim($v) !== '')); ?>
                <td><?= e(implode(' | ', $diag)) ?></td>
              <?php else: ?>
                <td><?= e((string)($candidate[$field] ?? '')) ?></td>
              <?php endif; ?>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card">
  <div class="section-head">
    <h3><?= e((string)$ui['fields_title']) ?></h3>
    <p class="muted"><?= e((string)$ui['fields_subtitle']) ?></p>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th> <?= e($tt('studio.field_column')) ?> </th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($candidateFields as $field): ?>
        <tr>
          <td><code><?= e((string)$field) ?></code></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <div class="row">
    <a class="btn" href="/apps/studio/library"><?= e((string)$ui['open_library']) ?></a>
    <a class="btn" href="/apps/studio/history"><?= e((string)$ui['open_history']) ?></a>
  </div>
</section>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php';
