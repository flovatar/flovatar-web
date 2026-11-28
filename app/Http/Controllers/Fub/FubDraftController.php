<?php

namespace App\Http\Controllers\Fub;

use App\Http\Controllers\Controller;
use App\Jobs\SyncFubDraft;
use App\Models\Fub;
use App\Models\FubDraft;
use App\Models\FubDraftItem;
use App\Services\Fub\DraftEditor;
use App\Services\Fub\FubChain;
use App\Services\Fub\FubIndexer;
use App\Support\Fub as FubSupport;
use App\Support\FubAuth;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Studio JSON API (session = the signed-in wallet). Thin: every rule lives in DraftEditor (shared with the assistant
 * tool API). Chain steps (commit, publish, additions) are wallet transactions; the server only links/verifies them.
 */
class FubDraftController extends Controller
{
    public function __construct(protected DraftEditor $editor, protected FubChain $chain)
    {
    }

    protected function actor(): string
    {
        abort_unless(FubSupport::enabled(), 404);
        $a = FubAuth::address();
        abort_unless($a !== null, 401, 'Connect your wallet first');

        return $a;
    }

    protected function draft(int $id, string $actor): FubDraft
    {
        $d = FubDraft::where('id', $id)->where('status', '!=', 'abandoned')->firstOrFail();
        abort_unless($d->isOwnedBy($actor), 404);

        return $d;
    }

    protected function maybeSync(FubDraft $d): void
    {
        if ($d->chain_fub_id && $d->sync_state === 'pending') {
            SyncFubDraft::dispatch($d->id);
        }
    }

    public function index()
    {
        $a = $this->actor();
        $drafts = FubDraft::where('owner_address', $a)->where('status', '!=', 'abandoned')->orderByDesc('updated_at')->get();

        return response()->json(['drafts' => $drafts->map(fn ($d) => [
            'id' => $d->id, 'name' => $d->name, 'status' => $d->status, 'chainFubId' => $d->chain_fub_id, 'syncState' => $d->sync_state,
            'islandId' => isset(($d->settings ?? [])['island_id']) ? (int) $d->settings['island_id'] : null, // bound at commit
            'items' => $d->items()->where('chain_state', '!=', 'deleted')->count(), 'updatedAt' => $d->updated_at->toIso8601String(),
        ]), 'fubs' => Fub::where('creator', $a)->orderByDesc('flow_id')->get()->map(fn ($f) => app(FubPagesController::class)->fubJson($f, false))]);
    }

    public function store(Request $request)
    {
        $d = $this->editor->createDraft($this->actor(), $request->only(['name', 'description']));

        return response()->json($this->editor->snapshot($d), 201);
    }

    public function show(int $id)
    {
        $a = $this->actor();

        return response()->json($this->editor->snapshot($this->draft($id, $a)));
    }

    public function update(Request $request, int $id)
    {
        $a = $this->actor();
        $d = $this->editor->updateDraft($a, $this->draft($id, $a), $request->all());
        $this->maybeSync($d);

        return response()->json($this->editor->snapshot($d));
    }

    public function destroy(Request $request, int $id)
    {
        $a = $this->actor();
        $this->editor->abandon($a, $this->draft($id, $a), (bool) $request->boolean('confirm'));

        return response()->json(['ok' => true]);
    }

