<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use SyntaxDevTeam\MiniPortal\Core\Security\Contract\IdentityProvider;
use SyntaxDevTeam\MiniPortal\Core\Security\ExternalIdentity;

final class GoogleIdentityProvider extends AbstractOAuthProvider implements IdentityProvider
{
    public function name(): string { return 'google'; }
    public function label(): string { return 'Google'; }

    public function authorizationUrl(string $state, string $codeChallenge, string $nonce): string
    {
        return $this->url('https://accounts.google.com/o/oauth2/v2/auth', [
            'response_type' => 'code', 'client_id' => $this->clientId, 'redirect_uri' => $this->callbackUrl,
            'scope' => 'openid email profile', 'state' => $state, 'nonce' => $nonce,
            'code_challenge' => $codeChallenge, 'code_challenge_method' => 'S256',
        ]);
    }

    public function resolveIdentity(string $code, string $codeVerifier, string $nonce): ExternalIdentity
    {
        $token = $this->postForm('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId, 'client_secret' => $this->clientSecret, 'code' => $code,
            'code_verifier' => $codeVerifier, 'grant_type' => 'authorization_code', 'redirect_uri' => $this->callbackUrl,
        ]);
        $idToken = $token['id_token'] ?? null;
        if (!is_string($idToken) || $idToken === '') {
            throw new \RuntimeException('Google did not return an ID token.');
        }
        $claims = $this->verifyIdToken($idToken, $nonce);
        $subject = $claims['sub'] ?? null;
        $name = $claims['name'] ?? $claims['email'] ?? null;
        if (!is_string($subject) || $subject === '' || !is_string($name) || $name === '') {
            throw new \RuntimeException('Google ID token has no stable identity.');
        }
        return new ExternalIdentity('google', $subject, $name,
            is_string($claims['email'] ?? null) ? $claims['email'] : null,
            ($claims['email_verified'] ?? false) === true,
            is_string($claims['picture'] ?? null) ? $claims['picture'] : null);
    }

    /** @return array<string, mixed> */
    private function verifyIdToken(string $token, string $nonce): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Google ID token has an invalid format.');
        }
        [$headerPart, $claimsPart, $signaturePart] = $parts;
        $header = $this->decodeSegment($headerPart);
        $claims = $this->decodeSegment($claimsPart);
        $kid = $header['kid'] ?? null;
        if (($header['alg'] ?? null) !== 'RS256' || !is_string($kid)) {
            throw new \RuntimeException('Google ID token uses a forbidden algorithm.');
        }
        $certificates = $this->getJson('https://www.googleapis.com/oauth2/v1/certs');
        $certificate = $certificates[$kid] ?? null;
        $signature = $this->base64UrlDecode($signaturePart);
        if (!is_string($certificate) || $signature === false
            || openssl_verify($headerPart . '.' . $claimsPart, $signature, $certificate, OPENSSL_ALGO_SHA256) !== 1) {
            throw new \RuntimeException('Google ID token signature is invalid.');
        }
        $now = time();
        $audience = $claims['aud'] ?? null;
        $audienceValid = is_string($audience)
            ? hash_equals($this->clientId, $audience)
            : is_array($audience) && in_array($this->clientId, $audience, true);
        if (!in_array($claims['iss'] ?? null, ['https://accounts.google.com', 'accounts.google.com'], true)
            || !$audienceValid
            || (is_array($audience) && count($audience) > 1
                && (!is_string($claims['azp'] ?? null) || !hash_equals($this->clientId, $claims['azp'])))
            || !is_int($claims['exp'] ?? null) || $claims['exp'] < $now
            || !is_int($claims['iat'] ?? null) || $claims['iat'] > $now + 60
            || (isset($claims['nbf']) && (!is_int($claims['nbf']) || $claims['nbf'] > $now + 60))
            || !is_string($claims['nonce'] ?? null) || !hash_equals($nonce, $claims['nonce'])) {
            throw new \RuntimeException('Google ID token claims are invalid.');
        }
        return $claims;
    }

    /** @return array<string, mixed> */
    private function decodeSegment(string $segment): array
    {
        $decoded = $this->base64UrlDecode($segment);
        if ($decoded === false) {
            throw new \RuntimeException('Google ID token encoding is invalid.');
        }
        try {
            $data = json_decode($decoded, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Google ID token JSON is invalid.', previous: $exception);
        }
        if (!is_array($data)) {
            throw new \RuntimeException('Google ID token payload is invalid.');
        }
        $result = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                throw new \RuntimeException('Google ID token payload is invalid.');
            }
            $result[$key] = $value;
        }
        return $result;
    }

    private function base64UrlDecode(string $value): string|false
    {
        $value = strtr($value, '-_', '+/');
        return base64_decode($value . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }
}
