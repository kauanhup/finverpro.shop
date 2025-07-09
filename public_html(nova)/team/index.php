<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit();
}

require '../bank/db.php';

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Buscar configurações e cores
try {
    // Configurações do site
    $stmt = $conn->query("SELECT chave, valor FROM configuracoes WHERE categoria IN ('sistema', 'design')");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $config = [];
    foreach ($configs as $item) {
        $config[$item['chave']] = $item['valor'];
    }
    
    $titulo_site = $config['nome_site'] ?? 'Finver Pro';
    $descricao_site = $config['descricao_site'] ?? 'Plataforma de investimentos';
    $keywords_site = $config['keywords_site'] ?? 'investimentos, renda passiva';
    $link_site = $config['url_site'] ?? 'https://finverpro.shop';
    
    // Buscar cores da personalização
    $stmt = $conn->query("SELECT elemento, valor FROM personalizacao WHERE categoria = 'cores'");
    $cores_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $defaultColors = [
        'cor_1' => '#0F172A',
        'cor_2' => '#FFFFFF', 
        'cor_3' => '#3B82F6',
        'cor_4' => '#10B981',
        'cor_5' => '#1E293B'
    ];
    
    $cores = $defaultColors;
    foreach ($cores_result as $cor) {
        $cores[$cor['elemento']] = $cor['valor'];
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar configurações: " . $e->getMessage());
    // Usar valores padrão
    $titulo_site = 'Finver Pro';
    $descricao_site = 'Plataforma de investimentos';
    $keywords_site = 'investimentos, renda passiva';
    $link_site = 'https://finverpro.shop';
    $cores = [
        'cor_1' => '#0F172A',
        'cor_2' => '#FFFFFF', 
        'cor_3' => '#3B82F6',
        'cor_4' => '#10B981',
        'cor_5' => '#1E293B'
    ];
}

try {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];

    // 1. Código de referência e foto de perfil
    $sqlCodigo = "SELECT codigo_referencia, foto_perfil, nome FROM usuarios WHERE id = ?";
    $stmtCodigo = $conn->prepare($sqlCodigo);
    $stmtCodigo->execute([$userId]);
    $codigoData = $stmtCodigo->fetch(PDO::FETCH_ASSOC);
    $codigoReferencia = $codigoData['codigo_referencia'] ?? '';
    $foto_perfil = $codigoData['foto_perfil'] ?? null;
    $nome_usuario = !empty($codigoData['nome']) ? $codigoData['nome'] : 'Investidor';

    // 2. SALDO DISPONÍVEL PARA TRANSFERÊNCIA (da nova tabela carteiras)
    $sqlSaldoDisponivel = "
        SELECT saldo_comissao as saldo_disponivel
        FROM carteiras 
        WHERE usuario_id = ?
    ";
    $stmtSaldo = $conn->prepare($sqlSaldoDisponivel);
    $stmtSaldo->execute([$userId]);
    $saldoDisponivel = $stmtSaldo->fetchColumn() ?? 0;

    // 3. TOTAL DE GANHOS ACUMULADOS (da tabela comissoes - NUNCA ZERA)
    $sqlTotalGanhos = "
        SELECT COALESCE(SUM(valor_comissao), 0) as total_ganhos_acumulados
        FROM comissoes 
        WHERE usuario_id = ? AND status = 'pago'
    ";
    $stmtTotalGanhos = $conn->prepare($sqlTotalGanhos);
    $stmtTotalGanhos->execute([$userId]);
    $totalGanhosAcumulados = $stmtTotalGanhos->fetchColumn() ?? 0;

    // 4. GANHOS DE HOJE (da tabela comissoes - apenas hoje)
    $sqlGanhosHoje = "
        SELECT COALESCE(SUM(valor_comissao), 0) as ganhos_hoje
        FROM comissoes 
        WHERE usuario_id = ? 
          AND status = 'pago'
          AND DATE(created_at) = CURRENT_DATE()
    ";
    $stmtGanhosHoje = $conn->prepare($sqlGanhosHoje);
    $stmtGanhosHoje->execute([$userId]);
    $ganhosHoje = $stmtGanhosHoje->fetchColumn() ?? 0;

    // 5. DADOS DA EQUIPE POR NÍVEL (usando nova estrutura)
    // CORREÇÃO: Linha 113 e 118 - subconsultas usando 'referenciado_por'
    $sqlNiveis1 = "SELECT COUNT(*) FROM usuarios WHERE referenciado_por = ?";
    $stmtNivel1 = $conn->prepare($sqlNiveis1);
    $stmtNivel1->execute([$userId]);
    $nivel1 = $stmtNivel1->fetchColumn() ?? 0;

    // Nível 2: indicados dos indicados diretos
    $sqlNiveis2 = "
        SELECT COUNT(*) 
        FROM usuarios u2 
        WHERE u2.referenciado_por IN (
            SELECT u1.id FROM usuarios u1 WHERE u1.referenciado_por = ?
        )
    ";
    $stmtNivel2 = $conn->prepare($sqlNiveis2);
    $stmtNivel2->execute([$userId]);
    $nivel2 = $stmtNivel2->fetchColumn() ?? 0;

    // Nível 3: indicados dos indicados dos indicados diretos
    $sqlNiveis3 = "
        SELECT COUNT(*) 
        FROM usuarios u3 
        WHERE u3.referenciado_por IN (
            SELECT u2.id 
            FROM usuarios u2 
            WHERE u2.referenciado_por IN (
                SELECT u1.id FROM usuarios u1 WHERE u1.referenciado_por = ?
            )
        )
    ";
    $stmtNivel3 = $conn->prepare($sqlNiveis3);
    $stmtNivel3->execute([$userId]);
    $nivel3 = $stmtNivel3->fetchColumn() ?? 0;

    $totalConvidados = $nivel1 + $nivel2 + $nivel3;

    // 6. VALORES INVESTIDOS PELA EQUIPE (usando nova tabela investimentos)
    // Nível 1
    $sqlValorNivel1 = "
        SELECT COALESCE(SUM(i.valor_investido), 0) as valor_nivel1
        FROM usuarios u
        INNER JOIN investimentos i ON u.id = i.usuario_id
        WHERE u.referenciado_por = ?
    ";
    $stmtValorNivel1 = $conn->prepare($sqlValorNivel1);
    $stmtValorNivel1->execute([$userId]);
    $valorNivel1 = $stmtValorNivel1->fetchColumn() ?? 0;

    // Nível 2
    $sqlValorNivel2 = "
        SELECT COALESCE(SUM(i.valor_investido), 0) as valor_nivel2
        FROM usuarios u2
        INNER JOIN investimentos i ON u2.id = i.usuario_id
        WHERE u2.referenciado_por IN (
            SELECT u1.id FROM usuarios u1 WHERE u1.referenciado_por = ?
        )
    ";
    $stmtValorNivel2 = $conn->prepare($sqlValorNivel2);
    $stmtValorNivel2->execute([$userId]);
    $valorNivel2 = $stmtValorNivel2->fetchColumn() ?? 0;

    // Nível 3
    $sqlValorNivel3 = "
        SELECT COALESCE(SUM(i.valor_investido), 0) as valor_nivel3
        FROM usuarios u3
        INNER JOIN investimentos i ON u3.id = i.usuario_id
        WHERE u3.referenciado_por IN (
            SELECT u2.id 
            FROM usuarios u2 
            WHERE u2.referenciado_por IN (
                SELECT u1.id FROM usuarios u1 WHERE u1.referenciado_por = ?
            )
        )
    ";
    $stmtValorNivel3 = $conn->prepare($sqlValorNivel3);
    $stmtValorNivel3->execute([$userId]);
    $valorNivel3 = $stmtValorNivel3->fetchColumn() ?? 0;

    $valorTotalEquipe = $valorNivel1 + $valorNivel2 + $valorNivel3;

} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?> - Equipe</title>
   <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
   <meta name="title" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
   <meta name="author" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
   <meta name="description" content="<?= htmlspecialchars($descricao_site, ENT_QUOTES, 'UTF-8'); ?>">
   <meta name="keywords" content="<?= htmlspecialchars($keywords_site, ENT_QUOTES, 'UTF-8'); ?>" />
   <link rel="canonical" href="<?= htmlspecialchars($link_site, ENT_QUOTES, 'UTF-8'); ?>">
   
   <!-- Favicon -->
   <link rel="apple-touch-icon" sizes="120x120" href="../assets/images/favicon/apple-touch-icon.png">
   <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon/favicon-32x32.png">
   <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon/favicon-16x16.png">
   <link rel="manifest" href="../assets/images/favicon/site.webmanifest">
   <meta name="msapplication-TileColor" content="#ffffff">
   <meta name="theme-color" content="#ffffff">
   
   <!-- Fonts & Icons -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   
   <style>
       :root {
           --background-color: <?= htmlspecialchars($cores['cor_1']) ?>;
           --text-color: <?= htmlspecialchars($cores['cor_2']) ?>;
           --primary-color: <?= htmlspecialchars($cores['cor_3']) ?>;
           --secondary-color: <?= htmlspecialchars($cores['cor_4']) ?>;
           --dark-background: <?= htmlspecialchars($cores['cor_5']) ?>;
           --success-color: #10B981;
           --error-color: #EF4444;
           --warning-color: #F59E0B;
           --info-color: #3B82F6;
           --purple-color: #8B5CF6;
           --pink-color: #EC4899;
           --orange-color: #F97316;
           --border-radius: 16px;
           --border-radius-sm: 8px;
           --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
           --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
           --blur-bg: rgba(255, 255, 255, 0.08);
           --border-color: rgba(255, 255, 255, 0.15);
       }

       * {
           margin: 0;
           padding: 0;
           box-sizing: border-box;
       }

       body {
           font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
           background: linear-gradient(135deg, var(--background-color) 0%, var(--dark-background) 100%);
           min-height: 100vh;
           color: var(--text-color);
           padding: 0 0 80px 0;
       }

       body::before {
           content: '';
           position: fixed;
           top: 0;
           left: 0;
           right: 0;
           bottom: 0;
           background: 
               radial-gradient(circle at 20% 20%, rgba(51, 93, 103, 0.15) 0%, transparent 50%),
               radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
           pointer-events: none;
           z-index: -1;
       }

       /* Header */
       .header-section {
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border-bottom: 1px solid var(--border-color);
           padding: 20px;
           position: relative;
       }

       .header-section::before {
           content: '';
           position: absolute;
           top: 0;
           left: 0;
           right: 0;
           height: 4px;
           background: linear-gradient(90deg, var(--secondary-color), var(--success-color));
       }

       .header-content {
           display: flex;
           align-items: center;
           justify-content: space-between;
           max-width: 400px;
           margin: 0 auto;
           gap: 15px;
       }

       .user-info {
           display: flex;
           align-items: center;
           gap: 15px;
           flex: 1;
           min-width: 0;
       }

       .logo-img {
           width: 50px;
           height: 50px;
           border-radius: 12px;
           background: linear-gradient(135deg, var(--success-color), #059669);
           display: flex;
           align-items: center;
           justify-content: center;
           color: white;
           font-size: 20px;
           overflow: hidden;
           flex-shrink: 0;
       }

       .logo-img img {
           width: 100%;
           height: 100%;
           object-fit: cover;
           border-radius: 12px;
       }

       .user-details {
           display: flex;
           flex-direction: column;
           gap: 4px;
           flex: 1;
           min-width: 0;
       }

       .uid {
           font-size: 14px;
           color: rgba(255, 255, 255, 0.9);
           font-weight: 600;
           white-space: nowrap;
           overflow: hidden;
           text-overflow: ellipsis;
       }

       .codigo-convite {
           font-size: 12px;
           color: rgba(255, 255, 255, 0.7);
           white-space: nowrap;
           overflow: hidden;
           text-overflow: ellipsis;
       }

       .share-btn {
           background: var(--success-color);
           color: white;
           border: none;
           padding: 12px 16px;
           border-radius: 12px;
           font-size: 13px;
           font-weight: 600;
           cursor: pointer;
           transition: all 0.3s ease;
           display: flex;
           align-items: center;
           gap: 8px;
           flex-shrink: 0;
           min-width: fit-content;
       }

       .share-btn:hover {
           background: #059669;
           transform: translateY(-2px);
       }

       /* Main Content */
       .container {
           max-width: 400px;
           margin: 0 auto;
           padding: 20px;
       }

       /* Benefícios Card */
       .benefits-card {
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border: 1px solid var(--border-color);
           border-radius: var(--border-radius);
           padding: 25px;
           margin-bottom: 25px;
           position: relative;
           overflow: hidden;
       }

       .benefits-card::before {
           content: '';
           position: absolute;
           top: 0;
           left: 0;
           right: 0;
           height: 3px;
           background: linear-gradient(90deg, var(--success-color), #059669);
       }

       .benefits-header {
           display: flex;
           justify-content: space-between;
           align-items: center;
           margin-bottom: 15px;
           flex-wrap: wrap;
           gap: 10px;
       }

       .benefits-title {
           font-size: 18px;
           font-weight: 700;
           color: var(--text-color);
       }

       .transfer-btn {
           background: var(--success-color);
           color: white;
           border: none;
           padding: 8px 16px;
           border-radius: 20px;
           font-size: 12px;
           font-weight: 600;
           cursor: pointer;
           transition: all 0.3s ease;
       }

       .transfer-btn:hover {
           background: #059669;
           transform: scale(1.05);
       }

       .balance-amount {
           font-size: 36px;
           font-weight: 800;
           color: var(--text-color);
           margin-bottom: 20px;
       }

       .wave-divider {
           height: 80px;
           background: linear-gradient(135deg, var(--success-color), #059669);
           border-radius: 0 0 var(--border-radius) var(--border-radius);
           margin: 0 -25px -25px -25px;
           position: relative;
           display: flex;
           align-items: flex-start;
           padding-top: 25px;
       }

       .wave-divider::before {
           content: '';
           position: absolute;
           top: -20px;
           left: 0;
           right: 0;
           height: 40px;
           background: var(--success-color);
           border-radius: 50% 50% 0 0 / 100% 100% 0 0;
       }

       .today-stats {
           display: flex;
           justify-content: space-between;
           align-items: flex-start;
           color: white;
           padding: 0 25px;
           position: relative;
           z-index: 2;
           width: 100%;
       }

       .today-stat {
           text-align: center;
           flex: 1;
           display: flex;
           flex-direction: column;
           justify-content: flex-start;
           align-items: center;
       }

       .today-stat .value {
           font-size: 22px;
           font-weight: 700;
           line-height: 1.2;
           margin-bottom: 2px;
       }

       .today-stat .label {
           font-size: 11px;
           opacity: 0.9;
           line-height: 1.1;
           font-weight: 500;
       }

       /* Section Title */
       .section-title {
           font-size: 18px;
           font-weight: 700;
           color: var(--text-color);
           margin-bottom: 15px;
       }

       /* Stats Grid */
       .stats-grid {
           display: grid;
           grid-template-columns: repeat(4, 1fr);
           gap: 10px;
           margin-bottom: 25px;
       }

       .stat-item {
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border: 1px solid var(--border-color);
           border-radius: var(--border-radius);
           padding: 15px 10px;
           text-align: center;
           transition: all 0.3s ease;
       }

       .stat-item:hover {
           transform: translateY(-3px);
           box-shadow: var(--shadow);
       }

       .stat-icon {
           width: 40px;
           height: 40px;
           border-radius: 50%;
           background: var(--success-color);
           display: flex;
           align-items: center;
           justify-content: center;
           margin: 0 auto 8px;
           color: white;
           font-size: 14px;
           font-weight: 700;
       }

       .stat-value {
           font-size: 18px;
           font-weight: 700;
           color: var(--text-color);
           margin-bottom: 4px;
       }

       .stat-label {
           font-size: 11px;
           color: rgba(255, 255, 255, 0.7);
           line-height: 1.2;
       }

       /* Team Stats */
       .team-stats {
           display: grid;
           grid-template-columns: repeat(4, 1fr);
           gap: 10px;
           margin-bottom: 25px;
       }

       .team-stat {
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border: 1px solid var(--border-color);
           border-radius: var(--border-radius);
           padding: 20px 10px;
           text-align: center;
           transition: all 0.3s ease;
       }

       .team-stat:hover {
           transform: translateY(-3px);
           box-shadow: var(--shadow);
       }

       .team-stat .icon {
           width: 50px;
           height: 50px;
           border-radius: 50%;
           background: var(--success-color);
           display: flex;
           align-items: center;
           justify-content: center;
           margin: 0 auto 12px;
           color: white;
           font-size: 16px;
           font-weight: 700;
       }

       .team-stat .value {
           font-size: 20px;
           font-weight: 700;
           color: var(--text-color);
           margin-bottom: 6px;
       }

       .team-stat .label {
           font-size: 12px;
           color: rgba(255, 255, 255, 0.7);
           line-height: 1.2;
       }

       /* Action Buttons */
       .action-buttons {
           display: grid;
           grid-template-columns: 1fr 1fr;
           gap: 15px;
           margin-bottom: 25px;
       }

       .action-btn {
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border: 1px solid var(--border-color);
           border-radius: var(--border-radius);
           padding: 20px;
           text-align: center;
           text-decoration: none;
           color: var(--text-color);
           transition: all 0.3s ease;
           display: flex;
           flex-direction: column;
           align-items: center;
           gap: 10px;
       }

       .action-btn:hover {
           transform: translateY(-3px);
           box-shadow: var(--shadow);
           background: var(--success-color);
           color: white;
       }

       .action-btn i {
           font-size: 24px;
           color: var(--success-color);
           transition: all 0.3s ease;
       }

       .action-btn:hover i {
           color: white;
       }

       .action-btn span {
           font-size: 14px;
           font-weight: 600;
       }

       /* Footer Navigation */
       .footer-nav {
           position: fixed;
           bottom: 0;
           left: 0;
           right: 0;
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border-top: 1px solid var(--border-color);
           padding: 15px 20px;
           z-index: 100;
       }

       .nav-items {
           display: flex;
           justify-content: space-around;
           align-items: center;
           max-width: 400px;
           margin: 0 auto;
       }

       .nav-item {
           display: flex;
           flex-direction: column;
           align-items: center;
           gap: 4px;
           text-decoration: none;
           color: rgba(255, 255, 255, 0.6);
           transition: all 0.3s ease;
           padding: 8px;
           border-radius: 12px;
           min-width: 60px;
       }

       .nav-item.active,
       .nav-item:hover {
           color: var(--success-color);
           background: rgba(16, 185, 129, 0.1);
       }

       .nav-item i {
           font-size: 18px;
       }

       .nav-item span {
           font-size: 10px;
           font-weight: 500;
       }

       /* Responsive */
       @media (max-width: 480px) {
           .container {
               padding: 15px;
           }
           
           .stats-grid {
               grid-template-columns: repeat(2, 1fr);
           }
           
           .team-stats {
               grid-template-columns: repeat(2, 1fr);
           }
       }
   </style>
</head>

<body>
    <!-- Header -->
    <div class="header-section">
        <div class="header-content">
            <div class="user-info">
                <div class="logo-img">
                    <?php if ($foto_perfil && file_exists("../uploads/fotos/" . $foto_perfil)): ?>
                        <img src="../uploads/fotos/<?= htmlspecialchars($foto_perfil) ?>" alt="Foto do usuário">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="uid"><?= htmlspecialchars($nome_usuario) ?></div>
                    <div class="codigo-convite">ID: <?= htmlspecialchars($codigoReferencia) ?></div>
                </div>
            </div>
            <button class="share-btn" onclick="compartilharLink()">
                <i class="fas fa-share-alt"></i>
                Compartilhar
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Benefits Card -->
        <div class="benefits-card">
            <div class="benefits-header">
                <h2 class="benefits-title">Comissões</h2>
                <button class="transfer-btn" onclick="transferirComissoes()">Transferir</button>
            </div>
            <div class="balance-amount">R$ <?= number_format($saldoDisponivel, 2, ',', '.') ?></div>
            
            <div class="wave-divider">
                <div class="today-stats">
                    <div class="today-stat">
                        <div class="value">R$ <?= number_format($ganhosHoje, 2, ',', '.') ?></div>
                        <div class="label">Hoje</div>
                    </div>
                    <div class="today-stat">
                        <div class="value">R$ <?= number_format($totalGanhosAcumulados, 2, ',', '.') ?></div>
                        <div class="label">Total Ganho</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Stats -->
        <h3 class="section-title">Estatísticas da Equipe</h3>
        <div class="team-stats">
            <div class="team-stat">
                <div class="icon">1</div>
                <div class="value"><?= $nivel1 ?></div>
                <div class="label">Nível 1</div>
            </div>
            <div class="team-stat">
                <div class="icon">2</div>
                <div class="value"><?= $nivel2 ?></div>
                <div class="label">Nível 2</div>
            </div>
            <div class="team-stat">
                <div class="icon">3</div>
                <div class="value"><?= $nivel3 ?></div>
                <div class="label">Nível 3</div>
            </div>
            <div class="team-stat">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="value"><?= $totalConvidados ?></div>
                <div class="label">Total</div>
            </div>
        </div>

        <!-- Investment Stats -->
        <h3 class="section-title">Investimentos da Equipe</h3>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon">R$</div>
                <div class="stat-value"><?= number_format($valorNivel1, 0, ',', '.') ?></div>
                <div class="stat-label">Nível 1</div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">R$</div>
                <div class="stat-value"><?= number_format($valorNivel2, 0, ',', '.') ?></div>
                <div class="stat-label">Nível 2</div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">R$</div>
                <div class="stat-value"><?= number_format($valorNivel3, 0, ',', '.') ?></div>
                <div class="stat-label">Nível 3</div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">R$</div>
                <div class="stat-value"><?= number_format($valorTotalEquipe, 0, ',', '.') ?></div>
                <div class="stat-label">Total</div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="verificar.php" class="action-btn">
                <i class="fas fa-sync-alt"></i>
                <span>Atualizar Dados</span>
            </a>
            <a href="../relatorios/" class="action-btn">
                <i class="fas fa-chart-bar"></i>
                <span>Relatórios</span>
            </a>
        </div>
    </div>

    <!-- Footer Navigation -->
    <div class="footer-nav">
        <div class="nav-items">
            <a href="../inicio/" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Início</span>
            </a>
            <a href="../investimentos/" class="nav-item">
                <i class="fas fa-chart-line"></i>
                <span>Investir</span>
            </a>
            <a href="./" class="nav-item active">
                <i class="fas fa-users"></i>
                <span>Equipe</span>
            </a>
            <a href="../perfil/" class="nav-item">
                <i class="fas fa-user"></i>
                <span>Perfil</span>
            </a>
        </div>
    </div>

    <script>
        function compartilharLink() {
            const link = `<?= $link_site ?>/cadastro/?ref=<?= $codigoReferencia ?>`;
            const texto = `Junte-se ao <?= $titulo_site ?> e comece a investir com inteligência artificial! Use meu código: <?= $codigoReferencia ?>`;
            
            if (navigator.share) {
                navigator.share({
                    title: '<?= $titulo_site ?>',
                    text: texto,
                    url: link
                });
            } else {
                navigator.clipboard.writeText(link).then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Link copiado!',
                        text: 'O link de convite foi copiado para a área de transferência.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                });
            }
        }

        function transferirComissoes() {
            Swal.fire({
                title: 'Transferir Comissões',
                text: `Deseja transferir R$ <?= number_format($saldoDisponivel, 2, ',', '.') ?> para sua carteira principal?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, transferir',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Fazer requisição AJAX
                    fetch('processar_transferencia.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Transferência realizada!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: data.message
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: 'Erro de conexão. Tente novamente.'
                        });
                    });
                }
            });
        }
    </script>
</body>
</html>