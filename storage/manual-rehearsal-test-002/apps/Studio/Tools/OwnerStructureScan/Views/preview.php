<?php
declare(strict_types=1);

$ossModel = isset($ownerStructureScanModel) && is_array($ownerStructureScanModel) ? $ownerStructureScanModel : [];
$scanRequested = !empty($ossModel['scan_requested']);
$scanResult = isset($ossModel['scan_result']) && is_array($ossModel['scan_result']) ? $ossModel['scan_result'] : null;
$classification = isset($ossModel['classification']) && is_array($ossModel['classification']) ? $ossModel['classification'] : null;
$contractV2Diagnosis = isset($ossModel['contract_v2_diagnosis']) && is_array($ossModel['contract_v2_diagnosis']) ? $ossModel['contract_v2_diagnosis'] : null;
$shellDomainModel = is_array($contractV2Diagnosis) && isset($contractV2Diagnosis['shell_domain_model']) && is_array($contractV2Diagnosis['shell_domain_model'])
  ? $contractV2Diagnosis['shell_domain_model']
  : null;
$migrationPlan = isset($ossModel['migration_plan']) && is_array($ossModel['migration_plan']) ? $ossModel['migration_plan'] : null;
$referenceDiscovery = isset($ossModel['reference_discovery']) && is_array($ossModel['reference_discovery']) ? $ossModel['reference_discovery'] : null;
$engineeringWorkspaceArtifacts = isset($ossModel['engineering_workspace_artifacts']) && is_array($ossModel['engineering_workspace_artifacts']) ? $ossModel['engineering_workspace_artifacts'] : null;
$coreOwnerScanSection = isset($ossModel['core_owner_scan_section']) && is_array($ossModel['core_owner_scan_section']) ? $ossModel['core_owner_scan_section'] : null;
$engineeringWorkspaceArtifactsSection = isset($ossModel['engineering_workspace_artifacts_section']) && is_array($ossModel['engineering_workspace_artifacts_section']) ? $ossModel['engineering_workspace_artifacts_section'] : null;
$migrationPlanSection = isset($ossModel['migration_plan_section']) && is_array($ossModel['migration_plan_section']) ? $ossModel['migration_plan_section'] : null;
$engineeringWorkspaceInitializationSection = isset($ossModel['engineering_workspace_initialization_section']) && is_array($ossModel['engineering_workspace_initialization_section']) ? $ossModel['engineering_workspace_initialization_section'] : null;
$referenceDiscoverySection = isset($ossModel['reference_discovery_section']) && is_array($ossModel['reference_discovery_section']) ? $ossModel['reference_discovery_section'] : null;
$engineeringWorkspaceInitialization = isset($ossModel['engineering_workspace_initialization']) && is_array($ossModel['engineering_workspace_initialization']) ? $ossModel['engineering_workspace_initialization'] : null;
$engineeringWorkspaceInitializationResult = isset($ossModel['engineering_workspace_initialization_result']) && is_array($ossModel['engineering_workspace_initialization_result']) ? $ossModel['engineering_workspace_initialization_result'] : null;
$owners = isset($ossModel['owners']) && is_array($ossModel['owners']) ? $ossModel['owners'] : [];
$selectedOwnerKey = (string)($ossModel['selected_owner_key'] ?? 'Manufacturing/Products');
$ownerError = (string)($ossModel['owner_error'] ?? '');
$csrfToken = (string)($ossModel['csrf'] ?? '');

