<?php
declare(strict_types=1);

// Change to your own timezone, e.g. 'Asia/Dhaka'.
const APP_TIMEZONE = 'UTC';
const MIN_PHP      = '8.1.0';

date_default_timezone_set(APP_TIMEZONE);

// Load Composer packages if they are installed.
$autoload     = __DIR__ . '/vendor/autoload.php';
$hasComposer  = is_file($autoload);
if ($hasComposer) {
    require $autoload;
}

// Use the minified stylesheet from `npm run build` when it exists.
$cssFile = is_file(__DIR__ . '/style.min.css') ? 'style.min.css' : 'style.css';
$cssVer  = (string) @filemtime(__DIR__ . '/' . $cssFile);

// Escape output safely.
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Current time: uses Carbon when available, plain PHP otherwise.
$now = $hasComposer
    ? \Carbon\Carbon::now(APP_TIMEZONE)->isoFormat('dddd, D MMMM YYYY, h:mm A')
    : date('l, j F Y, g:i A');

// Server checks.
$checks = [
    [
        'label'  => 'PHP version',
        'ok'     => version_compare(PHP_VERSION, MIN_PHP, '>='),
        'detail' => 'Running ' . PHP_VERSION . ', needs ' . MIN_PHP . ' or newer',
    ],
    [
        'label'  => 'Required extensions',
        'ok'     => extension_loaded('mbstring') && extension_loaded('json'),
        'detail' => 'mbstring and json',
    ],
    [
        'label'  => 'Database driver',
        'ok'     => extension_loaded('pdo_mysql'),
        'detail' => extension_loaded('pdo_mysql')
            ? 'pdo_mysql is available'
            : 'Enable pdo_mysql in php.ini if you use MySQL',
    ],
    [
        'label'  => 'Composer packages',
        'ok'     => $hasComposer,
        'detail' => $hasComposer
            ? 'vendor/autoload.php found'
            : 'Run: composer install',
    ],
    [
        'label'  => 'Front-end build',
        'ok'     => is_file(__DIR__ . '/style.min.css'),
        'detail' => is_file(__DIR__ . '/style.min.css')
            ? 'style.min.css found'
            : 'Run: npm install && npm run build',
    ],
];

$failed = count(array_filter($checks, static fn(array $c): bool => !$c['ok']));
$title  = $failed === 0
    ? 'Everything is set up'
    : ($failed === 1 ? 'One step left' : $failed . ' steps left');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Server check</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($cssFile) ?>?v=<?= e($cssVer) ?>">
</head>
<body>
    <main class="page">
        <header class="intro">
            <h1 class="headline <?= $failed === 0 ? 'is-ready' : 'is-pending' ?>">
                <?= e($title) ?>
            </h1>
            <p class="lede">
                This page checks that PHP, Composer and your front-end build
                are working on this server. Server time: <?= e($now) ?>.
            </p>
        </header>

        <section aria-labelledby="checks-title">
            <h2 id="checks-title">Checks</h2>
            <ul class="checks">
                <?php foreach ($checks as $check): ?>
                    <li class="check <?= $check['ok'] ? 'check--pass' : 'check--fix' ?>">
                        <div>
                            <strong><?= e($check['label']) ?></strong>
                            <span><?= e($check['detail']) ?></span>
                        </div>
                        <b class="badge"><?= $check['ok'] ? 'Pass' : 'Fix' ?></b>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section aria-labelledby="next-title">
            <h2 id="next-title">Setup commands</h2>
            <pre class="commands">composer install --no-dev --optimize-autoloader
npm install
npm run build</pre>
        </section>

        <footer class="foot">
            Remove or protect this page before going live. It shows server details.
        </footer>
    </main>
</body>
</html>
