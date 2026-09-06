<?php
declare(strict_types=1);

$helperModel = isset($helperToolModel) && is_array($helperToolModel) ? $helperToolModel : [];
$scanRequested = !empty($helperModel['scan_requested']);
$scanResult = isset($helperModel['scan_result']) && is_array($helperModel['scan_result']) ? $helperModel['scan_result'] : null;

$ht = static function (string $key): string {
    $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
    $dict = [
        'en' => [
            'title' => 'Repository Scanner',
            'subtitle' => 'Read-only repository discovery and inspection for Susankhya OS.',
            'scan_btn' => 'Scan Repository',
            'scan_desc' => 'Scans from APP_ROOT and renders folders/files with aggregate byte sizes.',
            'root_label' => 'Root',
            'duration_label' => 'Scan duration',
            'files_label' => 'Files',
            'dirs_label' => 'Folders',
            'errors_label' => 'Errors',
            'skipped_label' => 'Skipped',
            'size_label' => 'Size',
            'path_label' => 'Path',
            'children_label' => 'Children',
            'empty' => 'No scan result yet. Click the scan button to load the repository tree.',
            'inventory_title' => 'Repository Inventory Summary',
            'total_bytes_label' => 'Total bytes',
            'human_size_label' => 'Human size',
            'file_type_title' => 'File Type Summary',
            'type_label' => 'Type',
            'count_label' => 'Count',
            'review_buckets_help' => 'Review candidates are not errors. Runtime, dependency, snapshot, and unknown buckets are evidence-backed classifications from scanned files.',
            'category_label' => 'Category',
            'examples_label' => 'Examples',
            'no_examples' => 'No examples found.',
            'skipped_paths_title' => 'Skipped Paths',
            'reason_label' => 'Reason',
            'tree_filter_title' => 'Tree Filters',
            'filter_show_all' => 'Show all',
            'filter_hide_runtime' => 'Hide runtime/dependency artifacts',
            'filter_hide_snapshot' => 'Hide snapshot/recovery artifacts',
            'filter_review_only' => 'Review candidates only',
            'filter_unknown_only' => 'Unknown only',
            'inspector_title' => 'File Inspector',
            'inspector_empty' => 'Select a file in the tree to inspect metadata and a safe preview.',
            'inspector_owner' => 'Owner/path guess',
            'inspector_scope' => 'Scope',
            'inspector_categories' => 'Classification category',
            'inspector_reasons' => 'Reason',
            'inspector_preview' => 'Preview',
            'inspector_binary' => 'Binary or non-text file. Metadata only.',
            'inspector_truncated' => 'Preview truncated for safety.',
            'inspector_error' => 'Unable to inspect this file.',
            'read_only' => 'Read-only helper: no file writes, no mutations, no runtime changes.',
            'root_files_label' => 'Root files',
            'cat_runtime_dependency_artifacts' => 'Runtime',
            'cat_snapshot_recovery_artifacts' => 'Snapshot',
            'cat_review_candidates' => 'Review',
            'cat_unknown_needs_classification' => 'Unknown',
            'owner_lens_title' => 'Repository Owner Lens',
            'owner_lens_desc' => 'Repository owner hierarchy and engineering workspaces.',
            'owner_all' => 'All owners',
            'owner_ew_title' => 'Engineering Workspaces',
            'owner_ew_note' => 'Engineering Workspaces are documentation workspaces, not repository owners. They cannot be used as tree filters.',
            'owner_key_label' => 'Owner key',
            'owner_type_label' => 'Type',
            'owner_files_label' => 'Files',
            'owner_dirs_label' => 'Folders',
            'owner_size_label' => 'Size',
            'owner_workspace_label' => 'Workspace',
            'owner_workspace_present' => 'Present',
            'owner_workspace_absent' => '—',
            'owner_workspace_linked' => 'Linked to:',
            'owner_workspace_not_linked' => 'Not linked',
            'owner_workspace_open' => 'Open Workspace',
            'ew_linked_summary_title' => 'Workspace Linking Summary',
            'ew_total_workspaces' => 'Total workspaces',
            'ew_linked_owners' => 'Linked owners',
            'ew_unlinked_owners' => 'Unlinked owners',
            'ew_linked_owners_label' => 'Linked owners',
            'owner_filter_title' => 'Owner Filter',
            'owner_no_selection' => '— Show all —',
            'entity_summary_title' => 'Repository Entity Summary',
            'entity_summary_desc' => 'Aggregate entity counts across all owners.',
            'entity_summary_total' => 'Total entities',
            'entity_summary_owner_label' => 'Owner',
            'entity_summary_count_label' => 'Count',
            'entity_summary_show_all' => 'Show all owners',
            'entity_summary_collapse' => 'Collapse',
            'entity_summary_empty' => 'No entities discovered in this scan.',
            'entity_explorer_title' => 'Entity Explorer',
            'entity_explorer_desc' => 'Read-only entity discovery for the selected owner.',
            'entity_no_selection' => 'Select an owner above to explore its routes, controllers, services, and views.',
            'entity_filter_all' => 'All',
            'entity_type_route' => 'Routes',
            'entity_type_controller' => 'Controllers',
            'entity_type_service' => 'Services',
            'entity_type_view' => 'Views',
            'entity_state_confirmed' => 'Confirmed',
            'entity_state_uncertain' => 'Uncertain',
            'entity_evidence_label' => 'Evidence',
            'entity_path_label' => 'Source path',
            'search_title' => 'Repository Search',
            'search_placeholder' => 'Search files, owners, entities, routes, workspaces…',
            'search_hint' => 'Type to search repository data.',
            'search_empty_query' => 'Type to search repository data.',
            'search_no_results' => 'No repository matches found.',
            'search_result_type_file' => 'File',
            'search_result_type_owner' => 'Owner',
            'search_result_type_entity' => 'Entity',
            'search_result_type_route' => 'Route',
            'search_result_type_workspace' => 'Workspace',
            'search_result_count' => 'results',
            'search_action_inspect' => 'Inspect',
            'search_action_select_owner' => 'Filter',
            'search_owner_filter_label' => 'Search within selected owner only',
            'search_owner_filter_hint' => 'Applies when an owner is selected in Owner Lens.',
            'scan_summary_title' => 'Scan Summary',
            'review_buckets_title' => 'Review Buckets',
            'repository_statistics_title' => 'Repository Statistics',
            'raw_tree_title' => 'Repository Tree (Advanced)',
            'details_expand' => 'Expand',
            'details_collapse' => 'Collapse',
        ],
        'ja' => [
            'title' => 'Repository Scanner',
            'subtitle' => 'Susankhya OS の読み取り専用リポジトリ探索・点検ツール。',
            'scan_btn' => 'リポジトリをスキャン',
            'scan_desc' => 'APP_ROOT から走査し、フォルダ・ファイルを合計サイズ付きで表示します。',
            'root_label' => 'ルート',
            'duration_label' => '走査時間',
            'files_label' => 'ファイル',
            'dirs_label' => 'フォルダ',
            'errors_label' => 'エラー',
            'skipped_label' => 'スキップ',
            'size_label' => 'サイズ',
            'path_label' => 'パス',
            'children_label' => '子要素',
            'empty' => 'まだ走査結果がありません。走査ボタンを押してリポジトリツリーを読み込んでください。',
            'inventory_title' => 'リポジトリインベントリ概要',
            'total_bytes_label' => '総バイト数',
            'human_size_label' => '読みやすいサイズ',
            'file_type_title' => 'ファイル種別概要',
            'type_label' => '種別',
            'count_label' => '件数',
            'review_buckets_help' => '確認候補はエラーではありません。ランタイム、依存、スナップショット、不明の各分類は走査されたファイル証拠に基づきます。',
            'category_label' => 'カテゴリ',
            'examples_label' => '例',
            'no_examples' => '例は見つかりませんでした。',
            'skipped_paths_title' => 'スキップされたパス',
            'reason_label' => '理由',
            'tree_filter_title' => 'ツリーフィルター',
            'filter_show_all' => 'すべて表示',
            'filter_hide_runtime' => 'ランタイム/依存アーティファクトを非表示',
            'filter_hide_snapshot' => 'スナップショット/復旧アーティファクトを非表示',
            'filter_review_only' => '確認候補のみ',
            'filter_unknown_only' => '不明のみ',
            'inspector_title' => 'ファイルインスペクター',
            'inspector_empty' => 'ツリー内のファイルを選択すると、メタデータと安全なプレビューを表示します。',
            'inspector_owner' => 'オーナー/パス推定',
            'inspector_scope' => 'スコープ',
            'inspector_categories' => '分類カテゴリ',
            'inspector_reasons' => '理由',
            'inspector_preview' => 'プレビュー',
            'inspector_binary' => 'バイナリまたは非テキストファイルです。メタデータのみ表示します。',
            'inspector_truncated' => '安全のためプレビューを切り詰めました。',
            'inspector_error' => 'このファイルを検査できません。',
            'read_only' => '読み取り専用ヘルパー: ファイル書き込み・変更・ランタイム変更は行いません。',
            'root_files_label' => 'ルートファイル',
            'cat_runtime_dependency_artifacts' => 'ランタイム',
            'cat_snapshot_recovery_artifacts' => 'スナップショット',
            'cat_review_candidates' => '確認',
            'cat_unknown_needs_classification' => '不明',
            'owner_lens_title' => 'リポジトリオーナーレンズ',
            'owner_lens_desc' => 'リポジトリオーナーの階層とエンジニアリングワークスペース。',
            'owner_all' => 'すべてのオーナー',
            'owner_ew_title' => 'エンジニアリングワークスペース',
            'owner_ew_note' => 'エンジニアリングワークスペースはドキュメントワークスペースであり、リポジトリオーナーではありません。ツリーフィルターとしては使用できません。',
            'owner_key_label' => 'オーナーキー',
            'owner_type_label' => '種別',
            'owner_files_label' => 'ファイル',
            'owner_dirs_label' => 'フォルダ',
            'owner_size_label' => 'サイズ',
            'owner_workspace_label' => 'ワークスペース',
            'owner_workspace_present' => 'あり',
            'owner_workspace_absent' => '—',
            'owner_workspace_linked' => 'リンク先:',
            'owner_workspace_not_linked' => '未リンク',
            'owner_workspace_open' => 'ワークスペースを開く',
            'ew_linked_summary_title' => 'ワークスペースリンクサマリー',
            'ew_total_workspaces' => '総ワークスペース数',
            'ew_linked_owners' => 'リンク済みオーナー',
            'ew_unlinked_owners' => '未リンクオーナー',
            'ew_linked_owners_label' => 'リンク済みオーナー',
            'owner_filter_title' => 'オーナーフィルター',
            'owner_no_selection' => '— すべて表示 —',
            'entity_summary_title' => 'リポジトリエンティティサマリー',
            'entity_summary_desc' => '全オーナーにわたる集計エンティティ数。',
            'entity_summary_total' => '総エンティティ数',
            'entity_summary_owner_label' => 'オーナー',
            'entity_summary_count_label' => '件数',
            'entity_summary_show_all' => '全オーナーを表示',
            'entity_summary_collapse' => '折りたたむ',
            'entity_summary_empty' => 'このスキャンでエンティティは見つかりませんでした。',
            'entity_explorer_title' => 'エンティティエクスプローラー',
            'entity_explorer_desc' => '選択したオーナーの読み取り専用エンティティ探索です。',
            'entity_no_selection' => '上のフィルターからオーナーを選択すると、ルート、コントローラー、サービス、ビューを探索できます。',
            'entity_filter_all' => 'すべて',
            'entity_type_route' => 'ルート',
            'entity_type_controller' => 'コントローラー',
            'entity_type_service' => 'サービス',
            'entity_type_view' => 'ビュー',
            'entity_state_confirmed' => '確認済み',
            'entity_state_uncertain' => '不確実',
            'entity_evidence_label' => '証拠',
            'entity_path_label' => 'ソースパス',
            'search_title' => 'リポジトリ検索',
            'search_placeholder' => 'ファイル、オーナー、エンティティ、ルート、ワークスペースを検索…',
            'search_hint' => 'リポジトリデータを検索するには文字を入力してください。',
            'search_empty_query' => 'リポジトリデータを検索するには文字を入力してください。',
            'search_no_results' => 'リポジトリで一致するものが見つかりませんでした。',
            'search_result_type_file' => 'ファイル',
            'search_result_type_owner' => 'オーナー',
            'search_result_type_entity' => 'エンティティ',
            'search_result_type_route' => 'ルート',
            'search_result_type_workspace' => 'ワークスペース',
            'search_result_count' => '件',
            'search_action_inspect' => '検査',
            'search_action_select_owner' => 'フィルター',
            'search_owner_filter_label' => '選択したオーナーのみ検索',
            'search_owner_filter_hint' => 'オーナーレンズでオーナーが選択されている場合に適用されます。',
            'scan_summary_title' => 'スキャン概要',
            'review_buckets_title' => 'レビューバケット',
            'repository_statistics_title' => 'リポジトリ統計',
            'raw_tree_title' => 'リポジトリツリー (詳細)',
            'details_expand' => '展開',
            'details_collapse' => '折りたたむ',
        ],
        'ne' => [
            'title' => 'Repository Scanner',
            'subtitle' => 'Susankhya OS को लागि पढ्न-मात्र रिपोजिटरी खोज र निरीक्षण।',
            'scan_btn' => 'रिपोजिटरी स्क्यान गर्नुहोस्',
            'scan_desc' => 'APP_ROOT बाट स्क्यान गरी फोल्डर/फाइल संरचना आकारसहित देखाउँछ।',
            'root_label' => 'रुट',
            'duration_label' => 'स्क्यान समय',
            'files_label' => 'फाइल',
            'dirs_label' => 'फोल्डर',
            'errors_label' => 'त्रुटि',
            'skipped_label' => 'छोडिएका',
            'size_label' => 'आकार',
            'path_label' => 'पथ',
            'children_label' => 'बाल तत्व',
            'empty' => 'अहिलेसम्म स्क्यान परिणाम छैन। स्क्यान बटन थिचेर रिपोजिटरी ट्री लोड गर्नुहोस्।',
            'inventory_title' => 'रिपोजिटरी सूची सारांश',
            'total_bytes_label' => 'कुल बाइट',
            'human_size_label' => 'मानवीय आकार',
            'file_type_title' => 'फाइल प्रकार सारांश',
            'type_label' => 'प्रकार',
            'count_label' => 'गन्ती',
            'review_buckets_help' => 'समीक्षा उम्मेदवार त्रुटि होइनन्। रनटाइम, निर्भरता, स्न्यापसट, र अज्ञात समूहहरू स्क्यान गरिएका फाइल प्रमाणमा आधारित वर्गीकरण हुन्।',
            'category_label' => 'वर्ग',
            'examples_label' => 'उदाहरण',
            'no_examples' => 'उदाहरण भेटिएन।',
            'skipped_paths_title' => 'छोडिएका पथ',
            'reason_label' => 'कारण',
            'tree_filter_title' => 'ट्री फिल्टर',
            'filter_show_all' => 'सबै देखाउनुहोस्',
            'filter_hide_runtime' => 'रनटाइम/निर्भरता सामग्री लुकाउनुहोस्',
            'filter_hide_snapshot' => 'स्न्यापसट/रिकभरी सामग्री लुकाउनुहोस्',
            'filter_review_only' => 'समीक्षा उम्मेदवार मात्र',
            'filter_unknown_only' => 'अज्ञात मात्र',
            'inspector_title' => 'फाइल निरीक्षक',
            'inspector_empty' => 'मेटाडाटा र सुरक्षित पूर्वावलोकन हेर्न ट्रीमा फाइल चयन गर्नुहोस्।',
            'inspector_owner' => 'मालिक/पथ अनुमान',
            'inspector_scope' => 'स्कोप',
            'inspector_categories' => 'वर्गीकरण वर्ग',
            'inspector_reasons' => 'कारण',
            'inspector_preview' => 'पूर्वावलोकन',
            'inspector_binary' => 'बाइनरी वा गैर-पाठ फाइल। मेटाडाटा मात्र।',
            'inspector_truncated' => 'सुरक्षाका लागि पूर्वावलोकन काटिएको छ।',
            'inspector_error' => 'यो फाइल निरीक्षण गर्न सकिएन।',
            'read_only' => 'पढ्न-मात्र सहायक: कुनै फाइल लेखाइ, म्युटेसन, वा रनटाइम परिवर्तन हुँदैन।',
            'root_files_label' => 'रुट फाइलहरू',
            'cat_runtime_dependency_artifacts' => 'रनटाइम',
            'cat_snapshot_recovery_artifacts' => 'स्न्यापसट',
            'cat_review_candidates' => 'समीक्षा',
            'cat_unknown_needs_classification' => 'अज्ञात',
            'owner_lens_title' => 'रिपोजिटरी मालिक लेन्स',
            'owner_lens_desc' => 'रिपोजिटरी मालिक पदानुक्रम र ईन्जिनियरिङ कार्यक्षेत्र।',
            'owner_all' => 'सबै मालिक',
            'owner_ew_title' => 'ईन्जिनियरिङ कार्यक्षेत्र',
            'owner_ew_note' => 'ईन्जिनियरिङ कार्यक्षेत्र कागजात कार्यक्षेत्र हुन्, रिपोजिटरी मालिक होइन। तिनीहरू रूख फिल्टरको रूपमा प्रयोग गर्न सकिँदैन।',
            'owner_key_label' => 'मालिक कुञ्जी',
            'owner_type_label' => 'प्रकार',
            'owner_files_label' => 'फाइल',
            'owner_dirs_label' => 'फोल्डर',
            'owner_size_label' => 'आकार',
            'owner_workspace_label' => 'कार्यक्षेत्र',
            'owner_workspace_present' => 'उपलब्ध',
            'owner_workspace_absent' => '—',
            'owner_workspace_linked' => 'लिङ्क गरिएको:',
            'owner_workspace_not_linked' => 'लिङ्क नगरिएको',
            'owner_workspace_open' => 'कार्यक्षेत्र खोल्नुहोस्',
            'ew_linked_summary_title' => 'कार्यक्षेत्र लिङ्क सारांश',
            'ew_total_workspaces' => 'कुल कार्यक्षेत्रहरू',
            'ew_linked_owners' => 'लिङ्क गरिएका मालिकहरू',
            'ew_unlinked_owners' => 'लिङ्क नगरिएका मालिकहरू',
            'ew_linked_owners_label' => 'लिङ्क गरिएका मालिकहरू',
            'owner_filter_title' => 'मालिक फिल्टर',
            'owner_no_selection' => '— सबै देखाउनुहोस् —',
            'entity_summary_title' => 'रिपोजिटरी इकाई सारांश',
            'entity_summary_desc' => 'सबै मालिकहरूमा कुल इकाई गणनाहरू।',
            'entity_summary_total' => 'कुल इकाईहरू',
            'entity_summary_owner_label' => 'मालिक',
            'entity_summary_count_label' => 'गणना',
            'entity_summary_show_all' => 'सबै मालिकहरू देखाउनुहोस्',
            'entity_summary_collapse' => 'संक्षिप्त गर्नुहोस्',
            'entity_summary_empty' => 'यस स्क्यानमा कुनै इकाई भेटिएन।',
            'entity_explorer_title' => 'इकाई एक्सप्लोरर',
            'entity_explorer_desc' => 'चयन गरिएको मालिकको पढ्न-मात्र इकाई खोज।',
            'entity_no_selection' => 'माथिको फिल्टरबाट मालिक चयन गरेर यसको रूट, नियन्त्रक, सेवा, र दृश्यहरू अन्वेषण गर्नुहोस्।',
            'entity_filter_all' => 'सबै',
            'entity_type_route' => 'रूटहरू',
            'entity_type_controller' => 'नियन्त्रकहरू',
            'entity_type_service' => 'सेवाहरू',
            'entity_type_view' => 'दृश्यहरू',
            'entity_state_confirmed' => 'पुष्टि भयो',
            'entity_state_uncertain' => 'अनिश्चित',
            'entity_evidence_label' => 'प्रमाण',
            'entity_path_label' => 'स्रोत पथ',
            'search_title' => 'रिपोजिटरी खोज',
            'search_placeholder' => 'फाइल, मालिक, इकाई, रूट, कार्यक्षेत्र खोज्नुहोस्…',
            'search_hint' => 'रिपोजिटरी डेटा खोज्न टाइप गर्नुहोस्।',
            'search_empty_query' => 'रिपोजिटरी डेटा खोज्न टाइप गर्नुहोस्।',
            'search_no_results' => 'रिपोजिटरीमा कुनै मिल्दो परिणाम भेटिएन।',
            'search_result_type_file' => 'फाइल',
            'search_result_type_owner' => 'मालिक',
            'search_result_type_entity' => 'इकाई',
            'search_result_type_route' => 'रूट',
            'search_result_type_workspace' => 'कार्यक्षेत्र',
            'search_result_count' => 'परिणाम',
            'search_action_inspect' => 'निरीक्षण',
            'search_action_select_owner' => 'फिल्टर',
            'search_owner_filter_label' => 'चयन गरिएको मालिकभित्र मात्र खोज्नुहोस्',
            'search_owner_filter_hint' => 'मालिक लेन्समा मालिक चयन गरिएको बेला लागू हुन्छ।',
            'scan_summary_title' => 'स्क्यान सारांश',
            'review_buckets_title' => 'समीक्षा बाल्टीहरू',
            'repository_statistics_title' => 'रिपोजिटरी तथ्याङ्क',
            'raw_tree_title' => 'रिपोजिटरी रूख (उन्नत)',
            'details_expand' => 'विस्तार',
            'details_collapse' => 'संक्षिप्त',
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

$catBadge = static function (string $categoryAttr) use ($ht): string {
    if ($categoryAttr === '') { return ''; }
    $parts = explode(' ', $categoryAttr);
    $badges = [];
    foreach ($parts as $cat) {
        if ($cat === '') { continue; }
        $key = 'cat_' . str_replace('-', '_', $cat);
        $label = $ht($key);
        if ($label !== $key) {
            $badges[] = '<span class="ht-file-badge ht-cat-' . e($cat) . '">' . e($label) . '</span>';
        }
    }
    return $badges !== [] ? ' ' . implode('', $badges) : '';
};

$sortChildren = static function (array $children): array {
    $dirs = [];
    $files = [];
    foreach ($children as $child) {
        if (!is_array($child)) { continue; }
        if (($child['type'] ?? 'file') === 'dir') {
            $dirs[] = $child;
        } else {
            $files[] = $child;
        }
    }
    usort($dirs, static fn($a, $b) => strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
    usort($files, static fn($a, $b) => strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
    return array_merge($dirs, $files);
};

$defaultHideCat = 'runtime_dependency_artifacts';

$hasDefaultVisible = static function (array $node) use (&$hasDefaultVisible, $defaultHideCat): bool {
    $dirHasHideCat = in_array($defaultHideCat, $node['artifact_categories'] ?? [], true);
    foreach ($node['children'] ?? [] as $child) {
        if (!is_array($child)) { continue; }
        if (($child['type'] ?? 'file') === 'file') {
            $cats = $child['artifact_categories'] ?? [];
            if ($dirHasHideCat && in_array('review_candidates', $cats, true) && !in_array($defaultHideCat, $cats, true)) {
                continue;
            }
            if (!in_array($defaultHideCat, $cats, true)) { return true; }
        } elseif (($child['type'] ?? '') === 'dir') {
            if ($hasDefaultVisible($child)) { return true; }
        }
    }
    return false;
};

$renderTree = static function (array $node, int $depth = 0) use (&$renderTree, $formatBytes, $ht, $catBadge, $sortChildren, $defaultHideCat, $hasDefaultVisible): void {
    $type = (string)($node['type'] ?? 'file');
    $name = (string)($node['name'] ?? '');
    $relativePath = (string)($node['relative_path'] ?? '');
    $size = (int)($node['size'] ?? 0);
    $children = isset($node['children']) && is_array($node['children']) ? $node['children'] : [];
    $artifactCategories = isset($node['artifact_categories']) && is_array($node['artifact_categories']) ? $node['artifact_categories'] : [];
    $artifactReasons = isset($node['artifact_reasons']) && is_array($node['artifact_reasons']) ? $node['artifact_reasons'] : [];
    $categoryAttr = implode(' ', array_map('strval', $artifactCategories));
    $reasonAttr = implode(' ', array_map('strval', $artifactReasons));

    if ($type === 'dir') {
        $childCount = count($children);
        $openAttr = $depth <= 1 ? ' open' : '';
        $sortedChildren = $children !== [] ? $sortChildren($children) : $children;
        $defaultHidden = $depth > 1 && !$hasDefaultVisible($node);
        $hiddenAttr = $defaultHidden ? ' hidden' : '';
        $ownerKey = (string)($node['owner_key'] ?? '');
        echo '<li class="ht-tree-node ht-tree-dir" data-node-type="dir" data-categories="' . e($categoryAttr) . '" data-owner="' . e($ownerKey) . '"' . $hiddenAttr . '>';
        echo '<details' . $openAttr . '>';
        echo '<summary>';
        echo '<span class="ht-node-name">' . e($name) . '</span>';
        echo '<span class="ht-node-meta">';
        echo e($ht('children_label')) . ': ' . e((string)$childCount) . ' | ';
        echo e($ht('size_label')) . ': ' . e($formatBytes($size));
        echo '</span>';
        echo '</summary>';
        echo '<div class="ht-node-path">' . e($ht('path_label')) . ': ' . e($relativePath) . '</div>';
        if ($sortedChildren !== []) {
            echo '<ul class="ht-tree-list">';
            if ($depth === 0) {
                $dirChildren = array_filter($sortedChildren, static fn($c) => (($c['type'] ?? 'file') === 'dir'));
                $fileChildren = array_filter($sortedChildren, static fn($c) => (($c['type'] ?? 'file') !== 'dir'));
                foreach ($dirChildren as $child) {
                    $renderTree($child, $depth + 1);
                }
                if ($fileChildren !== []) {
                    echo '<li class="ht-tree-node ht-root-files-group">';
                    echo '<details>';
                    echo '<summary>';
                    echo '<span class="ht-node-name">' . e($ht('root_files_label')) . '</span>';
                    echo '<span class="ht-node-meta">' . e((string)count($fileChildren)) . ' ' . e($ht('files_label')) . '</span>';
                    echo '</summary>';
                    echo '<ul class="ht-tree-list">';
                    foreach ($fileChildren as $child) {
                        $renderTree($child, 99);
                    }
                    echo '</ul></details></li>';
                }
            } else {
                foreach ($sortedChildren as $child) {
                    $renderTree($child, $depth + 1);
                }
            }
            echo '</ul>';
        }
        echo '</details>';
        echo '</li>';
        return;
    }

    $defaultFileHidden = in_array($defaultHideCat, $artifactCategories, true);
    $fileHiddenAttr = $defaultFileHidden ? ' hidden' : '';
    echo '<li class="ht-tree-node ht-tree-file" data-node-type="file" data-categories="' . e($categoryAttr) . '" data-reasons="' . e($reasonAttr) . '" data-owner="' . e((string)($node['owner_key'] ?? '')) . '"' . $fileHiddenAttr . '>';
    echo '<button type="button" class="ht-file-select" data-inspector-path="' . e($relativePath) . '">';
    echo '<span class="ht-node-name">' . e($name) . '</span>';
    echo '</button>';
    echo '<span class="ht-node-meta">' . e($formatBytes($size)) . $catBadge($categoryAttr) . '</span>';
    echo '</li>';
};
?>

<section class="st-card st-card-full helper-tool-card">
  <header class="st-card-head">
    <h2><?= e($ht('title')) ?></h2>
    <p class="st-card-subtitle"><?= e($ht('subtitle')) ?></p>
  </header>

  <div class="helper-toolbar">
    <form method="get" action="/apps/studio/tools/helper-tool">
      <input type="hidden" name="scan" value="1">
      <button type="submit" class="btn btn-primary"><?= e($ht('scan_btn')) ?></button>
    </form>
    <p class="helper-note"><?= e($ht('scan_desc')) ?></p>
  </div>

    <?php if ($scanRequested && is_array($scanResult)): ?>
    <?php
      $counters = isset($scanResult['counters']) && is_array($scanResult['counters']) ? $scanResult['counters'] : [];
      $tree = isset($scanResult['tree']) && is_array($scanResult['tree']) ? $scanResult['tree'] : [];
      $inventorySummary = isset($scanResult['inventory_summary']) && is_array($scanResult['inventory_summary']) ? $scanResult['inventory_summary'] : [];
      $fileTypeSummary = isset($scanResult['file_type_summary']) && is_array($scanResult['file_type_summary']) ? $scanResult['file_type_summary'] : [];
      $fileTypes = isset($fileTypeSummary['types']) && is_array($fileTypeSummary['types']) ? $fileTypeSummary['types'] : [];
      $suspiciousSummary = isset($scanResult['suspicious_summary']) && is_array($scanResult['suspicious_summary']) ? $scanResult['suspicious_summary'] : [];
      $suspiciousCategories = isset($suspiciousSummary['categories']) && is_array($suspiciousSummary['categories']) ? $suspiciousSummary['categories'] : [];
      $skippedPaths = isset($scanResult['skipped_paths']) && is_array($scanResult['skipped_paths']) ? $scanResult['skipped_paths'] : [];
      $ownerOwners = isset($scanResult['owner_owners']) && is_array($scanResult['owner_owners']) ? $scanResult['owner_owners'] : [];
      $ownerHierarchy = isset($scanResult['owner_hierarchy']) && is_array($scanResult['owner_hierarchy']) ? $scanResult['owner_hierarchy'] : [];
      $ownerRepoOwners = isset($scanResult['owner_repo_owners']) && is_array($scanResult['owner_repo_owners']) ? $scanResult['owner_repo_owners'] : [];
      $ownerEwOwners = isset($scanResult['owner_ew_owners']) && is_array($scanResult['owner_ew_owners']) ? $scanResult['owner_ew_owners'] : [];
      $ownerStats = isset($scanResult['owner_stats']) && is_array($scanResult['owner_stats']) ? $scanResult['owner_stats'] : [];
      $ownerEntities = isset($scanResult['owner_entities']) && is_array($scanResult['owner_entities']) ? $scanResult['owner_entities'] : [];
      $entitySummary = isset($scanResult['entity_summary']) && is_array($scanResult['entity_summary']) ? $scanResult['entity_summary'] : null;
      $entityTypeRegistry = \Apps\Studio\Tools\HelperTool\Services\RepoTreeScannerService::getEntityTypeRegistry();

      $ownerWorkspaceStatus = [];
      foreach ($ownerOwners as $oo) {
          $ok = (string)($oo['owner_key'] ?? '');
          if ($ok === '') { continue; }
          $wskey = \Platform\Security\EngineeringWorkspaceResolver::ownerKeyToWorkspaceKey($ok);
          $links = \Platform\Security\EngineeringWorkspaceResolver::buildWorkspaceLinks($wskey, null);
          $ownerWorkspaceStatus[$ok] = $links !== null && ($links['available'] ?? false);
      }

      $ownerToEwMap = \Apps\Studio\Tools\HelperTool\Services\RepoTreeScannerService::resolveOwnerToWorkspaceMap($ownerOwners);
      $ewToOwnerMap = [];
      foreach ($ownerToEwMap as $repoKey => $ewKey) {
          $ewToOwnerMap[$ewKey][] = $repoKey;
      }
      $totalLinkedOwners = count($ownerToEwMap);
      $totalUnlinkedOwners = count($ownerRepoOwners) - $totalLinkedOwners;
      $repoOwnerLabelMap = [];
      foreach ($ownerRepoOwners as $ro) {
          $rok = (string)($ro['owner_key'] ?? '');
          if ($rok !== '') { $repoOwnerLabelMap[$rok] = (string)($ro['display_label'] ?? $rok); }
      }
      $ewLabelMap = [];
      foreach ($ownerEwOwners as $ew) {
          $ewk = (string)($ew['owner_key'] ?? '');
          if ($ewk !== '') { $ewLabelMap[$ewk] = (string)($ew['display_label'] ?? $ewk); }
      }
      $ownerWorkspaceLinks = [];
      foreach ($ownerRepoOwners as $ro) {
          $ok = (string)($ro['owner_key'] ?? '');
          if ($ok === '') { continue; }
          $ewKey = $ownerToEwMap[$ok] ?? null;
          if ($ewKey === null) {
              $ownerWorkspaceLinks[$ok] = ['linked' => false, 'ew_key' => null, 'ew_label' => null, 'url' => null];
              continue;
          }
          $ewLabel = $ewKey;
          $wsKey = preg_replace('#^EW/#', '', $ewKey);
          $url = '/apps/studio/engineering-workspace/viewer?workspace_key=' . urlencode($wsKey);
          $ownerWorkspaceLinks[$ok] = ['linked' => true, 'ew_key' => $ewKey, 'ew_label' => $ewLabel, 'url' => $url];
      }
    ?>
    <!-- ht-scan-summary -->
    <section class="helper-panel" data-ht-scan-summary>
      <h3><?= e($ht('scan_summary_title')) ?></h3>
      <div class="helper-summary-grid">
        <div class="helper-summary-card"><dt><?= e($ht('root_label')) ?></dt><dd><?= e((string)($scanResult['root_path'] ?? '')) ?></dd></div>
        <div class="helper-summary-card"><dt><?= e($ht('duration_label')) ?></dt><dd><?= e((string)($scanResult['duration_ms'] ?? 0)) ?> ms</dd></div>
        <div class="helper-summary-card"><dt><?= e($ht('files_label')) ?></dt><dd><?= e((string)($counters['files'] ?? 0)) ?></dd></div>
        <div class="helper-summary-card"><dt><?= e($ht('dirs_label')) ?></dt><dd><?= e((string)($counters['dirs'] ?? 0)) ?></dd></div>
        <div class="helper-summary-card"><dt><?= e($ht('errors_label')) ?></dt><dd><?= e((string)($counters['errors'] ?? 0)) ?></dd></div>
        <div class="helper-summary-card"><dt><?= e($ht('skipped_label')) ?></dt><dd><?= e((string)($counters['skipped'] ?? 0)) ?></dd></div>
      </div>
    </section>
    <!-- /ht-scan-summary -->

    <?php
      // Build search index from already-discovered scan data (no extra filesystem work)
      $searchIndex = \Apps\Studio\Tools\HelperTool\Services\RepoTreeScannerService::buildSearchIndex($scanResult);
    ?>
    <!-- ht-search-section -->
    <section class="helper-panel ht-search-section" data-ht-search-section
      data-label-empty-query="<?= e($ht('search_empty_query')) ?>"
      data-label-no-results="<?= e($ht('search_no_results')) ?>"
      data-label-count="<?= e($ht('search_result_count')) ?>"
      data-label-type-file="<?= e($ht('search_result_type_file')) ?>"
      data-label-type-owner="<?= e($ht('search_result_type_owner')) ?>"
      data-label-type-entity="<?= e($ht('search_result_type_entity')) ?>"
      data-label-type-route="<?= e($ht('search_result_type_route')) ?>"
      data-label-type-workspace="<?= e($ht('search_result_type_workspace')) ?>"
      data-label-action-inspect="<?= e($ht('search_action_inspect')) ?>"
      data-label-action-owner="<?= e($ht('search_action_select_owner')) ?>"
    >
      <h3><?= e($ht('search_title')) ?></h3>
      <div class="ht-search-bar">
        <input
          type="search"
          class="ht-search-input"
          data-ht-search-input
          placeholder="<?= e($ht('search_placeholder')) ?>"
          aria-label="<?= e($ht('search_title')) ?>"
          autocomplete="off"
          spellcheck="false"
        >
      </div>
      <div class="ht-search-owner-filter-row" data-ht-search-owner-filter-row>
        <label class="ht-search-owner-filter-label">
          <input type="checkbox" class="ht-search-owner-checkbox" data-ht-search-owner-filter>
          <?= e($ht('search_owner_filter_label')) ?>
        </label>
        <span class="ht-search-owner-filter-hint helper-muted"><?= e($ht('search_owner_filter_hint')) ?></span>
      </div>
      <p class="helper-panel-note ht-search-hint" data-ht-search-hint><?= e($ht('search_hint')) ?></p>
      <div class="ht-search-results" data-ht-search-results hidden>
        <div class="ht-search-chips" data-ht-search-chips></div>
        <p class="helper-panel-note ht-search-count" data-ht-search-count></p>
        <div class="ht-search-result-list" data-ht-search-result-list></div>
      </div>
      <script type="application/json" id="ht-search-index-data"><?= json_encode(
        $searchIndex,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
      ) ?></script>
    </section>
    <!-- /ht-search-section -->


    <!-- ht-review-buckets -->
    <section class="helper-panel" data-ht-review-buckets>
      <h3><?= e($ht('review_buckets_title')) ?></h3>
      <p class="helper-panel-note"><?= e($ht('review_buckets_help')) ?></p>
      <div class="helper-review-grid">
        <?php foreach ($suspiciousCategories as $category): ?>
          <?php
            if (!is_array($category)) { continue; }
            $examples = isset($category['examples']) && is_array($category['examples']) ? $category['examples'] : [];
            $catKey = str_replace('_', '-', $category['key'] ?? '');
          ?>
          <div class="helper-review-card" data-ht-review-cat="<?= e($catKey) ?>">
            <div class="helper-review-card-head">
              <span class="helper-review-card-label"><?= e((string)($category['label'] ?? '')) ?></span>
              <span class="helper-review-card-count"><?= e((string)($category['count'] ?? 0)) ?></span>
            </div>
            <div class="helper-review-card-body">
              <span class="helper-review-card-bytes"><?= e($formatBytes((int)($category['total_bytes'] ?? 0))) ?></span>
              <?php if ($examples !== []): ?>
                <ul class="helper-example-list">
                  <?php foreach ($examples as $example): ?>
                    <?php if (!is_array($example)) { continue; } ?>
                    <li><?= e((string)($example['path'] ?? '')) ?> <span class="helper-muted">(<?= e((string)($example['reason'] ?? '')) ?>)</span></li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <span class="helper-muted"><?= e($ht('no_examples')) ?></span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($skippedPaths !== []): ?>
        <details class="helper-collapsible" style="margin-top:0.75rem">
          <summary><?= e($ht('skipped_paths_title')) ?> (<?= e((string)count($skippedPaths)) ?>)</summary>
          <div class="helper-table-wrap" style="margin-top:0.5rem">
            <table class="helper-table">
              <thead>
                <tr>
                  <th><?= e($ht('path_label')) ?></th>
                  <th><?= e($ht('reason_label')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($skippedPaths as $skippedPath): ?>
                  <?php if (!is_array($skippedPath)) { continue; } ?>
                  <tr>
                    <td><?= e((string)($skippedPath['path'] ?? '')) ?></td>
                    <td><?= e((string)($skippedPath['reason'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </details>
      <?php endif; ?>
    </section>
    <!-- /ht-review-buckets -->


    <?php if ($ownerRepoOwners !== [] || $ownerEwOwners !== []): ?>
      <section class="helper-panel helper-owner-lens" data-helper-owner-lens>
        <h3><?= e($ht('owner_lens_title')) ?></h3>
        <p class="helper-panel-note"><?= e($ht('owner_lens_desc')) ?></p>

        <div class="helper-owner-filter">
          <label for="helper-owner-select"><?= e($ht('owner_filter_title')) ?>:</label>
          <select id="helper-owner-select" data-owner-select>
            <option value=""><?= e($ht('owner_no_selection')) ?></option>
            <?php foreach ($ownerHierarchy as $hItem): ?>
              <?php $po = $hItem['owner'] ?? []; $pk = (string)($po['owner_key'] ?? ''); if ($pk === '') continue; ?>
              <option value="<?= e($pk) ?>">
                <?= e((string)($po['display_label'] ?? $pk)) ?> (<?= e((string)($po['owner_type'] ?? '')) ?>)
              </option>
              <?php foreach (($hItem['children'] ?? []) as $co): ?>
                <?php $ck = (string)($co['owner_key'] ?? ''); if ($ck === '') continue; ?>
                <option value="<?= e($ck) ?>">
                  &nbsp;&nbsp;&nbsp;<?= e((string)($co['display_label'] ?? $ck)) ?> (<?= e((string)($co['owner_type'] ?? '')) ?>)
                </option>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="ht-hierarchy-list" data-owner-cards>
          <?php foreach ($ownerHierarchy as $hItem): ?>
            <?php
              $po = $hItem['owner'] ?? [];
              $pk = (string)($po['owner_key'] ?? ''); if ($pk === '') continue;
              $ps = isset($ownerStats[$pk]) && is_array($ownerStats[$pk]) ? $ownerStats[$pk] : null;
              $pfc = (int)($ps['file_count'] ?? 0);
              $pdc = (int)($ps['dir_count'] ?? 0);
              $ptb = (int)($ps['total_bytes'] ?? 0);
              $pwl = $ownerWorkspaceLinks[$pk] ?? ['linked' => false, 'ew_key' => null, 'ew_label' => null, 'url' => null];
              $children = $hItem['children'] ?? [];
            ?>
            <?php if ($children !== []): ?>
              <details class="ht-hierarchy-group" data-owner-card="<?= e($pk) ?>">
                <summary class="ht-hierarchy-summary">
                  <div class="helper-owner-card ht-hierarchy-card">
                    <div class="helper-owner-card-head">
                      <strong class="helper-owner-name"><?= e((string)($po['display_label'] ?? $pk)) ?></strong>
                      <span class="helper-owner-type ht-owner-type-<?= e((string)($po['owner_type'] ?? 'unknown')) ?>"><?= e((string)($po['owner_type'] ?? '')) ?></span>
                    </div>
                    <dl class="helper-owner-card-meta">
                      <dt><?= e($ht('owner_key_label')) ?></dt><dd><?= e($pk) ?></dd>
                      <dt><?= e($ht('owner_files_label')) ?></dt><dd><?= e((string)$pfc) ?></dd>
                      <dt><?= e($ht('owner_dirs_label')) ?></dt><dd><?= e((string)$pdc) ?></dd>
                      <dt><?= e($ht('owner_size_label')) ?></dt><dd><?= e($formatBytes($ptb)) ?></dd>
                      <dt><?= e($ht('owner_workspace_label')) ?></dt>
                      <dd class="helper-owner-ws <?= $pwl['linked'] ? 'ht-ws-linked' : 'ht-ws-absent' ?>"
                        data-ht-ew-key="<?= e((string)($pwl['ew_key'] ?? '')) ?>">
                        <?php if ($pwl['linked']): ?>
                          <span class="helper-ws-linked-label"><?= e($ht('owner_workspace_linked')) ?> <strong class="helper-ws-name"><?= e((string)($pwl['ew_label'] ?? '')) ?></strong></span>
                          <a href="<?= e((string)($pwl['url'] ?? '#')) ?>" class="helper-ws-link" target="_blank" rel="noopener"><?= e($ht('owner_workspace_open')) ?></a>
                        <?php else: ?>
                          <span class="helper-ws-not-linked"><?= e($ht('owner_workspace_not_linked')) ?></span>
                        <?php endif; ?>
                      </dd>
                    </dl>
                  </div>
                </summary>
                <div class="ht-hierarchy-children">
                  <?php foreach ($children as $co): ?>
                    <?php
                      $ck = (string)($co['owner_key'] ?? ''); if ($ck === '') continue;
                      $cs = isset($ownerStats[$ck]) && is_array($ownerStats[$ck]) ? $ownerStats[$ck] : null;
                      $cfc = (int)($cs['file_count'] ?? 0);
                      $cdc = (int)($cs['dir_count'] ?? 0);
                      $ctb = (int)($cs['total_bytes'] ?? 0);
                      $cwl = $ownerWorkspaceLinks[$ck] ?? ['linked' => false, 'ew_key' => null, 'ew_label' => null, 'url' => null];
                    ?>
                    <div class="helper-owner-card ht-hierarchy-child-card" data-owner-card="<?= e($ck) ?>">
                      <div class="helper-owner-card-head">
                        <strong class="helper-owner-name"><?= e((string)($co['display_label'] ?? $ck)) ?></strong>
                        <span class="helper-owner-type ht-owner-type-<?= e((string)($co['owner_type'] ?? 'unknown')) ?>"><?= e((string)($co['owner_type'] ?? '')) ?></span>
                      </div>
                      <dl class="helper-owner-card-meta">
                        <dt><?= e($ht('owner_key_label')) ?></dt><dd><?= e($ck) ?></dd>
                        <dt><?= e($ht('owner_files_label')) ?></dt><dd><?= e((string)$cfc) ?></dd>
                        <dt><?= e($ht('owner_dirs_label')) ?></dt><dd><?= e((string)$cdc) ?></dd>
                        <dt><?= e($ht('owner_size_label')) ?></dt><dd><?= e($formatBytes($ctb)) ?></dd>
                        <dt><?= e($ht('owner_workspace_label')) ?></dt>
                        <dd class="helper-owner-ws <?= $cwl['linked'] ? 'ht-ws-linked' : 'ht-ws-absent' ?>"
                            data-ht-ew-key="<?= e((string)($cwl['ew_key'] ?? '')) ?>">
                          <?php if ($cwl['linked']): ?>
                            <span class="helper-ws-linked-label"><?= e($ht('owner_workspace_linked')) ?> <strong class="helper-ws-name"><?= e((string)($cwl['ew_label'] ?? '')) ?></strong></span>
                            <a href="<?= e((string)($cwl['url'] ?? '#')) ?>" class="helper-ws-link" target="_blank" rel="noopener"><?= e($ht('owner_workspace_open')) ?></a>
                          <?php else: ?>
                            <span class="helper-ws-not-linked"><?= e($ht('owner_workspace_not_linked')) ?></span>
                          <?php endif; ?>
                        </dd>
                      </dl>
                    </div>
                  <?php endforeach; ?>
                </div>
              </details>
            <?php else: ?>
              <div class="helper-owner-card" data-owner-card="<?= e($pk) ?>">
                <div class="helper-owner-card-head">
                  <strong class="helper-owner-name"><?= e((string)($po['display_label'] ?? $pk)) ?></strong>
                  <span class="helper-owner-type ht-owner-type-<?= e((string)($po['owner_type'] ?? 'unknown')) ?>"><?= e((string)($po['owner_type'] ?? '')) ?></span>
                </div>
                <dl class="helper-owner-card-meta">
                  <dt><?= e($ht('owner_key_label')) ?></dt><dd><?= e($pk) ?></dd>
                  <dt><?= e($ht('owner_files_label')) ?></dt><dd><?= e((string)$pfc) ?></dd>
                  <dt><?= e($ht('owner_dirs_label')) ?></dt><dd><?= e((string)$pdc) ?></dd>
                  <dt><?= e($ht('owner_size_label')) ?></dt><dd><?= e($formatBytes($ptb)) ?></dd>
                  <dt><?= e($ht('owner_workspace_label')) ?></dt>
                  <dd class="helper-owner-ws <?= $pwl['linked'] ? 'ht-ws-linked' : 'ht-ws-absent' ?>"
                      data-ht-ew-key="<?= e((string)($pwl['ew_key'] ?? '')) ?>">
                    <?php if ($pwl['linked']): ?>
                      <span class="helper-ws-linked-label"><?= e($ht('owner_workspace_linked')) ?> <strong class="helper-ws-name"><?= e((string)($pwl['ew_label'] ?? '')) ?></strong></span>
                      <a href="<?= e((string)($pwl['url'] ?? '#')) ?>" class="helper-ws-link" target="_blank" rel="noopener"><?= e($ht('owner_workspace_open')) ?></a>
                    <?php else: ?>
                      <span class="helper-ws-not-linked"><?= e($ht('owner_workspace_not_linked')) ?></span>
                    <?php endif; ?>
                  </dd>
                </dl>
              </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <!-- ht-entity-explorer -->
    <section class="helper-panel" data-entity-explorer
      data-entity-json="<?= e(json_encode($ownerEntities, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>"
      data-entity-registry="<?= e(json_encode($entityTypeRegistry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>"
      data-label-type-controller="<?= e($ht('entity_type_controller')) ?>"
      data-label-type-service="<?= e($ht('entity_type_service')) ?>"
      data-label-type-view="<?= e($ht('entity_type_view')) ?>"
      data-label-type-route="<?= e($ht('entity_type_route')) ?>"
      data-label-state-confirmed="<?= e($ht('entity_state_confirmed')) ?>"
      data-label-state-uncertain="<?= e($ht('entity_state_uncertain')) ?>"
      data-label-evidence="<?= e($ht('entity_evidence_label')) ?>"
      data-label-path="<?= e($ht('entity_path_label')) ?>"
      data-label-filter-all="<?= e($ht('entity_filter_all')) ?>">
      <h3><?= e($ht('entity_explorer_title')) ?></h3>
      <p class="helper-panel-note"><?= e($ht('entity_explorer_desc')) ?></p>

    <?php if ($entitySummary !== null && ($entitySummary['total'] ?? 0) > 0): ?>
      <?php
        $esCounts = $entitySummary['counts'] ?? [];
        $esDistrib = $entitySummary['distribution'] ?? [];
        $esTotal = (int)($entitySummary['total'] ?? 0);
        $esDisplayOwners = 5;
      ?>
      <div class="helper-entity-repository-summary" data-entity-summary-section>
        <h4><?= e($ht('entity_summary_title')) ?> <span class="entity-summary-badge"><?= e((string)$esTotal) ?></span></h4>
        <p class="helper-panel-note"><?= e($ht('entity_summary_desc')) ?></p>
        <div class="entity-repo-summary-grid">
          <?php foreach (\Apps\Studio\Tools\HelperTool\Services\RepoTreeScannerService::getEntityTypeKeys() as $est): ?>
            <?php $estCount = (int)($esCounts[$est] ?? 0); if ($estCount === 0) { continue; } ?>
            <?php $estOwners = $esDistrib[$est] ?? []; ?>
            <div class="entity-repo-summary-card" data-entity-summary-type="<?= e($est) ?>">
              <div class="entity-repo-summary-head">
                <span class="entity-repo-summary-num"><?= e((string)$estCount) ?></span>
                <span class="entity-repo-summary-label"><?= e($ht('entity_type_' . $est)) ?></span>
              </div>
              <?php if ($estOwners !== []): ?>
                <ul class="entity-repo-distrib-list">
                  <?php $estShown = array_slice($estOwners, 0, $esDisplayOwners); ?>
                  <?php $estRemaining = count($estOwners) - count($estShown); ?>
                  <?php foreach ($estShown as $estO): ?>
                    <li>
                      <span class="entity-repo-distrib-owner"><?= e((string)($estO['owner_key'] ?? '')) ?></span>
                      <span class="entity-repo-distrib-count"><?= e((string)($estO['count'] ?? 0)) ?></span>
                    </li>
                  <?php endforeach; ?>
                  <?php if ($estRemaining > 0): ?>
                    <li class="entity-repo-distrib-more"
                      data-entity-distrib-toggle="<?= e($est) ?>"
                      data-expanded="false">
                      <span class="entity-repo-distrib-more-link">+ <?= e((string)$estRemaining) ?> <?= e($ht('entity_summary_show_all')) ?></span>
                    </li>
                    <li class="entity-repo-distrib-rest" data-entity-distrib-rest="<?= e($est) ?>" hidden>
                      <?php foreach (array_slice($estOwners, $esDisplayOwners) as $estO): ?>
                        <span class="entity-repo-distrib-item">
                          <span class="entity-repo-distrib-owner"><?= e((string)($estO['owner_key'] ?? '')) ?></span>
                          <span class="entity-repo-distrib-count"><?= e((string)($estO['count'] ?? 0)) ?></span>
                        </span>
                      <?php endforeach; ?>
                    </li>
                  <?php endif; ?>
                </ul>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <script>
      (function () {
        var collapseText = <?= json_encode($ht('entity_summary_collapse')) ?>;
        var toggles = document.querySelectorAll('[data-entity-distrib-toggle]');
        toggles.forEach(function (el) {
          var originalText = el.querySelector('.entity-repo-distrib-more-link').textContent;
          el.addEventListener('click', function () {
            var type = el.getAttribute('data-entity-distrib-toggle');
            var expanded = el.getAttribute('data-expanded') === 'true';
            var rest = document.querySelector('[data-entity-distrib-rest="' + type + '"]');
            if (rest) {
              rest.hidden = expanded;
            }
            el.setAttribute('data-expanded', String(!expanded));
            el.querySelector('.entity-repo-distrib-more-link').textContent = expanded ? originalText : collapseText;
          });
        });
      })();
      </script>
    <?php endif; ?>

      <div data-entity-empty class="helper-panel-note"><?= e($ht('entity_no_selection')) ?></div>
      <div data-entity-results hidden>
        <div class="entity-summary-grid" data-entity-summary>
          <button class="entity-summary-card entity-filter-all is-active"
            data-entity-filter="all"
            aria-pressed="true">
            <span class="entity-summary-num" data-entity-inline-num>0</span>
            <span class="entity-summary-label"><?= e($ht('entity_filter_all')) ?></span>
          </button>
        </div>
        <div class="entity-lists" data-entity-lists></div>
      </div>
    </section>
    <!-- /ht-entity-explorer -->

    <?php if ($ownerEwOwners !== []): ?>
      <!-- ht-engineering-workspaces -->
      <section class="helper-panel helper-ew-section" data-ew-section>
        <h3><?= e($ht('owner_ew_title')) ?></h3>
        <p class="helper-panel-note"><?= e($ht('owner_ew_note')) ?></p>
        <div class="helper-summary-grid helper-summary-grid-tight" style="margin-bottom:0.75rem">
          <div class="helper-summary-card"><dt><?= e($ht('ew_total_workspaces')) ?></dt><dd><?= e((string)count($ownerEwOwners)) ?></dd></div>
          <div class="helper-summary-card"><dt><?= e($ht('ew_linked_owners')) ?></dt><dd><?= e((string)$totalLinkedOwners) ?></dd></div>
          <div class="helper-summary-card"><dt><?= e($ht('ew_unlinked_owners')) ?></dt><dd><?= e((string)$totalUnlinkedOwners) ?></dd></div>
        </div>
        <div class="helper-owner-grid">
          <?php foreach ($ownerEwOwners as $ew): ?>
            <?php
              $ewk = (string)($ew['owner_key'] ?? ''); if ($ewk === '') continue;
              $ews = isset($ownerStats[$ewk]) && is_array($ownerStats[$ewk]) ? $ownerStats[$ewk] : null;
              $efc = (int)($ews['file_count'] ?? 0);
              $edc = (int)($ews['dir_count'] ?? 0);
              $etb = (int)($ews['total_bytes'] ?? 0);
            ?>
            <?php
              $ewLinkedRepos = $ewToOwnerMap[$ewk] ?? [];
              $ewLinkedLabels = [];
              foreach ($ewLinkedRepos as $lrk) {
                  $ewLinkedLabels[] = e((string)($repoOwnerLabelMap[$lrk] ?? $lrk));
              }
            ?>
            <div class="helper-owner-card" data-owner-card="<?= e($ewk) ?>" data-ht-linked-owners="<?= e(implode(',', $ewLinkedRepos)) ?>">
              <div class="helper-owner-card-head">
                <strong class="helper-owner-name"><?= e((string)($ew['display_label'] ?? $ewk)) ?></strong>
                <span class="helper-owner-type ht-owner-type-engineering_workspace">engineering_workspace</span>
              </div>
              <dl class="helper-owner-card-meta">
                <dt><?= e($ht('owner_key_label')) ?></dt><dd><?= e($ewk) ?></dd>
                <dt><?= e($ht('owner_files_label')) ?></dt><dd><?= e((string)$efc) ?></dd>
                <dt><?= e($ht('owner_dirs_label')) ?></dt><dd><?= e((string)$edc) ?></dd>
                <dt><?= e($ht('owner_size_label')) ?></dt><dd><?= e($formatBytes($etb)) ?></dd>
                <?php if ($ewLinkedLabels !== []): ?>
                  <dt><?= e($ht('ew_linked_owners_label')) ?></dt>
                  <dd class="helper-ew-linked-owners">
                    <span class="helper-ew-linked-owners-list"><?= e(implode(', ', $ewLinkedLabels)) ?></span>
                  </dd>
                <?php endif; ?>
              </dl>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
      <!-- /ht-engineering-workspaces -->
    <?php endif; ?>

    <!-- ht-repository-statistics -->
    <details class="helper-collapsible helper-repository-statistics" data-ht-repository-statistics>
      <summary>
        <strong><?= e($ht('repository_statistics_title')) ?></strong>
        <span class="helper-details-state" data-closed-label="<?= e($ht('details_expand')) ?>" data-open-label="<?= e($ht('details_collapse')) ?>"></span>
      </summary>
      <div class="helper-summary-grid helper-summary-grid-tight" style="margin-top:0.5rem">
        <div class="helper-summary-card"><dt><?= e($ht('dirs_label')) ?></dt><dd><?= e((string)($inventorySummary['total_folders'] ?? 0)) ?></dd></div>
        <div class="helper-summary-card"><dt><?= e($ht('files_label')) ?></dt><dd><?= e((string)($inventorySummary['total_files'] ?? 0)) ?></dd></div>
        <div class="helper-summary-card"><dt><?= e($ht('total_bytes_label')) ?></dt><dd><?= e((string)($inventorySummary['total_bytes'] ?? 0)) ?></dd></div>
        <div class="helper-summary-card"><dt><?= e($ht('human_size_label')) ?></dt><dd><?= e($formatBytes((int)($inventorySummary['total_bytes'] ?? 0))) ?></dd></div>
        <div class="helper-summary-card"><dt><?= e($ht('skipped_label')) ?></dt><dd><?= e((string)($inventorySummary['skipped_paths'] ?? 0)) ?></dd></div>
        <div class="helper-summary-card"><dt><?= e($ht('duration_label')) ?></dt><dd><?= e((string)($inventorySummary['scan_duration_ms'] ?? 0)) ?> ms</dd></div>
      </div>
      <h3 class="helper-subsection-title"><?= e($ht('inventory_title')) ?> / <?= e($ht('file_type_title')) ?></h3>
      <div class="helper-table-wrap" style="margin-top:0.5rem">
        <table class="helper-table">
          <thead>
            <tr>
              <th><?= e($ht('type_label')) ?></th>
              <th><?= e($ht('count_label')) ?></th>
              <th><?= e($ht('total_bytes_label')) ?></th>
              <th><?= e($ht('human_size_label')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($fileTypes as $typeRow): ?>
              <?php if (!is_array($typeRow)) { continue; } ?>
              <tr>
                <td><?= e((string)($typeRow['label'] ?? $typeRow['type'] ?? '')) ?></td>
                <td><?= e((string)($typeRow['count'] ?? 0)) ?></td>
                <td><?= e((string)($typeRow['total_bytes'] ?? 0)) ?></td>
                <td><?= e($formatBytes((int)($typeRow['total_bytes'] ?? 0))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
    <!-- /ht-repository-statistics -->

    <!-- ht-raw-tree -->
    <details class="helper-collapsible helper-raw-tree" data-ht-raw-tree>
      <summary>
        <strong><?= e($ht('raw_tree_title')) ?></strong>
        <span class="helper-details-state" data-closed-label="<?= e($ht('details_expand')) ?>" data-open-label="<?= e($ht('details_collapse')) ?>"></span>
      </summary>
      <?php if ($tree !== []): ?>
      <section class="helper-panel helper-tree-filter-panel" data-helper-tree-filters>
        <h3><?= e($ht('tree_filter_title')) ?></h3>
        <div class="helper-filter-controls" role="group" aria-label="<?= e($ht('tree_filter_title')) ?>">
          <button type="button" class="helper-filter-btn" data-tree-filter="all" aria-pressed="false"><?= e($ht('filter_show_all')) ?></button>
          <button type="button" class="helper-filter-btn is-active" data-tree-filter="hide-runtime" aria-pressed="true"><?= e($ht('filter_hide_runtime')) ?></button>
          <button type="button" class="helper-filter-btn" data-tree-filter="hide-snapshot" aria-pressed="false"><?= e($ht('filter_hide_snapshot')) ?></button>
          <button type="button" class="helper-filter-btn" data-tree-filter="review-only" aria-pressed="false"><?= e($ht('filter_review_only')) ?></button>
          <button type="button" class="helper-filter-btn" data-tree-filter="unknown-only" aria-pressed="false"><?= e($ht('filter_unknown_only')) ?></button>
        </div>
      </section>
      <div class="helper-tree-wrap">
        <ul class="ht-tree-list" data-helper-tree data-default-filter="hide-runtime">
          <?php $renderTree($tree, 0); ?>
        </ul>
      </div>
      <aside class="helper-inspector" data-file-inspector
        data-label-path="<?= e($ht('path_label')) ?>"
        data-label-type="<?= e($ht('type_label')) ?>"
        data-label-size="<?= e($ht('size_label')) ?>"
        data-label-owner="<?= e($ht('inspector_owner')) ?>"
        data-label-scope="<?= e($ht('inspector_scope')) ?>"
        data-label-categories="<?= e($ht('inspector_categories')) ?>"
        data-label-reasons="<?= e($ht('inspector_reasons')) ?>"
        data-label-owner-key="<?= e($ht('owner_key_label')) ?>"
        data-label-preview="<?= e($ht('inspector_preview')) ?>"
        data-label-binary="<?= e($ht('inspector_binary')) ?>"
        data-label-truncated="<?= e($ht('inspector_truncated')) ?>"
        data-label-error="<?= e($ht('inspector_error')) ?>">
        <h3><?= e($ht('inspector_title')) ?></h3>
        <p class="helper-panel-note" data-file-inspector-empty><?= e($ht('inspector_empty')) ?></p>
        <div class="helper-inspector-body" data-file-inspector-body hidden></div>
      </aside>
      <?php else: ?>
      <p class="helper-panel-note"><?= e($ht('empty')) ?></p>
      <?php endif; ?>
    </details>
    <!-- /ht-raw-tree -->
  <?php else: ?>
    <p class="helper-empty"><?= e($ht('empty')) ?></p>
  <?php endif; ?>

  <p class="helper-readonly"><?= e($ht('read_only')) ?></p>
</section>

<style>
.helper-tool-card { padding: 1rem; }
.helper-toolbar { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1rem; }
.helper-note { margin: 0; color: var(--muted); }
.helper-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
.helper-summary-grid-tight { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
.helper-summary-card { border: 1px solid var(--style-border-soft); border-radius: 8px; padding: 0.6rem 0.75rem; background: var(--style-subtle-bg); }
.helper-summary-card dt { font-size: 0.75rem; color: var(--muted); margin: 0; }
.helper-summary-card dd { margin: 0.2rem 0 0; font-weight: 600; font-size: 0.9rem; color: var(--text); word-break: break-word; }
.helper-panel { margin: 1rem 0; }
.helper-panel h3 { margin: 0 0 0.55rem; font-size: 1rem; color: var(--text); }
.helper-panel h4, .helper-subsection-title { margin: 0.75rem 0 0.45rem; font-size: 0.9rem; color: var(--text); }
.helper-panel-note { margin: 0 0 0.65rem; color: var(--muted); font-size: 0.85rem; }
.helper-table-wrap { border: 1px solid var(--style-border-soft); border-radius: 8px; overflow: auto; background: var(--style-content-bg); }
.helper-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
.helper-table th, .helper-table td { padding: 0.55rem 0.65rem; border-bottom: 1px solid var(--style-border-soft); text-align: left; vertical-align: top; }
.helper-table th { color: var(--muted); font-size: 0.74rem; text-transform: uppercase; }
.helper-table tr:last-child td { border-bottom: 0; }
.helper-example-list { margin: 0; padding-left: 1rem; }
.helper-muted { color: var(--muted); }
.helper-tree-filter-panel { margin-bottom: 0.6rem; }
.helper-filter-controls { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.helper-filter-btn { border: 1px solid var(--style-border-soft); border-radius: 8px; background: var(--style-content-bg); color: var(--text); padding: 0.45rem 0.6rem; cursor: pointer; font-size: 0.82rem; }
.helper-filter-btn.is-active { background: var(--style-subtle-bg); border-color: var(--text); font-weight: 700; }
.helper-tree-wrap { border: 1px solid var(--style-border-soft); border-radius: 8px; background: var(--style-content-bg); padding: 0.75rem; max-height: 70vh; overflow: auto; }
.ht-tree-list { list-style: none; margin: 0; padding-left: 1rem; }
.ht-tree-node { margin: 0.25rem 0; }
.ht-tree-dir > details > summary { cursor: pointer; }
.ht-file-select { appearance: none; border: 0; background: transparent; color: inherit; padding: 0; cursor: pointer; font: inherit; text-align: left; }
.ht-file-select:hover .ht-node-name, .ht-file-select:focus .ht-node-name { text-decoration: underline; }
.ht-node-name { font-weight: 600; color: var(--text); }
.ht-node-meta { color: var(--muted); margin-left: 0.45rem; font-size: 0.78rem; }
.ht-node-path { color: var(--muted); font-size: 0.75rem; margin-left: 1.2rem; word-break: break-all; }
.ht-root-files-group > details > summary { cursor: pointer; opacity: 0.85; }
.ht-root-files-group > details > summary .ht-node-name { font-weight: 500; font-size: 0.88rem; }
.ht-file-badge { display: inline-block; font-size: 0.65rem; font-weight: 600; padding: 0.1rem 0.35rem; border-radius: 4px; margin-left: 0.25rem; vertical-align: middle; line-height: 1.4; }
.ht-cat-runtime_dependency_artifacts { background: var(--tone-warning-bg, #fff3cd); color: var(--tone-warning-text, #856404); }
.ht-cat-snapshot_recovery_artifacts { background: var(--tone-info-bg, #d1ecf1); color: var(--tone-info-text, #0c5460); }
.ht-cat-review_candidates { background: var(--tone-danger-bg, #f8d7da); color: var(--tone-danger-text, #721c24); }
.ht-cat-unknown_needs_classification { background: var(--tone-neutral-bg, #e2e3e5); color: var(--tone-neutral-text, #383d41); }
.helper-empty { margin: 0.4rem 0 1rem; color: var(--muted); }
.helper-readonly { margin-top: 0.85rem; font-size: 0.82rem; color: var(--muted); }
.helper-inspector { margin-top: 1rem; border: 1px solid var(--style-border-soft); border-radius: 8px; background: var(--style-content-bg); padding: 0.75rem; }
.helper-inspector h3 { margin: 0 0 0.55rem; font-size: 1rem; }
.helper-inspector dl { display: grid; grid-template-columns: minmax(120px, 0.28fr) 1fr; gap: 0.35rem 0.75rem; margin: 0 0 0.75rem; }
.helper-inspector dt { color: var(--muted); font-size: 0.76rem; }
.helper-inspector dd { margin: 0; word-break: break-word; }
.helper-preview-table { width: 100%; border-collapse: collapse; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.78rem; }
.helper-preview-table td { border-bottom: 1px solid var(--style-border-soft); vertical-align: top; padding: 0.2rem 0.35rem; }
.helper-preview-line-no { width: 1%; color: var(--muted); text-align: right; user-select: none; white-space: nowrap; }
.helper-preview-line { white-space: pre-wrap; word-break: break-word; }
.helper-owner-filter { margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; }
.helper-owner-filter label { font-size: 0.85rem; color: var(--muted); }
.helper-owner-filter select { max-width: 400px; padding: 0.35rem 0.5rem; border: 1px solid var(--style-border-soft); border-radius: 6px; background: var(--style-content-bg); color: var(--text); font-size: 0.85rem; }
.helper-owner-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 0.65rem; }
.helper-owner-card { border: 1px solid var(--style-border-soft); border-radius: 8px; padding: 0.6rem 0.75rem; background: var(--style-content-bg); font-size: 0.83rem; }
.helper-owner-card-head { display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.3rem; }
.helper-owner-name { font-weight: 600; color: var(--text); }
.helper-owner-type { font-size: 0.68rem; padding: 0.08rem 0.3rem; border-radius: 4px; background: var(--tone-neutral-bg, #e2e3e5); color: var(--tone-neutral-text, #383d41); }
.ht-owner-type-app { background: var(--tone-info-bg, #d1ecf1); color: var(--tone-info-text, #0c5460); }
.ht-owner-type-module { background: var(--tone-warning-bg, #fff3cd); color: var(--tone-warning-text, #856404); }
.ht-owner-type-plugin { background: var(--tone-danger-bg, #f8d7da); color: var(--tone-danger-text, #721c24); }
.ht-owner-type-platform { background: #cce5ff; color: #004085; }
.ht-owner-type-engineering_workspace { background: #d4edda; color: #155724; }
.helper-owner-card-meta { display: grid; grid-template-columns: minmax(80px, 0.3fr) 1fr; gap: 0.2rem 0.4rem; margin: 0; }
.helper-owner-card-meta dt { color: var(--muted); font-size: 0.72rem; }
.helper-owner-card-meta dd { margin: 0; word-break: break-word; }
.ht-ws-present { color: var(--tone-success-text, #155724); font-weight: 600; }
.ht-ws-absent { color: var(--muted); }
.ht-ws-linked { color: var(--tone-success-text, #155724); }
.helper-ws-linked-label { font-size: 0.75rem; display: block; margin-bottom: 0.2rem; }
.helper-ws-name { font-weight: 600; }
.helper-ws-link { font-size: 0.72rem; color: var(--style-accent, #0066cc); text-decoration: underline; }
.helper-ws-link:hover { text-decoration: none; }
.helper-ws-not-linked { color: var(--muted); font-style: italic; font-size: 0.75rem; }
.helper-ew-linked-owners { font-size: 0.72rem; }
.helper-ew-linked-owners-list { display: inline-block; }
.ht-hierarchy-list { display: flex; flex-direction: column; gap: 0.5rem; }
.ht-hierarchy-group { border: 1px solid var(--style-border-soft); border-radius: 8px; background: var(--style-content-bg); padding: 0; }
.ht-hierarchy-group[open] { padding-bottom: 0.5rem; }
.ht-hierarchy-summary { cursor: pointer; list-style: none; display: flex; align-items: center; padding: 0; }
.ht-hierarchy-summary::-webkit-details-marker { display: none; }
.ht-hierarchy-summary .helper-owner-card { border: 0; border-radius: 8px; margin: 0; width: 100%; }
.ht-hierarchy-group[open] > .ht-hierarchy-summary .helper-owner-card { border-radius: 8px 8px 0 0; }
.ht-hierarchy-children { padding: 0.5rem 0.75rem 0 1.5rem; display: flex; flex-direction: column; gap: 0.4rem; }
.ht-hierarchy-child-card { border-left: 3px solid var(--style-border-soft); }
.helper-ew-section { margin-top: 1.25rem; padding-top: 0.75rem; border-top: 1px solid var(--style-border-soft); }
.ht-linked-active { outline: 2px solid var(--style-accent, #0066cc); outline-offset: 2px; border-radius: 8px; }
.helper-ew-section h3 { margin: 0 0 0.6rem; font-size: 1rem; color: var(--text); }
.helper-entity-repository-summary { margin: 0.75rem 0 1rem; }
.entity-summary-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 0.5rem; margin-bottom: 0.75rem; }
.entity-summary-card { border: 1px solid var(--style-border-soft); border-radius: 8px; padding: 0.5rem 0.65rem; background: var(--style-subtle-bg); text-align: center; cursor: pointer; font: inherit; }
.entity-summary-card:hover { border-color: var(--style-accent, #0066cc); }
.entity-summary-card.is-active { border-color: var(--style-accent, #0066cc); background: var(--style-selected-bg, #eef4ff); }
.entity-summary-card .entity-summary-num { font-size: 1.15rem; font-weight: 700; display: block; color: var(--text); }
.entity-summary-card .entity-summary-label { font-size: 0.72rem; color: var(--muted); text-transform: uppercase; }
.entity-list { margin-bottom: 0.75rem; }
.entity-list h4 { margin: 0 0 0.35rem; font-size: 0.88rem; color: var(--text); }
.entity-item { display: flex; align-items: center; gap: 0.4rem; padding: 0.3rem 0.5rem; border-bottom: 1px solid var(--style-border-soft); font-size: 0.83rem; }
.entity-item:last-child { border-bottom: 0; }
.entity-item-name { font-weight: 600; color: var(--text); }
.entity-item-path { color: var(--muted); font-size: 0.76rem; cursor: pointer; }
.entity-item-path:hover { text-decoration: underline; color: var(--style-accent, #0066cc); }
.entity-item-path::before { content: '['; }
.entity-item-path::after { content: ']'; }
.entity-item-evidence { color: var(--muted); font-size: 0.74rem; margin-left: auto; }
.entity-badge { font-size: 0.65rem; padding: 0.08rem 0.3rem; border-radius: 4px; font-weight: 600; }
.entity-badge-certain { background: var(--tone-success-bg, #d4edda); color: var(--tone-success-text, #155724); }
.entity-badge-uncertain { background: var(--tone-warning-bg, #fff3cd); color: var(--tone-warning-text, #856404); }
.entity-summary-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 1.6rem; height: 1.4rem; padding: 0 0.4rem; font-size: 0.75rem; font-weight: 700; border-radius: 999px; background: var(--style-accent, #0066cc); color: #fff; vertical-align: middle; margin-left: 0.4rem; }
.entity-repo-summary-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.75rem; margin-bottom: 0.5rem; }
.entity-repo-summary-card { border: 1px solid var(--style-border-soft); border-radius: 8px; padding: 0.65rem 0.75rem; background: var(--style-content-bg); }
.entity-repo-summary-head { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem; padding-bottom: 0.35rem; border-bottom: 1px solid var(--style-border-soft); }
.entity-repo-summary-num { font-size: 1.25rem; font-weight: 800; color: var(--text); }
.entity-repo-summary-label { font-size: 0.78rem; color: var(--muted); text-transform: uppercase; font-weight: 600; }
.entity-repo-distrib-list { list-style: none; margin: 0; padding: 0; }
.entity-repo-distrib-list li { display: flex; justify-content: space-between; align-items: center; padding: 0.2rem 0; font-size: 0.82rem; }
.entity-repo-distrib-owner { color: var(--text); font-weight: 500; }
.entity-repo-distrib-count { color: var(--muted); font-size: 0.78rem; }
.entity-repo-distrib-more { cursor: pointer; font-size: 0.78rem; }
.entity-repo-distrib-more-link { color: var(--style-accent, #0066cc); }
.entity-repo-distrib-more-link:hover { text-decoration: underline; }
.entity-repo-distrib-rest { display: flex; flex-wrap: wrap; gap: 0.25rem 0.6rem; padding: 0.25rem 0 0; border-top: 1px dashed var(--style-border-soft); }
.entity-repo-distrib-item { font-size: 0.8rem; white-space: nowrap; }
/* Search */
.ht-search-section { border: 1px solid var(--style-border-soft); border-radius: 8px; padding: 0.75rem; background: var(--style-content-bg); }
.ht-search-bar { margin-bottom: 0.5rem; }
.ht-search-input { width: 100%; box-sizing: border-box; padding: 0.5rem 0.75rem; border: 1px solid var(--style-border-soft); border-radius: 8px; background: var(--style-subtle-bg); color: var(--text); font-size: 0.9rem; font-family: inherit; }
.ht-search-input:focus { outline: 2px solid var(--style-accent, #0066cc); outline-offset: 1px; border-color: var(--style-accent, #0066cc); }
.ht-search-hint { margin-top: 0; }
.ht-search-count { margin-bottom: 0.4rem; font-size: 0.8rem; }
.ht-search-result-list { display: flex; flex-direction: column; gap: 0.25rem; max-height: 420px; overflow-y: auto; }
.ht-search-result { display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.4rem 0.5rem; border: 1px solid var(--style-border-soft); border-radius: 6px; background: var(--style-subtle-bg); font-size: 0.84rem; }
.ht-search-result:hover { border-color: var(--style-accent, #0066cc); }
.ht-search-result-badge { flex-shrink: 0; font-size: 0.64rem; font-weight: 700; padding: 0.1rem 0.35rem; border-radius: 4px; text-transform: uppercase; white-space: nowrap; margin-top: 0.1rem; }
.ht-search-badge-file { background: var(--tone-info-bg, #d1ecf1); color: var(--tone-info-text, #0c5460); }
.ht-search-badge-owner { background: #cce5ff; color: #004085; }
.ht-search-badge-entity { background: var(--tone-warning-bg, #fff3cd); color: var(--tone-warning-text, #856404); }
.ht-search-badge-route { background: var(--tone-success-bg, #d4edda); color: var(--tone-success-text, #155724); }
.ht-search-badge-workspace { background: #d4edda; color: #155724; }
.ht-search-result-body { flex: 1; min-width: 0; }
.ht-search-result-label { font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
.ht-search-result-sublabel { color: var(--muted); font-size: 0.76rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
.ht-search-result-action { flex-shrink: 0; }
.ht-search-result-btn { appearance: none; border: 1px solid var(--style-border-soft); border-radius: 6px; background: var(--style-content-bg); color: var(--style-accent, #0066cc); padding: 0.2rem 0.45rem; font-size: 0.76rem; cursor: pointer; font-family: inherit; white-space: nowrap; }
.ht-search-result-btn:hover { background: var(--style-accent, #0066cc); color: #fff; border-color: var(--style-accent, #0066cc); }
.ht-search-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 0.5rem; }
.ht-search-chip { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.18rem 0.5rem; border-radius: 999px; border: 1px solid var(--style-border-soft); font-size: 0.74rem; font-weight: 600; background: var(--style-subtle-bg); color: var(--text); cursor: pointer; white-space: nowrap; }
.ht-search-chip.is-active { border-color: var(--style-accent, #0066cc); background: var(--style-accent, #0066cc); color: #fff; }
.ht-search-chip-count { font-size: 0.68rem; opacity: 0.75; }
.ht-search-owner-filter-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem; font-size: 0.83rem; }
.ht-search-owner-filter-label { display: flex; align-items: center; gap: 0.35rem; cursor: pointer; }
.ht-search-owner-checkbox { cursor: pointer; }
.ht-search-owner-filter-hint { font-size: 0.76rem; }
/* Review Buckets */
.helper-review-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 0.75rem; }
.helper-review-card { border: 1px solid var(--style-border-soft); border-radius: 8px; background: var(--style-content-bg); overflow: hidden; }
.helper-review-card-head { display: flex; align-items: center; justify-content: space-between; padding: 0.55rem 0.75rem; border-bottom: 1px solid var(--style-border-soft); }
.helper-review-card-label { font-weight: 600; font-size: 0.85rem; color: var(--text); }
.helper-review-card-count { font-size: 1.1rem; font-weight: 800; color: var(--text); }
.helper-review-card-body { padding: 0.5rem 0.75rem; font-size: 0.82rem; }
.helper-review-card-bytes { color: var(--muted); font-size: 0.78rem; display: block; margin-bottom: 0.35rem; }
.helper-review-card .helper-example-list { margin: 0; padding: 0; list-style: none; }
.helper-review-card .helper-example-list li { padding: 0.15rem 0; font-size: 0.8rem; color: var(--text); word-break: break-word; }
/* Review card tones */
[data-ht-review-cat="review-candidates"] .helper-review-card-head { background: var(--tone-danger-bg, #f8d7da); }
[data-ht-review-cat="review-candidates"] .helper-review-card-label,
[data-ht-review-cat="review-candidates"] .helper-review-card-count { color: var(--tone-danger-text, #721c24); }
[data-ht-review-cat="runtime-dependency-artifacts"] .helper-review-card-head { background: var(--tone-warning-bg, #fff3cd); }
[data-ht-review-cat="runtime-dependency-artifacts"] .helper-review-card-label,
[data-ht-review-cat="runtime-dependency-artifacts"] .helper-review-card-count { color: var(--tone-warning-text, #856404); }
[data-ht-review-cat="snapshot-recovery-artifacts"] .helper-review-card-head { background: var(--tone-info-bg, #d1ecf1); }
[data-ht-review-cat="snapshot-recovery-artifacts"] .helper-review-card-label,
[data-ht-review-cat="snapshot-recovery-artifacts"] .helper-review-card-count { color: var(--tone-info-text, #0c5460); }
[data-ht-review-cat="unknown-needs-classification"] .helper-review-card-head { background: var(--tone-neutral-bg, #e2e3e5); }
[data-ht-review-cat="unknown-needs-classification"] .helper-review-card-label,
[data-ht-review-cat="unknown-needs-classification"] .helper-review-card-count { color: var(--tone-neutral-text, #383d41); }
/* Collapsible sections */
.helper-collapsible { border: 1px solid var(--style-border-soft); border-radius: 8px; padding: 0.6rem 0.75rem; background: var(--style-content-bg); margin-top: 0.5rem; }
.helper-collapsible > summary { cursor: pointer; padding: 0.15rem 0; font-size: 0.88rem; color: var(--text); }
.helper-repository-statistics > summary,
.helper-raw-tree > summary { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
.helper-details-state { flex-shrink: 0; font-size: 0.74rem; color: var(--style-accent, #0066cc); font-weight: 700; }
.helper-details-state::after { content: attr(data-closed-label); }
.helper-collapsible[open] > summary .helper-details-state::after { content: attr(data-open-label); }
.helper-collapsible > summary:focus { outline: 2px solid var(--style-accent, #0066cc); outline-offset: 2px; border-radius: 4px; }
.helper-raw-tree { margin-top: 1rem; }
.helper-repository-statistics { margin-top: 1rem; }
</style>

<script>
(function () {
  const tree = document.querySelector('[data-helper-tree]');
  const filterWrap = document.querySelector('[data-helper-tree-filters]');
  const ownerSelect = document.querySelector('[data-owner-select]');
  if (!tree || !filterWrap) {
    return;
  }

  const buttons = Array.from(filterWrap.querySelectorAll('[data-tree-filter]'));
  var currentFilter = String(tree.getAttribute('data-default-filter') || 'hide-runtime');
  var currentOwner = '';

  const hasCategory = function (node, category) {
    const catStr = node.getAttribute('data-categories');
    if (!catStr) { return false; }
    return catStr.split(/\s+/).indexOf(category) !== -1;
  };
  const fileMatches = function (node, filter) {
    if (filter === 'all') {
      return true;
    }
    if (filter === 'hide-runtime') {
      return !hasCategory(node, 'runtime_dependency_artifacts');
    }
    if (filter === 'hide-snapshot') {
      return !hasCategory(node, 'snapshot_recovery_artifacts');
    }
    if (filter === 'review-only') {
      return hasCategory(node, 'review_candidates');
    }
    if (filter === 'unknown-only') {
      return hasCategory(node, 'unknown_needs_classification');
    }
    return true;
  };
  const ownerMatches = function (node, owner) {
    if (!owner) { return true; }
    return node.getAttribute('data-owner') === owner;
  };
  const subtreeHasFile = function (node, filter, owner) {
    const details = node.firstElementChild;
    const childList = details ? details.querySelector('ul') : null;
    if (!childList) { return false; }
    var hideCat = null;
    if (filter === 'hide-runtime') { hideCat = 'runtime_dependency_artifacts'; }
    else if (filter === 'hide-snapshot') { hideCat = 'snapshot_recovery_artifacts'; }
    var dirIsHideCat = hideCat && hasCategory(node, hideCat);
    var i = 0;
    var child = childList.children[i];
    while (child) {
      if (child.classList && child.classList.contains('ht-tree-node')) {
        if (!ownerMatches(child, owner)) {
          /* skip: wrong owner */
        } else if (child.getAttribute('data-node-type') === 'file') {
          if (fileMatches(child, filter)) {
            if (dirIsHideCat && hasCategory(child, 'review_candidates') && !hasCategory(child, hideCat)) {
              /* review-only file inside hide-category dir — skip */
            } else {
              return true;
            }
          }
        } else if (subtreeHasFile(child, filter, owner)) {
          return true;
        }
      }
      i++;
      child = childList.children[i];
    }
    return false;
  };
  const processNode = function (node, filter, isRoot, owner) {
    if (node.getAttribute('data-node-type') === 'file') {
      const visible = fileMatches(node, filter) && ownerMatches(node, owner);
      node.hidden = !visible;
      return;
    }
    const details = node.firstElementChild;
    const childList = details ? details.querySelector('ul') : null;
    if (childList) {
      var i = 0;
      var child = childList.children[i];
      while (child) {
        if (child.classList && child.classList.contains('ht-tree-node')) {
          processNode(child, filter, false, owner);
        }
        i++;
        child = childList.children[i];
      }
    }
    var hasVisible = subtreeHasFile(node, filter, owner);
    if (!hasVisible && owner && owner !== node.getAttribute('data-owner')) {
      /* If an owner is selected and this dir is not that owner, don't show it even if it has matching files */
    }
    const treatAsRoot = isRoot && !node.classList.contains('ht-root-files-group');
    node.hidden = !(treatAsRoot || filter === 'all' || hasVisible);
  };
  const applyFilter = function (filter) {
    currentFilter = filter;
    buttons.forEach(function (button) {
      const active = button.getAttribute('data-tree-filter') === filter;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    var i = 0;
    var child = tree.children[i];
    while (child) {
      if (child.classList && child.classList.contains('ht-tree-node')) {
        processNode(child, filter, true, currentOwner);
      }
      i++;
      child = tree.children[i];
    }
  };
  const applyOwner = function (owner) {
    currentOwner = owner;
    applyFilter(currentFilter);
  };

  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      applyFilter(String(button.getAttribute('data-tree-filter') || 'all'));
    });
  });
  applyFilter(String(tree.getAttribute('data-default-filter') || 'hide-runtime'));

  if (ownerSelect) {
    ownerSelect.addEventListener('change', function () {
      applyOwner(String(ownerSelect.value || ''));
      renderEntities(String(ownerSelect.value || ''));
    });
  }

  /* Entity Explorer */
  var entitySection = document.querySelector('[data-entity-explorer]');
  var entityData = {};
  var entityRegistry = {};
  if (entitySection) {
    try {
      var raw = entitySection.getAttribute('data-entity-json') || '{}';
      entityData = JSON.parse(raw);
      var regRaw = entitySection.getAttribute('data-entity-registry') || '[]';
      entityRegistry = JSON.parse(regRaw);
    } catch (e) {
      entityData = {};
      entityRegistry = {};
    }
  }
  var entityTypeKeys = function () {
    var arr = [];
    for (var k in entityRegistry) {
      if (entityRegistry.hasOwnProperty(k)) {
        arr.push({ key: k, order: entityRegistry[k].sort_order || 99 });
      }
    }
    arr.sort(function (a, b) { return a.order - b.order; });
    return arr.map(function (o) { return o.key; });
  };
  var entityFilterType = 'all';
  var entityFilterButtons = [];
  var entityL = function (k) {
    return entitySection ? entitySection.getAttribute('data-label-' + k) || k : k;
  };
  var renderEntityLists = function (groups, filteredType) {
    var listsEl = entitySection ? entitySection.querySelector('[data-entity-lists]') : null;
    if (!listsEl) { return; }
    listsEl.innerHTML = '';
    var keys = entityTypeKeys();
    keys.forEach(function (t) {
      var items = groups[t] || [];
      if (items.length === 0) { return; }
      if (filteredType !== 'all' && filteredType !== t) { return; }
      var block = document.createElement('div');
      block.className = 'entity-list';
      var heading = document.createElement('h4');
      heading.textContent = entityL('type-' + t) + ' (' + items.length + ')';
      block.appendChild(heading);
      items.forEach(function (e) {
        var item = document.createElement('div');
        item.className = 'entity-item';
        var nameSpan = document.createElement('span');
        nameSpan.className = 'entity-item-name';
        nameSpan.textContent = e.name || '?';
        item.appendChild(nameSpan);
        var pathSpan = document.createElement('span');
        pathSpan.className = 'entity-item-path';
        pathSpan.textContent = e.source_path || '';
        pathSpan.setAttribute('data-inspector-path', e.source_path || '');
        pathSpan.title = 'Click to inspect: ' + (e.source_path || '');
        item.appendChild(pathSpan);
        var evidenceSpan = document.createElement('span');
        evidenceSpan.className = 'entity-item-evidence';
        evidenceSpan.textContent = e.evidence || '';
        item.appendChild(evidenceSpan);
        var badge = document.createElement('span');
        badge.className = 'entity-badge ' + (e.is_certain ? 'entity-badge-certain' : 'entity-badge-uncertain');
        badge.textContent = e.is_certain ? entityL('state-confirmed') : entityL('state-uncertain');
        item.appendChild(badge);
        block.appendChild(item);
      });
      listsEl.appendChild(block);
    });
  };
  var renderEntities = function (ownerKey) {
    var emptyEl = entitySection ? entitySection.querySelector('[data-entity-empty]') : null;
    var resultsEl = entitySection ? entitySection.querySelector('[data-entity-results]') : null;
    var summaryEl = entitySection ? entitySection.querySelector('[data-entity-summary]') : null;
    var listsEl = entitySection ? entitySection.querySelector('[data-entity-lists]') : null;
    if (!entitySection || !resultsEl || !summaryEl || !listsEl) { return; }
    if (!ownerKey) {
      if (emptyEl) { emptyEl.hidden = false; }
      resultsEl.hidden = true;
      return;
    }
    if (emptyEl) { emptyEl.hidden = true; }
    var entities = entityData[ownerKey];
    if (!entities || !Array.isArray(entities)) {
      resultsEl.hidden = true;
      if (emptyEl) { emptyEl.hidden = false; emptyEl.textContent = 'No entities discovered for this owner.'; }
      return;
    }
    var groups = {};
    entities.forEach(function (e) {
      var t = e.type || 'unknown';
      if (!groups[t]) { groups[t] = []; }
      groups[t].push(e);
    });
    var keys = entityTypeKeys();
    var counts = {};
    var total = 0;
    keys.forEach(function (t) { var c = (groups[t] || []).length; counts[t] = c; total += c; });
    entityFilterButtons = [];
    summaryEl.innerHTML = '';
    var allBtn = document.createElement('button');
    allBtn.className = 'entity-summary-card entity-filter-all' + (entityFilterType === 'all' ? ' is-active' : '');
    allBtn.setAttribute('data-entity-filter', 'all');
    allBtn.setAttribute('aria-pressed', entityFilterType === 'all' ? 'true' : 'false');
    allBtn.innerHTML = '<span class="entity-summary-num">' + total + '</span><span class="entity-summary-label">' + entityL('filter-all') + '</span>';
    summaryEl.appendChild(allBtn);
    entityFilterButtons.push(allBtn);
    keys.forEach(function (t) {
      var card = document.createElement('button');
      card.className = 'entity-summary-card' + (entityFilterType === t ? ' is-active' : '');
      card.setAttribute('data-entity-filter', t);
      card.setAttribute('aria-pressed', entityFilterType === t ? 'true' : 'false');
      card.innerHTML = '<span class="entity-summary-num">' + counts[t] + '</span><span class="entity-summary-label">' + entityL('type-' + t) + '</span>';
      summaryEl.appendChild(card);
      entityFilterButtons.push(card);
      card.addEventListener('click', function () {
        entityFilterType = t;
        updateEntityFilterUI();
        renderEntityLists(groups, entityFilterType);
      });
    });
    allBtn.addEventListener('click', function () {
      entityFilterType = 'all';
      updateEntityFilterUI();
      renderEntityLists(groups, entityFilterType);
    });
    renderEntityLists(groups, entityFilterType);
    resultsEl.hidden = false;
  };
  var updateEntityFilterUI = function () {
    entityFilterButtons.forEach(function (btn) {
      var ft = btn.getAttribute('data-entity-filter') || 'all';
      var active = ft === entityFilterType;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  };
  renderEntities('');
})();

(function () {
  const inspector = document.querySelector('[data-file-inspector]');
  const tree = document.querySelector('[data-helper-tree]');
  if (!inspector || !tree) {
    return;
  }
  const empty = inspector.querySelector('[data-file-inspector-empty]');
  const body = inspector.querySelector('[data-file-inspector-body]');
  const label = function (key) {
    return inspector.getAttribute('data-label-' + key) || key;
  };
  const formatBytes = function (bytes) {
    bytes = Number(bytes || 0);
    if (bytes < 1024) {
      return bytes + ' B';
    }
    const units = ['KB', 'MB', 'GB', 'TB'];
    let value = bytes / 1024;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
      value = value / 1024;
      unit++;
    }
    return value.toFixed(2) + ' ' + units[unit];
  };
  const text = function (value) {
    return Array.isArray(value) ? (value.length ? value.join(', ') : 'none') : String(value || 'none');
  };
  const addMeta = function (dl, name, value) {
    const dt = document.createElement('dt');
    const dd = document.createElement('dd');
    dt.textContent = name;
    dd.textContent = value;
    dl.appendChild(dt);
    dl.appendChild(dd);
  };
  const renderInspector = function (data) {
    body.innerHTML = '';
    body.hidden = false;
    if (empty) {
      empty.hidden = true;
    }
    if (!data || data.ok !== true) {
      const message = document.createElement('p');
      message.className = 'helper-panel-note';
      message.textContent = label('error');
      body.appendChild(message);
      return;
    }

    const dl = document.createElement('dl');
    addMeta(dl, label('path'), data.path || '');
    addMeta(dl, label('type'), data.type || '');
    addMeta(dl, label('size'), formatBytes(data.size));
    addMeta(dl, label('categories'), text(data.categories));
    addMeta(dl, label('reasons'), text(data.reasons));
    addMeta(dl, label('owner'), data.owner_guess || '');
    addMeta(dl, label('scope'), data.path_scope || '');
    addMeta(dl, label('owner-key'), data.owner_key || '—');
    body.appendChild(dl);

    const preview = data.preview || {};
    const title = document.createElement('h4');
    title.textContent = label('preview');
    body.appendChild(title);
    if (preview.is_text !== true) {
      const binary = document.createElement('p');
      binary.className = 'helper-panel-note';
      binary.textContent = label('binary');
      body.appendChild(binary);
      return;
    }
    if (preview.is_truncated === true) {
      const truncated = document.createElement('p');
      truncated.className = 'helper-panel-note';
      truncated.textContent = label('truncated') + ' (' + formatBytes(preview.max_bytes || 0) + ')';
      body.appendChild(truncated);
    }
    const table = document.createElement('table');
    table.className = 'helper-preview-table';
    const tbody = document.createElement('tbody');
    (preview.lines || []).forEach(function (line) {
      const tr = document.createElement('tr');
      const no = document.createElement('td');
      const content = document.createElement('td');
      no.className = 'helper-preview-line-no';
      content.className = 'helper-preview-line';
      no.textContent = String(line.line || '');
      content.textContent = String(line.text || '');
      tr.appendChild(no);
      tr.appendChild(content);
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    body.appendChild(table);
  };
  tree.addEventListener('click', function (event) {
    const button = event.target.closest('[data-inspector-path]');
    if (!button) {
      return;
    }
    const path = button.getAttribute('data-inspector-path') || '';
    fetch('/apps/studio/tools/helper-tool/inspect-file?path=' + encodeURIComponent(path), {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (response) { return response.json(); })
      .then(renderInspector)
      .catch(function () { renderInspector({ ok: false }); });
  });
})();

/* ===== Repository Search V1 ===== */
(function () {
  var section = document.querySelector('[data-ht-search-section]');
  if (!section) { return; }

  var input      = section.querySelector('[data-ht-search-input]');
  var hint       = section.querySelector('[data-ht-search-hint]');
  var resultsWrap = section.querySelector('[data-ht-search-results]');
  var countEl    = section.querySelector('[data-ht-search-count]');
  var listEl     = section.querySelector('[data-ht-search-result-list]');
  var chipsEl    = section.querySelector('[data-ht-search-chips]');
  var ownerCb    = section.querySelector('[data-ht-search-owner-filter]');
  if (!input || !resultsWrap || !countEl || !listEl || !chipsEl) { return; }

  var L = function (k) { return section.getAttribute('data-label-' + k) || k; };

  /* Load index from embedded JSON */
  var searchIndex = [];
  try {
    var raw = document.getElementById('ht-search-index-data');
    if (raw) { searchIndex = JSON.parse(raw.textContent || '[]'); }
  } catch (e) { searchIndex = []; }

  /* Badge class per result_type */
  var badgeClass = function (t) {
    var map = { file: 'ht-search-badge-file', owner: 'ht-search-badge-owner',
                entity: 'ht-search-badge-entity', route: 'ht-search-badge-route',
                workspace: 'ht-search-badge-workspace' };
    return map[t] || 'ht-search-badge-file';
  };

  /* Human type label */
  var typeLabel = function (t) {
    var map = { file: L('type-file'), owner: L('type-owner'),
                entity: L('type-entity'), route: L('type-route'),
                workspace: L('type-workspace') };
    return map[t] || t;
  };

  /* Active type filter for chips ('all' or a result_type string) */
  var activeType = 'all';

  /* Read currently selected owner from Owner Lens select */
  var selectedOwner = function () {
    var sel = document.querySelector('[data-owner-select]');
    return sel ? (sel.value || '') : '';
  };

  /* Scroll owner select to chosen value and fire change */
  var activateOwnerFilter = function (ownerKey) {
    var ownerSelect = document.querySelector('[data-owner-select]');
    if (!ownerSelect || !ownerKey) { return; }
    ownerSelect.value = ownerKey;
    var ev;
    try { ev = new Event('change', { bubbles: true }); }
    catch (e) { ev = document.createEvent('Event'); ev.initEvent('change', true, false); }
    ownerSelect.dispatchEvent(ev);
    ownerSelect.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };

  /* Trigger file inspector (reuses existing fetch + render pattern) */
  var inspectFilePath = function (path) {
    var inspector = document.querySelector('[data-file-inspector]');
    if (!inspector) { return; }
    fetch('/apps/studio/tools/helper-tool/inspect-file?path=' + encodeURIComponent(path), {
      method: 'GET', credentials: 'same-origin', headers: { 'Accept': 'application/json' }
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var body  = inspector.querySelector('[data-file-inspector-body]');
        var empty = inspector.querySelector('[data-file-inspector-empty]');
        if (!body) { return; }
        body.innerHTML = '';
        body.hidden = false;
        if (empty) { empty.hidden = true; }
        if (!data || data.ok !== true) {
          var msg = document.createElement('p');
          msg.className = 'helper-panel-note';
          msg.textContent = inspector.getAttribute('data-label-error') || 'Error';
          body.appendChild(msg);
          return;
        }
        var formatB = function (b) {
          b = Number(b || 0);
          if (b < 1024) { return b + ' B'; }
          var units = ['KB','MB','GB','TB'], v = b / 1024, u = 0;
          while (v >= 1024 && u < units.length - 1) { v /= 1024; u++; }
          return v.toFixed(2) + ' ' + units[u];
        };
        var lbl = function (k) { return inspector.getAttribute('data-label-' + k) || k; };
        var dl = document.createElement('dl');
        var addM = function (dl, name, value) {
          var dt = document.createElement('dt'), dd = document.createElement('dd');
          dt.textContent = name; dd.textContent = value;
          dl.appendChild(dt); dl.appendChild(dd);
        };
        addM(dl, lbl('path'),       data.path || '');
        addM(dl, lbl('type'),       data.type || '');
        addM(dl, lbl('size'),       formatB(data.size));
        var cats    = data.categories;
        var reasons = data.reasons;
        addM(dl, lbl('categories'), Array.isArray(cats)    ? (cats.length    ? cats.join(', ')    : 'none') : String(cats    || 'none'));
        addM(dl, lbl('reasons'),    Array.isArray(reasons) ? (reasons.length ? reasons.join(', ') : 'none') : String(reasons || 'none'));
        addM(dl, lbl('owner'),      data.owner_guess || '');
        addM(dl, lbl('scope'),      data.path_scope  || '');
        addM(dl, lbl('owner-key'),  data.owner_key   || '—');
        body.appendChild(dl);
        var preview = data.preview || {};
        var ptitle = document.createElement('h4');
        ptitle.textContent = lbl('preview');
        body.appendChild(ptitle);
        if (preview.is_text !== true) {
          var bin = document.createElement('p');
          bin.className = 'helper-panel-note';
          bin.textContent = lbl('binary');
          body.appendChild(bin);
          return;
        }
        if (preview.is_truncated === true) {
          var trunc = document.createElement('p');
          trunc.className = 'helper-panel-note';
          trunc.textContent = lbl('truncated') + ' (' + formatB(preview.max_bytes || 0) + ')';
          body.appendChild(trunc);
        }
        var tbl = document.createElement('table');
        tbl.className = 'helper-preview-table';
        var tbody = document.createElement('tbody');
        (preview.lines || []).forEach(function (line) {
          var tr = document.createElement('tr');
          var no = document.createElement('td'), content = document.createElement('td');
          no.className = 'helper-preview-line-no';
          content.className = 'helper-preview-line';
          no.textContent = String(line.line || '');
          content.textContent = String(line.text || '');
          tr.appendChild(no); tr.appendChild(content); tbody.appendChild(tr);
        });
        tbl.appendChild(tbody);
        body.appendChild(tbl);
        inspector.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      })
      .catch(function () {});
  };

  /* Render type-chips with per-type counts; clicking a chip filters results */
  var renderChips = function (typeCounts, total, filtered) {
    chipsEl.innerHTML = '';
    var types = ['file', 'owner', 'entity', 'route', 'workspace'];

    /* "All" chip */
    var allChip = document.createElement('button');
    allChip.type = 'button';
    allChip.className = 'ht-search-chip' + (activeType === 'all' ? ' is-active' : '');
    allChip.setAttribute('data-ht-chip', 'all');
    allChip.innerHTML = typeLabel('all') + ' <span class="ht-search-chip-count">' + (filtered !== undefined ? filtered : total) + '</span>';
    /* Override typeLabel for 'all' */
    allChip.firstChild.textContent = 'All ';
    allChip.addEventListener('click', function () { activeType = 'all'; renderResults(lastMatches); });
    chipsEl.appendChild(allChip);

    types.forEach(function (t) {
      var c = typeCounts[t] || 0;
      if (c === 0) { return; }
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'ht-search-chip ' + badgeClass(t) + (activeType === t ? ' is-active' : '');
      chip.setAttribute('data-ht-chip', t);
      chip.textContent = typeLabel(t) + ' ';
      var cnt = document.createElement('span');
      cnt.className = 'ht-search-chip-count';
      cnt.textContent = c;
      chip.appendChild(cnt);
      chip.addEventListener('click', function () {
        activeType = (activeType === t) ? 'all' : t;
        renderResults(lastMatches);
      });
      chipsEl.appendChild(chip);
    });
  };

  /* Keep last match set for chip re-renders */
  var lastMatches = [];

  /* Render result rows from a match set, filtered by activeType */
  var renderResults = function (matches) {
    lastMatches = matches;

    /* Compute per-type counts over full match set */
    var typeCounts = {};
    matches.forEach(function (item) {
      var t = item.result_type || 'file';
      typeCounts[t] = (typeCounts[t] || 0) + 1;
    });

    /* Apply type-chip filter */
    var visible = activeType === 'all' ? matches : matches.filter(function (item) {
      return item.result_type === activeType;
    });

    renderChips(typeCounts, matches.length, visible.length);

    countEl.textContent = visible.length + ' ' + L('count');
    listEl.innerHTML = '';

    if (visible.length === 0) {
      var none = document.createElement('p');
      none.className = 'helper-panel-note';
      none.setAttribute('data-ht-search-no-results', '');
      none.textContent = L('no-results');
      listEl.appendChild(none);
    } else {
      visible.forEach(function (item) {
        var row = document.createElement('div');
        row.className = 'ht-search-result';
        row.setAttribute('data-ht-search-result-type', item.result_type || '');

        /* Type badge */
        var badge = document.createElement('span');
        badge.className = 'ht-search-result-badge ' + badgeClass(item.result_type || '');
        badge.textContent = typeLabel(item.result_type || '');
        row.appendChild(badge);

        /* Body */
        var bodyDiv = document.createElement('div');
        bodyDiv.className = 'ht-search-result-body';

        var labelEl = document.createElement('span');
        labelEl.className = 'ht-search-result-label';
        labelEl.textContent = item.label || '';
        bodyDiv.appendChild(labelEl);

        if (item.sublabel) {
          var sub = document.createElement('span');
          sub.className = 'ht-search-result-sublabel';
          sub.textContent = item.sublabel;
          bodyDiv.appendChild(sub);
        }
        row.appendChild(bodyDiv);

        /* Action button */
        if (item.action === 'inspect_file' && item.path) {
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'ht-search-result-btn';
          btn.setAttribute('data-ht-search-inspect', item.path);
          btn.setAttribute('data-inspector-path', item.path);
          btn.textContent = L('action-inspect');
          btn.addEventListener('click', function () {
            inspectFilePath(btn.getAttribute('data-ht-search-inspect') || '');
          });
          var aw = document.createElement('div');
          aw.className = 'ht-search-result-action';
          aw.appendChild(btn);
          row.appendChild(aw);
        } else if (item.action === 'select_owner' && item.owner_key) {
          var obtn = document.createElement('button');
          obtn.type = 'button';
          obtn.className = 'ht-search-result-btn';
          obtn.setAttribute('data-ht-search-select-owner', item.owner_key);
          obtn.textContent = L('action-owner');
          obtn.addEventListener('click', function () {
            activateOwnerFilter(obtn.getAttribute('data-ht-search-select-owner') || '');
          });
          var oaw = document.createElement('div');
          oaw.className = 'ht-search-result-action';
          oaw.appendChild(obtn);
          row.appendChild(oaw);
        }

        listEl.appendChild(row);
      });
    }

    resultsWrap.hidden = false;
  };

  /* Perform search */
  var doSearch = function (query) {
    query = query.trim();

    if (query === '') {
      hint.hidden = false;
      resultsWrap.hidden = true;
      chipsEl.innerHTML = '';
      listEl.innerHTML = '';
      countEl.textContent = '';
      lastMatches = [];
      activeType = 'all';
      return;
    }

    hint.hidden = true;
    var q = query.toLowerCase();

    /* Owner-only filter: when checkbox checked and an owner is selected */
    var ownerOnly = ownerCb && ownerCb.checked;
    var activeOwner = selectedOwner();

    var matches = [];
    for (var i = 0; i < searchIndex.length; i++) {
      var item = searchIndex[i];
      var t = item.result_type || '';

      /* Owner-only scope: restrict file/entity/route to selected owner;
         owner + workspace results always pass through the scope filter */
      if (ownerOnly && activeOwner) {
        if (t === 'file' || t === 'entity' || t === 'route') {
          if ((item.owner_key || '') !== activeOwner) { continue; }
        }
        /* owner and workspace: only show if query matches (no suppression) */
      }

      var haystack = [
        (item.label    || '').toLowerCase(),
        (item.sublabel || '').toLowerCase(),
        (item.path     || '').toLowerCase(),
        (item.owner_key|| '').toLowerCase()
      ].join('\x00');

      if (haystack.indexOf(q) !== -1) {
        matches.push(item);
        if (matches.length >= 50) { break; }
      }
    }

    /* Sort: owners first, workspaces, entities/routes, files */
    var order = { owner: 0, workspace: 1, entity: 2, route: 3, file: 4 };
    matches.sort(function (a, b) {
      var oa = order[a.result_type] !== undefined ? order[a.result_type] : 5;
      var ob = order[b.result_type] !== undefined ? order[b.result_type] : 5;
      if (oa !== ob) { return oa - ob; }
      return (a.label || '').localeCompare(b.label || '');
    });

    renderResults(matches);
  };

  /* Re-run search when owner filter checkbox changes */
  if (ownerCb) {
    ownerCb.addEventListener('change', function () {
      if (input.value.trim() !== '') { doSearch(input.value); }
    });
  }

  /* Debounced input handler */
  var timer = 0;
  input.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(function () { doSearch(input.value); }, 160);
  });

  /* Clear on empty (native clear button / Escape) */
  input.addEventListener('search', function () {
    if (input.value === '') { doSearch(''); }
  });
})();

/* Owner ↔ EW cross-highlighting */
(function () {
  var ownerCards = document.querySelectorAll('[data-owner-card]');
  var ewSection = document.querySelector('[data-ew-section]');
  if (!ownerCards.length || !ewSection) { return; }

  var clearHighlights = function () {
    ownerCards.forEach(function (c) { c.classList.remove('ht-linked-active'); });
  };

  ownerCards.forEach(function (card) {
    card.addEventListener('click', function () {
      clearHighlights();
      var wsDd = card.querySelector('.helper-owner-ws');
      var ewKey = wsDd ? wsDd.getAttribute('data-ht-ew-key') : null;
      var linkedOwners = card.getAttribute('data-ht-linked-owners');
      if (ewKey && ewKey !== '') {
        /* Owner card clicked → highlight its EW card */
        ownerCards.forEach(function (c) {
          if (c.getAttribute('data-owner-card') === ewKey) {
            c.classList.add('ht-linked-active');
          }
        });
      }
      if (linkedOwners && linkedOwners !== '') {
        /* EW card clicked → highlight its linked owner cards */
        var owners = linkedOwners.split(',');
        ownerCards.forEach(function (c) {
          var ck = c.getAttribute('data-owner-card');
          if (ck && owners.indexOf(ck) !== -1) {
            c.classList.add('ht-linked-active');
          }
        });
      }
      /* If neither link, allow deselect */
      if ((!ewKey || ewKey === '') && (!linkedOwners || linkedOwners === '')) {
        /* single click on a non-linked card just clears */
      }
    });
  });

  /* Click outside a card to clear */
  document.addEventListener('click', function (e) {
    var card = e.target.closest('[data-owner-card]');
    if (!card) { clearHighlights(); }
  });
})();
</script>
