<?php
/**
 * Phase 12 — Snapshot / Apply / Event History
 *
 * Variables provided by route:
 *   $pageTitle    string
 *   $csrf         string
 *   $snapshotHistory  array<int, array>
 *   $applyHistory     array<int, array>
 *   $eventHistory     array<int, array>
 */
declare(strict_types=1);

$snapshotHistory = is_array($snapshotHistory ?? null) ? $snapshotHistory : [];
$applyHistory = is_array($applyHistory ?? null) ? $applyHistory : [];
$eventHistory = is_array($eventHistory ?? null) ? $eventHistory : [];
$packageHistory = is_array($packageHistory ?? null) ? $packageHistory : [];
$registryEntries = is_array($registryEntries ?? null) ? $registryEntries : [];
$exportedPackagePath = isset($exportedPackagePath) ? (string)$exportedPackagePath : '';
$registryInstalled = !empty($registryInstalled ?? false);

$lang = (string)(session_id() !== '' ? ($_SESSION['locale'] ?? 'en') : 'en');

$gs = static function (string $key) use ($lang): string {
    $dict = [
        'en' => [
            'title' => 'Studio History',
            'snapshot_history' => 'Snapshot History',
            'apply_history' => 'Apply History',
            'event_history' => 'Event Log',
            'snapshot_id' => 'Snapshot ID',
            'apply_id' => 'Apply ID',
            'status' => 'Status',
            'created_at' => 'Created At',
            'started_at' => 'Started At',
            'completed_at' => 'Completed At',
            'compile_id' => 'Compile ID',
            'hash' => 'Hash',
            'approval_valid' => 'Approval Valid',
            'has_rollback' => 'Has Rollback',
            'verification' => 'Verification',
            'bool.true' => 'Yes',
            'bool.false' => 'No',
            'empty' => 'No records found.',
            'event' => 'Event',
            'occurred_at' => 'Occurred At',
            'back' => 'Back to Studio',
            'package_history' => 'Package History',
            'package_filename' => 'Package File',
            'package_size' => 'Size',
            'package_modified' => 'Created',
            'package_signature' => 'Signature',
            'package_signed_at' => 'Signed At',
            'export_success' => 'Package exported successfully.',
            'export_form_title' => 'Export Snapshot Package',
            'export_snapshot_id_label' => 'Snapshot ID',
            'export_btn' => 'Export as ZIP',
            'registry_title' => 'Registry',
            'registry_package_id' => 'Package ID',
            'registry_status' => 'Status',
            'registry_installed_at' => 'Installed At',
            'registry_artifact_count' => 'Artifacts',
            'registry_approval' => 'Approval Valid',
            'registry_enable_btn' => 'Enable',
            'registry_disable_btn' => 'Disable',
            'registry_install_form_title' => 'Register Package',
            'registry_install_btn' => 'Register',
            'registry_install_success' => 'Package registered successfully.',
            'studio_tools_title' => 'Studio Workbench',
            'studio_tools_helper' => 'Studio is a governed workbench. These tools prepare, inspect, validate, and hand over owner-owned resources. Backend actions will be wired later through approved workflows.',
            'studio_tools_group_explore' => 'Explore',
            'studio_tools_group_build' => 'Build',
            'studio_tools_group_validate' => 'Validate',
            'studio_tools_group_govern' => 'Govern',
            'studio_tools_group_history' => 'History',
            'studio_tools_status_available' => 'Available',
            'studio_tools_status_read_only' => 'Read-only',
            'studio_tools_status_planned' => 'Planned',
            'studio_tools_status_requires_governed' => 'Requires governed workflow',
            'studio_tool_resource_explorer' => 'Resource Explorer / Library',
            'studio_tool_app_builder' => 'App Builder',
            'studio_tool_module_builder' => 'Module Builder',
            'studio_tool_view_layout_builder' => 'View / Layout Builder',
            'studio_tool_navigation_menu' => 'Navigation / Menu Tool',
            'studio_tool_widget_card_builder' => 'Widget / Card Builder',
            'studio_tool_report_builder' => 'Report Builder',
            'studio_tool_data_model_schema' => 'Data Model / DB Schema Tool',
            'studio_tool_validation_preview_center' => 'Validation / Preview Center',
            'studio_tool_approval_apply_center' => 'Approval / Apply Center',
            'studio_tool_change_history_snapshots' => 'Change History / Snapshots',
            'studio_tool_purpose_resource_explorer' => 'Discover existing owner resources and load inspectable context.',
            'studio_tool_purpose_app_builder' => 'Shape app-level draft structure and ownership metadata.',
            'studio_tool_purpose_module_builder' => 'Prepare module structure, contracts, and composition drafts.',
            'studio_tool_purpose_view_layout_builder' => 'Compose view and layout drafts before governed handoff.',
            'studio_tool_purpose_navigation_menu' => 'Draft route/menu exposure intent without changing runtime routing.',
            'studio_tool_purpose_widget_card_builder' => 'Prepare widget and card layouts for owner review.',
            'studio_tool_purpose_report_builder' => 'Draft report structure and export intent for governed review.',
            'studio_tool_purpose_data_model_schema' => 'Plan schema-level intent and impact before approved workflow.',
            'studio_tool_purpose_validation_preview_center' => 'Inspect validations and preview diffs in a read-only lane.',
            'studio_tool_purpose_approval_apply_center' => 'Review governed approval/apply state without direct runtime control.',
            'studio_tool_purpose_change_history_snapshots' => 'Review change lineage, snapshots, and audit history.',
            'studio_tools_boundary_owner_resources' => 'Works on owner-owned resources',
            'studio_tools_boundary_not_owner' => 'Does not own business modules',
            'studio_tools_boundary_no_runtime_without_apply' => 'No runtime changes until approved apply',
            'studio_tools_open_library' => 'Open Library',
            'studio_tools_open_history' => 'Open History',
          ],
          'ja' => [
            'title' => 'Studio履歴',
            'snapshot_history' => 'Snapshotの履歴',
            'apply_history' => 'Applyの履歴',
            'event_history' => 'イベントログ',
            'snapshot_id' => 'Snapshot ID',
            'apply_id' => 'Apply ID',
            'status' => 'ステータス',
            'created_at' => '作成日時',
            'started_at' => '開始日時',
            'completed_at' => '完了日時',
            'compile_id' => 'Compile ID',
            'hash' => 'ハッシュ',
            'approval_valid' => '承認有効',
            'has_rollback' => 'Rollbackあり',
            'verification' => '検証',
            'bool.true' => 'はい',
            'bool.false' => 'いいえ',
            'empty' => '記録が見つかりません。',
            'event' => 'イベント',
            'occurred_at' => '発生日時',
            'back' => 'Studioに戻る',
            'package_history' => 'パッケージ履歴',
            'package_filename' => 'パッケージファイル',
            'package_size' => 'サイズ',
            'package_modified' => '作成日時',
            'package_signature' => '署名',
            'package_signed_at' => '署名日時',
            'export_success' => 'パッケージが正常にエクスポートされました。',
            'export_form_title' => 'Snapshot Packageをエクスポート',
            'export_snapshot_id_label' => 'Snapshot ID',
            'export_btn' => 'ZIPとしてエクスポート',
            'registry_title' => 'レジストリ',
            'registry_package_id' => 'Package ID',
            'registry_status' => 'ステータス',
            'registry_installed_at' => 'インストール日時',
            'registry_artifact_count' => 'Artifact数',
            'registry_approval' => '承認有効',
            'registry_enable_btn' => '有効化',
            'registry_disable_btn' => '無効化',
            'registry_install_form_title' => 'Packageを登録',
            'registry_install_btn' => '登録',
            'registry_install_success' => 'Packageが正常に登録されました。',
            'studio_tools_title' => 'Studio Workbench',
            'studio_tools_helper' => 'Studio は統制されたワークベンチです。これらのツールは owner-owned リソースを準備・確認・検証し、引き渡しを支援します。バックエンド動作は承認済みワークフローで後から接続されます。',
            'studio_tools_group_explore' => '探索',
            'studio_tools_group_build' => '構築',
            'studio_tools_group_validate' => '検証',
            'studio_tools_group_govern' => '統制',
            'studio_tools_group_history' => '履歴',
            'studio_tools_status_available' => '利用可能',
            'studio_tools_status_read_only' => '読み取り専用',
            'studio_tools_status_planned' => '計画中',
            'studio_tools_status_requires_governed' => '統制ワークフローが必要',
            'studio_tool_resource_explorer' => 'リソースエクスプローラー / ライブラリ',
            'studio_tool_app_builder' => 'アプリビルダー',
            'studio_tool_module_builder' => 'モジュールビルダー',
            'studio_tool_view_layout_builder' => 'ビュー / レイアウトビルダー',
            'studio_tool_navigation_menu' => 'ナビゲーション / メニューツール',
            'studio_tool_widget_card_builder' => 'ウィジェット / カードビルダー',
            'studio_tool_report_builder' => 'レポートビルダー',
            'studio_tool_data_model_schema' => 'データモデル / DB スキーマツール',
            'studio_tool_validation_preview_center' => '検証 / プレビューセンター',
            'studio_tool_approval_apply_center' => '承認 / 適用センター',
            'studio_tool_change_history_snapshots' => '変更履歴 / スナップショット',
            'studio_tool_purpose_resource_explorer' => '既存の owner リソースを探索し、確認用コンテキストを読み込みます。',
            'studio_tool_purpose_app_builder' => 'アプリ下書き構造と所有メタデータを整えます。',
            'studio_tool_purpose_module_builder' => 'モジュール構成、契約、構成下書きを準備します。',
            'studio_tool_purpose_view_layout_builder' => '統制された引き渡し前にビュー/レイアウト下書きを構成します。',
            'studio_tool_purpose_navigation_menu' => '実行ルートを変更せずにナビ/メニュー公開意図を下書きします。',
            'studio_tool_purpose_widget_card_builder' => 'owner レビュー向けにウィジェット/カード構成を準備します。',
            'studio_tool_purpose_report_builder' => '統制レビュー向けにレポート構造と出力意図を下書きします。',
            'studio_tool_purpose_data_model_schema' => '承認ワークフロー前にスキーマ意図と影響を計画します。',
            'studio_tool_purpose_validation_preview_center' => '読み取り専用レーンで検証結果と差分を確認します。',
            'studio_tool_purpose_approval_apply_center' => '実行制御なしで承認/適用の統制状態を確認します。',
            'studio_tool_purpose_change_history_snapshots' => '変更系譜、スナップショット、監査履歴を確認します。',
            'studio_tools_boundary_owner_resources' => 'Works on owner-owned resources',
            'studio_tools_boundary_not_owner' => 'Does not own business modules',
            'studio_tools_boundary_no_runtime_without_apply' => 'No runtime changes until approved apply',
            'studio_tools_open_library' => 'ライブラリを開く',
            'studio_tools_open_history' => '履歴を開く',
          ],
          'ne' => [
            'title' => 'Studio इतिहास',
            'snapshot_history' => 'Snapshot इतिहास',
            'apply_history' => 'Apply इतिहास',
            'event_history' => 'घटना लग',
            'snapshot_id' => 'Snapshot ID',
            'apply_id' => 'Apply ID',
            'status' => 'स्थिति',
            'created_at' => 'बनाइएको',
            'started_at' => 'सुरु भएको',
            'completed_at' => 'सम्पन्न भएको',
            'compile_id' => 'Compile ID',
            'hash' => 'Hash',
            'approval_valid' => 'स्वीकृति मान्य',
            'has_rollback' => 'Rollback छ',
            'verification' => 'Verification',
            'bool.true' => 'हो',
            'bool.false' => 'होइन',
            'empty' => 'कुनै रेकर्ड फेला परेन।',
            'event' => 'घटना',
            'occurred_at' => 'भएको समय',
            'back' => 'Studioमा फर्कनुहोस्',
            'package_history' => 'Package इतिहास',
            'package_filename' => 'Package फाइल',
            'package_size' => 'आकार',
            'package_modified' => 'बनाइएको',
            'package_signature' => 'Signature',
            'package_signed_at' => 'Signed At',
            'export_success' => 'Package सफलतापूर्वक एक्सपोर्ट गरियो।',
            'export_form_title' => 'Snapshot Package एक्सपोर्ट',
            'export_snapshot_id_label' => 'Snapshot ID',
            'export_btn' => 'ZIP रूपमा एक्सपोर्ट',
            'registry_title' => 'Registry',
            'registry_package_id' => 'Package ID',
            'registry_status' => 'स्थिति',
            'registry_installed_at' => 'स्थापना मिति',
            'registry_artifact_count' => 'Artifact संख्या',
            'registry_approval' => 'स्वीकृति मान्य',
            'registry_enable_btn' => 'सक्षम गर्नुहोस्',
            'registry_disable_btn' => 'अक्षम गर्नुहोस्',
            'registry_install_form_title' => 'Package दर्ता गर्नुहोस्',
            'registry_install_btn' => 'दर्ता',
            'registry_install_success' => 'Package सफलतापूर्वक दर्ता गरियो।',
            'studio_tools_title' => 'Studio Workbench',
            'studio_tools_helper' => 'Studio is a governed workbench. These tools prepare, inspect, validate, and hand over owner-owned resources. Backend actions will be wired later through approved workflows.',
            'studio_tools_group_explore' => 'Explore',
            'studio_tools_group_build' => 'Build',
            'studio_tools_group_validate' => 'Validate',
            'studio_tools_group_govern' => 'Govern',
            'studio_tools_group_history' => 'History',
            'studio_tools_status_available' => 'Available',
            'studio_tools_status_read_only' => 'Read-only',
            'studio_tools_status_planned' => 'Planned',
            'studio_tools_status_requires_governed' => 'Requires governed workflow',
            'studio_tool_resource_explorer' => 'Resource Explorer / Library',
            'studio_tool_app_builder' => 'App Builder',
            'studio_tool_module_builder' => 'Module Builder',
            'studio_tool_view_layout_builder' => 'View / Layout Builder',
            'studio_tool_navigation_menu' => 'Navigation / Menu Tool',
            'studio_tool_widget_card_builder' => 'Widget / Card Builder',
            'studio_tool_report_builder' => 'Report Builder',
            'studio_tool_data_model_schema' => 'Data Model / DB Schema Tool',
            'studio_tool_validation_preview_center' => 'Validation / Preview Center',
            'studio_tool_approval_apply_center' => 'Approval / Apply Center',
            'studio_tool_change_history_snapshots' => 'Change History / Snapshots',
            'studio_tool_purpose_resource_explorer' => 'Discover existing owner resources and load inspectable context.',
            'studio_tool_purpose_app_builder' => 'Shape app-level draft structure and ownership metadata.',
            'studio_tool_purpose_module_builder' => 'Prepare module structure, contracts, and composition drafts.',
            'studio_tool_purpose_view_layout_builder' => 'Compose view and layout drafts before governed handoff.',
            'studio_tool_purpose_navigation_menu' => 'Draft route/menu exposure intent without changing runtime routing.',
            'studio_tool_purpose_widget_card_builder' => 'Prepare widget and card layouts for owner review.',
            'studio_tool_purpose_report_builder' => 'Draft report structure and export intent for governed review.',
            'studio_tool_purpose_data_model_schema' => 'Plan schema-level intent and impact before approved workflow.',
            'studio_tool_purpose_validation_preview_center' => 'Inspect validations and preview diffs in a read-only lane.',
            'studio_tool_purpose_approval_apply_center' => 'Review governed approval/apply state without direct runtime control.',
            'studio_tool_purpose_change_history_snapshots' => 'Review change lineage, snapshots, and audit history.',
            'studio_tools_boundary_owner_resources' => 'Works on owner-owned resources',
            'studio_tools_boundary_not_owner' => 'Does not own business modules',
            'studio_tools_boundary_no_runtime_without_apply' => 'No runtime changes until approved apply',
            'studio_tools_open_library' => 'Open Library',
            'studio_tools_open_history' => 'Open History',
          ],
        ];
    return (string)($dict[$lang][$key] ?? $dict['en'][$key] ?? $key);
};

  $gsBatch1 = static function (string $localKey) use ($gs): string {
    $globalKey = '';
    if ($localKey === 'studio_tools_title') {
      $globalKey = 'ops.gui_studio.studio_tools_title';
    } elseif ($localKey === 'studio_tools_helper') {
      $globalKey = 'ops.gui_studio.studio_tools_helper';
    }
    if ($globalKey !== '' && function_exists('t')) {
      $translated = (string)t($globalKey);
      if ($translated !== '' && $translated !== $globalKey) {
        return $translated;
      }
    }
    return $gs($localKey);
  };
