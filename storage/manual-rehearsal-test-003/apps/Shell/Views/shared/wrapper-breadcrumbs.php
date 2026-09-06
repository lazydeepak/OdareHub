<?php
/**
 * Shared Wrapper Breadcrumbs Template
 *
 * Variables (from wrapper composer scope):
 *   $wrapper — LayerWrapperComposer instance
 *   $breadcrumbsHTML — Pre-rendered breadcrumbs HTML from wrapper->renderBreadcrumbs()
 */

if (!isset($wrapper) || !isset($breadcrumbsHTML)) {
    return;
}

echo $breadcrumbsHTML;
