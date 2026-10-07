<?php
/**
 * IP réelle du client derrière le reverse-proxy Docker.
 *
 * REMOTE_ADDR vaut l'IP de la passerelle Docker (ex. 172.18.0.1) quand un proxy
 * est devant le conteneur. On ne lit X-Forwarded-For / X-Real-IP que si la
 * requête vient d'une IP privée/locale (donc du proxy) : un client externe ne
 * peut pas falsifier son IP. Dans X-Forwarded-For on prend la dernière IP
 * publique (celle ajoutée par notre proxy), pas la première (falsifiable).
 */
if (!function_exists('client_ip')) {
    function client_ip_is_public($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    function client_ip() {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        if ($remote === '' || client_ip_is_public($remote)) {
            return $remote !== '' ? $remote : 'unknown';
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $list = array_reverse(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
            foreach ($list as $ip) {
                if (client_ip_is_public($ip)) { return $ip; }
            }
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP']) && client_ip_is_public(trim($_SERVER['HTTP_X_REAL_IP']))) {
            return trim($_SERVER['HTTP_X_REAL_IP']);
        }
        return $remote;
    }
}
