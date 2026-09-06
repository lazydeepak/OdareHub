<?php
require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$runtimeModule = isset($runtimeModule) && is_array($runtimeModule) ? $runtimeModule : [];
$runtimeFields = isset($runtimeFields) && is_array($runtimeFields) ? $runtimeFields : [];
$runtimeRows = isset($runtimeRows) && is_array($runtimeRows) ? $runtimeRows : [];
$runtimeRole = isset($runtimeRole) ? (string)$runtimeRole : 'read_only';
$runtimeCanEdit = isset($runtimeCanEdit) ? !empty($runtimeCanEdit) : false;
$runtimeContract = isset($runtimeContract) && is_array($runtimeContract) ? $runtimeContract : [];
$runtimeWorkflow = is_array($runtimeContract['workflow'] ?? null) ? $runtimeContract['workflow'] : [];
$runtimeFlash = isset($runtimeFlash) && is_array($runtimeFlash) ? $runtimeFlash : [];
$csrf = isset($csrf) ? (string)$csrf : '';
$locale = (string)(session_id() !== '' ? ($_SESSION['locale'] ?? 'en') : 'en');

$tr = static function (string $key) use ($locale): string {
    $dict = [
        'en' => [
            'title' => 'Application Runtime',
            'subtitle' => 'User-facing execution surface for generated module runtime.',
            'role' => 'Role',
            'app' => 'App',
            'module' => 'Module',
            'mode_admin' => 'Admin',
            'mode_operator' => 'Operator',
            'mode_qc' => 'QC',
            'mode_dispatch' => 'Dispatch',
            'mode_read_only' => 'Read-only',
            'flash.saved' => 'Record saved.',
            'flash.failed' => 'Save failed.',
            'flash.runtime_read_only' => 'Read-only mode. Editing is not allowed.',
            'flash.generated_workflow_transition_saved' => 'Workflow status updated.',
            'flash.generated_workflow_transition_invalid' => 'Invalid workflow transition.',
            'flash.generated_workflow_transition_unauthorized' => 'You are not allowed to perform this transition.',
            'flash.generated_workflow_row_not_found' => 'Record not found.',
            'flash.generated_workflow_invalid_payload' => 'Workflow request is invalid.',
            'component.filter' => 'Filter',
            'component.table' => 'Records',
            'component.form' => 'Create Record',
            'component.kpi' => 'Record Count',
            'filter.field' => 'Field',
            'filter.placeholder' => 'Type to filter',
            'empty.rows' => 'No records found.',
            'form.submit' => 'Save',
            'readonly.note' => 'This user can view data only.',
            'table.col.status' => 'Status',
            'table.col.actions' => 'Actions',
            'table.col.history' => 'History',
            'table.col.time_in_state' => 'Time in State',
            'table.col.delay_warning' => 'Delay Warning',
            'action.mark_in_progress' => 'Start',
            'action.mark_completed' => 'Complete',
            'action.approve' => 'Approve',
            'action.dispatch' => 'Dispatch',
            'action.show_history' => 'History',
            'history.title' => 'History',
            'history.empty' => 'No workflow history yet.',
            'history.actor' => 'Actor',
            'history.role' => 'Role',
            'history.when' => 'Time',
            'history.arrow' => 'to',
            'history.duration' => 'Duration',
            'history.delay' => 'Delay',
            'delay.none' => 'On Track',
            'delay.overdue' => 'Overdue',
            'delay.transition_delayed' => 'Transition delayed',
            'delay.sla' => 'SLA',
            'status.draft' => 'Draft',
            'status.in_progress' => 'In Progress',
            'status.completed' => 'Completed',
            'status.approved' => 'Approved',
            'status.dispatched' => 'Dispatched',
        ],
        'ja' => [
            'title' => 'アプリ実行ランタイム',
            'subtitle' => '生成モジュールを利用者向けに実行する画面です。',
            'role' => 'ロール',
            'app' => 'アプリ',
            'module' => 'モジュール',
            'mode_admin' => '管理者',
            'mode_operator' => 'オペレーター',
            'mode_qc' => '検査',
            'mode_dispatch' => '出荷',
            'mode_read_only' => '閲覧専用',
            'flash.saved' => 'レコードを保存しました。',
            'flash.failed' => '保存に失敗しました。',
            'flash.runtime_read_only' => '閲覧専用モードのため編集できません。',
            'flash.generated_workflow_transition_saved' => 'ワークフロー状態を更新しました。',
            'flash.generated_workflow_transition_invalid' => '不正なワークフロー遷移です。',
            'flash.generated_workflow_transition_unauthorized' => 'この遷移を実行する権限がありません。',
            'flash.generated_workflow_row_not_found' => '対象レコードが見つかりません。',
            'flash.generated_workflow_invalid_payload' => 'ワークフロー要求が不正です。',
            'component.filter' => 'フィルター',
            'component.table' => 'レコード',
            'component.form' => '新規レコード',
            'component.kpi' => '件数',
            'filter.field' => '項目',
            'filter.placeholder' => '絞り込み文字を入力',
            'empty.rows' => 'レコードがありません。',
            'form.submit' => '保存',
            'readonly.note' => 'このユーザーはデータを閲覧のみ可能です。',
            'table.col.status' => '状態',
            'table.col.actions' => '操作',
            'table.col.history' => '履歴',
            'table.col.time_in_state' => '状態経過時間',
            'table.col.delay_warning' => '遅延警告',
            'action.mark_in_progress' => '着手',
            'action.mark_completed' => '完了',
            'action.approve' => '承認',
            'action.dispatch' => '出荷',
            'action.show_history' => '履歴',
            'history.title' => '履歴',
            'history.empty' => 'ワークフロー履歴はまだありません。',
            'history.actor' => '実行者',
            'history.role' => 'ロール',
            'history.when' => '時刻',
            'history.arrow' => '→',
            'history.duration' => '所要時間',
            'history.delay' => '遅延',
            'delay.none' => '順調',
            'delay.overdue' => '期限超過',
            'delay.transition_delayed' => '遷移遅延',
            'delay.sla' => 'SLA',
            'status.draft' => '下書き',
            'status.in_progress' => '進行中',
            'status.completed' => '完了',
            'status.approved' => '承認済み',
            'status.dispatched' => '出荷済み',
        ],
        'ne' => [
            'title' => 'एप रनटाइम',
            'subtitle' => 'Generated module को user-facing execution सतह।',
            'role' => 'भूमिका',
            'app' => 'एप',
            'module' => 'मोड्युल',
            'mode_admin' => 'एडमिन',
            'mode_operator' => 'अपरेटर',
            'mode_qc' => 'QC',
            'mode_dispatch' => 'डिस्प्याच',
            'mode_read_only' => 'हेर्न मात्र',
            'flash.saved' => 'रेकर्ड सेभ भयो।',
            'flash.failed' => 'सेभ असफल भयो।',
            'flash.runtime_read_only' => 'यो read-only मोड हो। सम्पादन अनुमति छैन।',
            'flash.generated_workflow_transition_saved' => 'Workflow स्थिति अपडेट भयो।',
            'flash.generated_workflow_transition_invalid' => 'अमान्य workflow transition।',
            'flash.generated_workflow_transition_unauthorized' => 'यो transition गर्ने अनुमति छैन।',
            'flash.generated_workflow_row_not_found' => 'रेकर्ड भेटिएन।',
            'flash.generated_workflow_invalid_payload' => 'Workflow अनुरोध अमान्य छ।',
            'component.filter' => 'Filter',
            'component.table' => 'Records',
            'component.form' => 'Create Record',
            'component.kpi' => 'Record Count',
            'filter.field' => 'Field',
            'filter.placeholder' => 'Filter text लेख्नुहोस्',
            'empty.rows' => 'रेकर्ड भेटिएन।',
            'form.submit' => 'Save',
            'readonly.note' => 'यो प्रयोगकर्ताले data हेर्न मात्र पाउँछ।',
            'table.col.status' => 'स्थिति',
            'table.col.actions' => 'कार्य',
            'table.col.history' => 'इतिहास',
            'table.col.time_in_state' => 'स्थिति समय',
            'table.col.delay_warning' => 'ढिलाइ चेतावनी',
            'action.mark_in_progress' => 'सुरु',
            'action.mark_completed' => 'सम्पन्न',
            'action.approve' => 'स्वीकृत',
            'action.dispatch' => 'डिस्प्याच',
            'action.show_history' => 'इतिहास',
            'history.title' => 'इतिहास',
            'history.empty' => 'अहिलेसम्म workflow history छैन।',
            'history.actor' => 'Actor',
            'history.role' => 'भूमिका',
            'history.when' => 'समय',
            'history.arrow' => 'to',
            'history.duration' => 'Duration',
            'history.delay' => 'Delay',
            'delay.none' => 'On Track',
            'delay.overdue' => 'Overdue',
            'delay.transition_delayed' => 'Transition delayed',
            'delay.sla' => 'SLA',
            'status.draft' => 'ड्राफ्ट',
            'status.in_progress' => 'प्रगति',
            'status.completed' => 'सम्पन्न',
            'status.approved' => 'स्वीकृत',
            'status.dispatched' => 'डिस्प्याच',
        ],
    ];

    return (string)($dict[$locale][$key] ?? $dict['en'][$key] ?? $key);
};

