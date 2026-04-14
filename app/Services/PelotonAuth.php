<?php

namespace App\Services;

use Illuminate\Cache\Repository;

/**
 * PelotonAuth
 *
 * A PHP class for automated Peloton OAuth token management.
 * Handles the full auth lifecycle - login, token refresh, and credential
 * fallback - without requiring an authorised Peloton API endpoint.
 *
 * @package     ultra-nick/peloton-auth
 * @author      ultra-nick
 * @license     MIT
 * @link        https://github.com/ultra-nick/peloton-auth
 */
class PelotonAuth
{
    public const string CACHE_KEY = 'peloton.tokens';

    private const AUTH_DOMAIN  = 'auth.onepeloton.com';
    private const CLIENT_ID    = 'WVoJxVDdPoFx4RNewvvg6ch2mZ7bwnsM';
    private const AUDIENCE     = 'https://api.onepeloton.com/';
    private const SCOPE        = 'openid email peloton-api.members:default offline_access';
    private const REDIRECT_URI = 'https://members.onepeloton.com/callback';
    private const AUTH0_CLIENT = 'eyJuYW1lIjoiYXV0aDBfc3BhLWpzIiwidmVyc2lvbiI6IjIuMS4zIn0=';

    private string $username;
    private string $password;
    private Repository $cache;
    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private ?int $tokenExpiry = null;
    private ?string $userId = null;

    /** In-memory cookie store: name => value */
    private array $cookies = [];

    /**
     * @param string $username Peloton account email
     * @param string $password Peloton account password
     * @param string|null $accessToken Previously stored access token (optional)
     * @param string|null $refreshToken Previously stored refresh token (optional)
     */
    public function __construct(
        string     $username,
        string     $password,
        Repository $cache
    )
    {
        $this->username = $username;
        $this->password = $password;
        $this->cache    = $cache;
    }

