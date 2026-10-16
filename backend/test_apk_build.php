<?php

require __DIR__ . '/vendor/autoload.php';

$sdkDir = 'C:\\Users\\megha\\AppData\\Local\\Android\\Sdk';
$buildToolsDir = $sdkDir . '\\build-tools\\35.0.0';
$androidJar = $sdkDir . '\\platforms\\android-36\\android.jar';
$jdkBin = 'C:\\Program Files\\Java\\jdk-24\\bin';

$aapt2 = $buildToolsDir . '\\aapt2.exe';
$d8 = $buildToolsDir . '\\d8.bat';
$zipalign = $buildToolsDir . '\\zipalign.exe';
$apksigner = $buildToolsDir . '\\apksigner.bat';
$javac = $jdkBin . '\\javac.exe';
$keytool = $jdkBin . '\\keytool.exe';

$workDir = __DIR__ . '/storage/app/test_apk_work';
if (is_dir($workDir)) {
    shell_exec("rmdir /s /q \"$workDir\"");
}
mkdir($workDir . '/res/values', 0755, true);
mkdir($workDir . '/res/mipmap-hdpi', 0755, true);
mkdir($workDir . '/src/com/store/app', 0755, true);
mkdir($workDir . '/bin', 0755, true);
mkdir($workDir . '/dex', 0755, true);

// 1. Strings
file_put_contents($workDir . '/res/values/strings.xml', '<?xml version="1.0" encoding="utf-8"?>
<resources>
    <string name="app_name">Website Development</string>
</resources>');

// 2. Icon (minimal 1x1 or sample PNG)
// 1x1 transparent PNG bytes
$png1x1 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
file_put_contents($workDir . '/res/mipmap-hdpi/ic_launcher.png', $png1x1);

// 3. AndroidManifest.xml
file_put_contents($workDir . '/AndroidManifest.xml', '<?xml version="1.0" encoding="utf-8"?>
<manifest xmlns:android="http://schemas.android.com/apk/res/android"
    package="com.store.app"
    android:versionCode="1"
    android:versionName="1.0.0">

    <uses-permission android:name="android.permission.INTERNET" />
    <uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />

    <application
        android:label="@string/app_name"
        android:icon="@mipmap/ic_launcher"
        android:usesCleartextTraffic="true"
        android:theme="@android:style/Theme.NoTitleBar">

        <activity
            android:name=".MainActivity"
            android:exported="true"
            android:configChanges="orientation|screenSize|keyboardHidden">
            <intent-filter>
                <action android:name="android.intent.action.MAIN" />
                <category android:name="android.intent.category.LAUNCHER" />
            </intent-filter>
        </activity>
    </application>
</manifest>');

// 4. Java source
file_put_contents($workDir . '/src/com/store/app/MainActivity.java', 'package com.store.app;

import android.app.Activity;
import android.os.Bundle;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.view.KeyEvent;

public class MainActivity extends Activity {
    private WebView webView;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        
        webView = new WebView(this);
        setContentView(webView);

        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(true);
        settings.setUserAgentString(settings.getUserAgentString() + " WebIntoApp/1.0");

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                view.loadUrl(url);
                return true;
            }
        });

        webView.loadUrl("https://google.com");
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        if ((keyCode == KeyEvent.KEYCODE_BACK) && webView.canGoBack()) {
            webView.goBack();
            return true;
        }
        return super.onKeyDown(keyCode, event);
    }
}');

echo "Starting compilation...\n";

// Step A: aapt2 compile
$cmd = "\"$aapt2\" compile --dir \"$workDir/res\" -o \"$workDir/res.zip\"";
exec($cmd, $out, $ret);
if ($ret !== 0) {
    die("aapt2 compile failed: " . implode("\n", $out));
}
echo "aapt2 compile OK\n";

// Step B: aapt2 link
$out = [];
$cmd = "\"$aapt2\" link -I \"$androidJar\" \"$workDir/res.zip\" --manifest \"$workDir/AndroidManifest.xml\" -o \"$workDir/unaligned.apk\" --auto-add-overlay";
exec($cmd, $out, $ret);
if ($ret !== 0) {
    die("aapt2 link failed: " . implode("\n", $out));
}
echo "aapt2 link OK\n";

// Step C: javac
$out = [];
$cmd = "\"$javac\" -cp \"$androidJar\" -source 8 -target 8 -d \"$workDir/bin\" \"$workDir/src/com/store/app/MainActivity.java\"";
exec($cmd, $out, $ret);
if ($ret !== 0) {
    die("javac failed: " . implode("\n", $out));
}
echo "javac OK\n";

// Step D: d8
$out = [];
$cmd = "\"$d8\" --output \"$workDir/dex\" \"$workDir/bin/com/store/app/MainActivity.class\" \"$workDir/bin/com/store/app/MainActivity$1.class\"";
exec($cmd, $out, $ret);
if ($ret !== 0) {
    die("d8 failed: " . implode("\n", $out));
}
echo "d8 OK\n";

// Step E: Insert classes.dex into unaligned.apk
$zip = new ZipArchive();
if ($zip->open("$workDir/unaligned.apk") === true) {
    $zip->addFile("$workDir/dex/classes.dex", "classes.dex");
    $zip->close();
    echo "classes.dex injected into APK OK\n";
} else {
    die("Failed to open unaligned.apk as zip\n");
}

// Step F: zipalign
$out = [];
$cmd = "\"$zipalign\" -p -f 4 \"$workDir/unaligned.apk\" \"$workDir/aligned.apk\"";
exec($cmd, $out, $ret);
if ($ret !== 0) {
    die("zipalign failed: " . implode("\n", $out));
}
echo "zipalign OK\n";

// Step G: Generate debug keystore if not exists
$keystorePath = __DIR__ . '/storage/app/debug.keystore';
if (!file_exists($keystorePath)) {
    $cmd = "\"$keytool\" -genkeypair -v -keystore \"$keystorePath\" -alias androiddebugkey -keyalg RSA -keysize 2048 -validity 10000 -storepass android -keypass android -dname \"CN=Android Debug,O=Android,C=US\"";
    exec($cmd);
}

// Step H: apksigner
$out = [];
$finalApk = "$workDir/final_signed.apk";
$cmd = "\"$apksigner\" sign --ks \"$keystorePath\" --ks-pass pass:android --key-pass pass:android --out \"$finalApk\" \"$workDir/aligned.apk\"";
exec($cmd, $out, $ret);
if ($ret !== 0) {
    die("apksigner failed: " . implode("\n", $out));
}
echo "apksigner OK! Final APK generated at: $finalApk (" . filesize($finalApk) . " bytes)\n";

// Verify with apksigner verify
$out = [];
$cmd = "\"$apksigner\" verify --verbose \"$finalApk\"";
exec($cmd, $out, $ret);
echo "Verification output:\n" . implode("\n", $out) . "\n";
