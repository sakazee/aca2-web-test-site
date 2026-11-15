<?php
declare(strict_types=1);

/*
 * Laravel -> shared hosting deploy script (PHP only, no shell needed)
 *
 * What it does:
 *  1. Copies everything inside <app_path>/public to <public_target> (public_html),
 *     skipping unchanged files.
 *  2. Rewrites public_html/index.php so it loads vendor/ and bootstrap/ from
 *     <app_path> (outside the web root).
 *  3. Tries to create the public_html/storage symlink.
 *
 * Run in browser:  https://yourdomain.com/deploy.php?key=YOUR_TOKEN
 * Or via cPanel Cron Job:  /usr/local/bin/php /home/CPANELUSER/deploy.php
 */

// ===================== CONFIG =====================
$config = [
    // Laravel project folder (outside public_html). Must contain vendor/ and public/
    'app_path'      => '/home/amarchokhamaralo/repositories/aca2-web-test-site',
    // The web root
    'public_target' => '/home/amarchokhamaralo/repositories/aca2-web-test-site-final',
    // Long random string. Required when running from the browser.
    'token'         => 'LKWJDOY&*(W*EYDKWDYS&DYy78qdst7s5rKHGYJKLSHK',
    // Top-level items in <app>/public that must NOT be copied
    'skip'          => ['index.php', 'hot', 'storage'],
    // true = only show what would happen
    'dry_run'       => false,
];
// ==================================================

header('Content-Type: text/plain; charset=utf-8');

// Access control (browser only)
if (PHP_SAPI !== 'cli') {
    $key = (string) ($_GET['key'] ?? '');
    if ($config['token'] === '' || !hash_equals($config['token'], $key)) {
        http_response_code(403);
        exit("Forbidden\n");
    }
}

$app    = rtrim($config['app_path'], '/');
$target = rtrim($config['public_target'], '/');
$dry    = (bool) $config['dry_run'];

function out(string $msg): void
{
    echo $msg . "\n";
}

function fail(string $msg): never
{
    out('ERROR: ' . $msg);
    exit(1);
}

// Sanity checks
if (!is_dir($app . '/public'))              fail("Not found: $app/public");
if (!is_file($app . '/vendor/autoload.php')) fail("Not found: $app/vendor/autoload.php (vendor folder missing)");
if (!is_file($app . '/bootstrap/app.php'))   fail("Not found: $app/bootstrap/app.php");
if (!is_dir($target) && !$dry && !mkdir($target, 0755, true)) fail("Cannot create $target");
if (realpath($target) === realpath($app . '/public')) fail('public_target and app public folder are the same.');

out($dry ? '--- DRY RUN ---' : '--- DEPLOY ---');

// 1) Copy public files
$copied = 0;
$same   = 0;

function syncDir(string $src, string $dst, array $skip, bool $dry, int &$copied, int &$same): void
{
    if (!is_dir($dst) && !$dry) {
        mkdir($dst, 0755, true);
    }
    foreach (scandir($src) ?: [] as $item) {
        if ($item === '.' || $item === '..' || in_array($item, $skip, true)) {
            continue;
        }
        $s = $src . '/' . $item;
        $d = $dst . '/' . $item;

        if (is_dir($s)) {
            syncDir($s, $d, [], $dry, $copied, $same);
            continue;
        }
        if (is_file($d) && filesize($s) === filesize($d) && md5_file($s) === md5_file($d)) {
            $same++;
            continue;
        }
        if (!$dry && !copy($s, $d)) {
            out("FAILED : $d");
            continue;
        }
        $copied++;
        out("copied : $d");
    }
}

syncDir($app . '/public', $target, $config['skip'], $dry, $copied, $same);
out("Files copied: $copied, unchanged: $same");

// 2) Patch index.php so it points to the app folder
$source = file_get_contents($app . '/public/index.php');
if ($source === false) fail('Cannot read the app public/index.php');

$safePath = addcslashes($app, "'\\");
$patched  = preg_replace_callback(
    "~__DIR__\s*\.\s*'/\.\./~",
    static fn(array $m): string => "'" . $safePath . '/',
    $source,
    -1,
    $count
);

if ($patched === null || $count === 0) {
    fail("Could not find __DIR__.'/../ paths in public/index.php to rewrite.");
}

$indexFile = $target . '/index.php';
if (is_file($indexFile) && file_get_contents($indexFile) === $patched) {
    out('index.php: already up to date');
} else {
    if (!$dry && file_put_contents($indexFile, $patched) === false) fail("Cannot write $indexFile");
    out("index.php: written ($count paths rewritten)");
}

// 3) storage symlink
$link = $target . '/storage';
$real = $app . '/storage/app/public';
if (is_dir($real) && !file_exists($link) && !is_link($link)) {
    if ($dry) {
        out('storage: would try to create symlink');
    } elseif (function_exists('symlink') && @symlink($real, $link)) {
        out('storage: symlink created');
    } else {
        out('storage: symlink could not be created (disabled on this host?)');
    }
}

out('Done.');
