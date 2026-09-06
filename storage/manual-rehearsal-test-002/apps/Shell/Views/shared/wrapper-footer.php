<?php
/**
 * Shared Wrapper Footer Template
 *
 * Variables (from wrapper composer scope):
 *   $wrapper — LayerWrapperComposer instance
 *   $footerHTML — Pre-rendered footer HTML from wrapper->renderFooter()
 */

if (!isset($wrapper) || !isset($footerHTML)) {
    return;
}

echo $footerHTML;
