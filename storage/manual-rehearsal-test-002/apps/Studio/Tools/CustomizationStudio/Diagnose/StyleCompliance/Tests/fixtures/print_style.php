<?php
/**
 * Print style fixture — contains @media print rules.
 * The filename matches /print/i so is_print_style should be true.
 */
?>
<style>
@media print {
    body {
        color: #000;
        background-color: #fff;
    }
    .no-print {
        display: none;
    }
    .print-only {
        display: block;
        font-size: 12px;
    }
}
</style>

<div style="color: #333; background-color: #f5f5f5;">Visible on screen only</div>