$oss = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dict = [
        'en' => [
            'title' => 'Owner Structure Scan',
            'subtitle' => 'Read-only physical structure inventory and Owner Contract v2 migration diagnosis for a selected owner.',
            'owner_label' => 'Owner',
            'scan_btn' => 'Scan Owner Structure',
            'scan_desc' => 'Owners are discovered from the real filesystem under apps/* and apps/*/modules/*.',
            'owner_error' => 'Owner selection notice',
            'owner_type' => 'Owner type',
            'summary_title' => 'Owner summary',
            'root_path' => 'Owner root path',
            'duration' => 'Scan duration',
            'folders' => 'Total folders',
            'files' => 'Total files',
            'size' => 'Total size',
            'unknown' => 'Unknown / unclassified',
            'search' => 'Search paths or filenames',
            'sort' => 'Sort',
            'sort_name' => 'Name',
            'sort_size' => 'Size',
            'physical_location' => 'Physical location',
            'relative_location' => 'Relative path from APP_ROOT',
            'counts' => 'Count',
            'entries' => 'Individual entries',
            'file_count' => 'Files',
            'folder_count' => 'Folders',
            'copy' => 'Copy relative path',
            'contract_title' => 'Contract v2 migration summary',
            'contract_findings' => 'Findings table',
            'domain_model_title' => 'Shell domain model',
            'domain_model_desc' => 'Architectural domains are contract-level ownership areas; implementation folders are concrete folders mapped to each domain.',
            'domain_name' => 'Domain',
            'domain_purpose' => 'Purpose',
            'architectural_domains' => 'Architectural domains',
            'implementation_folders' => 'Implementation folders',
            'taxonomy_decisions' => 'Taxonomy decisions',
            'ownership_boundaries' => 'Ownership boundaries',
            'boundary_domain' => 'Domain',
            'boundary_owns' => 'Owns',
            'boundary_never' => 'Never',
            'status_present' => 'present',
            'status_missing' => 'missing',
            'decomposition_title' => 'Decomposition Candidates',
            'decomposition_desc' => 'Oversized files reported as engineering improvement opportunities, not Owner Contract violations.',
            'line_count' => 'Lines',
            'category' => 'Category',
            'status' => 'Status',
            'severity' => 'Severity',
            'current_path' => 'Current path',
            'target_path' => 'Target path',
            'reason' => 'Reason',
            'recommendation' => 'Recommendation',
            'auto_fix' => 'Auto-fix eligible',
            'no' => 'No',
            'physical_truth_title' => 'Phase 1 physical truth',
            'planner_title' => 'Migration planner',
            'planner_operations' => 'Planned operations',
            'dependency_graph' => 'Dependency graph',
            'execution_order' => 'Execution order',
            'operation' => 'Operation',
            'automatic' => 'Automatic',
            'destructive' => 'Destructive',
            'validation_required' => 'Validation required',
            'depends_on' => 'Depends on',
            'planner_readonly' => 'Planning only. No filesystem changes and no execution endpoint.',
            'blocker_status' => 'Blocker status',
            'blocker_reason' => 'Blocker reason',
            'preparation_required' => 'Preparation required',
            'preparation_owner' => 'Preparation owner',
            'preparation_target' => 'Preparation target',
            'after_preparation' => 'After preparation',
            'can_become_executable' => 'Can become executable',
            'reference_title' => 'Reference discovery',
            'reference_readonly' => 'Read-only evidence for reference-discovery blockers. Operations are not promoted or executed here.',
            'reference_ready' => 'Ready to promote',
            'reference_review' => 'Needs review',
            'reference_none' => 'No references found',
            'reference_low' => 'Low-confidence matches',
            'reference_count' => 'Reference count',
            'files_referencing' => 'Files referencing it',
            'confidence' => 'Confidence',
            'safe_to_promote' => 'Safe to promote',
            'promotion_reason' => 'Promotion reason',
            'reference_key' => 'Reference key',
            'migration_readiness_state' => 'Migration readiness state',
            'readiness_blocked_runtime_references' => 'blocked_runtime_references',
            'readiness_needs_tooling_review' => 'needs_tooling_review',
            'readiness_ready_for_manual_rename' => 'ready_for_manual_rename',
            'reference_relevance_summary' => 'Reference relevance summary',
            'reference_blocking_references' => 'Blocking references',
            'reference_non_blocking_references' => 'Non-blocking references',
            'matched_pattern' => 'Matched pattern',
            'current_reference' => 'Current reference',
            'proposed_replacement' => 'Proposed replacement',
            'not_deterministic' => 'Not deterministic',
            'category_runtime' => 'runtime',
            'category_tooling' => 'tooling',
            'category_docs' => 'docs',
            'category_self_reference' => 'self-reference',
            'match_type' => 'Match type',
            'matched_excerpt' => 'Matched excerpt',
            'line' => 'Line',
            'search_scope' => 'Search scope',
            'empty' => 'No scan has been run yet.',
            'empty_group' => 'No artifacts discovered in this group.',
            'read_only' => 'Read-only interface. No repair buttons and no mutations. Auto-fix is disabled for every finding.',
            'engineering_workspace_title' => 'Engineering Workspace Artifacts',
            'engineering_workspace_desc' => 'Engineering workspace artifact state for the selected owner.',
            'workspace_key' => 'Workspace key',
            'canonical_path' => 'Canonical path',
            'overall_state' => 'Overall state',
            'document' => 'Document',
            'document_state' => 'Document state',
            'valid_count' => 'Valid documents',
            'missing_count' => 'Missing documents',
            'invalid_count' => 'Invalid documents',
            'migration_conflict_count' => 'Migration/conflict documents',
            'reason_code' => 'Reason',
            'no_linked_workspace' => 'No linked Engineering Workspace is registered for this owner.',
            'state_Linked valid' => 'Linked valid',
            'state_Initialization required' => 'Initialization required',
            'state_Contract repair required' => 'Contract repair required',
            'state_Migration required' => 'Migration required',
            'state_Conflict review required' => 'Conflict review required',
            'state_Artifact resolution failed' => 'Artifact resolution failed',
            'state_Not applicable' => 'Not applicable',
            'developer_strip_title' => 'Developer Strip Preview',
            'developer_strip_desc' => 'Preview of how the Developer Strip would render workspace links for this owner.',
            'developer_strip_label' => 'Developer Strip',
            'developer_strip_empty' => 'No valid documents to render links.',
            'workspace_init_title' => 'Engineering Workspace Initialization',
            'workspace_init_desc' => 'Initialize only selected missing canonical Engineering Workspace artifacts for this exact owner.',
            'workspace_init_empty' => 'No workspace initialization operations are available for this scan.',
            'workspace_init_status' => 'Ready to initialize',
            'workspace_init_select' => 'Select',
            'workspace_init_document_type' => 'Document type',
            'workspace_init_action' => 'Initialize Selected Workspace Artifacts',
            'workspace_init_result_title' => 'Initialization result',
            'workspace_init_reload' => 'Reload / re-scan',
            'scan_unavailable' => 'Scan unavailable',
            'code' => 'Code',
            'retry_section' => 'Retry this section',
            'section_waiting' => 'Waiting for prerequisite scan sections.',
            'final_result' => 'Final result',
            'created' => 'Created',
            'already_exists' => 'Already exists',
            'blocked' => 'Blocked',
            'rejected' => 'Rejected',
            'relevance' => 'Relevance',
            'relevance_RUNTIME_BLOCKING' => 'Runtime blocking',
            'relevance_STUDIO_TOOLING' => 'Studio tooling',
            'relevance_ENGINEERING_WORKSPACE' => 'Engineering workspace',
            'relevance_DOCUMENTATION_HISTORY' => 'Documentation history',
            'relevance_OWNER_METADATA' => 'Owner metadata',
            'relevance_SELF_REFERENCE' => 'Self reference',
            'relevance_LOW_CONFIDENCE_TEXT' => 'Low-confidence text',
        ],
        'ja' => [
            'title' => 'Owner Structure Scan',
            'subtitle' => '選択した所有者の物理構造インベントリと Owner Contract v2 移行診断を読み取り専用で表示します。',
            'owner_label' => '所有者',
            'scan_btn' => '所有者構造を走査',
            'scan_desc' => '所有者は apps/* と apps/*/modules/* の実ファイルシステムから検出されます。',
            'owner_error' => '所有者選択の通知',
            'owner_type' => '所有者タイプ',
            'summary_title' => '所有者サマリー',
            'root_path' => '所有者ルートパス',
            'duration' => '走査時間',
            'folders' => 'フォルダ合計',
            'files' => 'ファイル合計',
            'size' => '合計サイズ',
            'unknown' => '不明 / 未分類',
            'search' => 'パスまたはファイル名を検索',
            'sort' => '並び替え',
            'sort_name' => '名前',
            'sort_size' => 'サイズ',
            'physical_location' => '物理位置',
            'relative_location' => 'APP_ROOT からの相対パス',
            'counts' => '件数',
            'entries' => '個別エントリ',
            'file_count' => 'ファイル',
            'folder_count' => 'フォルダ',
            'copy' => '相対パスをコピー',
            'contract_title' => 'Contract v2 移行サマリー',
            'contract_findings' => '検出事項テーブル',
            'domain_model_title' => 'Shell ドメインモデル',
            'domain_model_desc' => 'Architectural domains は契約レベルの所有領域、implementation folders は各ドメインに対応する実装フォルダです。',
            'domain_name' => 'ドメイン',
            'domain_purpose' => '目的',
            'architectural_domains' => 'Architectural domains',
            'implementation_folders' => 'Implementation folders',
            'taxonomy_decisions' => 'Taxonomy decisions',
            'ownership_boundaries' => 'Ownership boundaries',
            'boundary_domain' => 'ドメイン',
            'boundary_owns' => '所有',
            'boundary_never' => '禁止',
            'status_present' => 'present',
            'status_missing' => 'missing',
            'status' => '状態',
            'severity' => '重要度',
            'current_path' => '現在のパス',
            'target_path' => '移行先パス',
            'reason' => '理由',
            'recommendation' => '推奨',
            'auto_fix' => '自動修正対象',
            'no' => 'いいえ',
            'physical_truth_title' => 'Phase 1 物理事実',
            'planner_title' => '移行プランナー',
            'planner_operations' => '計画された操作',
            'dependency_graph' => '依存関係グラフ',
            'execution_order' => '実行順',
            'operation' => '操作',
            'automatic' => '自動',
            'destructive' => '破壊的',
            'validation_required' => '検証必須',
            'depends_on' => '依存',
            'planner_readonly' => '計画のみです。ファイル変更や実行エンドポイントはありません。',
            'blocker_status' => 'ブロッカー状態',
            'blocker_reason' => 'ブロッカー理由',
            'preparation_required' => '必要な準備',
            'preparation_owner' => '準備担当',
            'preparation_target' => '準備対象',
            'after_preparation' => '準備後',
            'can_become_executable' => '実行可能化',
            'reference_title' => '参照検出',
            'reference_readonly' => '参照検出ブロッカーの読み取り専用エビデンスです。ここでは操作の昇格や実行は行いません。',
            'reference_ready' => '昇格可能',
            'reference_review' => 'レビューが必要',
            'reference_none' => '参照なし',
            'reference_low' => '低信頼度一致',
            'reference_count' => '参照数',
            'files_referencing' => '参照ファイル',
            'confidence' => '信頼度',
            'safe_to_promote' => '昇格安全',
            'promotion_reason' => '昇格理由',
            'reference_key' => '参照キー',
            'migration_readiness_state' => '移行準備状態',
            'readiness_blocked_runtime_references' => 'blocked_runtime_references',
            'readiness_needs_tooling_review' => 'needs_tooling_review',
            'readiness_ready_for_manual_rename' => 'ready_for_manual_rename',
            'reference_relevance_summary' => '参照関連性サマリー',
            'reference_blocking_references' => 'ブロッキング参照',
            'reference_non_blocking_references' => '非ブロッキング参照',
            'matched_pattern' => '一致パターン',
            'current_reference' => '現在の参照',
            'proposed_replacement' => '提案置換',
            'not_deterministic' => '自動判定不可',
            'category_runtime' => 'runtime',
            'category_tooling' => 'tooling',
            'category_docs' => 'docs',
            'category_self_reference' => 'self-reference',
            'match_type' => '一致タイプ',
            'matched_excerpt' => '一致抜粋',
            'line' => '行',
            'search_scope' => '検索範囲',
            'empty' => 'まだ走査されていません。',
            'empty_group' => 'このグループに成果物は見つかりませんでした。',
            'read_only' => '読み取り専用インターフェースです。修復ボタンと変更はありません。すべての検出事項で自動修正は無効です。',
            'engineering_workspace_title' => 'エンジニアリングワークスペース成果物',
            'engineering_workspace_desc' => '選択した所有者のエンジニアリングワークスペースのアーティファクト状態。',
            'workspace_key' => 'ワークスペースキー',
            'canonical_path' => '正規パス',
            'overall_state' => '全体状態',
            'document' => 'ドキュメント',
            'document_state' => 'ドキュメント状態',
            'valid_count' => '有効なドキュメント',
            'missing_count' => '欠落ドキュメント',
            'invalid_count' => '無効なドキュメント',
            'migration_conflict_count' => '移行/競合ドキュメント',
            'reason_code' => '理由',
            'no_linked_workspace' => 'この所有者に登録されたリンク済みエンジニアリングワークスペースはありません。',
            'state_Linked valid' => 'リンク済み有効',
            'state_Initialization required' => '初期化が必要',
            'state_Contract repair required' => 'コントラクト修復が必要',
            'state_Migration required' => '移行が必要',
            'state_Conflict review required' => '競合レビューが必要',
            'state_Artifact resolution failed' => 'アーティファクト解決に失敗',
            'state_Not applicable' => '該当なし',
            'developer_strip_title' => 'デベロッパーストリッププレビュー',
            'developer_strip_desc' => 'この所有者のワークスペースリンクがデベロッパーストリップにどのように表示されるかのプレビュー。',
            'developer_strip_label' => 'Developer Strip',
            'developer_strip_empty' => 'リンクを表示する有効なドキュメントがありません。',
            'relevance' => '関連性',
            'relevance_RUNTIME_BLOCKING' => 'Runtime blocking',
            'relevance_STUDIO_TOOLING' => 'Studio tooling',
            'relevance_ENGINEERING_WORKSPACE' => 'Engineering workspace',
            'relevance_DOCUMENTATION_HISTORY' => 'Documentation history',
            'relevance_OWNER_METADATA' => 'Owner metadata',
            'relevance_SELF_REFERENCE' => 'Self reference',
            'relevance_LOW_CONFIDENCE_TEXT' => 'Low-confidence text',
        ],
        'ne' => [
            'title' => 'Owner Structure Scan',
            'subtitle' => 'छानिएको मालिकको वास्तविक फाइल संरचना पढ्न-मात्र सूची गर्छ। Phase 1 ले डिस्कमा भएको कुरा मात्र देखाउँछ।',
            'owner_label' => 'मालिक',
            'scan_btn' => 'मालिक संरचना स्क्यान गर्नुहोस्',
            'scan_desc' => 'मालिकहरू apps/* र apps/*/modules/* को वास्तविक फाइल प्रणालीबाट खोजिन्छन्।',
            'owner_error' => 'Owner selection notice',
            'owner_type' => 'Owner type',
            'summary_title' => 'मालिक सारांश',
            'root_path' => 'मालिक रुट पथ',
            'duration' => 'स्क्यान समय',
            'folders' => 'कुल फोल्डर',
            'files' => 'कुल फाइल',
            'size' => 'कुल आकार',
            'unknown' => 'अज्ञात / वर्गीकृत नभएको',
            'search' => 'पथ वा फाइलनाम खोज्नुहोस्',
            'sort' => 'क्रमबद्ध',
            'sort_name' => 'नाम',
            'sort_size' => 'आकार',
            'physical_location' => 'भौतिक स्थान',
            'relative_location' => 'APP_ROOT बाट सापेक्ष पथ',
            'counts' => 'गणना',
            'entries' => 'व्यक्तिगत प्रविष्टि',
            'file_count' => 'फाइल',
            'folder_count' => 'फोल्डर',
            'copy' => 'सापेक्ष पथ प्रतिलिपि गर्नुहोस्',
            'contract_title' => 'Contract v2 migration summary',
            'contract_findings' => 'Findings table',
            'domain_model_title' => 'Shell domain model',
            'domain_model_desc' => 'Architectural domains contract-level ownership area हुन्; implementation folders प्रत्येक domain सँग नक्सा भएका concrete folders हुन्।',
            'domain_name' => 'Domain',
            'domain_purpose' => 'Purpose',
            'architectural_domains' => 'Architectural domains',
            'implementation_folders' => 'Implementation folders',
            'taxonomy_decisions' => 'Taxonomy decisions',
            'ownership_boundaries' => 'Ownership boundaries',
            'boundary_domain' => 'Domain',
            'boundary_owns' => 'Owns',
            'boundary_never' => 'Never',
            'status_present' => 'present',
            'status_missing' => 'missing',
            'status' => 'Status',
            'severity' => 'Severity',
            'current_path' => 'Current path',
            'target_path' => 'Target path',
            'reason' => 'Reason',
            'recommendation' => 'Recommendation',
            'auto_fix' => 'Auto-fix eligible',
            'no' => 'No',
            'physical_truth_title' => 'Phase 1 physical truth',
            'planner_title' => 'Migration planner',
            'planner_operations' => 'Planned operations',
            'dependency_graph' => 'Dependency graph',
            'execution_order' => 'Execution order',
            'operation' => 'Operation',
            'automatic' => 'Automatic',
            'destructive' => 'Destructive',
            'validation_required' => 'Validation required',
            'depends_on' => 'Depends on',
            'planner_readonly' => 'Planning only. No filesystem changes and no execution endpoint.',
            'blocker_status' => 'Blocker status',
            'blocker_reason' => 'Blocker reason',
            'preparation_required' => 'Preparation required',
            'preparation_owner' => 'Preparation owner',
            'preparation_target' => 'Preparation target',
            'after_preparation' => 'After preparation',
            'can_become_executable' => 'Can become executable',
            'reference_title' => 'Reference discovery',
            'reference_readonly' => 'Read-only evidence for reference-discovery blockers. Operations are not promoted or executed here.',
            'reference_ready' => 'Ready to promote',
            'reference_review' => 'Needs review',
            'reference_none' => 'No references found',
            'reference_low' => 'Low-confidence matches',
            'reference_count' => 'Reference count',
            'files_referencing' => 'Files referencing it',
            'confidence' => 'Confidence',
            'safe_to_promote' => 'Safe to promote',
            'promotion_reason' => 'Promotion reason',
            'reference_key' => 'Reference key',
            'migration_readiness_state' => 'Migration readiness state',
            'readiness_blocked_runtime_references' => 'blocked_runtime_references',
            'readiness_needs_tooling_review' => 'needs_tooling_review',
            'readiness_ready_for_manual_rename' => 'ready_for_manual_rename',
            'reference_relevance_summary' => 'Reference relevance summary',
            'reference_blocking_references' => 'Blocking references',
            'reference_non_blocking_references' => 'Non-blocking references',
            'matched_pattern' => 'Matched pattern',
            'current_reference' => 'Current reference',
            'proposed_replacement' => 'Proposed replacement',
            'not_deterministic' => 'Not deterministic',
            'category_runtime' => 'runtime',
            'category_tooling' => 'tooling',
            'category_docs' => 'docs',
            'category_self_reference' => 'self-reference',
            'match_type' => 'Match type',
            'matched_excerpt' => 'Matched excerpt',
            'line' => 'Line',
            'search_scope' => 'Search scope',
            'empty' => 'अझै स्क्यान गरिएको छैन।',
            'empty_group' => 'यो समूहमा कुनै artifact भेटिएन।',
            'read_only' => 'पढ्न-मात्र इन्टरफेस। मर्मत बटन वा परिवर्तन छैन। सबै finding मा auto-fix बन्द छ।',
            'engineering_workspace_title' => 'इन्जिनियरिङ कार्यक्षेत्र artifacts',
            'engineering_workspace_desc' => 'चयन गरिएको मालिकको इन्जिनियरिङ कार्यक्षेत्र अवस्था।',
            'workspace_key' => 'कार्यक्षेत्र कुञ्जी',
            'canonical_path' => 'प्रामाणिक पथ',
            'overall_state' => 'समग्र अवस्था',
            'document' => 'कागजात',
            'document_state' => 'कागजात अवस्था',
            'valid_count' => 'मान्य कागजात',
            'missing_count' => 'हराइरहेको कागजात',
            'invalid_count' => 'अमान्य कागजात',
            'migration_conflict_count' => 'Migration/conflict कागजात',
            'reason_code' => 'कारण',
            'no_linked_workspace' => 'यस मालिकका लागि कुनै linked Engineering Workspace दर्ता गरिएको छैन।',
            'state_Linked valid' => 'लिङ्क गरिएको मान्य',
            'state_Initialization required' => 'प्रारम्भिकरण आवश्यक',
            'state_Contract repair required' => 'कन्ट्र्याक्ट मर्मत आवश्यक',
            'state_Migration required' => 'Migration आवश्यक',
            'state_Conflict review required' => 'Conflict review आवश्यक',
            'state_Artifact resolution failed' => 'Artifact resolution असफल',
            'state_Not applicable' => 'लागू हुँदैन',
            'developer_strip_title' => 'Developer Strip पूर्वावलोकन',
            'developer_strip_desc' => 'यस मालिकको लागि Developer Strip ले कार्यक्षेत्र लिङ्कहरू कसरी देखाउँछ भन्ने पूर्वावलोकन।',
            'developer_strip_label' => 'Developer Strip',
            'developer_strip_empty' => 'लिङ्क देखाउनको लागि कुनै मान्य कागजात छैन।',
            'relevance' => 'सान्दर्भिकता',
            'relevance_RUNTIME_BLOCKING' => 'Runtime blocking',
            'relevance_STUDIO_TOOLING' => 'Studio tooling',
            'relevance_ENGINEERING_WORKSPACE' => 'Engineering workspace',
            'relevance_DOCUMENTATION_HISTORY' => 'Documentation history',
            'relevance_OWNER_METADATA' => 'Owner metadata',
            'relevance_SELF_REFERENCE' => 'Self reference',
            'relevance_LOW_CONFIDENCE_TEXT' => 'Low-confidence text',
        ],
    ];

    $active = isset($dict[$lang]) ? $dict[$lang] : $dict['en'];
    return (string)($active[$key] ?? $dict['en'][$key] ?? $key);
};

