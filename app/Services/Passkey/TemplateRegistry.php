<?php

namespace App\Services\Passkey;

use RuntimeException;

/**
 * Allowlist of the site's own transactions that the sponsor may co-sign for passkey accounts.
 *
 * Source of the Cadence: resources/passkey/templates.json, generated from resources/js/flow/*.tx.js by
 * `node bin/passkey-templates.js` (the exact strings the wallet path hands to fcl.mutate()). Allowed ids and their
 * SHA-256 are pinned in config/passkey-templates.php. The browser sends only the hash + JSON-Cadence arguments; the
 * Cadence that gets signed always comes from this server-side copy, after:
 *   1. the hash is on the allowlist and equals the manifest entry and sha256(manifest cadence) (no drift),
 *   2. the template has exactly one authorizer (the passkey account) and no custom signers,
 *   3. the arguments match the template's parameter list (count + JSON-Cadence types, size caps),
 *   4. the FCL address aliases (0xFlovatar, 0xNonFungible, ...) are replaced with config('passkey.aliases').
 */
class TemplateRegistry
{
    public const MAX_ARGS_BYTES = 16384;

    protected array $manifest;

    protected array $allow;

    protected array $aliases;

    public function __construct(?array $manifest = null, ?array $allow = null, ?array $aliases = null)
    {
        $this->manifest = $manifest ?? self::loadManifest();
        $this->allow = $allow ?? (array) config('passkey-templates', []);
        $this->aliases = $aliases ?? (array) config('passkey.aliases', []);
    }

    public static function loadManifest(): array
    {
        $path = base_path('resources/passkey/templates.json');
        $json = json_decode((string) @file_get_contents($path), true);
        if (! is_array($json) || ! isset($json['templates']) || ! is_array($json['templates'])) {
            throw new RuntimeException('Passkey template manifest missing (run node bin/passkey-templates.js)');
        }

        return $json['templates'];
    }

    /** Allowlisted ids => ['sha256', 'label']. */
    public function allowed(): array
    {
        return $this->allow;
    }

    public function manifest(): array
    {
        return $this->manifest;
    }

    /** Find the allowlisted template for a client-supplied hash (or id). Throws when not allowed. */
    public function find(string $hashOrId): array
    {
        $key = strtolower(trim($hashOrId));
        $id = null;
        if (preg_match('/^[0-9a-f]{64}$/', $key)) {
            foreach ($this->allow as $tid => $pin) {
                if (hash_equals((string) $pin['sha256'], $key)) {
                    $id = $tid;
                    break;
                }
            }
        } elseif (isset($this->allow[$key])) {
            $id = $key;
        }
        if ($id === null) {
            throw new RuntimeException('This action is not available for passkey accounts');
        }
        $entry = $this->manifest[$id] ?? null;
        $pin = (string) $this->allow[$id]['sha256'];
        if (! $entry || ! is_string($entry['cadence'] ?? null)) {
            throw new RuntimeException("Template {$id} missing from the manifest");
        }
        if (! hash_equals($pin, (string) $entry['sha256']) || ! hash_equals($pin, hash('sha256', $entry['cadence']))) {
            throw new RuntimeException("Template {$id} does not match its pinned hash");
        }
        if (! empty($entry['customSigners'])) {
            throw new RuntimeException("Template {$id} uses custom signers");
        }
        if (count(self::prepareParams($entry['cadence'])) !== 1) {
            throw new RuntimeException("Template {$id} needs more than one authorizer");
        }

        return ['id' => $id, 'label' => (string) ($this->allow[$id]['label'] ?? $id), 'cadence' => $entry['cadence'], 'sha256' => $pin];
    }

