<?php
/**
 * Shared Wrapper Header Template
 *
 * Variables (from wrapper composer scope):
 *   $wrapper — LayerWrapperComposer instance
 *   $headerHTML — Pre-rendered header HTML from wrapper->renderHeader()
 */

if (!isset($wrapper) || !isset($headerHTML)) {
    return;
}

echo $headerHTML;
