<?php

namespace App\Support;

use App\Models\FlovatarTemplate;

/**
 * Request-scoped memo of template SVGs used by the Flovatar/Flobot full_svg accessors.
 * Before: every accessor call ran `select * from flovatar_templates where flow_id = ?`
 * (up to 8 per Flovatar, N+1 on every list). Now each template is read once per
 * request/job (scoped binding, see AppServiceProvider) and only its svg column.
 */
class TemplateSvgs
{
    /** @var array<int,string> */
    protected array $svgs = [];

    public static function get($flowId): string
    {
        if ($flowId === null || $flowId === '' || ! is_numeric($flowId)) {
            return '';
        }

        return app(self::class)->find((int) $flowId);
    }

    public function find(int $flowId): string
    {
        if (! array_key_exists($flowId, $this->svgs)) {
            $this->svgs[$flowId] = (string) (FlovatarTemplate::where('flow_id', $flowId)->value('svg') ?? '');
        }

        return $this->svgs[$flowId];
    }
}
