<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\I18n;

class LangController extends Controller {
    public function set(Request $request, $locale = null) {
        $loc = is_string($locale) ? $locale : (string) $request->getParam('locale', '');
        $loc = I18n::normalizeLocale($loc);
        I18n::setLocale($loc);

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
