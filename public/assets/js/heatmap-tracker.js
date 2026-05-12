/**
 * Heatmap Tracker - Coleta comportamento do usuário
 * Inclua no frontend: <script src="/assets/js/heatmap-tracker.js"></script>
 */
(function(){
    var endpoint = '/admin/mapa-calor-site/collect';
    var sessionId = localStorage.getItem('hm_sid');
    if(!sessionId){
        sessionId = 'hm_' + Date.now() + '_' + Math.random().toString(36).substr(2,9);
        localStorage.setItem('hm_sid', sessionId);
    }

    var pagina = window.location.pathname;
    var startTime = Date.now();
    var maxScroll = 0;
    var sent = {};

    function send(data){
        data.session_id = sessionId;
        data.pagina = pagina;
        data.vw = window.innerWidth;
        data.vh = window.innerHeight;
        try {
            navigator.sendBeacon(endpoint, JSON.stringify(data));
        } catch(e){
            fetch(endpoint, {method:'POST', body:JSON.stringify(data), headers:{'Content-Type':'application/json'}}).catch(function(){});
        }
    }

    // Pageview
    send({tipo:'pageview'});

    // Click tracking
    document.addEventListener('click', function(e){
        var el = e.target.closest('a,button,input[type=submit],.btn,[onclick]') || e.target;
        var tag = el.tagName || '';
        var text = (el.textContent || el.value || '').trim().substring(0,60);
        var id = el.id || '';
        var title = el.getAttribute('title') || el.getAttribute('aria-label') || '';
        var href = el.getAttribute('href') || '';
        
        // Build friendly name: prioritize visible text
        var elemento = '';
        if(text && text.length > 1) {
            elemento = text;
        } else if(title) {
            elemento = title;
        } else if(id) {
            elemento = id;
        } else if(href && href !== '#' && href !== 'javascript:void(0)') {
            elemento = 'Link: ' + href.substring(0,40);
        } else {
            elemento = tag.toLowerCase();
        }
        
        // Add context
        if(tag === 'A' && href && !elemento.startsWith('Link:')) {
            elemento = '🔗 ' + elemento;
        } else if(tag === 'BUTTON' || el.classList.contains('btn')) {
            elemento = '🔘 ' + elemento;
        } else if(tag === 'INPUT') {
            elemento = '📝 ' + (el.placeholder || el.name || 'campo');
        } else if(tag === 'IMG') {
            elemento = '🖼️ ' + (el.alt || 'imagem');
        }

        send({
            tipo:'click',
            x: Math.round((e.pageX / document.documentElement.scrollWidth) * 100),
            y: Math.round((e.pageY / document.documentElement.scrollHeight) * 100),
            elemento: elemento.substring(0,80)
        });
    });

    // Scroll tracking (debounced, sends max depth)
    var scrollTimer = null;
    window.addEventListener('scroll', function(){
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var docHeight = Math.max(document.documentElement.scrollHeight, document.body.scrollHeight) - window.innerHeight;
        var depth = docHeight > 0 ? Math.round((scrollTop / docHeight) * 100) : 100;
        if(depth > maxScroll) maxScroll = depth;

        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(function(){
            if(!sent['scroll_'+maxScroll]){
                send({tipo:'scroll', scroll_depth: maxScroll});
                sent['scroll_'+maxScroll] = true;
            }
        }, 2000);
    });

    // Time on page (send on leave)
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
})();
