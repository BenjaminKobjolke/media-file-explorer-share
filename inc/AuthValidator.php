<?php
/**
 * Validates incoming Basic Auth credentials against config.
 */
class AuthValidator
{
    /**
     * Check the Authorization header against expected credentials.
     *
     * @param string $expectedUser
     * @param string $expectedPass
     * @return bool True if credentials match.
     */
    public static function validate(string $expectedUser, string $expectedPass): bool
    {
        // PHP-CGI / Apache may expose these directly
        $user = $_SERVER['PHP_AUTH_USER'] ?? null;
        $pass = $_SERVER['PHP_AUTH_PW'] ?? null;

        // Fallback: parse the Authorization header manually
        if ($user === null) {
            $header = $_SERVER['HTTP_AUTHORIZATION']
                ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                ?? '';

            if (stripos($header, 'Basic ') === 0) {
                $decoded = base64_decode(substr($header, 6), true);
                if ($decoded !== false && strpos($decoded, ':') !== false) {
                    [$user, $pass] = explode(':', $decoded, 2);
                }
            }
        }

        return $user === $expectedUser && $pass === $expectedPass;
    }
}
