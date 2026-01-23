<?php
namespace App\Config;

/**
 * Inicialização segura da sessão
 * Este arquivo deve ser incluído no início da aplicação
 */
class Session {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function regenerate() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
    
    public static function destroy() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