$formatBytes = static function (int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    $units = ['KB', 'MB', 'GB', 'TB'];
    $value = $bytes / 1024;
    $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }
    return number_format($value, 2) . ' ' . $units[$unit];
};

$renderLocations = static function (array $locations): void {
    if ($locations === []) {
        echo '<span class="oss-muted">-</span>';
        return;
    }
    echo '<ul class="oss-location-list">';
    foreach (array_slice($locations, 0, 8) as $location) {
        echo '<li>' . e((string)$location) . '</li>';
    }
    if (count($locations) > 8) {
        echo '<li>' . e('+' . (string)(count($locations) - 8)) . '</li>';
    }
    echo '</ul>';
};

$renderSectionNotice = static function (array $section, string $title) use ($oss, $selectedOwnerKey): void {
    $status = (string)($section['status'] ?? '');
    if (!in_array($status, ['failed', 'empty'], true)) {
        return;
    }

    $message = (string)($section['message'] ?? '');
    $code = (string)($section['error_code'] ?? '');
    $heading = $status === 'failed' ? $oss('scan_unavailable') : $oss('section_waiting');
    ?>
    <section class="oss-section-notice oss-section-notice-<?= e($status) ?>" aria-label="<?= e($title . ' ' . $heading) ?>">
      <strong><?= e($title) ?> - <?= e($heading) ?></strong>
      <?php if ($message !== ''): ?>
        <p><?= e($message) ?></p>
      <?php endif; ?>
      <?php if ($code !== ''): ?>
        <p><span><?= e($oss('code')) ?>:</span> <code><?= e($code) ?></code></p>
      <?php endif; ?>
      <a href="/apps/studio/tools/owner-structure-scan?owner=<?= e(rawurlencode($selectedOwnerKey)) ?>&amp;scan=1"><?= e($oss('retry_section')) ?></a>
    </section>
    <?php
};
?>

