<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\I18n;

class LangController extends Controller {
    public function set(Request $request, $locale = null) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $loc = is_string($locale) ? $locale : (string) $request->getParam('locale', '');
        $loc = I18n::normalizeLocale($loc);

        // Fonte única de verdade: idioma escolhido no site vale para admin + site.
        // Idioma e moeda são independentes: trocar o idioma NÃO altera a moeda.
        // Preserva a moeda já escolhida; se ainda não houver, mantém o padrão BRL.
        $uid = (int) ($_SESSION['usuario_id'] ?? 0);
        $moeda = strtoupper((string) ($_SESSION['admin_pref_moeda'] ?? 'USD'));
        if (!in_array($moeda, ['USD', 'BRL'], true)) {
            $moeda = 'USD';
        }

        \App\Controllers\AdminPreferencesController::savePreference($uid, $loc, $moeda);

        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref !== '') {
            $parts = parse_url($ref);
            $path = is_array($parts) && isset($parts['path']) ? (string) $parts['path'] : '';
            $query = is_array($parts) && isset($parts['query']) ? (string) $parts['query'] : '';
            $redir = $path !== '' ? $path : '/';
            if ($query !== '') {
                $redir .= '?' . $query;
            }
            header('Location: ' . $redir);
            exit;
        }

        header('Location: /');
        exit;
    }
}
