<?php
/*
 * One-time installer for InfinityFree (no SSH, no unzip in the file manager).
 *
 * scripts/infinityfree-publish.ps1 uploads this file as htdocs/_install-<token>.php
 * next to the bundle's zip parts and the first database dump. Opening it in a
 * browser:
 *   1. extracts bookease-part-*.zip into htdocs, one part per page load
 *      (free hosting stops long requests), deleting each zip after;
 *   2. imports bookease-production.sql into the database named in .env, then
 *      deletes the dump;
 *   3. puts the site's .htaccess in place (uploaded as htaccess.pending, so
 *      this page stays reachable until then) and deletes itself.
 * The random token in the file name is the only key; the file is gone after step 3.
 */

declare(strict_types=1);

@set_time_limit(120);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$base = __DIR__;                   // htdocs
$self = basename(__FILE__);
$step = $_GET['step'] ?? 'extract';

function page(string $title, string $body, ?string $next = null): never
{
    $refresh = $next ? '<meta http-equiv="refresh" content="1;url='.htmlspecialchars($next).'">' : '';
    echo "<!doctype html><meta charset=utf-8>{$refresh}<title>BookEase install</title>"
        .'<body style="font:16px/1.5 system-ui;max-width:40rem;margin:3rem auto;padding:0 1rem">'
        .'<h1 style="font-size:1.4rem">'.htmlspecialchars($title).'</h1>'.$body
        .($next ? '<p>Continuing automatically… <a href="'.htmlspecialchars($next).'">continue</a></p>' : '')
        .'</body>';
    exit;
}

function env_values(string $file): array
{
    $values = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', trim($line), $m)) {
            $v = trim($m[2]);
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
                $v = substr($v, 1, -1);
            }
            $values[$m[1]] = $v;
        }
    }

    return $values;
}

if ($step === 'extract') {
    $parts = glob($base.'/bookease-part-*.zip') ?: [];
    natsort($parts);
    $part = reset($parts);

    if ($part === false) {
        page('Files are in place', '<p>All parts extracted.</p>', $self.'?step=import');
    }

    if (! class_exists(ZipArchive::class)) {
        page('Cannot extract', '<p>This server has no ZipArchive. Upload deploy\\htdocs with FileZilla instead (DEPLOY.md step 5).</p>');
    }

    $zip = new ZipArchive;
    if ($zip->open($part) !== true || ! $zip->extractTo($base)) {
        page('Extraction failed', '<p>Could not extract '.htmlspecialchars(basename($part)).'. Upload it again and reload this page.</p>');
    }
    $count = $zip->numFiles;
    $zip->close();
    unlink($part);

    page('Extracting files', '<p>'.htmlspecialchars(basename($part))." done ({$count} files). ".(count($parts) - 1).' part(s) left.</p>', $self.'?step=extract');
}

if ($step === 'import') {
    $dump = $base.'/bookease-production.sql';

    if (! is_file($dump)) {
        page('Database already imported', '<p>No dump to import.</p>', $self.'?step=finish');
    }

    $env = env_values($base.'/.env');
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli($env['DB_HOST'] ?? '', $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', $env['DB_DATABASE'] ?? '', (int) ($env['DB_PORT'] ?? 3306));

    if ($db->connect_errno) {
        page('Cannot reach the database', '<p>'.htmlspecialchars($db->connect_error ?? '').'</p><p>Check DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD in htdocs/.env, then reload.</p>');
    }

    $exists = $db->query("SHOW TABLES LIKE 'migrations'");
    if ($exists && $exists->num_rows > 0) {
        unlink($dump);
        page('Database already has tables', '<p>Nothing imported, so existing data is kept.</p>', $self.'?step=finish');
    }

    $db->set_charset('utf8mb4');
    if (! $db->multi_query((string) file_get_contents($dump))) {
        page('Import failed', '<p>'.htmlspecialchars($db->error).'</p>');
    }
    do {
        if ($r = $db->store_result()) {
            $r->free();
        }
    } while ($db->more_results() && $db->next_result());

    if ($db->errno) {
        page('Import stopped with an error', '<p>'.htmlspecialchars($db->error).'</p><p>Empty the database in phpMyAdmin and reload this page.</p>');
    }

    unlink($dump);
    page('Database imported', '<p>All tables and the owner account are in place.</p>', $self.'?step=finish');
}

if ($step === 'finish') {
    foreach (['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache', 'storage/app/private'] as $dir) {
        @mkdir($base.'/'.$dir, 0755, true);
    }
    if (is_file($base.'/htaccess.pending') && ! rename($base.'/htaccess.pending', $base.'/.htaccess')) {
        page('Almost done', '<p>Rename htdocs/htaccess.pending to .htaccess in the file manager, then delete this installer.</p>');
    }
    @unlink($base.'/index2.html');
    @unlink(__FILE__);
    page('BookEase is installed', '<p>This installer has deleted itself.</p><p><a href="/">Open the site</a> · <a href="/staff/login">Staff login</a></p>');
}

http_response_code(404);
page('Not found', '');