<section class="st-card st-card-full oss-card">
  <header class="st-card-head">
    <h2><?= e($oss('title')) ?></h2>
    <p class="st-card-subtitle"><?= e($oss('subtitle')) ?></p>
  </header>

  <div class="oss-toolbar">
    <form method="get" action="/apps/studio/tools/owner-structure-scan" class="oss-scan-form">
      <label>
        <span><?= e($oss('owner_label')) ?></span>
        <select name="owner">
          <?php foreach ($owners as $owner): ?>
            <?php
              if (!is_array($owner)) {
                  continue;
              }
              $ownerKey = (string)($owner['owner_key'] ?? '');
              if ($ownerKey === '') {
                  continue;
              }
              $selectedAttr = $ownerKey === $selectedOwnerKey ? ' selected' : '';
              $ownerLabel = (string)($owner['display_label'] ?? $ownerKey);
              $ownerType = (string)($owner['owner_type'] ?? 'unknown');
            ?>
            <option value="<?= e($ownerKey) ?>"<?= $selectedAttr ?>><?= e($ownerLabel . ' [' . $ownerType . ']') ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <input type="hidden" name="scan" value="1">
      <button type="submit" class="btn btn-primary"><?= e($oss('scan_btn')) ?></button>
    </form>
    <p class="oss-note"><?= e($oss('scan_desc')) ?></p>
  </div>
  <?php if ($ownerError !== ''): ?>
    <p class="oss-owner-error"><strong><?= e($oss('owner_error')) ?>:</strong> <?= e($ownerError) ?></p>
  <?php endif; ?>
  <?php if (is_array($coreOwnerScanSection) && (string)($coreOwnerScanSection['status'] ?? '') === 'failed'): ?>
    <?php $renderSectionNotice($coreOwnerScanSection, $oss('physical_truth_title')); ?>
  <?php endif; ?>

  <?php if ($scanRequested && is_array($scanResult) && is_array($classification)): ?>
    <?php
      $groups = isset($classification['groups']) && is_array($classification['groups']) ? $classification['groups'] : [];
      $unknownCount = (int)($classification['unknown_count'] ?? 0);
    ?>
    <section class="oss-summary" aria-label="<?= e($oss('summary_title')) ?>">
      <div class="oss-summary-card"><dt><?= e($oss('owner_label')) ?></dt><dd><?= e((string)($scanResult['owner_label'] ?? '')) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($oss('owner_type')) ?></dt><dd><?= e((string)($scanResult['owner_type'] ?? 'unknown')) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($oss('root_path')) ?></dt><dd><?= e((string)($scanResult['owner_root_path'] ?? '')) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($oss('duration')) ?></dt><dd><?= e((string)($scanResult['duration_ms'] ?? 0)) ?> ms</dd></div>
      <div class="oss-summary-card"><dt><?= e($oss('folders')) ?></dt><dd><?= e((string)($scanResult['total_folders'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($oss('files')) ?></dt><dd><?= e((string)($scanResult['total_files'] ?? 0)) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($oss('size')) ?></dt><dd><?= e($formatBytes((int)($scanResult['total_size'] ?? 0))) ?></dd></div>
      <div class="oss-summary-card"><dt><?= e($oss('unknown')) ?></dt><dd><?= e((string)$unknownCount) ?></dd></div>
    </section>

    <?php if (is_array($contractV2Diagnosis)): ?>
      <?php
        $contractSummary = isset($contractV2Diagnosis['summary']) && is_array($contractV2Diagnosis['summary']) ? $contractV2Diagnosis['summary'] : [];
        $contractCounts = isset($contractSummary['counts']) && is_array($contractSummary['counts']) ? $contractSummary['counts'] : [];
        $contractFindings = isset($contractV2Diagnosis['findings']) && is_array($contractV2Diagnosis['findings']) ? $contractV2Diagnosis['findings'] : [];
        $decompositionCandidates = isset($contractV2Diagnosis['decomposition_candidates']) && is_array($contractV2Diagnosis['decomposition_candidates']) ? $contractV2Diagnosis['decomposition_candidates'] : [];
      ?>
      <section class="oss-contract" aria-label="<?= e($oss('contract_title')) ?>">
        <header class="oss-section-head">
          <h3><?= e($oss('contract_title')) ?></h3>
          <span><?= e((string)($contractV2Diagnosis['contract_version'] ?? 'Owner Contract v2')) ?></span>
        </header>
        <div class="oss-contract-summary">
          <div class="oss-summary-card"><dt>Total findings</dt><dd><?= e((string)($contractSummary['total_findings'] ?? count($contractFindings))) ?></dd></div>
          <?php foreach ($contractCounts as $statusLabel => $count): ?>
            <div class="oss-summary-card"><dt><?= e((string)$statusLabel) ?></dt><dd><?= e((string)$count) ?></dd></div>
          <?php endforeach; ?>
        </div>

        <?php if (is_array($shellDomainModel)): ?>
          <?php
            $domainRows = isset($shellDomainModel['domains']) && is_array($shellDomainModel['domains']) ? $shellDomainModel['domains'] : [];
            $taxonomyDecisions = isset($shellDomainModel['taxonomy_decisions']) && is_array($shellDomainModel['taxonomy_decisions']) ? $shellDomainModel['taxonomy_decisions'] : [];
            $ownershipBoundaries = isset($shellDomainModel['ownership_boundaries']) && is_array($shellDomainModel['ownership_boundaries']) ? $shellDomainModel['ownership_boundaries'] : [];
          ?>
          <details class="oss-contract-findings oss-domain-model" open>
            <summary><?= e($oss('domain_model_title')) ?></summary>
            <p class="oss-planner-note"><?= e($oss('domain_model_desc')) ?></p>
            <div class="oss-table-wrap">
              <table class="oss-finding-table">
                <thead>
                  <tr>
                    <th><?= e($oss('domain_name')) ?></th>
                    <th><?= e($oss('domain_purpose')) ?></th>
                    <th><?= e($oss('architectural_domains')) ?></th>
                    <th><?= e($oss('implementation_folders')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($domainRows as $domainRow): ?>
                    <?php
                      if (!is_array($domainRow)) {
                          continue;
                      }
                      $architectureItems = isset($domainRow['architectural_domains']) && is_array($domainRow['architectural_domains']) ? $domainRow['architectural_domains'] : [];
                      $implementationItems = isset($domainRow['implementation_folders']) && is_array($domainRow['implementation_folders']) ? $domainRow['implementation_folders'] : [];
                    ?>
                    <tr>
                      <td><strong><?= e((string)($domainRow['label'] ?? '')) ?></strong></td>
                      <td><?= e((string)($domainRow['purpose'] ?? '')) ?></td>
                      <td>
                        <ul class="oss-domain-list">
                          <?php foreach ($architectureItems as $item): ?>
                            <?php if (!is_array($item)) { continue; } ?>
                            <?php $status = (string)($item['status'] ?? 'missing'); ?>
                            <li>
                              <code><?= e((string)($item['path'] ?? '')) ?></code>
                              <span class="oss-status oss-status-<?= e($status) ?>"><?= e($oss($status === 'present' ? 'status_present' : 'status_missing')) ?></span>
                              <em><?= e((string)($item['rationale'] ?? '')) ?></em>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      </td>
                      <td>
                        <ul class="oss-domain-list">
                          <?php foreach ($implementationItems as $item): ?>
                            <?php if (!is_array($item)) { continue; } ?>
                            <?php $status = (string)($item['status'] ?? 'missing'); ?>
                            <li>
                              <code><?= e((string)($item['path'] ?? '')) ?></code>
                              <span class="oss-status oss-status-<?= e($status) ?>"><?= e($oss($status === 'present' ? 'status_present' : 'status_missing')) ?></span>
                              <em><?= e((string)($item['rationale'] ?? '')) ?></em>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php if ($taxonomyDecisions !== []): ?>
              <h4><?= e($oss('taxonomy_decisions')) ?></h4>
              <ul class="oss-domain-list">
                <?php foreach ($taxonomyDecisions as $decisionKey => $decisionValue): ?>
                  <li>
                    <strong><?= e(ucwords(str_replace('_', ' ', (string)$decisionKey))) ?>:</strong>
                    <span><?= e((string)$decisionValue) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <?php if ($ownershipBoundaries !== []): ?>
              <h4><?= e($oss('ownership_boundaries')) ?></h4>
              <div class="oss-table-wrap">
                <table class="oss-finding-table">
                  <thead>
                    <tr>
                      <th><?= e($oss('boundary_domain')) ?></th>
                      <th><?= e($oss('boundary_owns')) ?></th>
                      <th><?= e($oss('boundary_never')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($ownershipBoundaries as $boundary): ?>
                      <?php if (!is_array($boundary)) { continue; } ?>
                      <tr>
                        <td><strong><?= e((string)($boundary['domain'] ?? '')) ?></strong></td>
                        <td><?= e((string)($boundary['owns'] ?? '')) ?></td>
                        <td><?= e((string)($boundary['never'] ?? '')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </details>
        <?php endif; ?>

        <details class="oss-contract-findings" open>
          <summary><?= e($oss('contract_findings')) ?></summary>
          <div class="oss-table-wrap">
            <table class="oss-finding-table">
              <thead>
                <tr>
                  <th><?= e($oss('status')) ?></th>
                  <th><?= e($oss('severity')) ?></th>
                  <th><?= e($oss('current_path')) ?></th>
                  <th><?= e($oss('target_path')) ?></th>
                  <th><?= e($oss('reason')) ?></th>
                  <th><?= e($oss('recommendation')) ?></th>
                  <th><?= e($oss('auto_fix')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($contractFindings as $finding): ?>
                  <?php
                    if (!is_array($finding)) {
                        continue;
                    }
                    $status = (string)($finding['status'] ?? '');
                    $severity = (string)($finding['severity'] ?? 'info');
                  ?>
                  <tr>
                    <td><span class="oss-status oss-status-<?= e(strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $status))) ?>"><?= e($status) ?></span></td>
                    <td><span class="oss-severity oss-severity-<?= e($severity) ?>"><?= e($severity) ?></span></td>
                    <td><code><?= e((string)($finding['current_path'] ?? '')) ?></code></td>
                    <td><code><?= e((string)($finding['target_path'] ?? '')) ?></code></td>
                    <td><?= e((string)($finding['reason'] ?? '')) ?></td>
                    <td><?= e((string)($finding['recommendation'] ?? '')) ?></td>
                    <td><?= e($oss('no')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </details>

        <?php if ($decompositionCandidates !== []): ?>
          <details class="oss-contract-findings oss-decomposition-candidates" open>
            <summary><?= e($oss('decomposition_title')) ?> (<?= e((string)count($decompositionCandidates)) ?>)</summary>
            <p class="oss-planner-note"><?= e($oss('decomposition_desc')) ?></p>
            <div class="oss-table-wrap">
              <table class="oss-decomposition-table">
                <thead>
                  <tr>
                    <th><?= e($oss('current_path')) ?></th>
                    <th><?= e($oss('category')) ?></th>
                    <th><?= e($oss('line_count')) ?></th>
                    <th><?= e($oss('size')) ?></th>
                    <th><?= e($oss('reason')) ?></th>
                    <th><?= e($oss('recommendation')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($decompositionCandidates as $candidate): ?>
                    <?php if (!is_array($candidate)) { continue; } ?>
                    <tr>
                      <td><code><?= e((string)($candidate['current_path'] ?? '')) ?></code></td>
                      <td><?= e((string)($candidate['category'] ?? '')) ?></td>
                      <td><?= e((string)($candidate['line_count'] ?? 0)) ?></td>
                      <td><?= e($formatBytes((int)($candidate['size'] ?? 0))) ?></td>
                      <td><?= e((string)($candidate['reason'] ?? '')) ?></td>
                      <td><?= e((string)($candidate['recommendation'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </details>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if (!is_array($migrationPlan) && is_array($migrationPlanSection) && in_array((string)($migrationPlanSection['status'] ?? ''), ['failed', 'empty'], true)): ?>
      <?php $renderSectionNotice($migrationPlanSection, $oss('planner_title')); ?>
    <?php endif; ?>

    <?php if (is_array($migrationPlan)): ?>
      <?php
        $planSummary = isset($migrationPlan['summary']) && is_array($migrationPlan['summary']) ? $migrationPlan['summary'] : [];
        $operations = isset($migrationPlan['operations']) && is_array($migrationPlan['operations']) ? $migrationPlan['operations'] : [];
        $operationGroups = [
            'EXECUTABLE_NOW' => ['label' => 'Executable now', 'items' => []],
            'BLOCKED_BY_REFERENCE_DISCOVERY' => ['label' => 'Blocked - reference discovery', 'items' => []],
            'BLOCKED_BY_RUNTIME_SUPPORT' => ['label' => 'Blocked - runtime support', 'items' => []],
            'BLOCKED_BY_CONTRACT_DECISION' => ['label' => 'Blocked - contract decision', 'items' => []],
            'BLOCKED_BY_MISSING_TARGET_CONTRACT' => ['label' => 'Blocked - missing target contract', 'items' => []],
            'FUTURE_PHASE' => ['label' => 'Future phase', 'items' => []],
            'OBSERVATION_ONLY' => ['label' => 'Observations', 'items' => []],
        ];
        foreach ($operations as $operation) {
            if (!is_array($operation)) {
                continue;
            }
            $blockerStatus = (string)($operation['blocker_status'] ?? 'OBSERVATION_ONLY');
            if (!isset($operationGroups[$blockerStatus])) {
                $operationGroups[$blockerStatus] = ['label' => $blockerStatus, 'items' => []];
            }
            $operationGroups[$blockerStatus]['items'][] = $operation;
        }
      ?>
      <section class="oss-planner" aria-label="<?= e($oss('planner_title')) ?>">
        <header class="oss-section-head">
          <h3><?= e($oss('planner_title')) ?></h3>
          <span><?= e((string)($migrationPlan['planner_version'] ?? 'Owner Structure Migration Planner v1')) ?></span>
        </header>
        <p class="oss-planner-note"><?= e($oss('planner_readonly')) ?></p>
        <div class="oss-contract-summary">
          <?php foreach ($planSummary as $summaryKey => $summaryValue): ?>
            <div class="oss-summary-card"><dt><?= e(ucwords(str_replace('_', ' ', (string)$summaryKey))) ?></dt><dd><?= e((string)$summaryValue) ?></dd></div>
          <?php endforeach; ?>
        </div>

        <?php foreach ($operationGroups as $groupStatus => $group): ?>
          <?php
            $items = isset($group['items']) && is_array($group['items']) ? $group['items'] : [];
            if ($items === []) {
                continue;
            }
            $isExecutableGroup = $groupStatus === 'EXECUTABLE_NOW';
          ?>
          <details class="oss-contract-findings" open>
            <summary><?= e((string)$group['label']) ?> (<?= e((string)count($items)) ?>)</summary>
            <div class="oss-table-wrap">
              <?php if ($isExecutableGroup): ?>
                <table class="oss-plan-table">
                  <thead>
                    <tr>
                      <th><?= e($oss('execution_order')) ?></th>
                      <th><?= e($oss('operation')) ?></th>
                      <th><?= e($oss('current_path')) ?></th>
                      <th><?= e($oss('target_path')) ?></th>
                      <th><?= e($oss('reason')) ?></th>
                      <th><?= e($oss('automatic')) ?></th>
                      <th><?= e($oss('destructive')) ?></th>
                      <th><?= e($oss('validation_required')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($items as $operation): ?>
                      <?php if (!is_array($operation)) { continue; } ?>
                      <tr>
                        <td><?= e((string)($operation['execution_order'] ?? '')) ?></td>
                        <td><code><?= e((string)($operation['operation_type'] ?? '')) ?></code></td>
                        <td><code><?= e((string)($operation['source_path'] ?? '')) ?></code></td>
                        <td><code><?= e((string)($operation['target_path'] ?? '')) ?></code></td>
                        <td><?= e((string)($operation['reason'] ?? '')) ?></td>
                        <td><?= e((string)($operation['automatic'] ?? 'no')) ?></td>
                        <td><?= e((string)($operation['destructive'] ?? 'no')) ?></td>
                        <td><?= e((string)($operation['validation_required'] ?? 'no')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <table class="oss-plan-table">
                  <thead>
                    <tr>
                      <th><?= e($oss('operation')) ?></th>
                      <th><?= e($oss('current_path')) ?></th>
                      <th><?= e($oss('target_path')) ?></th>
                      <th><?= e($oss('blocker_status')) ?></th>
                      <th><?= e($oss('blocker_reason')) ?></th>
                      <th><?= e($oss('preparation_required')) ?></th>
                      <th><?= e($oss('preparation_owner')) ?></th>
                      <th><?= e($oss('preparation_target')) ?></th>
                      <th><?= e($oss('after_preparation')) ?></th>
                      <th><?= e($oss('can_become_executable')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($items as $operation): ?>
                      <?php if (!is_array($operation)) { continue; } ?>
                      <tr>
                        <td><code><?= e((string)($operation['operation_type'] ?? '')) ?></code></td>
                        <td><code><?= e((string)($operation['source_path'] ?? '')) ?></code></td>
                        <td><code><?= e((string)($operation['target_path'] ?? '')) ?></code></td>
                        <td><?= e((string)($operation['blocker_status'] ?? '')) ?></td>
                        <td><?= e((string)($operation['blocker_reason'] ?? '')) ?></td>
                        <td><?= e((string)($operation['preparation_required'] ?? '')) ?></td>
                        <td><?= e((string)($operation['preparation_owner'] ?? '')) ?></td>
                        <td><?= e((string)($operation['preparation_target'] ?? '')) ?></td>
                        <td>
                          <code><?= e((string)($operation['after_preparation_operation_type'] ?? '')) ?></code>
                          <?php if ((string)($operation['after_preparation_source_path'] ?? '') !== '' || (string)($operation['after_preparation_target_path'] ?? '') !== ''): ?>
                            <span class="oss-after-paths"><?= e((string)($operation['after_preparation_source_path'] ?? '')) ?> → <?= e((string)($operation['after_preparation_target_path'] ?? '')) ?></span>
                          <?php endif; ?>
                        </td>
                        <td><?= e((string)($operation['can_become_executable'] ?? 'no')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          </details>
        <?php endforeach; ?>

        <details class="oss-contract-findings">
          <summary><?= e($oss('dependency_graph')) ?></summary>
          <ul class="oss-dependency-list">
            <?php foreach ($operations as $operation): ?>
              <?php
                if (!is_array($operation)) {
                    continue;
                }
                $dependsOn = isset($operation['depends_on']) && is_array($operation['depends_on']) ? $operation['depends_on'] : [];
              ?>
              <li>
                <code><?= e((string)($operation['operation_id'] ?? '')) ?></code>
                <span><?= e((string)($operation['operation_type'] ?? '')) ?></span>
                <em><?= e($oss('depends_on')) ?>: <?= e($dependsOn === [] ? '-' : implode(', ', array_map('strval', $dependsOn))) ?></em>
              </li>
            <?php endforeach; ?>
          </ul>
        </details>
      </section>
    <?php endif; ?>

    <?php if (!is_array($referenceDiscovery) && is_array($referenceDiscoverySection) && in_array((string)($referenceDiscoverySection['status'] ?? ''), ['failed', 'empty'], true)): ?>
      <?php $renderSectionNotice($referenceDiscoverySection, $oss('reference_title')); ?>
    <?php endif; ?>

    <?php if (is_array($referenceDiscovery)): ?>
      <?php
        $referenceSummary = isset($referenceDiscovery['summary']) && is_array($referenceDiscovery['summary']) ? $referenceDiscovery['summary'] : [];
        $referenceGroups = isset($referenceDiscovery['groups']) && is_array($referenceDiscovery['groups']) ? $referenceDiscovery['groups'] : [];
        $referenceSearchScope = isset($referenceDiscovery['search_scope']) && is_array($referenceDiscovery['search_scope']) ? $referenceDiscovery['search_scope'] : [];
        $referenceGroupLabels = [
            'ready_to_promote' => $oss('reference_ready'),
            'needs_review' => $oss('reference_review'),
            'no_references_found' => $oss('reference_none'),
            'low_confidence_matches' => $oss('reference_low'),
        ];
      ?>
      <section class="oss-reference" aria-label="<?= e($oss('reference_title')) ?>">
        <header class="oss-section-head">
          <h3><?= e($oss('reference_title')) ?></h3>
          <span><?= e($oss('reference_readonly')) ?></span>
        </header>
        <div class="oss-contract-summary">
          <?php foreach ($referenceSummary as $summaryKey => $summaryValue): ?>
            <div class="oss-summary-card"><dt><?= e(ucwords(str_replace('_', ' ', (string)$summaryKey))) ?></dt><dd><?= e((string)$summaryValue) ?></dd></div>
          <?php endforeach; ?>
          <div class="oss-summary-card"><dt><?= e($oss('search_scope')) ?></dt><dd><?= e((string)($referenceSearchScope['scanned_files'] ?? 0)) ?> files</dd></div>
        </div>

        <?php foreach ($referenceGroupLabels as $referenceGroupKey => $referenceGroupLabel): ?>
          <?php
            $referenceItems = isset($referenceGroups[$referenceGroupKey]) && is_array($referenceGroups[$referenceGroupKey]) ? $referenceGroups[$referenceGroupKey] : [];
          ?>
          <details class="oss-contract-findings"<?= $referenceItems !== [] ? ' open' : '' ?>>
            <summary><?= e($referenceGroupLabel) ?> (<?= e((string)count($referenceItems)) ?>)</summary>
            <?php if ($referenceItems === []): ?>
              <p class="oss-empty-group"><?= e($oss('empty_group')) ?></p>
            <?php else: ?>
              <div class="oss-reference-list">
                <?php foreach ($referenceItems as $referenceItem): ?>
                  <?php
                    if (!is_array($referenceItem)) {
                        continue;
                    }
                    $references = isset($referenceItem['references']) && is_array($referenceItem['references']) ? $referenceItem['references'] : [];
                    $filesReferencing = isset($referenceItem['files_referencing']) && is_array($referenceItem['files_referencing']) ? $referenceItem['files_referencing'] : [];
                    $relevanceSummary = isset($referenceItem['relevance_summary']) && is_array($referenceItem['relevance_summary']) ? $referenceItem['relevance_summary'] : [];
                    $blockingReferences = [];
                    $nonBlockingReferences = [];
                    foreach ($references as $reference) {
                      if (!is_array($reference)) {
                        continue;
                      }
                      $relevance = (string)($reference['relevance'] ?? '');
                      if (in_array($relevance, ['RUNTIME_BLOCKING', 'STUDIO_TOOLING', 'LOW_CONFIDENCE_TEXT'], true)) {
                        $blockingReferences[] = $reference;
                      } else {
                        $nonBlockingReferences[] = $reference;
                      }
                    }
                  ?>
                  <article class="oss-reference-item">
                    <dl class="oss-reference-facts">
                      <div>
                        <dt><?= e($oss('operation')) ?></dt>
                        <dd><code><?= e((string)($referenceItem['operation_type'] ?? '')) ?></code> <span><?= e((string)($referenceItem['operation_id'] ?? '')) ?></span></dd>
                      </div>
                      <div>
                        <dt><?= e($oss('current_path')) ?></dt>
                        <dd><code><?= e((string)($referenceItem['source_path'] ?? '')) ?></code></dd>
                      </div>
                      <div>
                        <dt><?= e($oss('target_path')) ?></dt>
                        <dd><code><?= e((string)($referenceItem['target_path'] ?? '')) ?></code></dd>
                      </div>
                      <div>
                        <dt><?= e($oss('reference_key')) ?></dt>
                        <dd><code><?= e((string)($referenceItem['reference_key'] ?? '')) ?></code></dd>
                      </div>
                      <div>
                        <dt><?= e($oss('reference_count')) ?></dt>
                        <dd><?= e((string)($referenceItem['reference_count'] ?? 0)) ?></dd>
                      </div>
                      <div>
                        <dt><?= e($oss('confidence')) ?></dt>
                        <dd><?= e((string)($referenceItem['confidence'] ?? 'low')) ?></dd>
                      </div>
                      <div>
                        <dt><?= e($oss('safe_to_promote')) ?></dt>
                        <dd><?= e((string)($referenceItem['safe_to_promote'] ?? 'no')) ?></dd>
                      </div>
                      <div>
                        <dt><?= e($oss('promotion_reason')) ?></dt>
                        <dd><?= e((string)($referenceItem['promotion_reason'] ?? '')) ?></dd>
                      </div>
                      <div>
                        <dt><?= e($oss('migration_readiness_state')) ?></dt>
                        <?php $readiness = (string)($referenceItem['migration_readiness_state'] ?? 'needs_tooling_review'); ?>
                        <dd><?= e($oss('readiness_' . $readiness)) ?></dd>
                      </div>
                      <div>
                        <dt><?= e($oss('files_referencing')) ?></dt>
                        <dd><?php $renderLocations($filesReferencing); ?></dd>
                      </div>
                    </dl>

                    <?php if ($relevanceSummary !== []): ?>
                      <div class="oss-table-wrap">
                        <table class="oss-reference-table">
                          <thead>
                            <tr>
                              <th><?= e($oss('reference_relevance_summary')) ?></th>
                              <th><?= e($oss('reference_count')) ?></th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($relevanceSummary as $relevanceKey => $relevanceCount): ?>
                              <tr>
                                <td><?= e($oss('relevance_' . (string)$relevanceKey)) ?></td>
                                <td><?= e((string)$relevanceCount) ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>

                    <?php if ($blockingReferences !== []): ?>
                      <h4><?= e($oss('reference_blocking_references')) ?> (<?= e((string)count($blockingReferences)) ?>)</h4>
                      <div class="oss-table-wrap">
                        <table class="oss-reference-table">
                          <thead>
                            <tr>
                              <th><?= e($oss('files_referencing')) ?></th>
                              <th><?= e($oss('line')) ?></th>
                              <th><?= e($oss('matched_pattern')) ?></th>
                              <th><?= e($oss('current_reference')) ?></th>
                              <th><?= e($oss('proposed_replacement')) ?></th>
                              <th><?= e($oss('category')) ?></th>
                              <th><?= e($oss('relevance')) ?></th>
                              <th><?= e($oss('matched_excerpt')) ?></th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($blockingReferences as $reference): ?>
                              <?php if (!is_array($reference)) { continue; } ?>
                              <?php
                                $relevance = (string)($reference['relevance'] ?? 'RUNTIME_BLOCKING');
                                $category = (string)($reference['category'] ?? 'runtime');
                                $replacement = (string)($reference['proposed_replacement'] ?? '');
                              ?>
                              <tr>
                                <td><code><?= e((string)($reference['file_path'] ?? '')) ?></code></td>
                                <td><?= e((string)($reference['line_number'] ?? '')) ?></td>
                                <td><code><?= e((string)($reference['matched_pattern'] ?? '')) ?></code></td>
                                <td><code><?= e((string)($reference['current_reference'] ?? '')) ?></code></td>
                                <td><code><?= e($replacement !== '' ? $replacement : $oss('not_deterministic')) ?></code></td>
                                <td><?= e($oss('category_' . $category)) ?></td>
                                <td><?= e($oss('relevance_' . $relevance)) ?></td>
                                <td><?= e((string)($reference['matched_text_excerpt'] ?? '')) ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>

                    <?php if ($nonBlockingReferences !== []): ?>
                      <h4><?= e($oss('reference_non_blocking_references')) ?> (<?= e((string)count($nonBlockingReferences)) ?>)</h4>
                      <div class="oss-table-wrap">
                        <table class="oss-reference-table">
                          <thead>
                            <tr>
                              <th><?= e($oss('files_referencing')) ?></th>
                              <th><?= e($oss('line')) ?></th>
                              <th><?= e($oss('matched_pattern')) ?></th>
                              <th><?= e($oss('current_reference')) ?></th>
                              <th><?= e($oss('proposed_replacement')) ?></th>
                              <th><?= e($oss('category')) ?></th>
                              <th><?= e($oss('relevance')) ?></th>
                              <th><?= e($oss('matched_excerpt')) ?></th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($nonBlockingReferences as $reference): ?>
                              <?php if (!is_array($reference)) { continue; } ?>
                              <?php
                                $relevance = (string)($reference['relevance'] ?? 'RUNTIME_BLOCKING');
                                $category = (string)($reference['category'] ?? 'docs');
                                $replacement = (string)($reference['proposed_replacement'] ?? '');
                              ?>
                              <tr>
                                <td><code><?= e((string)($reference['file_path'] ?? '')) ?></code></td>
                                <td><?= e((string)($reference['line_number'] ?? '')) ?></td>
                                <td><code><?= e((string)($reference['matched_pattern'] ?? '')) ?></code></td>
                                <td><code><?= e((string)($reference['current_reference'] ?? '')) ?></code></td>
                                <td><code><?= e($replacement !== '' ? $replacement : $oss('not_deterministic')) ?></code></td>
                                <td><?= e($oss('category_' . $category)) ?></td>
                                <td><?= e($oss('relevance_' . $relevance)) ?></td>
                                <td><?= e((string)($reference['matched_text_excerpt'] ?? '')) ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </details>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if (is_array($engineeringWorkspaceArtifactsSection) && (string)($engineeringWorkspaceArtifactsSection['status'] ?? '') === 'failed'): ?>
      <?php $renderSectionNotice($engineeringWorkspaceArtifactsSection, $oss('engineering_workspace_title')); ?>
    <?php endif; ?>

    <?php if (is_array($engineeringWorkspaceArtifacts)): ?>
      <section class="oss-contract" aria-label="<?= e($oss('engineering_workspace_title')) ?>">
        <header class="oss-section-head">
          <h3><?= e($oss('engineering_workspace_title')) ?></h3>
          <span><?= e($oss('engineering_workspace_desc')) ?></span>
        </header>

        <div class="oss-contract-summary">
          <div class="oss-summary-card"><dt><?= e($oss('workspace_key')) ?></dt><dd><?= e((string)($engineeringWorkspaceArtifacts['workspace_key'] ?? '')) ?></dd></div>
          <div class="oss-summary-card"><dt><?= e($oss('canonical_path')) ?></dt><dd><code><?= e((string)($engineeringWorkspaceArtifacts['canonical_relative_path'] ?? '')) ?></code></dd></div>
          <div class="oss-summary-card"><dt><?= e($oss('overall_state')) ?></dt><dd><?php
            $overallState = (string)($engineeringWorkspaceArtifacts['overall_state'] ?? 'Not applicable');
            $stateLabelKey = 'state_' . $overallState;
            echo e($oss($stateLabelKey));
          ?></dd></div>
          <div class="oss-summary-card"><dt><?= e($oss('valid_count')) ?></dt><dd><?= e((string)($engineeringWorkspaceArtifacts['valid_count'] ?? 0)) ?></dd></div>
          <div class="oss-summary-card"><dt><?= e($oss('missing_count')) ?></dt><dd><?= e((string)($engineeringWorkspaceArtifacts['missing_count'] ?? 0)) ?></dd></div>
          <div class="oss-summary-card"><dt><?= e($oss('invalid_count')) ?></dt><dd><?= e((string)($engineeringWorkspaceArtifacts['invalid_count'] ?? 0)) ?></dd></div>
          <div class="oss-summary-card"><dt><?= e($oss('migration_conflict_count')) ?></dt><dd><?= e((string)((int)($engineeringWorkspaceArtifacts['migration_count'] ?? 0) + (int)($engineeringWorkspaceArtifacts['conflict_count'] ?? 0))) ?></dd></div>
        </div>

        <?php
          $ewError = (string)($engineeringWorkspaceArtifacts['error'] ?? '');
        ?>
        <?php if ($ewError !== ''): ?>
          <p class="oss-owner-error" style="margin:0.65rem 0;"><strong>Artifact resolution error:</strong> <?= e($ewError) ?></p>
        <?php endif; ?>

        <?php
          $ewDocuments = isset($engineeringWorkspaceArtifacts['documents']) && is_array($engineeringWorkspaceArtifacts['documents']) ? $engineeringWorkspaceArtifacts['documents'] : [];
          $isSupported = !empty($engineeringWorkspaceArtifacts['is_supported_workspace']);
        ?>

        <?php if (!$isSupported && $ewError === ''): ?>
          <p class="oss-empty-group"><?= e($oss('no_linked_workspace')) ?></p>
        <?php endif; ?>

        <?php if ($isSupported && $ewDocuments !== []): ?>
          <details class="oss-contract-findings" open>
            <summary><?= e($oss('document')) ?></summary>
            <div class="oss-table-wrap">
              <table class="oss-finding-table">
                <thead>
                  <tr>
                    <th><?= e($oss('document')) ?></th>
                    <th><?= e($oss('canonical_path')) ?></th>
                    <th><?= e($oss('document_state')) ?></th>
                    <th><?= e($oss('reason_code')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($ewDocuments as $ewDoc): ?>
                    <?php if (!is_array($ewDoc)) { continue; } ?>
                    <tr>
                      <td><strong><?= e((string)($ewDoc['document_label'] ?? '')) ?></strong></td>
                      <td><code><?= e((string)($ewDoc['canonical_path'] ?? '')) ?></code></td>
                      <td>
                        <?php
                          $docState = (string)($ewDoc['status'] ?? $ewDoc['state'] ?? 'Initialization required');
                          $docMachineState = (string)($ewDoc['state'] ?? '');
                          $docStateClass = $docMachineState === 'linked_valid' ? 'oss-status-native-v2' : 'oss-status-migration-required';
                        ?>
                        <span class="oss-status <?= e($docStateClass) ?>"><?= e($docState) ?></span>
                      </td>
                      <td><code><?= e((string)($ewDoc['reason_code'] ?? '')) ?></code></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </details>
        <?php endif; ?>

        <?php if (is_array($engineeringWorkspaceInitializationResult)): ?>
          <?php
            $initResults = isset($engineeringWorkspaceInitializationResult['results']) && is_array($engineeringWorkspaceInitializationResult['results'])
                ? $engineeringWorkspaceInitializationResult['results']
                : [];
          ?>
          <section class="oss-workspace-init-result" aria-label="<?= e($oss('workspace_init_result_title')) ?>">
            <header class="oss-section-head">
              <h4><?= e($oss('workspace_init_result_title')) ?></h4>
              <a href="/apps/studio/tools/owner-structure-scan?owner=<?= e(rawurlencode($selectedOwnerKey)) ?>&amp;scan=1"><?= e($oss('workspace_init_reload')) ?></a>
            </header>
            <div class="oss-table-wrap">
              <table class="oss-plan-table">
                <thead>
                  <tr>
                    <th><?= e($oss('operation')) ?></th>
                    <th><?= e($oss('target_path')) ?></th>
                    <th><?= e($oss('final_result')) ?></th>
                    <th><?= e($oss('reason_code')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($initResults as $initResult): ?>
                    <?php if (!is_array($initResult)) { continue; } ?>
                    <tr>
                      <td><code><?= e((string)($initResult['operation_type'] ?? '')) ?></code></td>
                      <td><code><?= e((string)($initResult['target_path'] ?? '')) ?></code></td>
                      <td><?= e((string)($initResult['final_result'] ?? '')) ?></td>
                      <td><code><?= e((string)($initResult['reason_code'] ?? '')) ?></code></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>
        <?php endif; ?>

        <?php if (!is_array($engineeringWorkspaceInitialization) && is_array($engineeringWorkspaceInitializationSection) && in_array((string)($engineeringWorkspaceInitializationSection['status'] ?? ''), ['failed', 'empty'], true)): ?>
          <?php $renderSectionNotice($engineeringWorkspaceInitializationSection, $oss('workspace_init_title')); ?>
        <?php endif; ?>

        <?php if (is_array($engineeringWorkspaceInitialization)): ?>
          <?php
            $initOperations = isset($engineeringWorkspaceInitialization['eligible_operations']) && is_array($engineeringWorkspaceInitialization['eligible_operations'])
                ? $engineeringWorkspaceInitialization['eligible_operations']
                : [];
            $initFingerprint = (string)($engineeringWorkspaceInitialization['fingerprint'] ?? '');
          ?>
          <section class="oss-workspace-init" aria-label="<?= e($oss('workspace_init_title')) ?>">
            <header class="oss-section-head">
              <h4><?= e($oss('workspace_init_title')) ?></h4>
              <span><?= e($oss('workspace_init_desc')) ?></span>
            </header>

            <?php if ($initOperations === []): ?>
              <p class="oss-empty-group"><?= e($oss('workspace_init_empty')) ?></p>
            <?php else: ?>
              <form method="post" action="/apps/studio/tools/owner-structure-scan/initialize-workspace-artifacts" class="oss-init-form">
                <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="owner" value="<?= e($selectedOwnerKey) ?>">
                <input type="hidden" name="plan_fingerprint" value="<?= e($initFingerprint) ?>">
                <div class="oss-table-wrap">
                  <table class="oss-plan-table">
                    <thead>
                      <tr>
                        <th><?= e($oss('workspace_init_select')) ?></th>
                        <th><?= e($oss('operation')) ?></th>
                        <th><?= e($oss('target_path')) ?></th>
                        <th><?= e($oss('workspace_init_document_type')) ?></th>
                        <th><?= e($oss('status')) ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($initOperations as $initOperation): ?>
                        <?php if (!is_array($initOperation)) { continue; } ?>
                        <?php $opId = (string)($initOperation['operation_id'] ?? ''); ?>
                        <tr>
                          <td>
                            <input type="checkbox" name="operation_ids[]" value="<?= e($opId) ?>" aria-label="<?= e($oss('workspace_init_select') . ' ' . $opId) ?>">
                          </td>
                          <td><code><?= e((string)($initOperation['operation_type'] ?? '')) ?></code></td>
                          <td><code><?= e((string)($initOperation['target_path'] ?? '')) ?></code></td>
                          <td><?= e((string)($initOperation['document_type'] ?? '')) ?></td>
                          <td><?= e($oss('workspace_init_status')) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div class="oss-init-actions">
                  <button type="submit" class="btn btn-primary"><?= e($oss('workspace_init_action')) ?></button>
                </div>
              </form>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if ($isSupported): ?>
          <details class="oss-contract-findings">
            <summary><?= e($oss('developer_strip_title')) ?></summary>
            <p class="oss-planner-note"><?= e($oss('developer_strip_desc')) ?></p>
            <div class="oss-developer-strip-preview">
              <?php
                $validDocs = array_filter($ewDocuments, static function (array $d): bool {
                    return !empty($d['exists']) && $d['state'] === 'linked_valid';
                });
                $workspaceKey = (string)($engineeringWorkspaceArtifacts['workspace_key'] ?? '');
              ?>
              <?php if ($validDocs !== []): ?>
                <nav class="developer-strip" aria-label="<?= e($oss('developer_strip_label')) ?>" style="margin:0;border:1px solid var(--style-border-soft);border-radius:8px;">
                  <div class="developer-strip-inner" style="display:flex;gap:1rem;align-items:center;padding:0.55rem 0.75rem;">
                    <span class="developer-strip-label" style="color:var(--muted);font-size:0.75rem;"><?= e($oss('developer_strip_label')) ?></span>
                    <span class="developer-strip-workspace" style="font-weight:700;color:var(--text);font-size:0.85rem;"><?= e($workspaceKey) ?></span>
                    <span class="developer-strip-links" style="display:flex;gap:0.65rem;flex-wrap:wrap;">
                      <?php foreach ($validDocs as $vd): ?>
                        <?php
                          $docKey = (string)($vd['document_key'] ?? '');
                          $docLabel = (string)($vd['document_label'] ?? '');
                          $docEp = '/apps/studio/engineering-workspaces?workspace_key=' . rawurlencode($workspaceKey) . '&document=' . rawurlencode($docKey);
                        ?>
                        <a href="<?= e($docEp) ?>" style="color:var(--primary);font-size:0.8rem;"><?= e($docLabel) ?></a>
                      <?php endforeach; ?>
                    </span>
                  </div>
                </nav>
              <?php else: ?>
                <p class="oss-empty-group"><?= e($oss('developer_strip_empty')) ?></p>
              <?php endif; ?>
            </div>
          </details>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <header class="oss-section-head">
      <h3><?= e($oss('physical_truth_title')) ?></h3>
      <span><?= e($oss('read_only')) ?></span>
    </header>

    <div class="oss-controls">
      <label>
        <span><?= e($oss('search')) ?></span>
        <input type="search" id="oss-filter" placeholder="<?= e($oss('search')) ?>">
      </label>
      <label>
        <span><?= e($oss('sort')) ?></span>
        <select id="oss-sort">
          <option value="name"><?= e($oss('sort_name')) ?></option>
          <option value="size"><?= e($oss('sort_size')) ?></option>
        </select>
      </label>
    </div>

    <div class="oss-groups" id="oss-groups">
      <?php foreach ($groups as $group): ?>
        <?php
          if (!is_array($group)) {
              continue;
          }
          $entries = isset($group['entries']) && is_array($group['entries']) ? $group['entries'] : [];
          $entryCount = count($entries);
          $openAttr = $entryCount > 0 ? ' open' : '';
        ?>
        <details class="oss-group"<?= $openAttr ?> data-group="<?= e((string)($group['key'] ?? '')) ?>">
          <summary>
            <span class="oss-group-title"><?= e((string)($group['label'] ?? '')) ?></span>
            <span class="oss-group-meta">
              <?= e($oss('folder_count')) ?> <?= e((string)($group['folder_count'] ?? 0)) ?> ·
              <?= e($oss('file_count')) ?> <?= e((string)($group['file_count'] ?? 0)) ?> ·
              <?= e($formatBytes((int)($group['total_size'] ?? 0))) ?>
            </span>
          </summary>
          <div class="oss-group-body">
            <dl class="oss-group-facts">
              <div>
                <dt><?= e($oss('physical_location')) ?></dt>
                <dd><?php $renderLocations(isset($group['physical_locations']) && is_array($group['physical_locations']) ? $group['physical_locations'] : []); ?></dd>
              </div>
              <div>
                <dt><?= e($oss('relative_location')) ?></dt>
                <dd><?php $renderLocations(isset($group['relative_locations']) && is_array($group['relative_locations']) ? $group['relative_locations'] : []); ?></dd>
              </div>
              <div>
                <dt><?= e($oss('counts')) ?></dt>
                <dd><?= e($oss('folder_count')) ?>: <?= e((string)($group['folder_count'] ?? 0)) ?> · <?= e($oss('file_count')) ?>: <?= e((string)($group['file_count'] ?? 0)) ?></dd>
              </div>
              <div>
                <dt><?= e($oss('size')) ?></dt>
                <dd><?= e($formatBytes((int)($group['total_size'] ?? 0))) ?></dd>
              </div>
            </dl>

            <?php if ($entries === []): ?>
              <p class="oss-empty-group"><?= e($oss('empty_group')) ?></p>
            <?php else: ?>
              <table class="oss-entry-table">
                <thead>
                  <tr>
                    <th><?= e($oss('entries')) ?></th>
                    <th>Type</th>
                    <th><?= e($oss('size')) ?></th>
                    <th><?= e($oss('copy')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($entries as $entry): ?>
                    <?php
                      if (!is_array($entry)) {
                          continue;
                      }
                      $relativePath = (string)($entry['relative_path'] ?? '');
                      $name = (string)($entry['name'] ?? '');
                      $size = (int)($entry['size'] ?? 0);
                    ?>
                    <tr class="oss-entry" data-path="<?= e(strtolower($relativePath . ' ' . $name)) ?>" data-name="<?= e(strtolower($name)) ?>" data-size="<?= e((string)$size) ?>">
                      <td>
                        <strong><?= e($name) ?></strong>
                        <code><?= e($relativePath) ?></code>
                      </td>
                      <td><?= e((string)($entry['type'] ?? 'file')) ?></td>
                      <td><?= e($formatBytes($size)) ?></td>
                      <td><button type="button" class="oss-copy" data-copy-path="<?= e($relativePath) ?>" title="<?= e($oss('copy')) ?>">Copy</button></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="oss-empty"><?= e($oss('empty')) ?></p>
  <?php endif; ?>

  <p class="oss-readonly"><?= e($oss('read_only')) ?></p>
</section>

<style>
.oss-card { padding: 1rem; }
.oss-toolbar, .oss-scan-form, .oss-controls { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: end; }
.oss-toolbar { margin-bottom: 1rem; }
.oss-scan-form label, .oss-controls label { display: grid; gap: 0.25rem; font-size: 0.78rem; color: var(--muted); }
.oss-scan-form select, .oss-controls input, .oss-controls select { min-height: 2.25rem; border: 1px solid var(--style-border-soft); border-radius: 6px; padding: 0.35rem 0.5rem; background: var(--style-content-bg); color: var(--text); }
.oss-note, .oss-empty, .oss-readonly, .oss-empty-group, .oss-muted { color: var(--muted); }
.oss-owner-error { border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 8px; padding: 0.55rem 0.7rem; color: #92400e; background: rgba(245, 158, 11, 0.12); }
.oss-section-notice { border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 8px; padding: 0.75rem; color: #92400e; background: rgba(245, 158, 11, 0.12); margin: 1rem 0; }
.oss-section-notice strong { display: block; color: #78350f; }
.oss-section-notice p { margin: 0.35rem 0 0; }
.oss-section-notice code { color: #78350f; }
.oss-section-notice a { display: inline-block; margin-top: 0.55rem; color: var(--primary); font-weight: 700; text-decoration: none; }
.oss-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0.75rem; margin: 1rem 0; }
.oss-summary-card { border: 1px solid var(--style-border-soft); border-radius: 8px; padding: 0.65rem 0.75rem; background: var(--style-subtle-bg); }
.oss-summary-card dt, .oss-group-facts dt { color: var(--muted); font-size: 0.75rem; margin: 0; }
.oss-summary-card dd, .oss-group-facts dd { margin: 0.2rem 0 0; color: var(--text); font-weight: 600; word-break: break-word; }
.oss-section-head { display: flex; justify-content: space-between; gap: 1rem; align-items: center; margin: 1rem 0 0.65rem; }
.oss-section-head h3, .oss-section-head h4 { margin: 0; font-size: 1rem; color: var(--text); }
.oss-section-head span { color: var(--muted); font-size: 0.78rem; }
.oss-section-head a { color: var(--primary); font-size: 0.78rem; font-weight: 700; text-decoration: none; }
.oss-contract { border: 1px solid var(--style-border-soft); border-radius: 8px; padding: 0.85rem; background: var(--style-content-bg); margin: 1rem 0; }
.oss-planner { border: 1px solid var(--style-border-soft); border-radius: 8px; padding: 0.85rem; background: var(--style-content-bg); margin: 1rem 0; }
.oss-reference { border: 1px solid var(--style-border-soft); border-radius: 8px; padding: 0.85rem; background: var(--style-content-bg); margin: 1rem 0; }
.oss-workspace-init, .oss-workspace-init-result { border: 1px solid var(--style-border-soft); border-radius: 8px; padding: 0.75rem; background: var(--style-subtle-bg); margin: 0.85rem 0; }
.oss-init-actions { display: flex; justify-content: flex-end; margin-top: 0.75rem; }
.oss-init-form input[type="checkbox"] { inline-size: 1rem; block-size: 1rem; }
.oss-planner-note { color: var(--muted); margin: -0.2rem 0 0.8rem; }
.oss-contract-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.65rem; margin-bottom: 0.8rem; }
.oss-contract-findings > summary { cursor: pointer; font-weight: 700; color: var(--text); margin-bottom: 0.5rem; }
.oss-table-wrap { overflow: auto; border: 1px solid var(--style-border-soft); border-radius: 8px; }
.oss-finding-table { width: 100%; min-width: 980px; border-collapse: collapse; font-size: 0.82rem; }
.oss-plan-table { width: 100%; min-width: 1100px; border-collapse: collapse; font-size: 0.82rem; }
.oss-reference-table { width: 100%; min-width: 960px; border-collapse: collapse; font-size: 0.82rem; }
.oss-decomposition-table { width: 100%; min-width: 920px; border-collapse: collapse; font-size: 0.82rem; }
.oss-finding-table th, .oss-finding-table td, .oss-plan-table th, .oss-plan-table td, .oss-reference-table th, .oss-reference-table td, .oss-decomposition-table th, .oss-decomposition-table td { border-top: 1px solid var(--style-border-soft); padding: 0.55rem; text-align: left; vertical-align: top; }
.oss-finding-table thead th, .oss-plan-table thead th, .oss-reference-table thead th, .oss-decomposition-table thead th { border-top: 0; color: var(--muted); font-size: 0.75rem; background: var(--style-subtle-bg); }
.oss-finding-table code, .oss-plan-table code, .oss-reference-table code, .oss-decomposition-table code { color: var(--text); white-space: normal; word-break: break-all; }
.oss-after-paths { display: block; margin-top: 0.2rem; color: var(--muted); word-break: break-all; }
.oss-reference-list { display: grid; gap: 0.8rem; }
.oss-reference-item { border: 1px solid var(--style-border-soft); border-radius: 8px; padding: 0.75rem; background: var(--style-subtle-bg); }
.oss-reference-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.7rem; margin: 0 0 0.75rem; }
.oss-reference-facts dt { color: var(--muted); font-size: 0.75rem; margin: 0; }
.oss-reference-facts dd { margin: 0.18rem 0 0; color: var(--text); font-weight: 600; word-break: break-word; }
.oss-reference-facts code { word-break: break-all; white-space: normal; }
.oss-dependency-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 0.45rem; }
.oss-dependency-list li { border-top: 1px solid var(--style-border-soft); padding: 0.5rem 0; display: grid; gap: 0.2rem; }
.oss-dependency-list code { color: var(--text); }
.oss-dependency-list span { color: var(--text); font-weight: 700; }
.oss-dependency-list em { color: var(--muted); font-style: normal; word-break: break-all; }
.oss-status, .oss-severity { display: inline-block; border-radius: 999px; padding: 0.14rem 0.45rem; font-size: 0.72rem; font-weight: 700; white-space: nowrap; }
.oss-status-native-v2 { background: rgba(16, 185, 129, 0.14); color: #047857; }
.oss-status-migration-required { background: rgba(245, 158, 11, 0.16); color: #92400e; }
.oss-status-current-legacy { background: rgba(59, 130, 246, 0.14); color: #1d4ed8; }
.oss-status-cleanup-required { background: rgba(239, 68, 68, 0.14); color: #b91c1c; }
.oss-status-invalid-unknown { background: rgba(107, 114, 128, 0.16); color: #374151; }
.oss-status-present { background: rgba(16, 185, 129, 0.14); color: #047857; }
.oss-status-missing { background: rgba(239, 68, 68, 0.14); color: #b91c1c; }
.oss-severity-high { background: rgba(239, 68, 68, 0.14); color: #b91c1c; }
.oss-severity-medium { background: rgba(245, 158, 11, 0.16); color: #92400e; }
.oss-severity-low { background: rgba(59, 130, 246, 0.14); color: #1d4ed8; }
.oss-severity-info { background: rgba(107, 114, 128, 0.14); color: #374151; }
.oss-domain-list { margin: 0; padding-left: 1rem; display: grid; gap: 0.45rem; }
.oss-domain-list li { display: grid; gap: 0.2rem; }
.oss-domain-list em { color: var(--muted); font-style: normal; }
.oss-controls { margin: 1rem 0; }
.oss-controls input { min-width: min(28rem, 80vw); }
.oss-group { border: 1px solid var(--style-border-soft); border-radius: 8px; background: var(--style-content-bg); margin: 0.65rem 0; overflow: hidden; }
.oss-group > summary { cursor: pointer; padding: 0.75rem 0.9rem; display: flex; gap: 0.75rem; justify-content: space-between; align-items: center; }
.oss-group-title { font-weight: 700; color: var(--text); }
.oss-group-meta { color: var(--muted); font-size: 0.8rem; white-space: nowrap; }
.oss-group-body { border-top: 1px solid var(--style-border-soft); padding: 0.8rem; }
.oss-group-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; margin: 0 0 0.85rem; }
.oss-location-list { margin: 0.2rem 0 0; padding-left: 1rem; font-size: 0.78rem; font-weight: 500; color: var(--text); }
.oss-entry-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.oss-entry-table th, .oss-entry-table td { border-top: 1px solid var(--style-border-soft); padding: 0.5rem; text-align: left; vertical-align: top; }
.oss-entry-table code { display: block; margin-top: 0.15rem; color: var(--muted); word-break: break-all; white-space: normal; }
.oss-copy { border: 1px solid var(--style-border-soft); border-radius: 6px; background: var(--style-subtle-bg); color: var(--text); padding: 0.3rem 0.55rem; cursor: pointer; }
.oss-copy:focus, .oss-copy:hover { border-color: var(--primary); }
.oss-entry[hidden], .oss-group[hidden] { display: none; }
@media (max-width: 720px) {
  .oss-group > summary { align-items: flex-start; flex-direction: column; }
  .oss-group-meta { white-space: normal; }
}
</style>

<script>
(function () {
  var filter = document.getElementById('oss-filter');
  var sort = document.getElementById('oss-sort');
  var groups = Array.prototype.slice.call(document.querySelectorAll('.oss-group'));

  function applyFilter() {
    var query = filter ? filter.value.trim().toLowerCase() : '';
    groups.forEach(function (group) {
      var visible = 0;
      Array.prototype.slice.call(group.querySelectorAll('.oss-entry')).forEach(function (row) {
        var match = query === '' || String(row.getAttribute('data-path') || '').indexOf(query) !== -1;
        row.hidden = !match;
        if (match) {
          visible++;
        }
      });
      group.hidden = query !== '' && visible === 0;
    });
  }

  function applySort() {
    var mode = sort ? sort.value : 'name';
    groups.forEach(function (group) {
      var body = group.querySelector('tbody');
      if (!body) {
        return;
      }
      Array.prototype.slice.call(body.querySelectorAll('.oss-entry')).sort(function (a, b) {
        if (mode === 'size') {
          return Number(b.getAttribute('data-size') || 0) - Number(a.getAttribute('data-size') || 0);
        }
        return String(a.getAttribute('data-name') || '').localeCompare(String(b.getAttribute('data-name') || ''));
      }).forEach(function (row) {
        body.appendChild(row);
      });
    });
    applyFilter();
  }

  if (filter) {
    filter.addEventListener('input', applyFilter);
  }
  if (sort) {
    sort.addEventListener('change', applySort);
  }
  document.addEventListener('click', function (event) {
    var button = event.target && event.target.closest ? event.target.closest('.oss-copy') : null;
    if (!button) {
      return;
    }
    var path = String(button.getAttribute('data-copy-path') || '');
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(path);
    }
  });
})();
</script>
