<?php
declare(strict_types=1);

namespace Apps\Studio\Adapters;

final class KpiAdapter implements StudioViewAdapter
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

        if ($fields === []) {
            return '<div class="muted">' . $escape((string)($context['msg_empty'] ?? '')) . '</div>';
        }

        ob_start();
        ?>
<div class="hero-meta u-style-291b7bbb01">
  <?php foreach ($fields as $field): ?>
    <?php
      $key = trim((string)($field['key'] ?? ''));
      if ($key === '') {
          continue;
      }
      $label = trim((string)($field['label'] ?? $key));
      $value = (string)($data[$key] ?? ($field['value'] ?? '0'));
    ?>
    <div class="hero-meta-card">
      <div class="muted"><?= $escape($label) ?></div>
      <div class="hero-meta-value"><?= $escape($value) ?></div>
    </div>
  <?php endforeach; ?>
</div>
        <?php
        return (string)ob_get_clean();
    }
}