    /** Cadence with the FCL aliases replaced by addresses (like FCL's config resolution). */
    public function resolve(string $cadence): string
    {
        $out = preg_replace_callback('/\b0x[A-Za-z][A-Za-z0-9_]*\b/', function ($m) {
            $alias = $m[0];
            if (! array_key_exists($alias, $this->aliases)) {
                return $alias;
            }
            $addr = (string) $this->aliases[$alias];
            if (! preg_match('/^(0x)?[0-9a-fA-F]{1,16}$/', $addr)) {
                throw new RuntimeException("Contract alias {$alias} is not configured");
            }

            return '0x'.FlowTransaction::addr($addr);
        }, $cadence);
        if (preg_match('/\bfrom\s+0x[A-Za-z]/', $out, $m)) {
            throw new RuntimeException('Unresolved contract alias in template: '.$m[0]);
        }

        return $out;
    }

    // ------------------------------------------------------------------ Cadence signature parsing

    /** Split a parameter list on top-level commas. */
    protected static function splitTop(string $s): array
    {
        $parts = [''];
        $depth = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if (strpbrk($c, '(<[{') !== false) {
                $depth++;
            } elseif (strpbrk($c, ')>]}') !== false) {
                $depth--;
            }
            if ($c === ',' && $depth === 0) {
                $parts[] = '';
            } else {
                $parts[count($parts) - 1] .= $c;
            }
        }