    public function setTokens(array $tokens): void
    {
        $this->accessToken  = $tokens['access_token'];
        $this->refreshToken = $tokens['refresh_token'];

        $this->tokenExpiry = $this->extractExpiry($this->accessToken);
        $this->userId      = $this->extractUserId($this->accessToken);
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function authenticate(): void
    {
        if ($this->cache->has(self::CACHE_KEY)) {
            $this->setTokens(json_decode($this->cache->get(self::CACHE_KEY), true));
        }
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Returns a valid token data object, refreshing or re-authenticating as needed.
     *
     * Priority order:
     *   1. Existing access token still valid  -> return immediately (no network call)
     *   2. Access token expired, refresh token present -> attempt token refresh
     *   3. No tokens, or refresh fails -> full PKCE login with username/password
     *
     * @throws \RuntimeException if all authentication attempts fail
     */
    public function getTokenData(): object
    {
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return $this->buildTokenData();
        }

        if ($this->refreshToken) {
            try {
                $this->refreshAccessToken();
                return $this->buildTokenData();
            } catch (\RuntimeException $e) {
                // Refresh token expired or revoked -- fall through to full login
            }
        }

        $this->pkceLogin();
        return $this->buildTokenData();
    }

    // =========================================================================
    // Auth flows
    // =========================================================================

    /**
     * Obtain a new access token using the stored refresh token.
     */
    private function refreshAccessToken(): void
    {
        $response = $this->postJson(
            'https://' . self::AUTH_DOMAIN . '/oauth/token',
            [
                'grant_type'    => 'refresh_token',
                'client_id'     => self::CLIENT_ID,
                'refresh_token' => $this->refreshToken,
                'scope'         => self::SCOPE,
            ]
        );

        $this->applyTokenResponse($response);
    }

    /**
     * Authenticate from scratch using the OAuth 2.0 PKCE flow.
     *
     * Steps:
     *   1. GET /authorize  -- establishes Auth0 session and CSRF cookie
     *   2. POST credentials -- submits username/password to Auth0
     *   3. Submit callback form -- follows the redirect/form chain to the callback URL
     *   4. Exchange code    -- trades the authorization code for access + refresh tokens
     */
    private function pkceLogin(): void
    {
        $verifier  = $this->generateRandomString(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $state     = $this->generateRandomString(32);
        $nonce     = $this->generateRandomString(32);

        $authorizeURL = $this->buildAuthorizeURL($challenge, $state, $nonce);
        [$loginURL, $auth0State] = $this->initiateAuthFlow($authorizeURL);

        // Auth0 issues its own state value in the redirect; echo it back exactly
        if ($auth0State !== '') {
            $state = $auth0State;
        }

        $nextURL  = $this->submitCredentials($loginURL, $state, $nonce, $challenge);
        $code     = $this->followRedirectsToCode($nextURL);
        $response = $this->exchangeCodeForToken($code, $verifier);

        $this->applyTokenResponse($response);
    }

    // =========================================================================
    // PKCE steps
    // =========================================================================

    private function buildAuthorizeURL(string $challenge, string $state, string $nonce): string
    {
        $params = http_build_query([
            'client_id'             => self::CLIENT_ID,
            'audience'              => self::AUDIENCE,
            'scope'                 => self::SCOPE,
            'response_type'         => 'code',
            'response_mode'         => 'query',
            'redirect_uri'          => self::REDIRECT_URI,
            'state'                 => $state,
            'nonce'                 => $nonce,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
            'auth0Client'           => self::AUTH0_CLIENT,
        ]);

        return 'https://' . self::AUTH_DOMAIN . '/authorize?' . $params;
    }

    /**
     * Follow the /authorize redirect chain to establish Auth0 session cookies.
     *
     * Auth0 sets its own state value in the redirect URL; we capture it here
     * so it can be echoed back verbatim during credential submission.
     *
     * @return array{0: string, 1: string}  [loginPageURL, auth0State]
     */
    private function initiateAuthFlow(string $authorizeURL): array
    {
        $url        = $authorizeURL;
        $loginURL   = '';
        $auth0State = '';

        for ($i = 0; $i < 10; $i++) {
            $response = $this->request($url);
            $this->parseSetCookieHeaders($response['headers']);

            if ($response['status'] >= 300 && $response['status'] < 400) {
                $location = $response['headers']['location'] ?? '';
                if (!$location) {
                    break;
                }
                $location = $this->makeAbsolute($location, self::AUTH_DOMAIN);

                if ($loginURL === '') {
                    $loginURL = $location;
                    $parsed   = parse_url($location);
                    if (!empty($parsed['query'])) {
                        parse_str($parsed['query'], $params);
                        $auth0State = $params['state'] ?? '';
                    }
                }

                $url = $location;
            } else {
                break;
            }
        }

        if (empty($this->cookies['_csrf'])) {
            throw new \RuntimeException(
                'Failed to obtain CSRF token from Auth0. ' .
                'Verify that the Peloton credentials are correct.'
            );
        }

        return [$loginURL ?: $url, $auth0State];
    }

    /**
     * POST username and password to Auth0.
     *
     * Auth0 responds with either:
     *   - A Location redirect header (unusual path), or
     *   - An HTML page containing a hidden form to submit to the callback endpoint
     *
     * Returns the next URL in the chain.
     */
    private function submitCredentials(
        string $loginURL,
        string $state,
        string $nonce,
        string $challenge
    ): string
    {
        $payload = json_encode([
            'client_id'             => self::CLIENT_ID,
            'redirect_uri'          => self::REDIRECT_URI,
            'tenant'                => 'peloton-prod',
            'response_type'         => 'code',
            'scope'                 => self::SCOPE,
            'audience'              => self::AUDIENCE,
            '_csrf'                 => $this->cookies['_csrf'],
            'state'                 => $state,
            '_intstate'             => 'deprecated',
            'nonce'                 => $nonce,
            'username'              => $this->username,
            'password'              => $this->password,
            'connection'            => 'pelo-user-password',
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        $endpoint = 'https://' . self::AUTH_DOMAIN . '/usernamepassword/login';

        $response = $this->request($endpoint, 'POST', $payload, 'application/json', [
            'Origin: https://' . self::AUTH_DOMAIN,
            'Referer: ' . $loginURL,
            'Auth0-Client: eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTQuMyJ9',
        ]);
        $this->parseSetCookieHeaders($response['headers']);

        if (!empty($response['headers']['location'])) {
            return $this->makeAbsolute($response['headers']['location'], self::AUTH_DOMAIN);
        }

        if ($response['status'] >= 400) {
            throw new \RuntimeException(
                "Credential submission failed (HTTP {$response['status']}): " .
                substr($response['body'], 0, 500)
            );
        }

        [$action, $fields] = $this->parseHiddenForm($response['body']);
        return $this->submitHiddenForm($action, $fields);
    }

    /**
     * POST the hidden callback form that Auth0 returns after credential submission.
     */
    private function submitHiddenForm(string $action, array $fields): string
    {
        $action   = $this->makeAbsolute($action, self::AUTH_DOMAIN);
        $response = $this->request($action, 'POST', http_build_query($fields), 'application/x-www-form-urlencoded');
        $this->parseSetCookieHeaders($response['headers']);

        $location = $response['headers']['location'] ?? '';
        if (!$location) {
            throw new \RuntimeException('Hidden form submission did not produce a redirect');
        }

        return $this->makeAbsolute($location, self::AUTH_DOMAIN);
    }

    /**
     * Follow redirects until we reach a callback URL containing ?code=.
     */
    private function followRedirectsToCode(string $startURL): string
    {
        $url = $startURL;

        for ($i = 0; $i < 10; $i++) {
            $code = $this->extractCodeFromURL($url);
            if ($code !== null) {
                return $code;
            }

            $response = $this->request($url);
            $this->parseSetCookieHeaders($response['headers']);

            if ($response['status'] >= 300 && $response['status'] < 400) {
                $location = $response['headers']['location'] ?? '';
                if (!$location) {
                    break;
                }
                $location = $this->makeAbsolute($location, self::AUTH_DOMAIN);

                $code = $this->extractCodeFromURL($location);
                if ($code !== null) {
                    return $code;
                }

                $url = $location;
            } else {
                break;
            }
        }

        throw new \RuntimeException(
            'Authorization code not found in redirect chain. ' .
            'Credentials may be incorrect, or Peloton may have changed their auth flow.'
        );
    }

    /**
     * Exchange the authorization code for access and refresh tokens.
     */
    private function exchangeCodeForToken(string $code, string $verifier): array
    {
        return $this->postJson(
            'https://' . self::AUTH_DOMAIN . '/oauth/token',
            [
                'grant_type'    => 'authorization_code',
                'client_id'     => self::CLIENT_ID,
                'code_verifier' => $verifier,
                'code'          => $code,
                'redirect_uri'  => self::REDIRECT_URI,
            ]
        );
    }

    // =========================================================================
    // HTTP
    // =========================================================================

    /**
     * Make an HTTP request, manually handling cookies and redirects.
     *
     * @param string $url
     * @param string $method GET or POST
     * @param string|null $body Raw request body
     * @param string $contentType
     * @param string[] $extraHeaders Additional raw header strings
     * @return array{status: int, headers: array<string,string>, body: string}
     */
    private function request(
        string  $url,
        string  $method = 'GET',
        ?string $body = null,
        string  $contentType = 'application/json',
        array   $extraHeaders = []
    ): array
    {
        $ch = curl_init($url);

        $headers = array_merge([
            'Content-Type: ' . $contentType,
            'Accept: application/json, text/html, */*',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0',
        ], $extraHeaders);

        if (!empty($this->cookies)) {
            $parts = [];
            foreach ($this->cookies as $name => $value) {
                $parts[] = $name . '=' . $value;
            }
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $parts));
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        $raw        = curl_exec($ch);
        $status     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        if ($raw === false) {
            throw new \RuntimeException('cURL request failed: ' . $url);
        }

        return [
            'status'  => $status,
            'headers' => $this->parseResponseHeaders(substr($raw, 0, $headerSize)),
            'body'    => substr($raw, $headerSize),
        ];
    }

    /**
     * POST JSON-encoded data and return the decoded response array.
     */
    private function postJson(string $url, array $data): array
    {
        $response = $this->request($url, 'POST', json_encode($data));

        if ($response['status'] >= 400) {
            throw new \RuntimeException(
                "Auth0 returned HTTP {$response['status']}: " . substr($response['body'], 0, 300)
            );
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Unexpected non-JSON response from Auth0');
        }

        return $decoded;
    }

    // =========================================================================
    // Cookie handling
    // =========================================================================

    /**
     * Parse Set-Cookie headers from a response into the in-memory cookie store.
     * Multiple Set-Cookie values are joined with "\n" by parseResponseHeaders().
     */
    private function parseSetCookieHeaders(array $headers): void
    {
        $raw = $headers['set-cookie'] ?? '';
        if (!$raw) {
            return;
        }

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if (!$line) {
                continue;
            }
            // Only the first name=value pair matters; everything after ';' is attributes
            $pair = explode('=', trim(explode(';', $line, 2)[0]), 2);
            if (count($pair) === 2) {
                $this->cookies[trim($pair[0])] = trim($pair[1]);
            }
        }
    }

    // =========================================================================
    // Header parsing
    // =========================================================================

    /**
     * Parse raw HTTP response headers into a lowercase-keyed array.
     * Multiple Set-Cookie lines are joined with "\n" to preserve all values.
     */
    private function parseResponseHeaders(string $raw): array
    {
        $result = [];

        foreach (explode("\r\n", $raw) as $line) {
            $line = trim($line);
            if (!$line || str_starts_with($line, 'HTTP/')) {
                continue;
            }

            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }

            $name  = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));

            if ($name === 'set-cookie' && isset($result[$name])) {
                $result[$name] .= "\n" . $value;
            } else {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    // =========================================================================
    // HTML form parser
    // =========================================================================

    /**
     * Extract the action URL and hidden field values from an HTML form.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function parseHiddenForm(string $html): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML($html);

        $action = '';
        $fields = [];

        foreach ($doc->getElementsByTagName('form') as $form) {
            $action = $form->getAttribute('action');
            break;
        }

        foreach ($doc->getElementsByTagName('input') as $input) {
            if (strtolower($input->getAttribute('type')) === 'hidden') {
                $name = $input->getAttribute('name');
                if ($name !== '') {
                    $fields[$name] = $input->getAttribute('value');
                }
            }
        }

        if ($action === '') {
            throw new \RuntimeException(
                'Could not parse Auth0 callback form. ' .
                'Credentials may be incorrect, or the auth flow may have changed.'
            );
        }

        return [$action, $fields];
    }

    // =========================================================================
    // Token helpers
    // =========================================================================

    /**
     * Apply a successful token response to internal state.
     */
    private function applyTokenResponse(array $response): void
    {
        if (empty($response['access_token'])) {
            throw new \RuntimeException('Token response is missing access_token');
        }

        $this->accessToken = $response['access_token'];
        $this->tokenExpiry = $this->extractExpiry($this->accessToken);
        $this->userId      = $this->extractUserId($this->accessToken);

        // Peloton rotates the refresh token on every use -- always store the latest
        if (!empty($response['refresh_token'])) {
            $this->refreshToken = $response['refresh_token'];
        }
    }

    private function buildTokenData(): object
    {
        return (object) [
            'access_token'  => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_at'    => $this->tokenExpiry,
            'user_id'       => $this->userId,
        ];
    }

    private function extractExpiry(string $token): ?int
    {
        return $this->decodeJwtPayload($token)['exp'] ?? null;
    }

    private function extractUserId(string $token): ?string
    {
        $payload = $this->decodeJwtPayload($token);
        return $payload['http://onepeloton.com/user_id'] ?? $payload['sub'] ?? null;
    }

    private function decodeJwtPayload(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return [];
        }
        $json = base64_decode(strtr($parts[1], '-_', '+/'));
        return json_decode($json, true) ?? [];
    }

    // =========================================================================
    // Utilities
    // =========================================================================

    /**
     * Extract the ?code= value from a URL string, or return null if absent.
     */
    private function extractCodeFromURL(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!$query) {
            return null;
        }
        parse_str($query, $params);
        return !empty($params['code']) ? $params['code'] : null;
    }

    /**
     * Resolve a potentially relative URL against $domain.
     */
    private function makeAbsolute(string $url, string $domain): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }
        return 'https://' . $domain . '/' . ltrim($url, '/');
    }

    /**
     * Generate a cryptographically random URL-safe string of exactly $length characters.
     */
    private function generateRandomString(int $length): string
    {
        $bytes = random_bytes((int) ceil($length * 3 / 4));
        return substr(rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='), 0, $length);
    }
}
