<?php

namespace App\Http\Controllers;

use App\Support\EvmMetadata;
use Illuminate\Http\JsonResponse;

/**
 * Public NFT metadata JSON = the ERC-721 tokenURI documents for Flow EVM (EVM-A 2026-11). The Cadence contracts'
 * EVMBridgedMetadata view and the project ERC-721s point here. See App\Support\EvmMetadata for the format.
 * Edge-cacheable (PublicCache lists these paths); 404 JSON when the item is unknown to the indexer.
 * (Before 2026-11 these routes returned the raw indexer flow_metadata incl. the SVG; nothing on the site used them.)
 */
class JsonController extends Controller
{
    protected function out(?array $data): JsonResponse
    {
        if ($data === null) {
            return response()->json(['error' => 'not found'], 404);
        }

        return response()->json($data, 200, ['Access-Control-Allow-Origin' => '*'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function flovatar($flovatarId)
    {
        return $this->out(EvmMetadata::flovatar((int) $flovatarId));
    }

    public function flobot($flobotId)
    {
        return $this->out(EvmMetadata::flobot((int) $flobotId));
    }

    public function component($componentId)
    {
        return $this->out(EvmMetadata::component((int) $componentId));
    }

    public function collectible($collectibleId)
    {
        return $this->out(EvmMetadata::collectible((int) $collectibleId));
    }

    public function accessory($accessoryId)
    {
        return $this->out(EvmMetadata::accessory((int) $accessoryId));
    }

    public function evmCollection($slug)
    {
        return $this->out(EvmMetadata::collection((string) $slug));
    }
}
