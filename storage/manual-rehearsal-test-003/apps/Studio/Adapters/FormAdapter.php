<?php
declare(strict_types=1);

namespace Apps\Studio\Adapters;

final class FormAdapter implements StudioViewAdapter
{
    /**
     * @param array<string,mixed> $viewManifest
     * @param array<string,mixed> $context
     */
    public function render(array $viewManifest, array $context): string
    {
        $escape = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $fields = array_values(array_filter((array)($viewManifest['fields'] ?? []), 'is_array'));
        $actions = array_values(array_filter((array)($viewManifest['actions'] ?? []), 'is_array'));
        $data = is_array($context['data'] ?? null) ? $context['data'] : [];
        $actionUrl = trim((string)($context['action_url'] ?? ''));
        $csrf = trim((string)($context['csrf'] ?? ''));

        $primaryActionKey = '';
        foreach ($actions as $action) {
            $candidate = trim((string)($action['action_key'] ?? $action['key'] ?? ''));
            if ($candidate !== '') {
                $primaryActionKey = $candidate;
                break;
            }
        }

        $canSubmit = $actionUrl !== '' && $primaryActionKey !== '';
        $submitLabel = $canSubmit ? (string)($context['msg_submit'] ?? 'Submit') : (string)($context['msg_form_readonly'] ?? 'Read-only Preview');

        if ($fields === []) {
            return '<div class="muted">' . $escape((string)($context['msg_empty'] ?? '')) . '</div>';
        }

        ob_start();
        ?>
<form method="post" action="<?= $escape($canSubmit ? $actionUrl : '#') ?>" class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
  <?php if ($csrf !== ''): ?>
    <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
  <?php endif; ?>
  <?php if ($primaryActionKey !== ''): ?>
    <input type="hidden" name="action_key" value="<?= $escape($primaryActionKey) ?>">
  <?php endif; ?>
  <?php foreach ($fields as $field): ?>
    <?php
      $key = trim((string)($field['key'] ?? ''));
      if ($key === '') {
          continue;
      }
      $label = trim((string)($field['label'] ?? $key));
      $type = strtolower(trim((string)($field['type'] ?? 'text')));
      if (!in_array($type, ['text', 'number', 'date', 'email'], true)) {
          $type = 'text';
      }
      $placeholder = (string)($field['placeholder'] ?? '');
      $required = !empty($field['required']) ? 'required' : '';
      $value = (string)($data[$key] ?? ($field['value'] ?? ''));
    ?>
    <label class="u-style-9d3009ebf6">
      <span><?= $escape($label) ?></span>
      <input class="input" type="<?= $escape($type) ?>" name="<?= $escape($key) ?>" value="<?= $escape($value) ?>" placeholder="<?= $escape($placeholder) ?>" <?= $required ?>>
    </label>
  <?php endforeach; ?>
  <div class="u-style-d48b20040e">
    <button class="btn" type="submit" <?= $canSubmit ? '' : 'disabled' ?>><?= $escape($submitLabel) ?></button>
  </div>
</form>
        <?php
        return (string)ob_get_clean();
    }
}
