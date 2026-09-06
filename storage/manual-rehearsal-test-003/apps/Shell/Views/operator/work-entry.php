<?php
// Operator Layer View: work-entry
// All composer scope variables available via include.
?>
<section class="surface-card work-entry-embed-card" aria-label="Work Entry">
    <?php
        $weQuery = [];
        if (isset($currentQuery['action']) && trim((string)$currentQuery['action']) !== '') {
            $weQuery['action'] = (string)$currentQuery['action'];
        }
        if (isset($currentQuery['type']) && trim((string)$currentQuery['type']) !== '') {
            $weQuery['type'] = (string)$currentQuery['type'];
        }
    ?>
    <div class="work-entry-embed-head">
        <div class="work-entry-embed-title-wrap">
            <div class="work-entry-embed-title"><?php echo htmlspecialchars($this->tr('operator.work_entry.title', 'Work Entry Form')); ?></div>
            <div class="work-entry-embed-subtitle"><?php echo htmlspecialchars($this->tr('operator.work_entry.subtitle', 'Add, update, approve, receive, and report work from one place.')); ?></div>
        </div>
        <a class="work-entry-embed-link" href="/u/<?php echo urlencode((string)$data['username']); ?>/dashboard"><?php echo htmlspecialchars($this->tr('operator.work_entry.back_to_dashboard', 'Back to Dashboard')); ?></a>
    </div>
    <?php echo \Apps\Shell\Composers\WorkEntryComposer::renderFragment((string)($data['username'] ?? ''), (array)$this->context, $weQuery); ?>
</section>