?>

<style>
.gs-history-workbench-panel { margin-top: 0.6rem; }
.gs-history-workbench-title { margin: 0 0 0.2rem 0; }
.gs-history-workbench-helper { margin: 0 0 0.7rem 0; font-size: 0.85rem; color: var(--muted); }
.gs-history-workbench-grid { display: grid; gap: 0.65rem; }
.gs-history-workbench-group { margin: 0; }
.gs-history-workbench-group dt { font-size: 0.78rem; color: var(--muted); margin-bottom: 0.35rem; text-transform: uppercase; }
.gs-history-workbench-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.5rem; }
.gs-history-workbench-card { display: grid; gap: 0.3rem; padding: 0.55rem; border: 1px solid var(--style-border-soft); border-radius: 8px; background: var(--style-subtle-bg); color: inherit; text-decoration: none; }
.gs-history-workbench-card[aria-disabled="true"] { opacity: 0.9; }
.gs-history-workbench-title-row { display: flex; justify-content: space-between; gap: 0.5rem; align-items: flex-start; }
.gs-history-workbench-name { font-weight: 600; font-size: 0.85rem; }
.gs-history-workbench-status { display: inline-flex; align-items: center; font-size: 0.68rem; color: var(--muted); border: 1px solid var(--style-border-soft); border-radius: 999px; padding: 0.04rem 0.42rem; background: var(--style-content-bg); white-space: nowrap; }
.gs-history-workbench-purpose { margin: 0; font-size: 0.77rem; color: var(--muted); }
.gs-history-workbench-boundary { margin: 0; font-size: 0.74rem; color: var(--muted); }
.gs-history-workbench-link { font-size: 0.72rem; color: var(--accent); text-decoration: underline; }
</style>

