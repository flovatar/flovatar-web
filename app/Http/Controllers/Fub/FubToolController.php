<?php

namespace App\Http\Controllers\Fub;

use App\Http\Controllers\Controller;
use App\Jobs\SyncFubDraft;
use App\Models\FubDraft;
use App\Models\FubDraftItem;
use App\Services\Fub\DraftEditor;
use App\Support\Fub as FubSupport;
use App\Support\FubAuth;
use Illuminate\Http\Request;

/**
 * Draft-tool API for the design assistant server (FUB-PLAN §d.3). Auth = FUB_TOOL_TOKEN bearer AND an owner-minted
 * draft grant (X-Fub-Grant). Scope = exactly one draft of one wallet. The same DraftEditor methods as the studio, with
 * source "assistant" (items get aiAssisted, every call is in the draft history with undo hints).
 *
 * Not here, by design: commit, publish, additions, payout, royalty, mint, refills, other drafts, originals.
 * Destructive tools require confirm=true, which the assistant server only sends after the user clicked "Confirm" in
 * its UI (the model can't set it).
 */
class FubToolController extends Controller
{
    public function __construct(protected DraftEditor $editor)
    {
    }

    /** @return array{0: string, 1: FubDraft} */
    protected function ctx(Request $request): array
    {
        abort_unless(FubSupport::active(), 404);
        abort_unless(FubAuth::toolTokenOk($request->bearerToken()), 401, 'Bad tool token');
        $g = FubAuth::verifyGrant($request->header('X-Fub-Grant'));
        abort_unless($g !== null, 403, 'Draft grant missing, expired or revoked');

        return [$g[0], $g[1]];
    }

    protected function done(FubDraft $d, array $extra = [])
    {
        $d = $d->fresh();
        if ($d->chain_fub_id && $d->sync_state === 'pending') {
            SyncFubDraft::dispatch($d->id);
        }

        return response()->json($extra + ['draft' => $this->editor->snapshot($d)]);
    }

    protected function item(FubDraft $d, int $id): FubDraftItem
    {
        $it = FubDraftItem::where('id', $id)->where('fub_draft_id', $d->id)->where('chain_state', '!=', 'deleted')->first();
        abort_unless($it, 404, 'No such item in this draft');

        return $it;
    }

    public function grant(Request $request)
    {
        [$a, $d] = $this->ctx($request);
        $g = FubAuth::verifyGrant($request->header('X-Fub-Grant'));

        return response()->json(['address' => $a, 'draftId' => $d->id, 'expires' => $g[2], 'status' => $d->status]);
    }

    public function draft(Request $request)
    {
        [, $d] = $this->ctx($request);

        return response()->json(['draft' => $this->editor->snapshot($d)]);
    }

    public function itemSvg(Request $request, int $id)
    {
        [, $d] = $this->ctx($request);

        return response()->json(['id' => $id, 'svg' => $this->item($d, $id)->svg]);
    }

    public function validateSvg(Request $request)
    {
        $this->ctx($request);

        return response()->json($this->editor->validateSvg((string) $request->input('svg', ''), $request->boolean('large')));
    }

    public function updateDraft(Request $request)
    {
        [$a, $d] = $this->ctx($request);
        $this->editor->updateDraft($a, $d, $request->only(['name', 'description', 'unique', 'max_supply', 'license', 'royalty']), 'assistant');

        return $this->done($d);
    }

    public function addLayer(Request $request)
    {
        [$a, $d] = $this->ctx($request);
        $l = $this->editor->addLayer($a, $d, $request->only(['name', 'required', 'key']), 'assistant');

        return $this->done($d, ['layer' => $l->key]);
    }

    public function updateLayer(Request $request, string $key)
    {
        [$a, $d] = $this->ctx($request);
        $this->editor->updateLayer($a, $d, $key, $request->only(['name', 'required']), 'assistant');

        return $this->done($d);
    }

    public function reorderLayers(Request $request)
    {
        [$a, $d] = $this->ctx($request);
        $this->editor->reorderLayers($a, $d, (array) $request->input('keys', []), 'assistant');

        return $this->done($d);
    }

    public function deleteLayer(Request $request, string $key)
    {
        [$a, $d] = $this->ctx($request);
        $this->editor->deleteLayer($a, $d, $key, $request->boolean('confirm'), 'assistant');

        return $this->done($d);
    }

    public function addItem(Request $request)
    {
        [$a, $d] = $this->ctx($request);
        $r = $this->editor->createItem($a, $d, $request->only(['layer', 'name', 'description', 'rarity', 'price', 'max_mint', 'increase', 'price_cap', 'large', 'svg']), 'assistant');

        return $this->done($d, ['item' => $r['item']->id, 'report' => $r['report']]);
    }

    public function updateItem(Request $request, int $id)
    {
        [$a, $d] = $this->ctx($request);
        $this->editor->updateItem($a, $d, $this->item($d, $id), $request->only(['layer', 'name', 'description', 'rarity', 'price', 'max_mint', 'increase', 'price_cap']), 'assistant');

        return $this->done($d);
    }

    public function replaceSvg(Request $request, int $id)
    {
        [$a, $d] = $this->ctx($request);
        $r = $this->editor->replaceItemSvg($a, $d, $this->item($d, $id), (string) $request->input('svg', ''), $request->has('large') ? $request->boolean('large') : null, 'assistant');

        return $this->done($d, ['report' => $r['report']]);
    }

    public function deleteItem(Request $request, int $id)
    {
        [$a, $d] = $this->ctx($request);
        $this->editor->deleteItem($a, $d, $this->item($d, $id), $request->boolean('confirm'), 'assistant');

        return $this->done($d);
    }
}
