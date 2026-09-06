<?php
declare(strict_types=1);

$root = sys_get_temp_dir() . '/studio-postlude-probe-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);
file_put_contents($root . '/tool_placeholder_renderer.php', "<?php echo 'BASE';\n");
file_put_contents($root . '/preview.php', "<?php echo 'TOOL';\n");
file_put_contents($root . '/preview.postlude.php', <<<'PHP'
<?php echo '+IMPACT'; $impactWorkspace = ['assessment' => ['status' => 'ok']];
PHP
);
file_put_contents($root . '/preview.postlude.deletion-plan.php', <<<'PHP'
<?php echo isset($impactWorkspace['assessment']) ? '+PLAN' : '+NO-IMPACT';
PHP
);
file_put_contents($root . '/preview.postlude.z-last.php', "<?php echo '+LAST';\n");

$wrapper = file_get_contents(dirname(__DIR__) . '/Views/pages/tool_placeholder.php');
$rendererPath = dirname(__DIR__) . '/Views/pages/tool_placeholder_renderer.php';
$probeWrapper = $root . '/tool_placeholder.php';
if (!is_string($wrapper)) {
    echo "FAIL: wrapper unreadable\n";
    exit(1);
}
$wrapper = str_replace("__DIR__ . '/tool_placeholder_renderer.php'", var_export($root . '/tool_placeholder_renderer.php', true), $wrapper);
file_put_contents($probeWrapper, $wrapper);

$toolTemplatePath = $root . '/preview.php';
ob_start();
require $probeWrapper;
$output = (string)ob_get_clean();

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) { $passes++; echo "PASS: {$message}\n"; return; }
    $fails++; echo "FAIL: {$message}\n";
};
$assert($output === 'BASE+IMPACT+PLAN+LAST', 'primary and additional postludes load in deterministic order');
$assert(is_file($rendererPath), 'canonical renderer remains present');
$assert(str_contains($wrapper, '.postlude.*.php'), 'wrapper discovers composable postludes');

foreach (glob($root . '/*') ?: [] as $file) { @unlink($file); }
@rmdir($root);

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
