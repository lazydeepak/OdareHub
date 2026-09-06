<?php
/**
 * Shared Wrapper Sidebar Template
 *
 * Variables (from wrapper composer scope):
 *   $wrapper — LayerWrapperComposer instance
 *   $sidebarHTML — Pre-rendered sidebar HTML from wrapper->renderSidebar()
 */

if (!isset($wrapper) || !isset($sidebarHTML)) {
    return;
}

echo $sidebarHTML;
