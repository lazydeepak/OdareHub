<?php
// Operator Layer View: account
// All composer scope variables available via include.
?>
<section class="surface-card" aria-label="My Account">
                        <h2 class="surface-title"><?php echo htmlspecialchars($this->tr('common.my_account', 'My Account')); ?></h2>
                        <?php echo $this->renderAccountPanelMarkup((array)($data['account_panel'] ?? [])); ?>
                    </section>
