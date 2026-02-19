<?php
namespace App\Services;

class ViaCepService {
    private int $lastHttpCode = 0;

    public function getLastHttpCode(): int {
        return $this->lastHttpCode;
    }

    private function httpGetJson(string $url): array {
        if (!function_exists('curl_init')) {
            return ['success' => false, 'error' => 'cURL não disponível no servidor'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'User-Agent: brz-new/1.0 (+https://brazilianashop.com)',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = (string) curl_error($ch);
        curl_close($ch);

        $this->lastHttpCode = $httpCode;

        if ($err !== '') {
            return ['success' => false, 'error' => $err];
        }

        $decoded = $body !== false ? json_decode((string) $body, true) : null;
        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'Resposta inválida do ViaCEP', 'raw' => $body, 'http_code' => $httpCode];
        }

        return ['success' => true, 'data' => $decoded, 'http_code' => $httpCode];
    }

    public function consultarPorCep(string $cep): array {
        $digits = preg_replace('/\D+/', '', (string) $cep);
        if ($digits === '' || strlen($digits) !== 8) {
            return ['success' => false, 'error' => 'CEP inválido'];
        }

        $url = 'https://viacep.com.br/ws/' . rawurlencode($digits) . '/json/';
        $res = $this->httpGetJson($url);
        if (empty($res['success'])) {
            return $res;
        }

        $data = $res['data'] ?? [];
        if (is_array($data) && !empty($data['erro'])) {
            return ['success' => false, 'error' => 'CEP não encontrado'];
        }

        return ['success' => true, 'data' => $data];
    }

    public function consultarPorEndereco(string $uf, string $cidade, string $logradouro): array {
        $uf = strtoupper(trim((string) $uf));
        $cidade = trim((string) $cidade);
        $logradouro = trim((string) $logradouro);

        if ($uf === '' || $cidade === '' || $logradouro === '') {
            return ['success' => false, 'error' => 'Endereço insuficiente para buscar CEP (UF/Cidade/Logradouro)'];
        }

        $url = 'https://viacep.com.br/ws/'
            . rawurlencode($uf) . '/'
            . rawurlencode($cidade) . '/' 
            . rawurlencode($logradouro) . '/json/';

        $res = $this->httpGetJson($url);
        if (empty($res['success'])) {
            return $res;
        }

        $data = $res['data'] ?? null;
        if (!is_array($data)) {
            return ['success' => false, 'error' => 'Resposta inválida do ViaCEP'];
        }

        if (isset($data['erro']) && $data['erro']) {
            return ['success' => false, 'error' => 'Endereço não encontrado'];
        }

        // Nesse endpoint, o ViaCEP retorna uma lista.
        return ['success' => true, 'data' => $data];
    }

    private function normalizeText(string $s): string {
        $s = strtolower(trim($s));
        if ($s === '') return '';
        if (function_exists('iconv')) {
            $x = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
            if (is_string($x) && $x !== '') {
                $s = $x;
            }
        }
        $s = preg_replace('/[^a-z0-9\s]/', ' ', $s);
        if ($s === null) $s = '';
        $s = preg_replace('/\s+/', ' ', $s);
        if ($s === null) $s = '';
        return trim($s);
    }

    private function textTokens(string $s): array {
        $s = $this->normalizeText($s);
        if ($s === '') return [];
        $parts = preg_split('/\s+/', $s) ?: [];
        $stop = [
            'rua', 'r', 'avenida', 'av', 'travessa', 'tv', 'alameda', 'praca', 'pc', 'rodovia', 'estrada',
            'logradouro', 'dos', 'das', 'do', 'da', 'de', 'di', 'du', 'e', 'no', 'na', 'num', 'numero',
        ];
        $stop = array_fill_keys($stop, true);
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p === '' || isset($stop[$p])) continue;
            if (strlen($p) <= 1) continue;
            $out[$p] = true;
        }
        return array_keys($out);
    }

    private function scoreLogradouro(string $query, string $candidate): float {
        $qNorm = $this->normalizeText($query);
        $cNorm = $this->normalizeText($candidate);
        if ($qNorm === '' || $cNorm === '') return 0.0;
        if ($qNorm === $cNorm) return 1.0;

        $qTokens = $this->textTokens($qNorm);
        $cTokens = $this->textTokens($cNorm);
        if (empty($qTokens) || empty($cTokens)) {
            return (strpos($cNorm, $qNorm) !== false || strpos($qNorm, $cNorm) !== false) ? 0.4 : 0.0;
        }

        $qSet = array_fill_keys($qTokens, true);
        $cSet = array_fill_keys($cTokens, true);
        $inter = 0;
        foreach ($qSet as $k => $_) {
            if (isset($cSet[$k])) $inter++;
        }
        $union = count($qSet) + count($cSet) - $inter;
        if ($union <= 0) return 0.0;

        $j = $inter / $union;
        if (strpos($cNorm, $qNorm) !== false || strpos($qNorm, $cNorm) !== false) {
            $j += 0.15;
        }
        if ($j > 1.0) $j = 1.0;
        return (float) $j;
    }

    public function pickBestEnderecoCandidate(string $address1, array $results): array {
        $address1 = trim((string) $address1);
        if ($address1 === '' || empty($results)) {
            return ['success' => false, 'error' => 'Sem dados para escolher candidato'];
        }

        $best = null;
        $bestScore = -1.0;
        $secondScore = -1.0;

        foreach ($results as $r) {
            if (!is_array($r)) continue;
            $log = trim((string) ($r['logradouro'] ?? ''));
            $score = $this->scoreLogradouro($address1, $log);
            if ($score > $bestScore) {
                $secondScore = $bestScore;
                $bestScore = $score;
                $best = $r;
            } elseif ($score > $secondScore) {
                $secondScore = $score;
            }
        }

        if (!is_array($best)) {
            return ['success' => false, 'error' => 'Nenhum candidato válido'];
        }

        $minScore = 0.25;
        if ($bestScore < $minScore) {
            return ['success' => false, 'error' => 'Candidatos insuficientes (score baixo)', 'best_score' => $bestScore];
        }

        $ambiguous = false;
        if ($secondScore >= 0 && abs($bestScore - $secondScore) <= 0.05) {
            $ambiguous = true;
        }

        return [
            'success' => true,
            'candidate' => $best,
            'best_score' => $bestScore,
            'second_score' => $secondScore,
            'ambiguous' => $ambiguous,
        ];
    }
}
