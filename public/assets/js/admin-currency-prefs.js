/**
 * Admin Currency Preferences - Conversão visual global
 * 
 * Detecta valores monetários nas páginas e aplica a preferência de moeda do usuário.
 * Funciona em todas as telas do admin e no site quando admin está logado.
 * 
 * Variáveis globais esperadas (injetadas pelo PHP):
 *   window.ADMIN_PREF_MOEDA  — 'USD' ou 'BRL'
 *   window.USD_BRL_RATE      — taxa de conversão (ex: 5.85)
 */
(function() {
    'use strict';

    var prefMoeda = (window.ADMIN_PREF_MOEDA || 'USD').toUpperCase();
    var taxa = parseFloat(window.USD_BRL_RATE) || 5.85;
    
    // Se não tem preferência definida, não faz nada
    if (!window.ADMIN_PREF_MOEDA) return;

    var fmtBRL = function(v) {
        return 'R$ ' + v.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    };
    var fmtUSD = function(v) {
        return '$ ' + v.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    };

    /**
     * Converte elementos que já têm data-value-brl ou data-value-usd
     * (como os cards do Financeiro, DRE, etc.)
     */
    function convertDataAttributes() {
        // Elementos com valor em BRL — converter para USD se preferência é USD
        document.querySelectorAll('[data-value-brl]').forEach(function(el) {
            if (el.dataset.prefConverted === '1') return;
            var brl = parseFloat(el.getAttribute('data-value-brl')) || 0;
            if (prefMoeda === 'USD') {
                var usd = brl / taxa;
                el.textContent = fmtUSD(usd);
                el.dataset.prefConverted = '1';
            }
        });

        // Elementos com valor em USD — converter para BRL se preferência é BRL
        document.querySelectorAll('[data-value-usd]').forEach(function(el) {
            if (el.dataset.prefConverted === '1') return;
            var usd = parseFloat(el.getAttribute('data-value-usd')) || 0;
            if (prefMoeda === 'BRL') {
                var brl = usd * taxa;
                el.textContent = fmtBRL(brl);
                el.dataset.prefConverted = '1';
            }
        });
    }

    /**
     * Para elementos que mostram valores em moeda (sem data attribute),
     * adiciona um tooltip/equivalente na moeda alternativa.
     * Detecta padrões: "$ 123.45", "R$ 123,45", "US$ 123.45"
     */
    function addEquivalents() {
        // Selecionar células de tabela e spans com valores monetários que não foram processados
        var selector = 'td, .fw-bold, .fs-5, .kpi-value, .summary-value';
        document.querySelectorAll(selector).forEach(function(el) {
            if (el.dataset.prefEquiv === '1') return;
            if (el.querySelector('input, select, button, a')) return; // Não processar elementos interativos
            if (el.closest('.modal, form, .dropdown-menu')) return; // Não processar modais/forms

            var text = (el.textContent || '').trim();
            if (text.length > 30) return; // Textos muito longos não são valores

            // Detectar padrão USD: $ 123.45 ou $ 1,234.45
            var matchUsd = text.match(/^\$\s*([\d,]+\.?\d*)$/);
            if (!matchUsd) matchUsd = text.match(/^US\$\s*([\d,]+\.?\d*)$/);
            
            // Detectar padrão BRL: R$ 123,45 ou R$ 1.234,45
            var matchBrl = text.match(/^R\$\s*([\d.]+,?\d*)$/);

            if (matchUsd && prefMoeda === 'BRL') {
                var val = parseFloat(matchUsd[1].replace(/,/g, '')) || 0;
                if (val > 0 && val < 1000000) {
                    var equiv = val * taxa;
                    el.setAttribute('title', '≈ ' + fmtBRL(equiv));
                    el.dataset.prefEquiv = '1';
                }
            } else if (matchBrl && prefMoeda === 'USD') {
                var valStr = matchBrl[1].replace(/\./g, '').replace(',', '.');
                var val = parseFloat(valStr) || 0;
                if (val > 0 && val < 10000000) {
                    var equiv = val / taxa;
                    el.setAttribute('title', '≈ ' + fmtUSD(equiv));
                    el.dataset.prefEquiv = '1';
                }
            }
        });
    }

    /**
     * Sincronizar com o CurrencyConverter do site (main.php)
     * Se o admin está logado e tem preferência, aplicar no localStorage do site
     */
    function syncSiteCurrency() {
        if (typeof window.CurrencyConverter !== 'undefined') {
            // Se existe o conversor do site, atualizar a moeda preferida
            var siteCurrency = prefMoeda === 'BRL' ? 'BRL' : 'USD';
            if (window.CurrencyConverter.currentCurrency !== siteCurrency) {
                try {
                    localStorage.setItem('selected_currency', siteCurrency);
                    if (typeof window.CurrencyConverter.changeCurrency === 'function') {
                        window.CurrencyConverter.changeCurrency(siteCurrency);
                    }
                } catch (e) {}
            }
        } else {
            // No site sem conversor, setar o localStorage para quando carregar
            try {
                localStorage.setItem('selected_currency', prefMoeda === 'BRL' ? 'BRL' : 'USD');
            } catch (e) {}
        }
    }

    /**
     * Aplicar tudo após DOM carregar
     */
    function apply() {
        convertDataAttributes();
        addEquivalents();
        syncSiteCurrency();
    }

    // Executar quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', apply);
    } else {
        apply();
    }

    // Re-aplicar após conteúdo dinâmico (AJAX, tabs, etc.)
    // Observar mudanças no DOM
    if (typeof MutationObserver !== 'undefined') {
        var debounceTimer = null;
        var observer = new MutationObserver(function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                convertDataAttributes();
                addEquivalents();
            }, 500);
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // Expor API global para uso manual
    window.AdminCurrencyPrefs = {
        prefMoeda: prefMoeda,
        taxa: taxa,
        convert: function(valor, moedaOrigem) {
            moedaOrigem = (moedaOrigem || 'USD').toUpperCase();
            if (moedaOrigem === prefMoeda) return valor;
            if (moedaOrigem === 'USD' && prefMoeda === 'BRL') return valor * taxa;
            if (moedaOrigem === 'BRL' && prefMoeda === 'USD') return valor / taxa;
            return valor;
        },
        format: function(valor, moeda) {
            moeda = (moeda || prefMoeda).toUpperCase();
            return moeda === 'BRL' ? fmtBRL(valor) : fmtUSD(valor);
        },
        reapply: apply
    };
})();
