<!-- Calendário de Marketing -->
<div class="col-12">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-4 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">
                <i class="fas fa-calendar-alt me-2 text-primary"></i>
                <?= __('admin.dashboard.marketing_calendar', 'Calendário de Marketing') ?>
            </h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRefreshCalendar" title="Atualizar">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
        <div class="card-body" id="marketingCalendarContent">
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <p class="text-muted mt-2 mb-0">Carregando datas comemorativas...</p>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    function loadMarketingCalendar() {
        var container = document.getElementById('marketingCalendarContent');
        if (!container) return;

        container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div><p class="text-muted mt-2 mb-0">Carregando datas comemorativas...</p></div>';

        fetch('/admin/marketing-calendar/dates')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.ok || !data.data) {
                    container.innerHTML = '<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Não foi possível carregar as datas.</div>';
                    return;
                }
                renderCalendar(data.data);
            })
            .catch(function() {
                container.innerHTML = '<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Erro ao carregar calendário de marketing.</div>';
            });
    }

    function renderCalendar(data) {
        var container = document.getElementById('marketingCalendarContent');
        var html = '<div class="row g-4">';

        // USA
        html += '<div class="col-md-6">';
        html += '<div class="d-flex align-items-center mb-3">';
        html += '<span class="me-2" style="font-size:1.4rem;">🇺🇸</span>';
        html += '<h6 class="mb-0 fw-bold text-dark">Estados Unidos</h6>';
        html += '</div>';
        if (data.usa && data.usa.length > 0) {
            data.usa.forEach(function(item) {
                html += buildDateCard(item, 'usa');
            });
        } else {
            html += '<p class="text-muted small">Nenhuma data próxima encontrada.</p>';
        }
        html += '</div>';

        // Brazil
        html += '<div class="col-md-6">';
        html += '<div class="d-flex align-items-center mb-3">';
        html += '<span class="me-2" style="font-size:1.4rem;">🇧🇷</span>';
        html += '<h6 class="mb-0 fw-bold text-dark">Brasil</h6>';
        html += '</div>';
        if (data.brazil && data.brazil.length > 0) {
            data.brazil.forEach(function(item) {
                html += buildDateCard(item, 'brazil');
            });
        } else {
            html += '<p class="text-muted small">Nenhuma data próxima encontrada.</p>';
        }
        html += '</div>';

        html += '</div>';
        container.innerHTML = html;
    }

    function buildDateCard(item, country) {
        var daysText = item.days_until === 0 ? 'Hoje!' : item.days_until + ' dias';
        var urgencyClass = '';
        var badgeBg = '';

        if (item.days_until === 0) {
            urgencyClass = 'border-start border-4 border-success';
            badgeBg = 'background: rgba(16, 185, 129, 0.12); color: #065f46; border: 1px solid rgba(16, 185, 129, 0.22);';
        } else if (item.days_until <= 7) {
            urgencyClass = 'border-start border-4 border-danger';
            badgeBg = 'background: rgba(239, 68, 68, 0.12); color: #991b1b; border: 1px solid rgba(239, 68, 68, 0.22);';
        } else if (item.days_until <= 30) {
            urgencyClass = 'border-start border-4 border-warning';
            badgeBg = 'background: rgba(245, 158, 11, 0.12); color: #7c2d12; border: 1px solid rgba(245, 158, 11, 0.22);';
        } else {
            urgencyClass = 'border-start border-4 border-info';
            badgeBg = 'background: rgba(56, 189, 248, 0.10); color: #0b1f3a; border: 1px solid rgba(56, 189, 248, 0.22);';
        }

        var dateFormatted = formatDate(item.date);

        var html = '<div class="p-3 mb-2 rounded ' + urgencyClass + '" style="background: rgba(248, 250, 252, 0.8);">';
        html += '<div class="d-flex justify-content-between align-items-center">';
        html += '<div class="d-flex align-items-center">';
        html += '<span class="me-2" style="font-size:1.3rem;">' + (item.emoji || '📅') + '</span>';
        html += '<div>';
        html += '<div class="fw-semibold" style="font-size:0.9rem;">' + item.name + '</div>';
        html += '<div class="text-muted" style="font-size:0.75rem;">' + dateFormatted + '</div>';
        html += '</div>';
        html += '</div>';
        html += '<div>';
        html += '<span class="badge px-2 py-1 fw-bold" style="' + badgeBg + ' font-size:0.85rem;">';
        html += item.days_until === 0 ? '🎉 Hoje!' : item.days_until + ' <small>dias</small>';
        html += '</span>';
        html += '</div>';
        html += '</div>';
        html += '</div>';

        return html;
    }

    function formatDate(dateStr) {
        var parts = dateStr.split('-');
        var months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        return parts[2] + ' ' + months[parseInt(parts[1], 10) - 1] + ' ' + parts[0];
    }

    // Carregar ao iniciar
    document.addEventListener('DOMContentLoaded', function() {
        loadMarketingCalendar();

        var btn = document.getElementById('btnRefreshCalendar');
        if (btn) {
            btn.addEventListener('click', function() {
                loadMarketingCalendar();
            });
        }
    });
})();
</script>
