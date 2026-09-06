<?php
// Operator Layer View: preferences
// Workspace display and notification preferences.
// All composer scope variables available via include.
?>
<?php
    $data         = isset($data) && is_array($data) ? $data : [];
    $prefs        = (array)($data['preferences_focus'] ?? \Apps\Shell\Services\OperatorPreferencesService::DEFAULTS);
    $prefUser     = rawurlencode((string)($data['username'] ?? ''));
    $prefSaved    = (isset($_GET['saved']) && $_GET['saved'] === '1');
    $prefFormErr  = htmlspecialchars(trim((string)($_GET['error'] ?? '')));
    $prefCsrf     = (string)\App\Core\Auth::csrfToken();

    $curDateRange  = (string)($prefs['date_range']         ?? 'today');
    $curRefresh    = (string)($prefs['refresh_rate']        ?? '0');
    $curNotif      = (string)($prefs['notification_level']  ?? 'all');
    $curDefaultView = (string)($prefs['default_view']       ?? 'dashboard');
    $curTheme      = \Apps\Shell\Services\ThemePreferenceService::normalizePreference((string)($prefs['theme'] ?? ''));

    $dateRangeOptions = [
        'today' => $this->tr('operator.prefs.date_range.today',  'Today only'),
        '7d'    => $this->tr('operator.prefs.date_range.7d',     'Last 7 days'),
        '30d'   => $this->tr('operator.prefs.date_range.30d',    'Last 30 days'),
    ];
    $refreshOptions = [
        '0'   => $this->tr('operator.prefs.refresh.off',   'Off (manual)'),
        '60'  => $this->tr('operator.prefs.refresh.60',    '1 minute'),
        '300' => $this->tr('operator.prefs.refresh.300',   '5 minutes'),
        '600' => $this->tr('operator.prefs.refresh.600',   '10 minutes'),
    ];
    $notifOptions = [
        'all'      => $this->tr('operator.prefs.notif.all',      'All notifications'),
        'critical' => $this->tr('operator.prefs.notif.critical', 'Critical only'),
        'none'     => $this->tr('operator.prefs.notif.none',     'None'),
    ];
    $defaultViewOptions = [
        'dashboard'  => $this->tr('operator.prefs.view.dashboard',  'Dashboard'),
        'production' => $this->tr('operator.prefs.view.production',  'Production'),
        'dispatch'   => $this->tr('operator.prefs.view.dispatch',    'Dispatch'),
        'machines'   => $this->tr('operator.prefs.view.machines',    'Machines'),
        'coverage'   => $this->tr('operator.prefs.view.coverage',    'Coverage'),
        'materials'  => $this->tr('operator.prefs.view.materials',   'Materials'),
        'handoff'    => $this->tr('operator.prefs.view.handoff',     'Shift Handoff'),
    ];
