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

/**
 * Tests for the plugin's JWT signing and verification.
 *
 * verify() guards api_sdk.php, which is the one endpoint this plugin trusts
 * without a Moodle session: whoever produces an acceptable token can write an
 * exam result. These tests pin the rejections, since a bug that makes verify()
 * too permissive looks exactly like a working integration.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_proctor\jwt
 */
final class jwt_test extends \advanced_testcase {
    /** @var string A secret to sign with. */
    const SECRET = 'test-secret-value';

    /**
     * A token this plugin signed must verify against the same secret.
     */
    public function test_round_trip(): void {
        $token = jwt::sign(['exp' => time() + 30, 'sessionId' => 'abc'], self::SECRET);

        $claims = jwt::verify($token, self::SECRET);

        $this->assertIsArray($claims);
        $this->assertSame('abc', $claims['sessionId']);
    }

    /**
     * The token is the three base64url segments the spec calls for, and the
     * header names HS256 -- what Constructor's side parses.
     */
    public function test_sign_produces_a_compact_hs256_token(): void {
        $parts = explode('.', jwt::sign(['exp' => time() + 30], self::SECRET));

        $this->assertCount(3, $parts);
        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        $this->assertSame('HS256', $header['alg']);
        $this->assertSame('JWT', $header['typ']);
    }

    /**
     * A token signed with a different secret must be refused.
     */
    public function test_wrong_secret_is_refused(): void {
        $token = jwt::sign(['exp' => time() + 30], 'a-different-secret');

        $this->assertNull(jwt::verify($token, self::SECRET));
    }

    /**
     * An empty configured secret refuses everything.
     *
     * Without this an unconfigured site would accept a token signed with '',
     * which anyone can produce.
     */
    public function test_empty_secret_refuses_everything(): void {
        $token = jwt::sign(['exp' => time() + 30], '');

        $this->assertNull(jwt::verify($token, ''));
    }

    /**
     * "alg": "none" must not be honoured.
     *
     * The algorithm is taken from the plugin's own list, never from the token --
     * trusting the token's header is the classic JWT forgery.
     */
    public function test_alg_none_is_refused(): void {
        $b64 = static function (array $data): string {
            return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        };
        $forged = $b64(['alg' => 'none', 'typ' => 'JWT']) . '.' . $b64(['exp' => time() + 30]) . '.';

        $this->assertNull(jwt::verify($forged, self::SECRET));
    }

    /**
     * A tampered payload invalidates the signature.
     */
    public function test_tampered_payload_is_refused(): void {
        $parts = explode('.', jwt::sign(['exp' => time() + 30, 'score' => 10], self::SECRET));
        $parts[1] = rtrim(strtr(base64_encode(json_encode(['exp' => time() + 30, 'score' => 100])), '+/', '-_'), '=');

        $this->assertNull(jwt::verify(implode('.', $parts), self::SECRET));
    }

    /**
     * An expired token is refused, and a token inside the clock-skew allowance
     * is still accepted.
     *
     * The allowance exists because the two servers' clocks differ; without it a
     * correctly-minted token would be rejected on a site running a minute fast.
     */
    public function test_expiry_is_enforced_with_clock_skew(): void {
        $expired = jwt::sign(['exp' => time() - jwt::CLOCK_SKEW - 30], self::SECRET);
        $this->assertNull(jwt::verify($expired, self::SECRET), 'a stale token must be refused');

        $recent = jwt::sign(['exp' => time() - 5], self::SECRET);
        $this->assertIsArray(jwt::verify($recent, self::SECRET), 'clock skew must be tolerated');
    }

    /**
     * A token with no exp claim is refused: the integration always mints one, so
     * its absence means the token was not built the documented way -- and a token
     * without an expiry never stops being replayable.
     */
    public function test_missing_expiry_is_refused(): void {
        $this->assertNull(jwt::verify(jwt::sign(['sessionId' => 'abc'], self::SECRET), self::SECRET));
    }

    /**
     * Structurally broken input is refused rather than raising.
     *
     * @dataProvider malformed_token_provider
     * @param string $token The malformed token.
     */
    public function test_malformed_tokens_are_refused(string $token): void {
        $this->assertNull(jwt::verify($token, self::SECRET));
    }

    /**
     * Shapes an attacker (or a broken client) can send.
     *
     * @return array[]
     */
    public static function malformed_token_provider(): array {
        return [
            'empty' => [''],
            'not a token' => ['nonsense'],
            'two segments' => ['aaa.bbb'],
            'four segments' => ['aaa.bbb.ccc.ddd'],
            'empty segments' => ['..'],
            'non-base64' => ['!!!.???.***'],
        ];
    }

    /**
     * request_token() mints something verify() accepts, carrying only an expiry.
     */
    public function test_request_token_is_short_lived_and_valid(): void {
        $claims = jwt::verify(jwt::request_token(self::SECRET), self::SECRET);

        $this->assertIsArray($claims);
        $this->assertSame(['exp'], array_keys($claims), 'the signature is the proof; nothing else belongs here');
        $this->assertGreaterThan(time(), $claims['exp']);
    }
}
