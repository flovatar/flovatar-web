<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use App\Models\SportvatarPack;
use App\Models\User;
use App\Models\Whitelist;
use App\Jobs\ReservePack;
use App\Services\Flow\FlovatarPackAuthorizer;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Session;
use Cache;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Str;

class PackController extends AuthController
{
    public function home()
    {
        $signature = Auth::check() ? '' : $this->getMessage();
        return view('pack')->with(['signature' => $signature]);
    }
    public function login()
    {
        $signature = Auth::check() ? '' : $this->getMessage();

        if(Auth::check()){
            $user = Auth::user();

            $whitelistArr = [
                '0x57991b17c63ea6c1',
                '0x2a0eccae942667be',
                '0x8f6bf7a919bf4edb',
                '0x188048722b3c358f',
                '0x5f662af6efe4a273',
                '0x832fd1359b50835f',
                '0x36ef378785835e55',
                '0x16ae8f1cbfceaa9e',
                '0x28e936ae7ac6c181',
                '0x7d3610ad2540cef1',
                '0xe1d5954d03ccb02d',
                '0x3bd5880100da5c26',
                '0xef43d8b9414d3faf',
                '0x53f389d96fb4ce5e',
                '0x10c7bc1ff7499dc5',
                '0x886f3aeaf848c535',
                '0xa7ed1eba0b4b718f',
                '0x25524dbaefda5bf0',
                '0x423b45c54e3b2d9b',
                '0x5b64854c16a96267',
                '0xa9a82c6a04d6df2b',
                '0x80da55eacc26b043',
                '0xdf868d4de6d2e0ab',
                '0xcbdf19d61cc0eb80',
                '0x8b34540ffdaa02f3',
                '0x5a4a88e545f4e61c',
                '0xc51a17ff9c75f76a',
                '0xe0e1ee8a3981909d',
                '0x0e359cf9249e63b1',
                '0xff8bb75b02657619',
                '0x4592d82b8e815602',
                '0x5845710edc24c356',
                '0xb7a173318ba077a5',
                '0xb2ea0fe697fdb4ff'
            ];
            if(in_array($user->email, $whitelistArr)){
                session(['vipcookie' => 'hdfh55yyerg5g34t34t344']);
                return response()->view('pack-login', ['signature' => $signature])->withCookie(cookie('vipcookie', 'hdfh55yyerg5g34t34t344', 3600));
            }
        }
        return view('pack-login')->with(['signature' => $signature]);
    }
    public function buy()
    {
        $packCountTerraKit = Pack::readyToSell()->whereNull('user_id')->whereNull('assigned_at')->whereName('TerraKit Pack')->count();
        $cookieValue = session('vipcookie', '');
        return view('pack-buy')->with(['availableTerraKit' => $packCountTerraKit, 'vipcookie' => $cookieValue]);
    }
    public function dustBuy()
    {
        //$packCountStandard = Pack::readyToSell()->whereNull('user_id')->whereNull('assigned_at')->whereName('Genesis Standard Pack')->count();
        //$packCountDeluxe = Pack::readyToSell()->whereNull('user_id')->whereNull('assigned_at')->whereName('Genesis Deluxe Pack')->count();
        //$packCountFlobit = Pack::readyToSell()->whereNull('user_id')->whereNull('assigned_at')->whereName('Flobit Pack')->count();
        $packCountDustFlobit = Pack::readyToSell()->whereNull('user_id')->whereNull('assigned_at')->whereName('Dust Flobit Pack')->count();
        return view('pack-dust-buy')->with(['availableDustPacks' => $packCountDustFlobit]);
    }
    public function buyAll()
    {
        $packCountStandard = Pack::readyToSell()->whereNull('user_id')->whereNull('assigned_at')->whereName('Genesis Standard Pack')->count();
        $packCountDeluxe = Pack::readyToSell()->whereNull('user_id')->whereNull('assigned_at')->whereName('Genesis Deluxe Pack')->count();
        $packCountFlobit = Pack::readyToSell()->whereNull('user_id')->whereNull('assigned_at')->whereName('Flobit Pack')->count();
        $packCountTerraKit = Pack::readyToSell()->whereNull('user_id')->whereNull('assigned_at')->whereName('TerraKit Pack')->count();
        $cookieValue = session('vipcookie', '');
        return view('pack-buy-all')->with(['availableTerraKit' => $packCountTerraKit, 'availableStandard' => $packCountStandard, 'availableDeluxe' => $packCountDeluxe, 'availableFlobit' => $packCountFlobit, 'vipcookie' => $cookieValue]);
    }

    public function sport(Request $request)
    {

        if(auth()->check()){
            return SportvatarPack::select('flow_id', 'signature')->whereName('Genesis Free Pack')->whereFlowAddress(auth()->user()->email)->orderBy('flow_id', 'desc')->get();
        } else {
            return [];
        }
    }

    public function reserve(Request $request)
    {
        $packType = $request->get('type');
        $pack = null;

        // Reservations require a wallet session that was verified at login
        // (the posted address is no longer trusted).
        $address = $this->authenticatedFlowAddress();
        if(!$address){
            return response()->json(['packId' => null, 'signature' => null, 'processing' => false, 'loginRequired' => true], 401);
        }

        if($packType && ($packType == 'deluxe' || $packType == 'standard' || $packType == 'flobit' || $packType == 'terrakit' || $packType == 'dustpack')){
            $packName = ($packType == 'deluxe') ? 'Genesis Deluxe Pack' : 'Genesis Standard Pack';
            $packName = ($packType == 'flobit') ? 'Flobit Pack' : $packName;
            $packName = ($packType == 'terrakit') ? 'TerraKit Pack' : $packName;
            $packName = ($packType == 'dustpack') ? 'Dust Flobit Pack' : $packName;

            $userId = auth()->id();

            $pack = Pack::readyToSell()->whereName($packName)->where('user_id', $userId)->where('assigned_at', '>', Carbon::now()->subtract('minutes', 1))->first();

            if(!$pack || true){

                Pack::readyToSell()->whereNull('user_id')->whereNull('assigned_at')->whereName($packName)->limit(1)->update([
                    'user_id' => $userId,
                    'assigned_at' => Carbon::now()
                ]);
                $pack = Pack::readyToSell()->whereName($packName)->where('user_id', $userId)->orderBy('assigned_at', 'desc')->first();

            }

        }

        if($pack && config('flovatar.packsV2Enabled')) {
            // Packs v2: fresh authorisation bound to this wallet and payment kind, expiring
            // (FlovatarPackVerifier on-chain). The signer key is not an account key.
            $auth = app(FlovatarPackAuthorizer::class)->authorize(
                FlovatarPackAuthorizer::kindFor((string) $pack->name, $request->get('wallet') === 'dapper' ? 'dapper' : null),
                (int) $pack->flow_id,
                $address
            );
            return [
                'version' => 2,
                'packId' => $pack->flow_id,
                'signature' => $auth['signature'],
                'expiry' => $auth['expiry'],
                'kind' => $auth['kind'],
                'processing' => false
            ];
        }

        if($pack) {
            return [
                'packId' => $pack->flow_id,
                'signature' => $pack->signature,
                'processing' => false
            ];
        } else {
            return [
                'packId' => null,
                'signature' => null,
                'processing' => false
            ];
        }
    }


    public function purchased(Request $request){
        $request->session()->flush();
        $request->session()->regenerateToken();
        return ['result' => true];
    }
}