<section class="card">
  <h2><?= e($gs('title')) ?></h2>

<section class="card gs-history-workbench-panel">
  <h3 class="gs-history-workbench-title"><?= e($gsBatch1('studio_tools_title')) ?></h3>
  <p class="gs-history-workbench-helper"><?= e($gsBatch1('studio_tools_helper')) ?></p>
  <div class="gs-history-workbench-grid">
    <dl class="gs-history-workbench-group">
      <dt><?= e($gs('studio_tools_group_explore')) ?></dt>
      <dd class="gs-history-workbench-cards">
        <a class="gs-history-workbench-card" href="/apps/studio/library">
          <div class="gs-history-workbench-title-row"><span class="gs-history-workbench-name"><?= e($gs('studio_tool_resource_explorer')) ?></span><span class="gs-history-workbench-status"><?= e($gs('studio_tools_status_available')) ?></span></div>
          <p class="gs-history-workbench-purpose"><?= e($gs('studio_tool_purpose_resource_explorer')) ?></p>
          <p class="gs-history-workbench-boundary"><?= e($gs('studio_tools_boundary_owner_resources')) ?></p>
          <span class="gs-history-workbench-link"><?= e($gs('studio_tools_open_library')) ?></span>
        </a>
      </dd>
    </dl>
    <dl class="gs-history-workbench-group">
      <dt><?= e($gs('studio_tools_group_build')) ?></dt>
      <dd class="gs-history-workbench-cards">
        <div class="gs-history-workbench-card" aria-disabled="true"><div class="gs-history-workbench-title-row"><span class="gs-history-workbench-name"><?= e($gs('studio_tool_app_builder')) ?></span><span class="gs-history-workbench-status"><?= e($gs('studio_tools_status_planned')) ?></span></div><p class="gs-history-workbench-purpose"><?= e($gs('studio_tool_purpose_app_builder')) ?></p><p class="gs-history-workbench-boundary"><?= e($gs('studio_tools_boundary_not_owner')) ?></p></div>
        <div class="gs-history-workbench-card" aria-disabled="true"><div class="gs-history-workbench-title-row"><span class="gs-history-workbench-name"><?= e($gs('studio_tool_module_builder')) ?></span><span class="gs-history-workbench-status"><?= e($gs('studio_tools_status_planned')) ?></span></div><p class="gs-history-workbench-purpose"><?= e($gs('studio_tool_purpose_module_builder')) ?></p><p class="gs-history-workbench-boundary"><?= e($gs('studio_tools_boundary_not_owner')) ?></p></div>
        <div class="gs-history-workbench-card" aria-disabled="true"><div class="gs-history-workbench-title-row"><span class="gs-history-workbench-name"><?= e($gs('studio_tool_view_layout_builder')) ?></span><span class="gs-history-workbench-status"><?= e($gs('studio_tools_status_planned')) ?></span></div><p class="gs-history-workbench-purpose"><?= e($gs('studio_tool_purpose_view_layout_builder')) ?></p><p class="gs-history-workbench-boundary"><?= e($gs('studio_tools_boundary_owner_resources')) ?></p></div>
        <div class="gs-history-workbench-card" aria-disabled="true"><div class="gs-history-workbench-title-row"><span class="gs-history-workbench-name"><?= e($gs('studio_tool_navigation_menu')) ?></span><span class="gs-history-workbench-status"><?= e($gs('studio_tools_status_planned')) ?></span></div><p class="gs-history-workbench-purpose"><?= e($gs('studio_tool_purpose_navigation_menu')) ?></p><p class="gs-history-workbench-boundary"><?= e($gs('studio_tools_boundary_not_owner')) ?></p></div>
        <div class="gs-history-workbench-card" aria-disabled="true"><div class="gs-history-workbench-title-row"><span class="gs-history-workbench-name"><?= e($gs('studio_tool_widget_card_builder')) ?></span><span class="gs-history-workbench-status"><?= e($gs('studio_tools_status_planned')) ?></span></div><p class="gs-history-workbench-purpose"><?= e($gs('studio_tool_purpose_widget_card_builder')) ?></p><p class="gs-history-workbench-boundary"><?= e($gs('studio_tools_boundary_owner_resources')) ?></p></div>
        <div class="gs-history-workbench-card" aria-disabled="true"><div class="gs-history-workbench-title-row"><span class="gs-history-workbench-name"><?= e($gs('studio_tool_report_builder')) ?></span><span class="gs-history-workbench-status"><?= e($gs('studio_tools_status_planned')) ?></span></div><p class="gs-history-workbench-purpose"><?= e($gs('studio_tool_purpose_report_builder')) ?></p><p class="gs-history-workbench-boundary"><?= e($gs('studio_tools_boundary_not_owner')) ?></p></div>
        <div class="gs-history-workbench-card" aria-disabled="true"><div class="gs-history-workbench-title-row"><span class="gs-history-workbench-name"><?= e($gs('studio_tool_data_model_schema')) ?></span><span class="gs-history-workbench-status"><?= e($gs('studio_tools_status_planned')) ?></span></div><p class="gs-history-workbench-purpose"><?= e($gs('studio_tool_purpose_data_model_schema')) ?></p><p class="gs-history-workbench-boundary"><?= e($gs('studio_tools_boundary_no_runtime_without_apply')) ?></p></div>
      </dd>
    </dl>
    <dl class="gs-history-workbench-group">
      <dt><?= e($gs('studio_tools_group_validate')) ?></dt>
      <dd class="gs-history-workbench-cards">
        <div class="gs-history-workbench-card" aria-disabled="true"><div class="gs-history-workbench-title-row"><span class="gs-history-workbench-name"><?= e($gs('studio_tool_validation_preview_center')) ?></span><span class="gs-history-workbench-status"><?= e($gs('studio_tools_status_read_only')) ?></span></div><p class="gs-history-workbench-purpose"><?= e($gs('studio_tool_purpose_validation_preview_center')) ?></p><p class="gs-history-workbench-boundary"><?= e($gs('studio_tools_boundary_no_runtime_without_apply')) ?></p></div>
      </dd>
    </dl>
    <dl class="gs-history-workbench-group">
      <dt><?= e($gs('studio_tools_group_govern')) ?></dt>
      <dd class="gs-history-workbench-cards">
        <div class="gs-history-workbench-card" aria-disabled="true"><div class="gs-history-workbench-title-row"><span class="gs-history-workbench-name"><?= e($gs('studio_tool_approval_apply_center')) ?></span><span class="gs-history-workbench-status"><?= e($gs('studio_tools_status_requires_governed')) ?></span></div><p class="gs-history-workbench-purpose"><?= e($gs('studio_tool_purpose_approval_apply_center')) ?></p><p class="gs-history-workbench-boundary"><?= e($gs('studio_tools_boundary_no_runtime_without_apply')) ?></p></div>
      </dd>
    </dl>
    <dl class="gs-history-workbench-group">
      <dt><?= e($gs('studio_tools_group_history')) ?></dt>
      <dd class="gs-history-workbench-cards">
        <a class="gs-history-workbench-card" href="/apps/studio/history">
          <div class="gs-history-workbench-title-row"><span class="gs-history-workbench-name"><?= e($gs('studio_tool_change_history_snapshots')) ?></span><span class="gs-history-workbench-status"><?= e($gs('studio_tools_status_available')) ?></span></div>
          <p class="gs-history-workbench-purpose"><?= e($gs('studio_tool_purpose_change_history_snapshots')) ?></p>
          <p class="gs-history-workbench-boundary"><?= e($gs('studio_tools_boundary_not_owner')) ?></p>
          <span class="gs-history-workbench-link"><?= e($gs('studio_tools_open_history')) ?></span>
        </a>
      </dd>
    </dl>
  </div>