    public function addLayer(Request $request, int $id)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);
        $this->editor->addLayer($a, $d, $request->all());
        $this->maybeSync($d->fresh());

        return response()->json($this->editor->snapshot($d->fresh()), 201);
    }

    public function updateLayer(Request $request, int $id, string $key)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);
        $this->editor->updateLayer($a, $d, $key, $request->all());
        $this->maybeSync($d->fresh());

        return response()->json($this->editor->snapshot($d->fresh()));
    }

    public function reorderLayers(Request $request, int $id)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);
        $this->editor->reorder($a, $d, $request->only(['keys', 'key', 'position']));
        $this->maybeSync($d->fresh());

        return response()->json($this->editor->snapshot($d->fresh()));
    }

    public function deleteLayer(Request $request, int $id, string $key)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);
        $this->editor->deleteLayer($a, $d, $key, $request->boolean('confirm'));
        $this->maybeSync($d->fresh());

        return response()->json($this->editor->snapshot($d->fresh()));
    }

    /** multipart (file) or JSON (svg string) */
    public function addItem(Request $request, int $id)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);
        $in = $request->except(['file']);
        $in['svg'] = $this->svgInput($request);
        $r = $this->editor->createItem($a, $d, $in);
        $this->maybeSync($d->fresh());

        return response()->json(['report' => $r['report'], 'draft' => $this->editor->snapshot($d->fresh())], 201);
    }

    public function updateItem(Request $request, int $id, int $itemId)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);
        $this->editor->updateItem($a, $d, FubDraftItem::findOrFail($itemId), $request->all());
        $this->maybeSync($d->fresh());

        return response()->json($this->editor->snapshot($d->fresh()));
    }

    public function replaceSvg(Request $request, int $id, int $itemId)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);
        $r = $this->editor->replaceItemSvg($a, $d, FubDraftItem::findOrFail($itemId), $this->svgInput($request),
            $request->has('large') ? $request->boolean('large') : null);
        $this->maybeSync($d->fresh());

        return response()->json(['report' => $r['report'], 'draft' => $this->editor->snapshot($d->fresh())]);
    }

    public function deleteItem(Request $request, int $id, int $itemId)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);
        $this->editor->deleteItem($a, $d, FubDraftItem::findOrFail($itemId), $request->boolean('confirm'));
        $this->maybeSync($d->fresh());

        return response()->json($this->editor->snapshot($d->fresh()));
    }

    public function validateSvg(Request $request)
    {
        $this->actor();

        return response()->json($this->editor->validateSvg($this->svgInput($request), $request->boolean('large')));
    }

    protected function svgInput(Request $request): string
    {
        if ($request->hasFile('file')) {
            $f = $request->file('file');
            if (! $f->isValid() || $f->getSize() > (int) config('fub.max_upload_bytes', 524288)) {
                throw ValidationException::withMessages(['file' => 'Upload an SVG file up to 512 KB']);
            }

            return (string) file_get_contents($f->getRealPath());
        }

        return (string) $request->input('svg', '');
    }

    /** After the Commit tx: verify FubCommitted (creator = this wallet), link the chain FUB, start syncing. */
    public function link(Request $request, int $id)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);
        $tx = (string) $request->input('tx', '');
        if (! preg_match('/^(0x)?[0-9a-f]{64}$/i', $tx)) {
            throw ValidationException::withMessages(['tx' => 'Transaction id required']);
        }
        if ($d->chain_fub_id) {
            return response()->json($this->editor->snapshot($d));
        }
        $r = $this->chain->txResult($tx);
        $fubId = null;
        foreach ((array) ($r['events'] ?? []) as $e) {
            if ($e['type'] === FubSupport::typeId('FubCommitted') && FubSupport::normalizeAddress($e['values']['creator'] ?? '') === $a) {
                $fubId = (int) $e['values']['id'];
            }
        }
        if (! $fubId) {
            throw ValidationException::withMessages(['tx' => 'That transaction did not commit a FUB for this wallet']);
        }
        if (FubDraft::where('chain_fub_id', $fubId)->where('id', '!=', $d->id)->exists()) {
            throw ValidationException::withMessages(['tx' => 'That FUB is linked to another draft']);
        }
        $f = app(FubIndexer::class)->refreshFub($fubId, $this->chain->txHeight($tx));
        $s = $d->settings ?? [];
        $s['tier'] = $f->tier;
        $s['slots'] = $f->slots;
        $s['max_supply'] = min((int) ($s['max_supply'] ?? $f->mint_cap), $f->mint_cap);
        $s['island_id'] = $f->island_id; // bound at commit, permanent
        $d->update(['chain_fub_id' => $fubId, 'status' => 'committed', 'settings' => $s, 'sync_state' => 'pending']);
        SyncFubDraft::dispatch($d->id);

        return response()->json($this->editor->snapshot($d->fresh()));
    }

    /** Absorb more vouchers before publish: refresh the chain record (tier/slots grow). */
    public function absorbed(Request $request, int $id)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);
        abort_unless($d->chain_fub_id, 422);
        $f = app(FubIndexer::class)->refreshFub((int) $d->chain_fub_id, ($tx = $request->input('tx')) ? $this->chain->txHeight((string) $tx) : null);
        $s = $d->settings ?? [];
        $s['tier'] = $f->tier;
        $s['slots'] = $f->slots;
        $d->update(['settings' => $s]);
        $this->maybeSync($d->fresh());

        return response()->json($this->editor->snapshot($d->fresh()));
    }

    public function sync(int $id)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);
        abort_unless($d->chain_fub_id, 422, 'Commit first');
        $d->update(['sync_state' => 'pending']);
        SyncFubDraft::dispatch($d->id);

        return response()->json($this->editor->snapshot($d->fresh()));
    }

    /**
     * Publish / additions preflight: the chain truth the creator signs (manifest or pending hash), budget, blockers.
     * The browser shows the true on-chain preview (previewSvg) before the wallet opens.
     */
    public function preflight(int $id)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);
        abort_unless($d->chain_fub_id, 422, 'Commit first');
        $cf = $this->chain->fub((int) $d->chain_fub_id);
        abort_unless($cf, 404);
        $f = (array) $cf['fub'];
        $blockers = [];
        if ($d->sync_state !== 'idle') {
            $blockers[] = 'Your latest changes are still being written to the chain ('.$d->sync_state.').';
        }
        if ($d->items()->whereIn('chain_state', ['new', 'dirty', 'deleted'])->exists()) {
            $blockers[] = 'Some items are not on the chain yet.';
        }
        if ($f['status'] === 'building') {
            foreach ((array) $f['layers'] as $l) {
                if ($l['required'] && (int) (($f['layerCount'] ?? [])[$l['key']] ?? 0) === 0) {
                    $blockers[] = 'Layer "'.$l['name'].'" is required but has no items.';
                }
            }
            if ((int) $cf['heaviest'] > (int) $cf['composedCap']) {
                $blockers[] = 'The heaviest F.U.N. is over the size budget.';
            }
            if ((int) $f['items'] === 0) {
                $blockers[] = 'Add at least one item.';
            }
        }

        return response()->json([
            'fubId' => (int) $d->chain_fub_id, 'status' => $f['status'], 'manifest' => (string) $f['manifest'], 'pending' => (string) ($f['pending'] ?? ''),
            'pendingIds' => array_map('intval', (array) $cf['pendingIds']), 'heaviest' => (int) $cf['heaviest'], 'composedCap' => (int) $cf['composedCap'],
            'items' => (int) $f['items'], 'layers' => $f['layers'], 'blockers' => $blockers, 'tosHash' => FubPagesController::tosHash(),
            // island bound at commit (permanent); null only for FUBs committed before the island-at-commit update (testnet)
            'islandId' => isset($f['islandId']) ? (int) $f['islandId'] : null,
        ]);
    }

    /** The real on-chain composition of draft picks (chain item ids) — what the contract will serve. */
    public function chainPreview(Request $request, int $id)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);
        abort_unless($d->chain_fub_id, 422);
        $picks = [];
        foreach ((array) $request->input('picks', []) as $k => $v) {
            if (preg_match('/^[a-z0-9_-]{1,16}$/', (string) $k) && (int) $v > 0) {
                $picks[(string) $k] = (int) $v;
            }
        }
        try {
            $svg = $this->chain->previewSvg((int) $d->chain_fub_id, $picks);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Preview failed: '.substr($e->getMessage(), 0, 200)], 422);
        }

        return FubPagesController::svgResponse($svg, 0);
    }

    // ------------------------------------------------------------------------------------------------- assistant link

    public function grant(int $id)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);
        abort_unless((string) config('fub.assistant_url', '') !== '', 404, 'The design assistant is not configured');

        return response()->json(FubAuth::mintGrant($d) + ['assistantUrl' => config('fub.assistant_url')]);
    }

    public function revokeGrants(int $id)
    {
        $a = $this->actor();
        FubAuth::revokeGrants($this->draft($id, $a));

        return response()->json(['ok' => true]);
    }

    public function history(int $id)
    {
        $a = $this->actor();
        $d = $this->draft($id, $a);

        return response()->json(['calls' => \App\Models\FubToolCall::where('fub_draft_id', $d->id)->orderByDesc('id')->limit(100)
            ->get(['id', 'source', 'tool', 'ok', 'summary', 'created_at'])]);
    }
}