?>
<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars($this->tr('operator.prefs.page_title', 'Preferences')); ?>">

    <!-- Page header -->
    <div class="coverage-focus-header">
        <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('operator.prefs.page_title', 'Preferences')); ?></h2>
    </div>

    <!-- Flash states -->
    <?php if ($prefSaved): ?>
        <p class="coverage-focus-saved"><?php echo htmlspecialchars($this->tr('operator.prefs.saved', 'Preferences saved.')); ?></p>
    <?php endif; ?>
    <?php if ($prefFormErr !== ''): ?>
        <p class="coverage-focus-error"><?php echo $prefFormErr; ?></p>
    <?php endif; ?>

    <!-- Preferences form -->
    <div class="prefs-form-container">
        <form method="post" action="<?php echo htmlspecialchars('/u/' . $prefUser . '/preferences/save'); ?>" class="prefs-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">

            <!-- Date range preference -->
            <fieldset class="prefs-fieldset">
                <legend class="prefs-legend"><?php echo htmlspecialchars($this->tr('operator.prefs.date_range.label', 'Default date range')); ?></legend>
                <p class="prefs-hint"><?php echo htmlspecialchars($this->tr('operator.prefs.date_range.hint', 'How many days of data to show by default across workspace views.')); ?></p>
                <div class="prefs-radio-group" role="radiogroup">
                    <?php foreach ($dateRangeOptions as $val => $label): ?>
                        <label class="prefs-radio-label">
                            <input type="radio" name="date_range" value="<?php echo htmlspecialchars($val); ?>"<?php echo ($curDateRange === $val ? ' checked' : ''); ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <!-- Auto-refresh preference -->
            <fieldset class="prefs-fieldset">
                <legend class="prefs-legend"><?php echo htmlspecialchars($this->tr('operator.prefs.refresh.label', 'Auto-refresh interval')); ?></legend>
                <p class="prefs-hint"><?php echo htmlspecialchars($this->tr('operator.prefs.refresh.hint', 'Automatically reload this page at a regular interval.')); ?></p>
                <div class="prefs-radio-group" role="radiogroup">
                    <?php foreach ($refreshOptions as $val => $label): ?>
                        <label class="prefs-radio-label">
                            <input type="radio" name="refresh_rate" value="<?php echo htmlspecialchars($val); ?>"<?php echo ($curRefresh === $val ? ' checked' : ''); ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <!-- Notification level preference -->
            <fieldset class="prefs-fieldset">
                <legend class="prefs-legend"><?php echo htmlspecialchars($this->tr('operator.prefs.notif.label', 'Notification level')); ?></legend>
                <p class="prefs-hint"><?php echo htmlspecialchars($this->tr('operator.prefs.notif.hint', 'Which alert types to display in the notifications rail.')); ?></p>
                <div class="prefs-radio-group" role="radiogroup">
                    <?php foreach ($notifOptions as $val => $label): ?>
                        <label class="prefs-radio-label">
                            <input type="radio" name="notification_level" value="<?php echo htmlspecialchars($val); ?>"<?php echo ($curNotif === $val ? ' checked' : ''); ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <!-- Default view preference -->
            <fieldset class="prefs-fieldset">
                <legend class="prefs-legend"><?php echo htmlspecialchars($this->tr('operator.prefs.view.label', 'Default view')); ?></legend>
                <p class="prefs-hint"><?php echo htmlspecialchars($this->tr('operator.prefs.view.hint', 'The view shown when you first open the workspace.')); ?></p>
                <div class="prefs-select-group">
                    <select name="default_view" class="prefs-select" aria-label="<?php echo htmlspecialchars($this->tr('operator.prefs.view.label', 'Default view')); ?>">
                        <?php foreach ($defaultViewOptions as $val => $label): ?>
                            <option value="<?php echo htmlspecialchars($val); ?>"<?php echo ($curDefaultView === $val ? ' selected' : ''); ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </fieldset>

            <!-- Theme preference -->
            <fieldset class="prefs-fieldset">
                <legend class="prefs-legend"><?php echo htmlspecialchars($this->tr('operator.prefs.theme.label', 'Display theme')); ?></legend>
                <p class="prefs-hint"><?php echo htmlspecialchars($this->tr('operator.prefs.theme.hint', 'Sets your colour scheme and dark/light mode. Takes effect immediately after saving.')); ?></p>
                <div class="prefs-select-group">
                    <select name="theme" class="prefs-select" aria-label="<?php echo htmlspecialchars($this->tr('operator.prefs.theme.label', 'Display theme')); ?>">
                        <?php foreach (\Apps\Shell\Services\ThemePreferenceService::themeChoices() as $themeVal => $themeLabel): ?>
                            <option value="<?php echo htmlspecialchars($themeVal); ?>"<?php echo ($curTheme === $themeVal ? ' selected' : ''); ?>><?php echo htmlspecialchars($themeLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </fieldset>

            <!-- Realtime Updates preference -->
            <fieldset class="prefs-fieldset">
                <legend class="prefs-legend"><?php echo htmlspecialchars($this->tr('operator.prefs.realtime.label', 'Real-time updates')); ?></legend>
                <p class="prefs-hint"><?php echo htmlspecialchars($this->tr('operator.prefs.realtime.hint', 'Enable WebSocket for instant KPI updates. Falls back to auto-refresh if unavailable.')); ?></p>
                <div class="prefs-radio-group" role="radiogroup">
                    <label class="prefs-radio-label">
                        <input type="radio" name="realtime_enabled" value="1"<?php echo (($prefs['realtime_enabled'] ?? 1) ? ' checked' : ''); ?>>
                        <?php echo htmlspecialchars($this->tr('operator.prefs.realtime.enabled', 'Enabled (live WebSocket)')); ?>
                    </label>
                    <label class="prefs-radio-label">
                        <input type="radio" name="realtime_enabled" value="0"<?php echo (!($prefs['realtime_enabled'] ?? 1) ? ' checked' : ''); ?>>
                        <?php echo htmlspecialchars($this->tr('operator.prefs.realtime.disabled', 'Disabled (auto-refresh only)')); ?>
                    </label>
                </div>
                <?php if (($prefs['realtime_enabled'] ?? 1)): ?>
                <div class="prefs-realtime-test">
                    <button type="button" class="btn-secondary" id="testRealtimeBtn" data-username="<?php echo htmlspecialchars($prefUser); ?>">
                        <?php echo htmlspecialchars($this->tr('operator.prefs.realtime.test', 'Test Connection')); ?>
                    </button>
                    <span id="realtimeTestStatus" class="realtime-test-status"></span>
                </div>
                <?php endif; ?>
            </fieldset>

            <div class="prefs-actions">
                <button type="submit" class="btn-primary"><?php echo htmlspecialchars($this->tr('operator.prefs.save_button', 'Save preferences')); ?></button>
            </div>
        </form>
    </div>

    <?php
    // Effective workspace profile read-only card
    $__wp = is_array($data['workspace_profile'] ?? null) ? $data['workspace_profile'] : null;
    if ($__wp !== null):
    ?>
    <div class="prefs-form-container prefs-profile-container">
        <div class="prefs-profile-card">
            <div class="prefs-profile-header">
                <strong class="prefs-profile-title"><?php echo htmlspecialchars($this->tr('operator.prefs.workspace_profile.title', 'Active Workspace Profile')); ?></strong>
                <?php if (!empty($__wp['is_pinned'])): ?>
                    <span class="prefs-profile-badge"><?php echo htmlspecialchars($this->tr('operator.prefs.workspace_profile.pinned', 'Pinned')); ?></span>
                <?php endif; ?>
            </div>
            <div class="prefs-profile-grid">
                <div class="ui-block">
                    <div class="prefs-profile-label"><?php echo htmlspecialchars($this->tr('operator.prefs.workspace_profile.key', 'Profile')); ?></div>
                    <code class="prefs-profile-code"><?php echo htmlspecialchars((string)($__wp['profile_key'] ?? '')); ?></code>
                </div>
                <div class="ui-block">
                    <div class="prefs-profile-label"><?php echo htmlspecialchars($this->tr('operator.prefs.workspace_profile.name', 'Name')); ?></div>
                    <span><?php echo htmlspecialchars((string)($__wp['name'] ?? '')); ?></span>
                </div>
                <div class="ui-block">
                    <div class="prefs-profile-label"><?php echo htmlspecialchars($this->tr('operator.prefs.workspace_profile.landing', 'Landing')); ?></div>
                    <code class="prefs-profile-code"><?php echo htmlspecialchars((string)($__wp['landing_route'] ?? '')); ?></code>
                </div>
                <div class="ui-block">
                    <div class="prefs-profile-label"><?php echo htmlspecialchars($this->tr('operator.prefs.workspace_profile.widgets', 'Widgets')); ?></div>
                    <span><?php echo htmlspecialchars((string)($__wp['widget_discovery'] ?? 'auto')); ?></span>
                </div>
            </div>
            <div class="prefs-profile-note"><?php echo htmlspecialchars($this->tr('operator.prefs.workspace_profile.note', 'Managed by your administrator. Contact your admin to change your workspace profile.')); ?></div>
        </div>
    </div>
    <?php endif; unset($__wp); ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const testBtn = document.getElementById('testRealtimeBtn');
        const statusSpan = document.getElementById('realtimeTestStatus');
        
        if (!testBtn) return;

        testBtn.addEventListener('click', function() {
            const username = testBtn.getAttribute('data-username');
            testRealtimeConnection(username, statusSpan);
        });

        function testRealtimeConnection(username, statusElement) {
            statusElement.textContent = '<?php echo $this->tr('operator.prefs.realtime.testing', 'Testing...'); ?>';
            statusElement.className = 'realtime-test-status testing';

            const wsProtocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
            const params = new URLSearchParams({
                auth: <?php echo json_encode($prefCsrf, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
                username,
                view: 'dashboard',
            });
            const wsUrl = `${wsProtocol}//${location.hostname}:8001/operator/${encodeURIComponent(username)}/dashboard?${params.toString()}`;
            const startTime = Date.now();

            try {
                const ws = new WebSocket(wsUrl);

                ws.onopen = function() {
                    const latency = Date.now() - startTime;
                    statusElement.textContent = `✓ <?php echo $this->tr('operator.prefs.realtime.connected', 'Connected'); ?> (${latency}ms)`;
                    statusElement.className = 'realtime-test-status success';
                    ws.close();
                };

                ws.onerror = function() {
                    statusElement.textContent = '✗ <?php echo $this->tr('operator.prefs.realtime.error', 'Connection failed'); ?>';
                    statusElement.className = 'realtime-test-status error';
                };

                ws.onclose = function() {
                    if (!statusElement.textContent.includes('✓')) {
                        statusElement.textContent = '✗ <?php echo $this->tr('operator.prefs.realtime.unavailable', 'Server unavailable'); ?>';
                        statusElement.className = 'realtime-test-status error';
                    }
                };

                setTimeout(function() {
                    if (ws.readyState === WebSocket.CONNECTING) {
                        ws.close();
                        statusElement.textContent = '✗ <?php echo $this->tr('operator.prefs.realtime.timeout', 'Connection timeout'); ?>';
                        statusElement.className = 'realtime-test-status error';
                    }
                }, 5000);

            } catch (error) {
                statusElement.textContent = `✗ <?php echo $this->tr('operator.prefs.realtime.error', 'Connection failed'); ?>: ${error.message}`;
                statusElement.className = 'realtime-test-status error';
            }
        }
    });
    </script>

</section>
