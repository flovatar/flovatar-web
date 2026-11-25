<?php

namespace App\Services\Passkey;

use GMP;
use RuntimeException;

/**
 * Minimal NIST P-256 (secp256r1) arithmetic with GMP, used for ECDSA public-key RECOVERY:
 * from one signature (r, s) over a message we get the (up to 4, in practice 2) public keys that verify it.
 *
 * Passkey assertions are ECDSA-P256/SHA-256 signatures over authenticatorData || SHA-256(clientDataJSON), so after a
 * database loss the server can still find the user's Flow account: recover the candidates, look them up in the Flow
 * key indexer, and confirm with the on-chain key (AccountRecovery). Public data only; no secrets involved.
 */
final class P256
{
    private const P = 'ffffffff00000001000000000000000000000000ffffffffffffffffffffffff';
    private const A = 'ffffffff00000001000000000000000000000000fffffffffffffffffffffffc';
    private const B = '5ac635d8aa3a93e7b3ebbd55769886bc651d06b0cc53b0f63bce3c3e27d2604b';
    private const N = 'ffffffff00000000ffffffffffffffffbce6faada7179e84f3b9cac2fc632551';
    private const GX = '6b17d1f2e12c4247f8bce6e563a440f277037d812deb33a0f4a13945d898c296';
    private const GY = '4fe342e2fe1a7f9b8ee7eb4a7c0f9e162bce33576b315ececbb6406837bf51f5';

    private static function g(string $hex): GMP
    {
        return gmp_init($hex, 16);
    }

    private static function mod(GMP $a, GMP $m): GMP
    {
        $r = gmp_mod($a, $m);

        return gmp_cmp($r, 0) < 0 ? gmp_add($r, $m) : $r;
    }

    /** @param array{0:GMP,1:GMP}|null $p  null = point at infinity */
    private static function double(?array $p): ?array
    {
        if ($p === null || gmp_cmp($p[1], 0) === 0) {
            return null;
        }
        $P = self::g(self::P);
        $l = self::mod(gmp_mul(gmp_add(gmp_mul(3, gmp_mul($p[0], $p[0])), self::g(self::A)), gmp_invert(gmp_mul(2, $p[1]), $P)), $P);
        $x = self::mod(gmp_sub(gmp_mul($l, $l), gmp_mul(2, $p[0])), $P);
        $y = self::mod(gmp_sub(gmp_mul($l, gmp_sub($p[0], $x)), $p[1]), $P);

        return [$x, $y];
    }

    private static function add(?array $p, ?array $q): ?array
    {
        if ($p === null) {
            return $q;
        }
        if ($q === null) {
            return $p;
        }
        $P = self::g(self::P);
        if (gmp_cmp($p[0], $q[0]) === 0) {
            return gmp_cmp(self::mod(gmp_add($p[1], $q[1]), $P), 0) === 0 ? null : self::double($p);
        }
        $l = self::mod(gmp_mul(gmp_sub($q[1], $p[1]), gmp_invert(self::mod(gmp_sub($q[0], $p[0]), $P), $P)), $P);
        $x = self::mod(gmp_sub(gmp_sub(gmp_mul($l, $l), $p[0]), $q[0]), $P);
        $y = self::mod(gmp_sub(gmp_mul($l, gmp_sub($p[0], $x)), $p[1]), $P);

        return [$x, $y];
    }

    private static function mul(GMP $k, ?array $p): ?array
    {
        $r = null;
        $bits = gmp_strval($k, 2);
        for ($i = 0, $n = strlen($bits); $i < $n; $i++) {
            $r = self::double($r);
            if ($bits[$i] === '1') {
                $r = self::add($r, $p);
            }
        }

        return $r;
    }

    /** k·G as [x, y] (the mock KMS signer in tests / testnet checks uses it for ECDSA over a precomputed digest). */
    public static function multiplyBase(GMP $k): array
    {
        $p = self::mul($k, [self::g(self::GX), self::g(self::GY)]);
        if ($p === null) {
            throw new RuntimeException('k·G is the point at infinity');
        }

        return $p;
    }

    public static function isOnCurve(GMP $x, GMP $y): bool
    {
        $P = self::g(self::P);
        $lhs = self::mod(gmp_mul($y, $y), $P);
        $rhs = self::mod(gmp_add(gmp_add(gmp_powm($x, 3, $P), gmp_mul(self::g(self::A), $x)), self::g(self::B)), $P);

        return gmp_cmp($lhs, $rhs) === 0;
    }

    /**
     * Public keys (128 hex chars X||Y each) that verify the ECDSA-P256/SHA-256 signature $rawSig (r||s, 64 bytes) over
     * $message. Usually 2 candidates (both parities of R); the right one is decided outside (key indexer / chain).
     *
     * @return string[]
     */
    public static function recoverPublicKeys(string $message, string $rawSig): array
    {
        if (strlen($rawSig) !== 64) {
            throw new RuntimeException('Expected a 64-byte r||s signature');
        }
        $P = self::g(self::P);
        $N = self::g(self::N);
        $r = gmp_init(bin2hex(substr($rawSig, 0, 32)), 16);
        $s = gmp_init(bin2hex(substr($rawSig, 32, 32)), 16);
        if (gmp_cmp($r, 1) < 0 || gmp_cmp($r, $N) >= 0 || gmp_cmp($s, 1) < 0 || gmp_cmp($s, $N) >= 0) {
            throw new RuntimeException('Signature values out of range');
        }
        $e = gmp_init(hash('sha256', $message), 16);
        $rInv = gmp_invert($r, $N);
        $u1 = self::mod(gmp_neg(gmp_mul($e, $rInv)), $N);
        $u2 = self::mod(gmp_mul($s, $rInv), $N);
        $G = [self::g(self::GX), self::g(self::GY)];
        $u1G = self::mul($u1, $G);
        $exp = gmp_div_q(gmp_add($P, 1), 4); // p ≡ 3 (mod 4)
        $out = [];
        foreach ([0, 1] as $j) {
            $x = gmp_add($r, gmp_mul($j, $N));
            if (gmp_cmp($x, $P) >= 0) {
                continue;
            }
            $alpha = self::mod(gmp_add(gmp_add(gmp_powm($x, 3, $P), gmp_mul(self::g(self::A), $x)), self::g(self::B)), $P);
            $beta = gmp_powm($alpha, $exp, $P);
            if (gmp_cmp(gmp_powm($beta, 2, $P), $alpha) !== 0) {
                continue; // r is not the x of a curve point
            }
            foreach ([$beta, gmp_sub($P, $beta)] as $y) {
                $Q = self::add($u1G, self::mul($u2, [$x, $y]));
                if ($Q === null) {
                    continue;
                }
                $hex = str_pad(gmp_strval($Q[0], 16), 64, '0', STR_PAD_LEFT).str_pad(gmp_strval($Q[1], 16), 64, '0', STR_PAD_LEFT);
                $out[$hex] = true;
            }
        }

        return array_keys($out);
    }
}