$flashMessageKey = trim((string)($runtimeFlash['message'] ?? ''));
$flashStatus = trim((string)($runtimeFlash['status'] ?? ''));
if ($flashMessageKey !== '') {
    $flashLookupKey = 'flash.' . $flashMessageKey;
    $resolvedFlash = $tr($flashLookupKey);
    if ($resolvedFlash !== $flashLookupKey) {
        $flashMessageKey = $resolvedFlash;
    }
}
?>

<div class="card">
    <h2 class="u-style-41bd2e6abc"><?= e($tr('title')) ?></h2>
    <div class="muted u-style-2d98adf5c6"><?= e($tr('subtitle')) ?></div>
    <div class="note u-style-bd2bbfc6f6">
      <div class="ui-block"><strong><?= e($tr('role')) ?>:</strong> <?= e($tr('mode_' . $runtimeRole)) ?></div>
      <div class="ui-block"><strong><?= e($tr('app')) ?>:</strong> <?= e((string)($runtimeModule['app_key'] ?? '')) ?></div>
      <div class="ui-block"><strong><?= e($tr('module')) ?>:</strong> <?= e((string)($runtimeModule['module_key'] ?? '')) ?></div>
    </div>
    <?php if ($flashMessageKey !== ''): ?>
        <div class="note <?= $flashStatus === 'failed' ? 'warning' : '' ?>" style="margin-top: 10px;"><?= e($flashMessageKey) ?></div>
    <?php endif; ?>
    <?php if (!$runtimeCanEdit): ?>
        <div class="note u-style-54d32d80db"><?= e($tr('readonly.note')) ?></div>
    <?php endif; ?>
