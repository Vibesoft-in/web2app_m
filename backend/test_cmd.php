<?php
require __DIR__ . '/vendor/autoload.php';

$workDir = __DIR__ . '/storage/app/test_png_check';
if (is_dir($workDir)) {
    shell_exec("rmdir /s /q \"$workDir\"");
}
mkdir($workDir . '/res/values', 0755, true);
mkdir($workDir . '/res/mipmap-hdpi', 0755, true);

file_put_contents($workDir . '/res/values/strings.xml', '<resources><string name="app_name">App</string></resources>');

// Try the 1x1 png from test_apk_build.php
$png1x1 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
file_put_contents($workDir . '/res/mipmap-hdpi/ic_launcher.png', $png1x1);

$aapt2 = 'C:\\Users\\megha\\AppData\\Local\\Android\\Sdk\\build-tools\\35.0.0\\aapt2.exe';
$cmd = "\"$aapt2\" compile --dir \"$workDir/res\" -o \"$workDir/res.zip\" 2>&1";
exec($cmd, $out, $ret);
echo "PNG 1x1 Ret: $ret\n";
echo implode("\n", $out) . "\n";
