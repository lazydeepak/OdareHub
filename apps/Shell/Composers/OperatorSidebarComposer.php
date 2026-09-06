<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

final class OperatorSidebarComposer
{
    /**
     * @param array<string,mixed> $data
     */
    public static function render(array $data): string
    {
        $activeRoute = (string)($data['sidebar_active_route'] ?? '');
        ob_start();
        ?>
        <div class="app-sidebar" id="contextualSidebar">
            <div class="sidebar-header">
                <div class="sidebar-app-label">
                    <?php echo htmlspecialchars((string)($data['contextual_sidebar']['app_label'] ?? 'Workspace')); ?>
                </div>
            </div>
            <?php foreach ($data['contextual_sidebar']['sections'] ?? [] as $section): ?>
                <div class="sidebar-section">
                    <div class="sidebar-section-title">
                        <?php echo htmlspecialchars((string)($section['title'] ?? '')); ?>
                    </div>
                    <?php foreach ($section['items'] ?? [] as $item): ?>
                        <?php $itemRoute = (string)($item['route'] ?? '#'); $isActive = ($activeRoute !== '' && $itemRoute === $activeRoute); ?>
                        <a href="<?php echo htmlspecialchars($itemRoute); ?>" class="nav-item<?php if ($isActive): ?> is-active<?php endif; ?>"<?php if ($isActive): ?> aria-current="page"<?php endif; ?>>
                            <span class="nav-icon"><?php echo htmlspecialchars((string)($item['icon'] ?? '')); ?></span>
                            <span class="nav-label"><?php echo htmlspecialchars((string)($item['label'] ?? '')); ?></span>
                            <?php if (isset($item['badge']) && $item['badge'] > 0): ?>
                                <span class="nav-badge"><?php echo (int)$item['badge']; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }
}
