<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace availability_proctor;

defined('MOODLE_INTERNAL') || die();

/**
 * HS256 JWT signing.
 *
 * Uses hash_hmac rather than the Firebase\JWT library that Moodle bundles.
 * Signing HS256 is a dozen lines, and the library's API moved between versions
 * in a way that forced the reference availability_proctor plugin to branch on
 * class_exists('\Firebase\JWT\Key'). Not depending on it removes that coupling.
 *
 * Signing is used for the session token and for server-to-server requests;
 * verification is used to authenticate the inbound result webhook.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jwt {
    /**
     * Seconds a server-to-server token stays valid.
     *
     * The Developer Guide specifies "current time + 30 seconds" for these tokens,
     * which is short enough that a captured one is of little use.
     *
     * @var int
     */
    const REQUEST_TOKEN_TTL = 30;

    /**
     * Leeway allowed when checking an inbound token's expiry, in seconds.
     *
     * Covers ordinary clock drift between this server and Constructor's. Without
     * it, a token minted with a 30-second life can arrive already expired on a
     * machine whose clock is a few seconds ahead.
     *
     * @var int
     */
    const CLOCK_SKEW = 60;

    /**
     * Sign a payload as a compact-serialised JWT using HS256.
     *
     * @param array $payload The claims.
     * @param string $secret The jwt_secret from the plugin settings.
     * @return string The signed token.
     * @throws \moodle_exception When the payload cannot be encoded.
     */
    public static function sign(array $payload, string $secret): string {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        // JSON_UNESCAPED_SLASHES and JSON_UNESCAPED_UNICODE keep URLs and
        // non-ASCII names readable in the token. Neither changes how the server
        // parses it, but both make a captured token far easier to debug.
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        $encodedheader = json_encode($header, $flags);
        $encodedpayload = json_encode($payload, $flags);

        if ($encodedheader === false || $encodedpayload === false) {
            throw new \moodle_exception('error_jwtencode', 'availability_proctor');
        }

        $signinginput = self::base64url($encodedheader) . '.' . self::base64url($encodedpayload);

        // Binary output, then base64url: hash_hmac's hex output would be a valid
        // signature of the wrong bytes and every token would be rejected.
        $signature = hash_hmac('sha256', $signinginput, $secret, true);

        return $signinginput . '.' . self::base64url($signature);
    }

    /**
     * Sign a short-lived token for a server-to-server request.
     *
     * The payload carries nothing but the expiry: the signature is what proves the
     * caller holds jwt_secret, and the expiry is what stops a captured token being
     * replayed later.
     *
     * @param string $secret The jwt_secret from the plugin settings.
     * @return string
     */
    public static function request_token(string $secret): string {
        return self::sign(['exp' => time() + self::REQUEST_TOKEN_TTL], $secret);
    }

    /**
     * Verify an inbound token and return its claims.
     *
     * Used for the result webhook, which is the one place this plugin trusts a
     * request that carries no Moodle session.
     *
     * @param string $token The compact-serialised token.
     * @param string $secret The jwt_secret from the plugin settings.
     * @return array|null The claims, or null when the token is not acceptable.
     */
    public static function verify(string $token, string $secret): ?array {
        if ($secret === '') {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedheader, $encodedpayload, $encodedsignature] = $parts;

        $header = json_decode((string) self::base64url_decode($encodedheader), true);
        if (!is_array($header)) {
            return null;
        }

        // Only HS256 is accepted, and the algorithm comes from this list rather than
        // from the token. Trusting the token's own alg header is how "alg: none"
        // and algorithm-confusion attacks get in.
        if (($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        $expected = hash_hmac('sha256', $encodedheader . '.' . $encodedpayload, $secret, true);
        $actual = self::base64url_decode($encodedsignature);

        // Constant-time comparison: a length-or-content shortcut would leak enough
        // timing information to forge a signature byte by byte.
        if ($actual === null || !hash_equals($expected, $actual)) {
            return null;
        }

        $claims = json_decode((string) self::base64url_decode($encodedpayload), true);
        if (!is_array($claims)) {
            return null;
        }

        // The guide mints these with a 30-second life, so an absent exp means the
        // token was not built the way the integration specifies.
        if (!isset($claims['exp']) || !is_numeric($claims['exp'])) {
            return null;
        }

        if ((int) $claims['exp'] + self::CLOCK_SKEW < time()) {
            return null;
        }

        return $claims;
    }

    /**
     * Base64url-encode, per RFC 7515: standard base64 with +/ swapped for -_
     * and the padding removed.
     *
     * @param string $data Raw bytes.
     * @return string
     */
    protected static function base64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decode a base64url segment.
     *
     * @param string $data Encoded segment.
     * @return string|null Raw bytes, or null when the segment is not valid base64.
     */
    protected static function base64url_decode(string $data): ?string {
        // Padding is stripped when encoding and has to be restored before decoding.
        $padded = str_pad(strtr($data, '-_', '+/'), (int) (ceil(strlen($data) / 4) * 4), '=');

        // Strict mode, so stray characters are a failure rather than being skipped.
        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}