</div>

<div class="card">
    <div id="runtime-layout" class="table-wrap u-style-345742ed85"></div>
</div>

<form id="runtime-submit-form" method="post" action="<?= e((string)($runtimeModule['runtime_path'] ?? '')) ?>" style="display:none;">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="runtime_action" value="">
  <input type="hidden" name="row_id" value="">
  <input type="hidden" name="target_status" value="">
    <?php foreach ($runtimeFields as $field): ?>
        <?php $fieldKey = (string)($field['key'] ?? ''); if ($fieldKey === '') { continue; } ?>
        <input type="hidden" name="<?= e($fieldKey) ?>" value="">
    <?php endforeach; ?>
</form>

<script>
(function () {
  var runtimeRows = <?= json_encode($runtimeRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var runtimeFields = <?= json_encode($runtimeFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var runtimeContract = <?= json_encode($runtimeContract, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var runtimeRole = <?= json_encode($runtimeRole, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var runtimeCanEdit = <?= $runtimeCanEdit ? 'true' : 'false' ?>;
  var tr = <?= json_encode([
      'filter' => $tr('component.filter'),
      'table' => $tr('component.table'),
      'form' => $tr('component.form'),
      'kpi' => $tr('component.kpi'),
      'filterField' => $tr('filter.field'),
      'filterPlaceholder' => $tr('filter.placeholder'),
      'emptyRows' => $tr('empty.rows'),
      'submit' => $tr('form.submit'),
      'modeAdmin' => $tr('mode_admin'),
      'modeOperator' => $tr('mode_operator'),
      'modeQc' => $tr('mode_qc'),
      'modeDispatch' => $tr('mode_dispatch'),
      'modeReadOnly' => $tr('mode_read_only'),
        'statusColumn' => $tr('table.col.status'),
        'actionsColumn' => $tr('table.col.actions'),
        'historyColumn' => $tr('table.col.history'),
        'timeInStateColumn' => $tr('table.col.time_in_state'),
        'delayWarningColumn' => $tr('table.col.delay_warning'),
        'actionInProgress' => $tr('action.mark_in_progress'),
        'actionCompleted' => $tr('action.mark_completed'),
        'actionApprove' => $tr('action.approve'),
        'actionDispatch' => $tr('action.dispatch'),
        'actionHistory' => $tr('action.show_history'),
        'historyTitle' => $tr('history.title'),
        'historyEmpty' => $tr('history.empty'),
        'historyActor' => $tr('history.actor'),
        'historyRole' => $tr('history.role'),
        'historyWhen' => $tr('history.when'),
        'historyArrow' => $tr('history.arrow'),
        'historyDuration' => $tr('history.duration'),
        'historyDelay' => $tr('history.delay'),
        'delayNone' => $tr('delay.none'),
        'delayOverdue' => $tr('delay.overdue'),
        'delayTransition' => $tr('delay.transition_delayed'),
        'delaySla' => $tr('delay.sla'),
        'statusDraft' => $tr('status.draft'),
        'statusInProgress' => $tr('status.in_progress'),
        'statusCompleted' => $tr('status.completed'),
        'statusApproved' => $tr('status.approved'),
        'statusDispatched' => $tr('status.dispatched'),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  var state = {
    rows: Array.isArray(runtimeRows) ? runtimeRows.slice() : [],
    filteredRows: Array.isArray(runtimeRows) ? runtimeRows.slice() : [],
    componentState: {},
    selectedRow: null,
    historyExpanded: {}
  };

  var layout = runtimeContract && runtimeContract.layout ? runtimeContract.layout : {};
  var workflow = runtimeContract && runtimeContract.workflow ? runtimeContract.workflow : {};
  var items = Array.isArray(layout.items) ? layout.items : [];
  var relations = Array.isArray(layout.relations) ? layout.relations : [];

  function normalizeStatus(status) {
    var safe = String(status || '').toLowerCase();
    var states = Array.isArray(workflow.states) ? workflow.states.map(function (s) { return String(s || '').toLowerCase(); }) : [];
    return states.indexOf(safe) >= 0 ? safe : 'draft';
  }

  function statusLabel(status) {
    var safe = normalizeStatus(status);
    if (safe === 'in_progress') return tr.statusInProgress;
    if (safe === 'completed') return tr.statusCompleted;
    if (safe === 'approved') return tr.statusApproved;
    if (safe === 'dispatched') return tr.statusDispatched;
    return tr.statusDraft;
  }

  function statusChipStyle(status) {
    var safe = normalizeStatus(status);
    if (safe === 'dispatched') return 'display:inline-block;padding:2px 8px;border-radius:12px;background: var(--color-success-bg);color: var(--color-success-text);font-weight:600;font-size:11px;';
    if (safe === 'approved') return 'display:inline-block;padding:2px 8px;border-radius:12px;background: var(--color-success-bg);color: var(--color-success-text);font-weight:600;font-size:11px;';
    if (safe === 'completed') return 'display:inline-block;padding:2px 8px;border-radius:12px;background: var(--color-success-bg);color: var(--color-success-text);font-weight:600;font-size:11px;';
    if (safe === 'in_progress') return 'display:inline-block;padding:2px 8px;border-radius:12px;background: var(--color-success-bg);color: var(--color-success-text);font-weight:600;font-size:11px;';
    return 'display:inline-block;padding:2px 8px;border-radius:12px;background: var(--style-subtle-bg);color: var(--text);font-weight:600;font-size:11px;';
  }

  function actionLabelForStatus(status) {
    var safe = normalizeStatus(status);
    if (safe === 'in_progress') return tr.actionInProgress;
    if (safe === 'completed') return tr.actionCompleted;
    if (safe === 'approved') return tr.actionApprove;
    if (safe === 'dispatched') return tr.actionDispatch;
    return safe;
  }

  function roleLabel(role) {
    var safe = String(role || '').toLowerCase();
    if (safe === 'admin') return tr.modeAdmin || 'Admin';
    if (safe === 'operator') return tr.modeOperator || 'Operator';
    if (safe === 'qc') return tr.modeQc || 'QC';
    if (safe === 'dispatch') return tr.modeDispatch || 'Dispatch';
    return safe || tr.modeReadOnly || 'Read-only';
  }

  function normalizeHistoryEntries(history) {
    if (!Array.isArray(history)) return [];
    return history.filter(function (entry) { return entry && typeof entry === 'object'; });
  }

  function formatHistoryTimestamp(value) {
    var raw = String(value || '');
    if (!raw) return '-';
    var parsed = new Date(raw);
    if (isNaN(parsed.getTime())) return raw;
    return parsed.toLocaleString();
  }

  function delayWarningText(row) {
    if (row && row.is_overdue) {
      var overdueBy = String(row.overdue_by_label || '-');
      var sla = String(row.sla_max_label || '-');
      return tr.delayOverdue + ' +' + overdueBy + ' (' + tr.delaySla + ': ' + sla + ')';
    }
    var delayedCount = parseInt(row && row.delayed_transition_count || 0, 10) || 0;
    if (delayedCount > 0) {
      return tr.delayTransition + ' (' + delayedCount + ')';
    }
    return tr.delayNone;
  }

  function delayWarningStyle(row) {
    if (row && row.is_overdue) {
      return 'color: var(--text);font-weight:600;';
    }
    var delayedCount = parseInt(row && row.delayed_transition_count || 0, 10) || 0;
    if (delayedCount > 0) {
      return 'color: var(--text);font-weight:600;';
    }
    return 'color: var(--text);';
  }

  function allowedTransitionTargets(fromStatus) {
    var from = normalizeStatus(fromStatus);
    var transitions = workflow && workflow.transitions && typeof workflow.transitions === 'object' ? workflow.transitions : {};
    var transitionRoles = workflow && workflow.transition_roles && typeof workflow.transition_roles === 'object' ? workflow.transition_roles : {};
    var candidates = Array.isArray(transitions[from]) ? transitions[from] : [];
    return candidates.filter(function (candidate) {
      var to = normalizeStatus(candidate);
      var key = from + '->' + to;
      var roles = Array.isArray(transitionRoles[key]) ? transitionRoles[key].map(function (r) { return String(r || '').toLowerCase(); }) : [];
      return roles.length === 0 || roles.indexOf(String(runtimeRole || '').toLowerCase()) >= 0;
    }).map(function (candidate) { return normalizeStatus(candidate); });
  }

  function submitTransition(rowId, targetStatus) {
    var form = document.getElementById('runtime-submit-form');
    if (!form) return;
    var actionInput = form.querySelector('input[name="runtime_action"]');
    var rowInput = form.querySelector('input[name="row_id"]');
    var statusInput = form.querySelector('input[name="target_status"]');
    if (actionInput) actionInput.value = 'workflow_transition';
    if (rowInput) rowInput.value = String(rowId || '');
    if (statusInput) statusInput.value = String(targetStatus || '');
    form.submit();
  }

  function ensureComponentState(itemId) {
    var key = String(itemId || '');
    if (!state.componentState[key] || typeof state.componentState[key] !== 'object') {
      state.componentState[key] = {};
    }
    return state.componentState[key];
  }

  function itemById(itemId) {
    var target = String(itemId || '');
    return items.find(function (it) { return String(it.id || '') === target; }) || null;
  }

  function applyFilter(fieldKey, value) {
    var needle = String(value || '').toLowerCase();
    if (!fieldKey || needle === '') {
      state.filteredRows = state.rows.slice();
      return;
    }
    state.filteredRows = state.rows.filter(function (row) {
      if (!row || typeof row !== 'object') return false;
      return String(row[fieldKey] || '').toLowerCase().indexOf(needle) !== -1;
    });
  }

  function handleComponentInteraction(sourceId, targetId, type, payload) {
    var targetState = ensureComponentState(targetId);
    if (type === 'filter_to_table') {
      applyFilter(String(payload.field || ''), String(payload.value || ''));
      targetState.rows = state.filteredRows.slice();
      return true;
    }
    if (type === 'filter_to_kpi') {
      targetState.value = state.filteredRows.length;
      targetState.delta = String(payload.value || '') !== '' ? ('filtered: ' + state.filteredRows.length) : '';
      return true;
    }
    if (type === 'form_refresh_table') {
      targetState.rows = state.rows.slice();
      state.filteredRows = state.rows.slice();
      return true;
    }
    if (type === 'table_to_kpi_derived') {
      targetState.value = state.filteredRows.length;
      targetState.delta = state.selectedRow ? 'selected' : '';
      return true;
    }
    return false;
  }

  function dispatchComponentEvent(sourceId, eventName, payload) {
    var source = String(sourceId || '');
    if (!source) return;
    var changed = false;
    relations.forEach(function (relation) {
      if (!relation || String(relation.source_id || '') !== source) return;
      if (handleComponentInteraction(source, String(relation.target_id || ''), String(relation.type || 'affects_table'), payload || {})) {
        changed = true;
      }
    });
    if (changed) {
      renderLayout();
    }
  }

  function columnsFromRows(rows) {
    var first = Array.isArray(rows) && rows[0] && typeof rows[0] === 'object' ? rows[0] : {};
    var cols = Object.keys(first);
    if (cols.length > 0) return cols;
    return runtimeFields.map(function (field) { return String(field.key || ''); }).filter(Boolean);
  }

  function renderFilter(item, host) {
    var componentState = ensureComponentState(item.id);
    var label = document.createElement('label');
    label.className = 'form-label';
    label.textContent = tr.filter;

    var fieldSelect = document.createElement('select');
    fieldSelect.className = 'form-input';
    var defaultField = String(item.props && item.props.field || runtimeFields[0] && runtimeFields[0].key || '');
    runtimeFields.forEach(function (field) {
      var key = String(field.key || '');
      if (!key) return;
      var option = document.createElement('option');
      option.value = key;
      option.textContent = key;
      if (key === String(componentState.field || defaultField)) option.selected = true;
      fieldSelect.appendChild(option);
    });

    var input = document.createElement('input');
    input.className = 'form-input';
    input.type = 'text';
    input.placeholder = String(item.props && item.props.placeholder || tr.filterPlaceholder);
    input.value = String(componentState.value || '');

    fieldSelect.addEventListener('change', function () {
      componentState.field = fieldSelect.value;
      dispatchComponentEvent(item.id, 'onSelect', { field: fieldSelect.value, value: input.value });
    });

    input.addEventListener('input', function () {
      componentState.field = fieldSelect.value;
      componentState.value = input.value;
      dispatchComponentEvent(item.id, 'onChange', { field: fieldSelect.value, value: input.value });
    });

    host.appendChild(label);
    host.appendChild(fieldSelect);
    host.appendChild(input);
  }

  function renderTable(item, host) {
    var stateForItem = ensureComponentState(item.id);
    var rows = Array.isArray(stateForItem.rows) ? stateForItem.rows : state.filteredRows;

    var title = document.createElement('div');
    title.style.fontWeight = '600';
    title.style.marginBottom = '6px';
    title.textContent = String(item.props && item.props.title || tr.table);
    host.appendChild(title);

    if (!Array.isArray(rows) || rows.length === 0) {
      var empty = document.createElement('div');
      empty.className = 'muted';
      empty.textContent = tr.emptyRows;
      host.appendChild(empty);
      return;
    }

    var cols = columnsFromRows(rows).slice(0, 6).filter(function (col) { return String(col || '') !== 'status'; });
    var table = document.createElement('table');
    table.className = 'table';

    var thead = document.createElement('thead');
    var headRow = document.createElement('tr');
    cols.forEach(function (col) {
      var th = document.createElement('th');
      th.textContent = col;
      headRow.appendChild(th);
    });
    var statusHead = document.createElement('th');
    statusHead.textContent = tr.statusColumn;
    headRow.appendChild(statusHead);
    var timeHead = document.createElement('th');
    timeHead.textContent = tr.timeInStateColumn;
    headRow.appendChild(timeHead);
    var delayHead = document.createElement('th');
    delayHead.textContent = tr.delayWarningColumn;
    headRow.appendChild(delayHead);
    var actionsHead = document.createElement('th');
    actionsHead.textContent = tr.actionsColumn;
    headRow.appendChild(actionsHead);
    var historyHead = document.createElement('th');
    historyHead.textContent = tr.historyColumn;
    headRow.appendChild(historyHead);
    thead.appendChild(headRow);
    table.appendChild(thead);

    var tbody = document.createElement('tbody');
    rows.forEach(function (row) {
      var trRow = document.createElement('tr');
      trRow.style.cursor = 'pointer';
      if (row && row.is_overdue) {
        trRow.style.background = '#fef2f2';
      }
      trRow.addEventListener('click', function () {
        state.selectedRow = row;
        dispatchComponentEvent(item.id, 'onSelect', { row: row });
      });
      cols.forEach(function (col) {
        var td = document.createElement('td');
        td.textContent = String(row[col] || '');
        trRow.appendChild(td);
      });

      var statusCell = document.createElement('td');
      var statusNode = document.createElement('span');
      statusNode.style.cssText = statusChipStyle(row.status);
      statusNode.textContent = statusLabel(row.status);
      statusCell.appendChild(statusNode);
      trRow.appendChild(statusCell);

      var timeCell = document.createElement('td');
      timeCell.textContent = String(row.time_in_state_label || '-');
      trRow.appendChild(timeCell);

      var delayCell = document.createElement('td');
      delayCell.style.cssText = delayWarningStyle(row);
      delayCell.textContent = delayWarningText(row);
      trRow.appendChild(delayCell);

      var actionCell = document.createElement('td');
      var targets = allowedTransitionTargets(String(row.status || ''));
      if (targets.length === 0) {
        actionCell.textContent = '-';
      } else {
        targets.forEach(function (target) {
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'btn';
          btn.style.marginRight = '6px';
          btn.style.padding = '3px 8px';
          btn.textContent = actionLabelForStatus(target);
          btn.addEventListener('click', function (event) {
            event.preventDefault();
            submitTransition(String(row.id || ''), target);
          });
          actionCell.appendChild(btn);
        });
      }
      trRow.appendChild(actionCell);

      var historyCell = document.createElement('td');
      var historyBtn = document.createElement('button');
      historyBtn.type = 'button';
      historyBtn.className = 'btn';
      historyBtn.style.padding = '3px 8px';
      historyBtn.textContent = tr.actionHistory;
      historyBtn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        var rowId = String(row.id || '');
        state.historyExpanded[rowId] = !state.historyExpanded[rowId];
        renderLayout();
      });
      historyCell.appendChild(historyBtn);
      trRow.appendChild(historyCell);
      tbody.appendChild(trRow);

      var rowHistory = normalizeHistoryEntries(row.history);
      if (state.historyExpanded[String(row.id || '')]) {
        var historyRow = document.createElement('tr');
        var historyTd = document.createElement('td');
        historyTd.colSpan = cols.length + 5;
        historyTd.style.background = '#fafafa';

        var historyWrap = document.createElement('div');
        historyWrap.style.padding = '8px 4px';

        var historyTitle = document.createElement('div');
        historyTitle.style.fontWeight = '600';
        historyTitle.style.marginBottom = '6px';
        historyTitle.textContent = tr.historyTitle;
        historyWrap.appendChild(historyTitle);

        if (rowHistory.length === 0) {
          var emptyHistory = document.createElement('div');
          emptyHistory.className = 'muted';
          emptyHistory.textContent = tr.historyEmpty;
          historyWrap.appendChild(emptyHistory);
        } else {
          var list = document.createElement('div');
          rowHistory.forEach(function (entry) {
            var line = document.createElement('div');
            line.className = 'note';
            line.style.marginBottom = '6px';
            line.style.padding = '6px 8px';

            var transitionText = statusLabel(entry.from_status) + ' ' + tr.historyArrow + ' ' + statusLabel(entry.to_status);
            var primary = document.createElement('div');
            primary.style.fontWeight = '600';
            primary.textContent = transitionText;

            var meta = document.createElement('div');
            meta.className = 'muted';
            meta.style.fontSize = '11px';
            meta.textContent = tr.historyRole + ': ' + roleLabel(entry.role) + ' | ' + tr.historyActor + ': ' + String(entry.actor || '-') + ' | ' + tr.historyWhen + ': ' + formatHistoryTimestamp(entry.timestamp);

            var detail = document.createElement('div');
            detail.className = 'muted';
            detail.style.fontSize = '11px';
            var detailText = tr.historyDuration + ': ' + String(entry.duration_in_previous_state_label || '-');
            if (entry && entry.is_delayed) {
              detailText += ' | ' + tr.historyDelay + ': +' + String(entry.delay_by_label || '-');
            }
            detail.textContent = detailText;

            line.appendChild(primary);
            line.appendChild(meta);
            line.appendChild(detail);
            list.appendChild(line);
          });
          historyWrap.appendChild(list);
        }

        historyTd.appendChild(historyWrap);
        historyRow.appendChild(historyTd);
        tbody.appendChild(historyRow);
      }
    });
    table.appendChild(tbody);
    host.appendChild(table);
  }

  function renderKpi(item, host) {
    var itemState = ensureComponentState(item.id);
    var value = typeof itemState.value !== 'undefined' ? itemState.value : state.filteredRows.length;

    var label = document.createElement('div');
    label.style.fontSize = '11px';
    label.style.opacity = '0.8';
    label.textContent = String(item.props && item.props.label || tr.kpi);

    var valueNode = document.createElement('div');
    valueNode.style.fontSize = '24px';
    valueNode.style.fontWeight = '700';
    valueNode.textContent = String(value);

    var delta = document.createElement('div');
    delta.style.fontSize = '11px';
    delta.textContent = String(itemState.delta || '');

    host.appendChild(label);
    host.appendChild(valueNode);
    host.appendChild(delta);
  }

  function renderForm(item, host) {
    var title = document.createElement('div');
    title.style.fontWeight = '600';
    title.style.marginBottom = '6px';
    title.textContent = String(item.props && item.props.title || tr.form);
    host.appendChild(title);

    var values = {};
    runtimeFields.forEach(function (field) {
      var key = String(field.key || '');
      if (!key) return;
      var row = document.createElement('div');
      row.style.marginBottom = '6px';

      var label = document.createElement('label');
      label.className = 'form-label';
      label.textContent = key;

      var input = document.createElement('input');
      input.className = 'form-input';
      input.type = 'text';
      input.disabled = !runtimeCanEdit;
      input.addEventListener('input', function () {
        values[key] = input.value;
      });

      row.appendChild(label);
      row.appendChild(input);
      host.appendChild(row);
    });

    var submit = document.createElement('button');
    submit.type = 'button';
    submit.className = 'btn';
    submit.disabled = !runtimeCanEdit;
    submit.textContent = String(item.props && item.props.submit_label || tr.submit);
    submit.addEventListener('click', function () {
      if (!runtimeCanEdit) return;
      var form = document.getElementById('runtime-submit-form');
      if (!form) return;
      var actionInput = form.querySelector('input[name="runtime_action"]');
      var rowInput = form.querySelector('input[name="row_id"]');
      var statusInput = form.querySelector('input[name="target_status"]');
      if (actionInput) actionInput.value = '';
      if (rowInput) rowInput.value = '';
      if (statusInput) statusInput.value = '';
      Object.keys(values).forEach(function (key) {
        var field = form.querySelector('input[name="' + key + '"]');
        if (field) {
          field.value = String(values[key] || '');
        }
      });
      dispatchComponentEvent(item.id, 'onClick', { values: values });
      form.submit();
    });
    host.appendChild(submit);
  }

  function renderItem(item) {
    var block = document.createElement('div');
    block.className = 'note';
    block.style.gridColumn = String((parseInt(item.x, 10) || 0) + 1) + ' / span ' + String(parseInt(item.w, 10) || 12);
    block.style.gridRow = String((parseInt(item.y, 10) || 0) + 1) + ' / span ' + String(parseInt(item.h, 10) || 3);
    block.style.padding = '10px';
    block.setAttribute('data-runtime-item', String(item.id || ''));

    var component = String(item.component || 'table');
    if (component === 'filter') {
      renderFilter(item, block);
    } else if (component === 'table') {
      renderTable(item, block);
    } else if (component === 'form') {
      renderForm(item, block);
    } else if (component === 'kpi_card') {
      renderKpi(item, block);
    }

    return block;
  }

  function renderLayout() {
    var mount = document.getElementById('runtime-layout');
    if (!mount) return;
    mount.innerHTML = '';

    items.forEach(function (item) {
      mount.appendChild(renderItem(item));
    });
  }

  applyFilter(String(runtimeFields[0] && runtimeFields[0].key || ''), '');
  renderLayout();
})();
</script>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php';
