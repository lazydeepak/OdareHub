<div class="panel" style="background-color: <?php echo $bgColor ?? '#fff'; ?>; color: #333;">
    <span style="color: <?= $textColor ?>;">Dynamic text</span>
</div>

<style>
.panel {
    border: 1px solid #ddd;
    border-radius: <?php echo $radius ?? '8px'; ?>;
}
.panel-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}
</style>
