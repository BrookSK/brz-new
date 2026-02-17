<?php
use App\Core\Url;

$activePage = isset($activePage) ? (string) $activePage : '';
$activePage = strtolower(trim($activePage));

$avatarColumnCandidates = ['avatar', 'foto_perfil', 'imagem_perfil', 'foto'];
$avatarUrl = null;
foreach ($avatarColumnCandidates as $c) {
    if (!empty($usuario[$c]) && is_string($usuario[$c])) {
        $avatarUrl = $usuario[$c];
        break;
    }
}
if (empty($avatarUrl)) {
    $avatarUrl = $_SESSION['usuario_avatar'] ?? null;
}

if (empty($avatarUrl)) {
    $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode((string) ($usuario['nome'] ?? '')) . '&background=0b1f3a&color=fff&size=128';
} else {
    $raw = trim((string) $avatarUrl);
    if (preg_match('#^https?://#i', $raw) || strpos($raw, '//') === 0) {
        $avatarUrl = (strpos($raw, '//') === 0) ? ('https:' . $raw) : $raw;
    } else {
        if ($raw !== '' && $raw[0] !== '/') {
            $raw = '/' . $raw;
        }
        $avatarUrl = Url::absolute($raw);
    }
}
?>
<div class="col-lg-3 mb-4 account-sidebar">
    <div class="card shadow-sm">
        <div class="card-body text-center">
            <div class="user-avatar mx-auto mb-3">
                <img src="<?= htmlspecialchars((string) $avatarUrl) ?>" alt="<?= htmlspecialchars((string) ($usuario['nome'] ?? '')) ?>" style="width: 80px; height: 80px; object-fit: cover; border-radius: 999px;">
            </div>
            <h5 class="card-title"><?= htmlspecialchars((string) ($usuario['nome'] ?? '')) ?></h5>
            <p class="text-muted"><?= htmlspecialchars((string) ($usuario['email'] ?? '')) ?></p>
            <span class="badge" style="background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: #0b1f3a;">
                <?= ucfirst((string) ($usuario['perfil'] ?? '')) ?>
            </span>
            <?php $suiteValue = $usuario['suite'] ?? ($usuario['switch'] ?? null); ?>
            <?php if (!empty($suiteValue)): ?>
                <div class="mt-2 small text-muted"><?= __('user.suite', 'Suite') ?>: <strong><?= (int) $suiteValue ?></strong></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-body">
            <h6 class="card-title"><?= __('user.quick_menu', 'Menu Rápido') ?></h6>
            <nav class="nav flex-column">
                <a class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="/minha-conta">
                    <i class="fas fa-tachometer-alt me-2"></i> <?= __('user.dashboard', 'Dashboard') ?>
                </a>
                <a class="nav-link <?= $activePage === 'dados' ? 'active' : '' ?>" href="/meus-dados">
                    <i class="fas fa-user me-2"></i> <?= __('user.my_data', 'Meus Dados') ?>
                </a>
                <a class="nav-link <?= $activePage === 'enderecos' ? 'active' : '' ?>" href="/meus-enderecos">
                    <i class="fas fa-map-marker-alt me-2"></i> <?= __('user.addresses', 'Endereços') ?>
                </a>
                <a class="nav-link <?= $activePage === 'pedidos' ? 'active' : '' ?>" href="/meus-pedidos">
                    <i class="fas fa-shopping-bag me-2"></i> <?= __('user.my_orders', 'Meus Pedidos') ?>
                </a>
                <?php
                    $unreadTickets = 0;
                    try {
                        if (session_status() === PHP_SESSION_NONE) {
                            session_start();
                        }
                        $uid = (int) ($_SESSION['usuario_id'] ?? 0);
                        if ($uid > 0) {
                            $pdo = \Config\Database::getConnection();
                            if ($pdo instanceof \PDO) {
                                $stT = $pdo->query("SHOW TABLES LIKE 'support_ticket_views'");
                                $hasViews = (bool) ($stT && $stT->fetchColumn());
                                if ($hasViews) {
                                    $sqlUnread = "
                                        SELECT COUNT(*)
                                        FROM support_tickets t
                                        INNER JOIN (
                                            SELECT ticket_id, MAX(created_at) AS last_admin_msg_at
                                            FROM support_ticket_messages
                                            WHERE autor_tipo = 'admin'
                                            GROUP BY ticket_id
                                        ) m ON m.ticket_id = t.id
                                        LEFT JOIN support_ticket_views v
                                            ON v.ticket_id = t.id AND v.viewer_type = 'cliente' AND v.viewer_user_id = ?
                                        WHERE t.usuario_id = ?
                                          AND t.status = 'open'
                                          AND (v.last_seen_at IS NULL OR m.last_admin_msg_at > v.last_seen_at)
                                    ";
                                    $stU = $pdo->prepare($sqlUnread);
                                    $stU->execute([$uid, $uid]);
                                    $unreadTickets = (int) ($stU->fetchColumn() ?: 0);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        $unreadTickets = 0;
                    }
                ?>
                <a class="nav-link <?= $activePage === 'tickets' ? 'active' : '' ?>" href="/meus-tickets">
                    <i class="fas fa-life-ring me-2"></i> <?= __('user.my_tickets', 'Meus Tickets') ?>
                    <?php if ($unreadTickets > 0): ?>
                        <span class="badge bg-danger ms-2" style="background: rgba(239, 68, 68, 0.18) !important; border: 1px solid rgba(239, 68, 68, 0.35) !important; color: #7f1d1d !important;"><?= (int) $unreadTickets ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link <?= $activePage === 'carrinho' ? 'active' : '' ?>" href="/carrinho">
                    <i class="fas fa-shopping-cart me-2"></i> <?= __('user.my_cart', 'Meu Carrinho') ?>
                </a>
                <hr>
                <a class="nav-link text-danger" href="/logout">
                    <i class="fas fa-sign-out-alt me-2"></i> <?= __('auth.logout', 'Sair') ?>
                </a>
            </nav>
        </div>
    </div>
</div>