        return array_values(array_filter(array_map('trim', $parts), 'strlen'));
    }

    /** Balanced-paren contents after $keyword( in the code. */
    protected static function parenAfter(string $code, string $keyword): ?string
    {
        if (! preg_match('/\b'.preg_quote($keyword, '/').'\s*\(/', $code, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $start = $m[0][1] + strlen($m[0][0]);
        $depth = 1;
        $len = strlen($code);
        for ($i = $start; $i < $len; $i++) {
            if ($code[$i] === '(') {
                $depth++;
            } elseif ($code[$i] === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($code, $start, $i - $start);
                }
            }
        }

        return null;
    }

    protected static function stripComments(string $code): string
    {
        $code = preg_replace('~/\*.*?\*/~s', '', $code);

        return preg_replace('~//[^\n]*~', '', $code);
    }

    /** prepare(...) parameters (one per authorizer). */
    public static function prepareParams(string $cadence): array
    {
        $inner = self::parenAfter(self::stripComments($cadence), 'prepare');

        return $inner === null ? [] : self::splitTop($inner);
    }

    /** transaction(...) parameter types, e.g. ['UInt64', 'UInt64?', '[UInt64]', 'Address']. */
    public static function parameterTypes(string $cadence): array
    {
        $inner = self::parenAfter(self::stripComments($cadence), 'transaction');
        if ($inner === null) {
            return [];
        }

        return array_map(function ($p) {
            $pos = strpos($p, ':');

            return trim($pos === false ? $p : substr($p, $pos + 1));
        }, self::splitTop($inner));
    }

    // ------------------------------------------------------------------ argument validation

    /** Validate JSON-Cadence arguments against the template parameters; returns them normalised. */
    public function validateArgs(string $cadence, array $args): array
    {
        if (strlen(json_encode($args)) > self::MAX_ARGS_BYTES) {
            throw new RuntimeException('Arguments too large');
        }
        $types = self::parameterTypes($cadence);
        if (count($types) !== count($args)) {
            throw new RuntimeException('Expected '.count($types).' arguments, got '.count($args));
        }
        $out = [];
        foreach (array_values($args) as $i => $a) {
            if (! is_array($a)) {
                throw new RuntimeException('Argument '.($i + 1).' is not JSON-Cadence');
            }
            $out[] = self::checkValue($types[$i], $a, 'argument '.($i + 1));
        }

        return $out;
    }

    protected const SIMPLE = ['UInt8', 'UInt16', 'UInt32', 'UInt64', 'UInt128', 'UInt256', 'Int', 'Int8', 'Int16', 'Int32', 'Int64', 'Int128', 'Int256', 'UInt', 'Word8', 'Word16', 'Word32', 'Word64', 'UFix64', 'Fix64', 'String', 'Address', 'Bool'];

    protected static function checkValue(string $type, array $v, string $where): array
    {
        $type = trim($type);
        if (str_ends_with($type, '?')) {
            if (($v['type'] ?? null) !== 'Optional') {
                throw new RuntimeException("{$where}: expected Optional");
            }
            if (($v['value'] ?? null) === null) {
                return ['type' => 'Optional', 'value' => null];
            }
            if (! is_array($v['value'])) {
                throw new RuntimeException("{$where}: bad Optional value");
            }

            return ['type' => 'Optional', 'value' => self::checkValue(substr($type, 0, -1), $v['value'], $where)];
        }
        if (str_starts_with($type, '[') && str_ends_with($type, ']')) {
            if (($v['type'] ?? null) !== 'Array' || ! is_array($v['value'] ?? null) || count($v['value']) > 500) {
                throw new RuntimeException("{$where}: expected Array (max 500)");
            }

            return ['type' => 'Array', 'value' => array_map(fn ($x) => is_array($x) ? self::checkValue(substr($type, 1, -1), $x, $where) : throw new RuntimeException("{$where}: bad Array element"), array_values($v['value']))];
        }
        if (str_starts_with($type, '{')) {
            if (($v['type'] ?? null) !== 'Dictionary' || ! is_array($v['value'] ?? null) || count($v['value']) > 500) {
                throw new RuntimeException("{$where}: expected Dictionary");
            }
            [$kt, $vt] = array_map('trim', explode(':', substr($type, 1, -1), 2)) + [null, null];

            return ['type' => 'Dictionary', 'value' => array_map(fn ($kv) => [
                'key' => self::checkValue((string) $kt, (array) ($kv['key'] ?? []), $where),
                'value' => self::checkValue((string) $vt, (array) ($kv['value'] ?? []), $where),
            ], array_values($v['value']))];
        }
        if (! in_array($type, self::SIMPLE, true)) {
            throw new RuntimeException("{$where}: unsupported parameter type {$type}");
        }
        if (($v['type'] ?? null) !== $type) {
            throw new RuntimeException("{$where}: expected {$type}, got ".(is_string($v['type'] ?? null) ? $v['type'] : '?'));
        }
        $val = $v['value'] ?? null;
        if ($type === 'Bool') {
            if (! is_bool($val)) {
                throw new RuntimeException("{$where}: bad Bool");
            }

            return ['type' => 'Bool', 'value' => $val];
        }
        if (! is_string($val)) {
            throw new RuntimeException("{$where}: value must be a string");
        }
        if ($type === 'Address') {
            if (! preg_match('/^(0x)?[0-9a-fA-F]{1,16}$/', $val)) {
                throw new RuntimeException("{$where}: bad Address value");
            }

            return ['type' => 'Address', 'value' => '0x'.FlowTransaction::addr($val)];
        }
        $ok = match (true) {
            $type === 'String' => strlen($val) <= 4096,
            // FCL pads strings to 8 decimals but sends JS numbers as toString() ("1"), which the node rejects -> normalised below
            $type === 'UFix64' => (bool) preg_match('/^\d{1,11}(\.\d{1,8})?$/', $val),
            $type === 'Fix64' => (bool) preg_match('/^-?\d{1,11}(\.\d{1,8})?$/', $val),
            str_starts_with($type, 'U') || str_starts_with($type, 'Word') => (bool) preg_match('/^\d{1,78}$/', $val),
            default => (bool) preg_match('/^-?\d{1,78}$/', $val),
        };
        if (! $ok) {
            throw new RuntimeException("{$where}: bad {$type} value");
        }
        if (($type === 'UFix64' || $type === 'Fix64') && ! str_contains($val, '.')) {
            $val .= '.0'; // the node rejects "1" (FCL sends JS numbers that way): normalise instead of paying for a failed tx
        }

        return ['type' => $type, 'value' => $val];
    }
}
