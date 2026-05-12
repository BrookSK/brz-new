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
        var text = (el.textContent || '').trim().substring(0,50);
        var id = el.id || '';
        var cls = (el.className || '').toString().substring(0,50);
        var elemento = tag;
        if(id) elemento += '#'+id;
        else if(text) elemento += ':'+text;
        else if(cls) elemento += '.'+cls.split(' ')[0];

        send({
            tipo:'click',
            x: Math.round((e.pageX / document.documentElement.scrollWidth) * 100),
            y: Math.round((e.pageY / document.documentElement.scrollHeight) * 100),
            elemento: elemento
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
