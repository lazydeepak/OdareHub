# Multilanguage Change Guide

This guide defines the default workflow for all new UI changes so language behavior stays consistent across layers (`/apps/*`, `/u/*`, shared shell).

## Objective

- Every user-facing string must be translation-key based.
- New features must ship with `en`, `ja`, and `ne` entries in the same change.
- While editing any file, normalize nearby hardcoded user-facing text on the spot.

## Source of Truth

- Locale dictionaries:
  - `app/Locale/en.php`
  - `app/Locale/ja.php`
  - `app/Locale/ne.php`
- Runtime translation helpers:
  - `t(key, params)`
  - `__(key, params)`
- Active language getter:
  - `current_lang()`

## Required Rules

1. Never hardcode user-facing text in templates, views, composer HTML, or JS messages.
2. Add keys in all three locale files in one commit slice.
3. Keep key namespaces stable and feature-scoped.
4. Use interpolation placeholders (`{name}`) instead of string concatenation.
5. Do not mix English/Japanese/Nepali labels for the same concept on one screen.

## Key Naming Standard

Use one namespace per surface/feature:

- `operator.surface.*` for `/u/{username}` shell text
- `nav.*` for shared navigation labels
- `common.*` only for truly cross-surface generic labels
- module-specific keys under module prefix (example: `sbaio.host.*`)

## Implementation Pattern

### PHP template text

```php
<h3><?= e(t('operator.surface.quick_actions')) ?></h3>
```

### PHP with fallback in composer logic

```php
$label = function_exists('t') ? (string)t('operator.surface.queue') : 'Queue';
```

### Interpolated title

```php
$title = (string)__('operator.surface.workspace_title', ['username' => $username]);
```

### JS i18n payload from PHP

```php
const I18N = <?= json_encode([
  'scan_prompt' => (string)t('operator.surface.scan_prompt_manual'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
```

### Locale-switch behavior

Always preserve query-param semantics:

```js
function switchQueryParam(key, value) {
  const url = new URL(window.location.href);
  url.searchParams.set(key, value);
  window.location.href = url.toString();
}
```

## On-the-Spot Normalization Rule

When touching a file, normalize nearby user-facing literals in the same edit if they are in scope and low risk:

- Button labels
- Titles/subtitles
- Field labels
- Placeholder text
- `aria-label`/`title`
- Runtime validation and prompt messages

If a nearby literal cannot be safely normalized in that slice, add a TODO entry in the PR/commit note.

## Validation Checklist

1. Run syntax check on changed PHP files.
2. Switch to `?lang=en`, `?lang=ja`, `?lang=ne` and confirm:
   - page title
   - top labels/buttons
   - form labels
   - runtime messages used by edited flows
3. Confirm language selector value matches `current_lang()`.
4. Confirm no key string (`operator.surface.*`) leaks to UI.

## Review Gate (Required)

A localization change is complete only if:

- all new keys exist in `en.php`, `ja.php`, `ne.php`
- no new hardcoded user-facing text remains in edited scope
- route behavior and language persistence are validated in browser
