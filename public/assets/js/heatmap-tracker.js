/**
 * Heatmap + Behavior Tracker
 * Coleta: pageviews, cliques, scroll, tempo, UTMs, sessão, cookies
 */
(function(){
    var endpoint = '/admin/mapa-calor-site/collect';
    
    // Visitor ID (persistent across sessions)
    var visitorId = localStorage.getItem('bz_vid');
    if(!visitorId){
        visitorId = 'v_' + Date.now() + '_' + Math.random().toString(36).substr(2,12);
        localStorage.setItem('bz_vid', visitorId);
    }

    // Session ID (per session)
    var sessionId = sessionStorage.getItem('bz_sid');
    if(!sessionId){
        sessionId = 's_' + Date.now() + '_' + Math.random().toString(36).substr(2,9);
        sessionStorage.setItem('bz_sid', sessionId);
    }

    // Track first visit
    if(!localStorage.getItem('bz_first_visit')){
        localStorage.setItem('bz_first_visit', new Date().toISOString());
    }
    localStorage.setItem('bz_last_visit', new Date().toISOString());

    // Increment visit count
    var visitCount = parseInt(localStorage.getItem('bz_visits') || '0') + 1;
    if(!sessionStorage.getItem('bz_counted')){
        localStorage.setItem('bz_visits', visitCount);
        sessionStorage.setItem('bz_counted', '1');
    }

    // Parse UTMs from URL
    var params = new URLSearchParams(window.location.search);
    var utmSource = params.get('utm_source') || '';
    var utmMedium = params.get('utm_medium') || '';
    var utmCampaign = params.get('utm_campaign') || '';
    var utmContent = params.get('utm_content') || '';
    var utmTerm = params.get('utm_term') || '';

    // Store UTMs if present
    if(utmSource) localStorage.setItem('bz_utm_source', utmSource);
    if(utmCampaign) localStorage.setItem('bz_utm_campaign', utmCampaign);

    // Detect device
    var deviceType = 'desktop';
    if(/Mobi|Android/i.test(navigator.userAgent)) deviceType = 'mobile';
    else if(/Tablet|iPad/i.test(navigator.userAgent)) deviceType = 'tablet';

    // Detect page type
    var pagina = window.location.pathname;
    var pageType = 'other';
    if(pagina === '/' || pagina === '') pageType = 'home';
    else if(pagina.indexOf('/produto/') >= 0) pageType = 'product';
    else if(pagina.indexOf('/produtos') >= 0) pageType = 'catalog';
    else if(pagina.indexOf('/carrinho') >= 0) pageType = 'cart';
    else if(pagina.indexOf('/checkout') >= 0) pageType = 'checkout';
    else if(pagina.indexOf('/grupo') >= 0) pageType = 'group';
    else if(pagina.indexOf('/assessoria') >= 0) pageType = 'service';

    var startTime = Date.now();
    var maxScroll = 0;
    var scrollSent = {};

    function send(data){
        data.visitor_id = visitorId;
        data.session_id = sessionId;
        data.pagina = pagina;
        data.page_type = pageType;
        data.vw = window.innerWidth;
        data.vh = window.innerHeight;
        data.device_type = deviceType;
        data.referrer = document.referrer || '';
        data.utm_source = utmSource || localStorage.getItem('bz_utm_source') || '';
        data.utm_medium = utmMedium;
        data.utm_campaign = utmCampaign || localStorage.getItem('bz_utm_campaign') || '';
        try {
            if(navigator.sendBeacon){
                navigator.sendBeacon(endpoint, JSON.stringify(data));
            } else {
                fetch(endpoint, {method:'POST', body:JSON.stringify(data), headers:{'Content-Type':'application/json'}, keepalive:true}).catch(function(){});
            }
        } catch(e){}
    }

    // Pageview
    send({tipo:'pageview'});

    // Product view (if on product page)
    if(pageType === 'product'){
        var productMatch = pagina.match(/\/produto\/detalhes\/(\d+)/);
        if(productMatch) send({tipo:'product_view', product_id: parseInt(productMatch[1])});
    }

    // Click tracking - precise coordinates
    document.addEventListener('click', function(e){
        var el = e.target.closest('a,button,input[type=submit],.btn,[onclick]') || e.target;
        var tag = el.tagName || '';
        var text = (el.textContent || el.value || '').trim().substring(0,60);
        var title = el.getAttribute('title') || el.getAttribute('aria-label') || '';
        var href = el.getAttribute('href') || '';
        
        var elemento = '';
        if(text && text.length > 1) elemento = text;
        else if(title) elemento = title;
        else if(href && href !== '#') elemento = 'Link: ' + href.substring(0,40);
        else elemento = tag.toLowerCase();
        
        if(tag === 'A' && href && !elemento.startsWith('Link:')) elemento = '🔗 ' + elemento;
        else if(tag === 'BUTTON' || el.classList.contains('btn')) elemento = '🔘 ' + elemento;
        else if(tag === 'INPUT') elemento = '📝 ' + (el.placeholder || el.name || 'campo');
        else if(tag === 'IMG') elemento = '🖼️ ' + (el.alt || 'imagem');

        var pageX = e.pageX || (e.clientX + window.pageXOffset);
        var pageY = e.pageY || (e.clientY + window.pageYOffset);
        var docWidth = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
        var docHeight = Math.max(document.documentElement.scrollHeight, document.body.scrollHeight);
        
        var xPct = docWidth > 0 ? Math.round((pageX / docWidth) * 1000) / 10 : 50;
        var yPct = docHeight > 0 ? Math.round((pageY / docHeight) * 1000) / 10 : 50;

        var eventData = {tipo:'click', x: xPct, y: yPct, elemento: elemento.substring(0,80)};

        // Detect special clicks
        if(el.closest('[data-add-cart],.add-to-cart,#btn-add-to-cart') || texto_contem(text, ['carrinho','cart','adicionar','comprar'])){
            eventData.event_type = 'add_to_cart';
        } else if(href && href.indexOf('/checkout') >= 0){
            eventData.event_type = 'begin_checkout';
        }

        send(eventData);
    });

    function texto_contem(text, palavras){
        var t = (text||'').toLowerCase();
        for(var i=0;i<palavras.length;i++){ if(t.indexOf(palavras[i])>=0) return true; }
        return false;
    }

    // Scroll tracking
    var scrollTimer = null;
    window.addEventListener('scroll', function(){
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var docHeight = Math.max(document.documentElement.scrollHeight, document.body.scrollHeight) - window.innerHeight;
        var depth = docHeight > 0 ? Math.round((scrollTop / docHeight) * 100) : 100;
        if(depth > maxScroll) maxScroll = depth;

        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(function(){
            // Send at 25% intervals
            var milestones = [25,50,75,100];
            milestones.forEach(function(m){
                if(maxScroll >= m && !scrollSent['s'+m]){
                    send({tipo:'scroll', scroll_depth: m, event_type: 'scroll_'+m});
                    scrollSent['s'+m] = true;
                }
            });
        }, 1500);
    });

    // Time on page
    function sendTimeOnPage(){
        var tempo = Math.round((Date.now() - startTime) / 1000);
        if(tempo > 2){
            send({tipo:'time_on_page', tempo: tempo, scroll_depth: maxScroll});
        }
    }
    window.addEventListener('beforeunload', sendTimeOnPage);
    document.addEventListener('visibilitychange', function(){
        if(document.visibilityState === 'hidden') sendTimeOnPage();
    });

    // Detect exit intent (mouse leaves viewport on desktop)
    if(deviceType === 'desktop'){
        document.addEventListener('mouseleave', function(e){
            if(e.clientY < 5){
                send({tipo:'click', event_type:'exit_intent', elemento:'🚪 Intenção de sair'});
            }
        }, {once:true});
    }

    // Search tracking
    document.addEventListener('submit', function(e){
        var form = e.target;
        var searchInput = form.querySelector('input[name="busca"],input[name="q"],input[name="search"],input[type="search"]');
        if(searchInput && searchInput.value.trim()){
            send({tipo:'click', event_type:'search_performed', elemento:'🔍 Busca: ' + searchInput.value.trim().substring(0,50)});
        } else {
            // Generic form submit
            var formId = form.id || form.action || 'formulário';
            send({tipo:'click', event_type:'form_submit', elemento:'📋 Formulário: ' + formId.substring(0,50)});
        }
    });

    // Cart page detection
    if(pageType === 'cart'){
        send({tipo:'click', event_type:'cart_view'});
    }

    // Checkout page detection
    if(pageType === 'checkout'){
        send({tipo:'click', event_type:'begin_checkout'});
    }

    // Cookie consent banner
    if(!localStorage.getItem('bz_cookie_consent')){
        var banner = document.createElement('div');
        banner.id = 'cookieConsentBanner';
        banner.innerHTML = '<div style="position:fixed;bottom:0;left:0;right:0;background:#18253D;color:#fff;padding:14px 20px;z-index:99998;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:13px;font-family:system-ui,sans-serif;">'
            + '<div style="flex:1;min-width:200px;">🍪 Usamos cookies para melhorar sua experiência, analisar o tráfego e personalizar conteúdo. <a href="/politica-privacidade" style="color:#94A3B8;text-decoration:underline;">Saiba mais</a></div>'
            + '<div style="display:flex;gap:8px;flex-shrink:0;">'
            + '<button onclick="aceitarCookies(\'todos\')" style="padding:8px 16px;background:#fff;color:#18253D;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">Aceitar todos</button>'
            + '<button onclick="aceitarCookies(\'essenciais\')" style="padding:8px 16px;background:transparent;color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:6px;font-size:12px;cursor:pointer;">Apenas essenciais</button>'
            + '</div></div>';
        document.body.appendChild(banner);
    }

    window.aceitarCookies = function(tipo){
        var consent = {essential:true, analytics: tipo==='todos', marketing: tipo==='todos', date: new Date().toISOString()};
        localStorage.setItem('bz_cookie_consent', JSON.stringify(consent));
        var el = document.getElementById('cookieConsentBanner');
        if(el) el.remove();
        // Send consent to server
        send({tipo:'click', event_type:'cookie_consent', elemento: tipo==='todos' ? 'Aceitou todos' : 'Apenas essenciais'});
        // Save consent on server
        fetch('/admin/mapa-calor-site/collect', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({
            tipo:'consent', visitor_id:visitorId, session_id:sessionId, pagina:pagina,
            consent_essential:true, consent_analytics:consent.analytics, consent_marketing:consent.marketing
        })}).catch(function(){});
    };
})();
