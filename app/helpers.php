<?php

if (!function_exists('gravatar')) {
    /**
     * Generate a Gravatar URL for the given email address.
     *
     * @param string $email
     * @param int $size
     * @param string $default
     * @param string $rating
     * @return string
     */
    function gravatar(string $email, int $size = 80, string $default = 'mp', string $rating = 'g'): string
    {
        $hash = md5(strtolower(trim($email)));
        $query = http_build_query([
            's' => $size,
            'd' => $default,
            'r' => $rating,
        ]);
        
        return "https://www.gravatar.com/avatar/{$hash}?{$query}";
    }
}