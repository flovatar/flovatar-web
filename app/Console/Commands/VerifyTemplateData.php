<?php

namespace App\Console\Commands;

use App\Http\Controllers\Controller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Regression check for the template data scripts (/templates/data/{set}.js): fetches each
 * served file over HTTP (through Cloudflare when --base is the public URL), parses it and
 * asserts that it holds exactly the DB templates of that set, in the same order, with every
 * svg string BYTE-IDENTICAL to the DB. Flobit combos / easter eggs are CSS sibling selectors
 * inside template SVGs (`#TRIGGER ~ #TARGET` + @keyframes), so any change to an svg string
 * or to the order would silently break them.
 *
 * For each set it checks both the script URL referenced by the page that uses it (what
 * browsers load) and the current-version URL. Exit code 1 on any mismatch.
 */
class VerifyTemplateData extends Command
{
    protected $signature = 'flovatar:verify-template-data
        {--base= : site base URL (default APP_URL)}
        {--set=* : sets to check (default: all of series1, flobot, dust, dust2)}';

    protected $description = 'Assert that /templates/data/{set}.js svgs are byte-identical to the DB (same templates, same order)';

    const PAGES = ['series1' => '/builder', 'flobot' => '/builder/flobot', 'dust' => '/builder/dust', 'dust2' => '/builder/dust/2'];

    public function handle(): int
    {
        $base = rtrim($this->option('base') ?: (string) config('app.url'), '/');
        $sets = $this->option('set') ?: Controller::TEMPLATE_DATA_SETS;
        $http = fn () => Http::timeout(180)->withHeaders(['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) flovatar-verify-template-data', 'Accept-Encoding' => 'gzip']);
        $failed = false;

        foreach ($sets as $set) {
            if (! in_array($set, Controller::TEMPLATE_DATA_SETS, true)) {
                $this->error("unknown set $set");
                $failed = true;

                continue;
            }
            $db = Controller::templateQuery($set)->toBase()->get();
            $combos = $db->filter(fn ($t) => str_contains((string) $t->svg, '~'))->count();
            $urls = ['current' => $base.'/templates/data/'.$set.'.js?v='.Controller::templatesVersion($set)];

            $page = $http()->get($base.self::PAGES[$set]);
            if ($page->ok() && preg_match('#<script src="([^"]*/templates/data/'.preg_quote($set, '#').'\.js\?v=[^"]+)"#', $page->body(), $m)) {
                $urls['page'] = str_starts_with($m[1], 'http') ? $m[1] : $base.$m[1];
            } else {
                $this->error("$set: ".self::PAGES[$set]." (HTTP {$page->status()}) does not reference /templates/data/$set.js");
                $failed = true;
            }
            if (str_contains($page->body(), 'window.templates = [')) {
                $this->error("$set: ".self::PAGES[$set].' still inlines window.templates');
                $failed = true;
            }

            foreach (array_unique($urls) as $label => $url) {
                $res = $http()->get($url);
                $body = $res->body();
                $prefix = 'window.templates = ';
                if (! $res->ok() || ! str_starts_with($body, $prefix) || ! str_ends_with($body, ';')) {
                    $this->error("$set [$label] $url: HTTP {$res->status()}, unexpected body");
                    $failed = true;

                    continue;
                }
                $served = json_decode(substr($body, strlen($prefix), -1), true, 512, JSON_THROW_ON_ERROR);
                $errors = [];
                if (count($served) !== $db->count()) {
                    $errors[] = 'count served '.count($served).' != db '.$db->count();
                }
                $identical = 0;
                foreach ($db->values() as $i => $row) {
                    $s = $served[$i] ?? null;
                    if (! $s || (int) $s['id'] !== (int) $row->id) {
                        $errors[] = "position $i: id ".($s['id'] ?? 'none')." != db {$row->id}";

                        continue;
                    }
                    if (! array_key_exists('svg', $s) || $s['svg'] !== $row->svg) {
                        $errors[] = "id {$row->id}: svg differs (served ".strlen((string) ($s['svg'] ?? '')).' bytes, db '.strlen((string) $row->svg).')';

                        continue;
                    }
                    foreach (['flow_id', 'name', 'category', 'layer', 'rarity', 'series', 'dust_series_id'] as $k) {
                        if (property_exists($row, $k) && (string) ($s[$k] ?? '') !== (string) ($row->$k ?? '')) {
                            $errors[] = "id {$row->id}: $k differs";
                        }
                    }
                    $identical++;
                }
                $cf = $res->header('cf-cache-status') ?: '-';
                $this->line(sprintf('%-7s [%s] %s: %d/%d svgs byte-identical, order identical: %s, combo templates (~ selectors): %d, %s bytes, cache-control "%s", cf %s',
                    $set, $label, parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY), $identical, $db->count(), $errors ? 'NO' : 'yes', $combos, strlen($body), $res->header('cache-control'), $cf));
                foreach (array_slice($errors, 0, 10) as $e) {
                    $this->error('   '.$e);
                }
                $failed = $failed || (bool) $errors;
            }
        }

        $failed ? $this->error('FAILED') : $this->info('OK: every served template data file matches the DB byte for byte');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
