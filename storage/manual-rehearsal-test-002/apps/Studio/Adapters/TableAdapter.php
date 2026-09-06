<?php
declare(strict_types=1);

namespace Apps\Studio\Adapters;

final class TableAdapter implements StudioViewAdapter
{
    /**
     * @param array<string,mixed> $viewManifest
     * @param array<string,mixed> $context
     */
    public function render(array $viewManifest, array $context): string
    {
        $escape = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $fields = array_values(array_filter((array)($viewManifest['fields'] ?? []), 'is_array'));
      $data = is_array($context['data'] ?? null) ? $context['data'] : [];
      $rows = [];
      if (isset($data['rows']) && is_array($data['rows'])) {
        $rows = array_values(array_filter((array)$data['rows'], 'is_array'));
      } elseif ($data !== [] && array_is_list($data)) {
        $rows = array_values(array_filter($data, 'is_array'));
      }

        if ($fields === []) {
            return '<div class="muted">' . $escape((string)($context['msg_empty'] ?? '')) . '</div>';
        }

        ob_start();
        ?>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <?php foreach ($fields as $field): ?>
          <?php $label = trim((string)($field['label'] ?? $field['key'] ?? '')); ?>
          <th><?= $escape($label) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if ($rows === []): ?>
        <tr>
          <td colspan="<?= count($fields) ?>" class="muted"><?= $escape((string)($context['msg_no_rows'] ?? '')) ?></td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <?php foreach ($fields as $field): ?>
              <?php
                $key = (string)($field['key'] ?? '');
                $val = $key !== '' ? (string)($row[$key] ?? '') : '';
              ?>
              <td><?= $escape($val) ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
        <?php
        return (string)ob_get_clean();
    }
}