</section>
</section>

<section class="card">
  <h3><?= e($gs('snapshot_history')) ?></h3>
  <?php if ($snapshotHistory === []): ?>
    <div class="note"><?= e($gs('empty')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e($gs('snapshot_id')) ?></th>
            <th><?= e($gs('compile_id')) ?></th>
            <th><?= e($gs('hash')) ?></th>
            <th><?= e($gs('approval_valid')) ?></th>
            <th><?= e($gs('created_at')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($snapshotHistory as $sn): ?>
            <?php if (!is_array($sn)) { continue; } ?>
            <tr>
              <td><code><?= e((string)($sn['snapshot_id'] ?? '')) ?></code></td>
              <td><code><?= e((string)($sn['compile_id'] ?? '')) ?></code></td>
              <td><code><?= e(substr((string)($sn['snapshot_hash'] ?? ''), 0, 24)) ?>…</code></td>
              <td><span class="status-chip <?= !empty($sn['approval_valid']) ? 'success' : 'danger' ?>"><?= !empty($sn['approval_valid']) ? e($gs('bool.true')) : e($gs('bool.false')) ?></span></td>
              <td><?= e((string)($sn['created_at'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card">
  <h3><?= e($gs('apply_history')) ?></h3>
  <?php if ($applyHistory === []): ?>
    <div class="note"><?= e($gs('empty')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e($gs('apply_id')) ?></th>
            <th><?= e($gs('snapshot_id')) ?></th>
            <th><?= e($gs('status')) ?></th>
            <th><?= e($gs('has_rollback')) ?></th>
            <th><?= e($gs('verification')) ?></th>
            <th><?= e($gs('started_at')) ?></th>
            <th><?= e($gs('completed_at')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($applyHistory as $ap): ?>
            <?php if (!is_array($ap)) { continue; } ?>
            <?php
              $apStatus = strtoupper((string)($ap['status'] ?? ''));
              $apStatusClass = match ($apStatus) {
                  'APPLIED' => 'success',
                  'FAILED' => 'danger',
                  default => 'warning',
              };
            ?>
            <tr>
              <td><code><?= e((string)($ap['apply_id'] ?? '')) ?></code></td>
              <td><code><?= e((string)($ap['snapshot_id'] ?? '')) ?></code></td>
              <td><span class="status-chip <?= e($apStatusClass) ?>"><?= e($apStatus) ?></span></td>
              <td><span class="status-chip <?= !empty($ap['has_rollback_binding']) ? 'success' : '' ?>"><?= !empty($ap['has_rollback_binding']) ? e($gs('bool.true')) : e($gs('bool.false')) ?></span></td>
              <?php $verificationStatus = strtoupper((string)($ap['verification_status'] ?? '')); ?>
              <?php $verificationClass = match ($verificationStatus) { 'VERIFIED' => 'success', 'FAILED' => 'danger', default => 'warning', }; ?>
              <td><span class="status-chip <?= e($verificationClass) ?>"><?= e($verificationStatus !== '' ? $verificationStatus : 'N/A') ?></span></td>
              <td><?= e((string)($ap['started_at'] ?? '')) ?></td>
              <td><?= e((string)($ap['completed_at'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card">
  <h3><?= e($gs('event_history')) ?></h3>
  <?php
    // Show last 50 events (most recent first)
    $recentEvents = array_reverse(array_slice($eventHistory, -50));
  ?>
  <?php if ($recentEvents === []): ?>
    <div class="note"><?= e($gs('empty')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e($gs('event')) ?></th>
            <th><?= e($gs('snapshot_id')) ?></th>
            <th><?= e($gs('apply_id')) ?></th>
            <th><?= e($gs('status')) ?></th>
            <th><?= e($gs('occurred_at')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentEvents as $ev): ?>
            <?php if (!is_array($ev)) { continue; } ?>
            <tr>
              <td><code><?= e((string)($ev['event'] ?? '')) ?></code></td>
              <td><code><?= e(substr((string)($ev['snapshot_id'] ?? ''), 0, 16)) ?></code></td>
              <td><code><?= e(substr((string)($ev['apply_id'] ?? ''), 0, 16)) ?></code></td>
              <td><?= e((string)($ev['status'] ?? '')) ?></td>
              <td><?= e((string)($ev['occurred_at'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card">
  <h3><?= e($gs('export_form_title')) ?></h3>
  <?php if ($exportedPackagePath !== ''): ?>
    <div class="note success"><?= e($gs('export_success')) ?> <code><?= e($exportedPackagePath) ?></code></div>
  <?php endif; ?>
  <?php if ($snapshotHistory !== []): ?>
    <form method="POST" action="/apps/studio/export-package">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <label for="export_snapshot_id"><?= e($gs('export_snapshot_id_label')) ?></label>
      <select id="export_snapshot_id" name="snapshot_id" required>
        <?php foreach ($snapshotHistory as $sn): ?>
          <?php if (!is_array($sn)) { continue; } ?>
          <option value="<?= e((string)($sn['snapshot_id'] ?? '')) ?>"><?= e((string)($sn['snapshot_id'] ?? '')) ?> (<?= e((string)($sn['created_at'] ?? '')) ?>)</option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn"><?= e($gs('export_btn')) ?></button>
    </form>
  <?php else: ?>
    <div class="note"><?= e($gs('empty')) ?></div>
  <?php endif; ?>
</section>

<section class="card">
  <h3><?= e($gs('package_history')) ?></h3>
  <?php if ($packageHistory === []): ?>
    <div class="note"><?= e($gs('empty')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e($gs('package_filename')) ?></th>
            <th><?= e($gs('package_size')) ?></th>
            <th><?= e($gs('package_modified')) ?></th>
            <th><?= e($gs('package_signature')) ?></th>
            <th><?= e($gs('package_signed_at')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($packageHistory as $pkg): ?>
            <?php if (!is_array($pkg)) { continue; } ?>
            <tr>
              <td><code><?= e((string)($pkg['filename'] ?? '')) ?></code></td>
              <td><?= e((string)($pkg['size_bytes'] ?? '')) ?> B</td>
              <td><?= e((string)($pkg['modified_at'] ?? '')) ?></td>
              <?php $signatureStatus = strtoupper((string)($pkg['signature_status'] ?? 'UNSIGNED')); ?>
              <?php $signatureClass = match ($signatureStatus) { 'VALID' => 'success', 'INVALID' => 'danger', default => 'warning', }; ?>
              <td><span class="status-chip <?= e($signatureClass) ?>"><?= e($signatureStatus) ?></span></td>
              <td><?= e((string)($pkg['signed_at'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card">
  <h3><?= e($gs('registry_install_form_title')) ?></h3>
  <?php if ($registryInstalled): ?>
    <div class="note success"><?= e($gs('registry_install_success')) ?></div>
  <?php endif; ?>
  <?php if ($snapshotHistory !== []): ?>
    <form method="POST" action="/apps/studio/registry-install">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <label for="registry_snapshot_id"><?= e($gs('export_snapshot_id_label')) ?></label>
      <select id="registry_snapshot_id" name="snapshot_id" required>
        <?php foreach ($snapshotHistory as $sn): ?>
          <?php if (!is_array($sn)) { continue; } ?>
          <option value="<?= e((string)($sn['snapshot_id'] ?? '')) ?>"><?= e((string)($sn['snapshot_id'] ?? '')) ?> (<?= e((string)($sn['created_at'] ?? '')) ?>)</option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn"><?= e($gs('registry_install_btn')) ?></button>
    </form>
  <?php else: ?>
    <div class="note"><?= e($gs('empty')) ?></div>
  <?php endif; ?>
</section>

<section class="card">
  <h3><?= e($gs('registry_title')) ?></h3>
  <?php if ($registryEntries === []): ?>
    <div class="note"><?= e($gs('empty')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e($gs('registry_package_id')) ?></th>
            <th><?= e($gs('snapshot_id')) ?></th>
            <th><?= e($gs('registry_status')) ?></th>
            <th><?= e($gs('registry_artifact_count')) ?></th>
            <th><?= e($gs('registry_approval')) ?></th>
            <th><?= e($gs('registry_installed_at')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($registryEntries as $reg): ?>
            <?php if (!is_array($reg)) { continue; } ?>
            <?php $regStatus = (string)($reg['status'] ?? 'disabled'); ?>
            <tr>
              <td><code><?= e((string)($reg['package_id'] ?? '')) ?></code></td>
              <td><code><?= e(substr((string)($reg['snapshot_id'] ?? ''), 0, 20)) ?></code></td>
              <td><span class="status-chip <?= $regStatus === 'enabled' ? 'success' : 'warning' ?>"><?= e($regStatus) ?></span></td>
              <td><?= e((string)($reg['artifact_count'] ?? '')) ?></td>
              <td><span class="status-chip <?= !empty($reg['approval_valid']) ? 'success' : 'danger' ?>"><?= !empty($reg['approval_valid']) ? e($gs('bool.true')) : e($gs('bool.false')) ?></span></td>
              <td><?= e((string)($reg['installed_at'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
