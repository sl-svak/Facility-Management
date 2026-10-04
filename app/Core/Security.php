<?php

class Security {
    
    /**
     * Vygeneruje nebo vrátí existující CSRF token pro aktuální session.
     * Využívá bezpečný kryptografický generátor náhodných bajtů.
     */
    public static function getToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Ověří, zda se odeslaný token shoduje s tím v session.
     * Používá hash_equals pro ochranu proti "timing attacks".
     */
    public static function verifyToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Vygeneruje HTML kód pro skryté pole s CSRF tokenem.
     * Tento string se vkládá dovnitř HTML značky <form>.
     */
    public static function csrfField() {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}
