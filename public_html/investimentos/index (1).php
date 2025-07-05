<?php
session_start();

if (!isset($_SESSION['user_id'])) {
   header('Location: ../');
   exit();
}

require '../bank/db.php';
$pdo = getDBConnection();

// Consulta configurações
$stmt = $pdo->query("SELECT link_suporte, pop_up, anuncio, titulo_site, descricao_site, keywords_site, link_site FROM configurar_textos LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$linkSuporte = $result['link_suporte'] ?? '/';
$popUp = $result['pop_up'] ?? '';
$anuncio = $result['anuncio'] ?? '';
$titulo_site = $result['titulo_site'] ?? '';
$descricao_site = $result['descricao_site'] ?? '';
$keywords_site = $result['keywords_site'] ?? '';
$link_site = $result['link_site'] ?? '';

// Consulta imagens e cores
$stmt = $pdo->query("SELECT logo FROM personalizar_imagens LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$logo = $result['logo'] ?? '3.png';

$stmt = $pdo->query("SELECT cor_1, cor_2, cor_3, cor_4, cor_5 FROM personalizar_cores LIMIT 1");
$cores = $stmt->fetch(PDO::FETCH_ASSOC);

$defaultColors = [
   'cor_1' => '#121A1E', 'cor_2' => 'white', 'cor_3' => '#152731',
   'cor_4' => '#335D67', 'cor_5' => '#152731',
];
$cores = $cores ?: $defaultColors;

try {
   $conn = getDBConnection();
   
   // Criar tabela investimentos (padronizada)
   $tabelas = [
       'investimentos' => "CREATE TABLE IF NOT EXISTS `investimentos` (
           `id` int(11) NOT NULL AUTO_INCREMENT,
           `usuario_id` int(11) NOT NULL,
           `produto_id` int(11) NOT NULL,
           `valor_investido` decimal(10,2) NOT NULL,
           `renda_diaria` decimal(10,2) NOT NULL,
           `renda_total` decimal(10,2) DEFAULT 0.00,
           `dias_restantes` int(11) NOT NULL,
           `data_investimento` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
           `data_vencimento` date NOT NULL,
           `status` enum('ativo','concluido','cancelado') DEFAULT 'ativo',
           `ultimo_rendimento` date DEFAULT NULL,
           PRIMARY KEY (`id`),
           FOREIGN KEY (`produto_id`) REFERENCES `produtos`(`id`),
           FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
       
       'comissoes' => "CREATE TABLE IF NOT EXISTS `comissoes` (
           `id` int(11) NOT NULL AUTO_INCREMENT,
           `user_id` int(11) NOT NULL,
           `referido_id` int(11) NOT NULL,
           `produto_id` int(11) NOT NULL,
           `valor_investimento` decimal(10,2) NOT NULL,
           `valor_comissao` decimal(10,2) NOT NULL,
           `nivel` int(11) NOT NULL,
           `status` enum('pendente','processado') DEFAULT 'pendente',
           `data_comissao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
           PRIMARY KEY (`id`),
           KEY `user_id` (`user_id`),
           KEY `referido_id` (`referido_id`),
           KEY `produto_id` (`produto_id`)
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
   ];
   
   foreach ($tabelas as $nome => $sql) {
       try { $conn->exec($sql); } catch (Exception $e) {}
   }
   
} catch (Exception $e) {
   die("Erro de conexão: " . $e->getMessage());
}

// Buscar produtos disponíveis COM LIMITE POR PESSOA
$produtos_disponiveis = [];
try {
    $query_produtos = "SELECT 
        p.*,
        COALESCE(p.limite_compras, 1) as limite_por_pessoa,
        COALESCE(p.vendidos, 0) as vendidos_safe,
        COALESCE(p.duracao_dias, p.validade, 30) as duracao_dias_safe,
        COALESCE(p.robot_number, CONCAT('R', p.id + 50)) as robot_number_safe,
        COALESCE(p.tipo_rendimento, 'diario') as tipo_rendimento_safe,
        COALESCE(p.status, 'ativo') as status_safe,
        COALESCE(p.data_criacao, p.created_at) as data_criacao_safe,
        
        -- Calcular quantas vezes o usuário atual já comprou
        (SELECT COUNT(*) FROM investimentos i 
         WHERE i.usuario_id = ? AND i.produto_id = p.id) as compras_usuario,
         
        -- Calcular se pode comprar mais
        (COALESCE(p.limite_compras, 1) - 
         (SELECT COUNT(*) FROM investimentos i2 
          WHERE i2.usuario_id = ? AND i2.produto_id = p.id)) as pode_comprar,
          
        -- Cálculos de tempo de venda
        CASE 
            WHEN p.limite_dias_venda IS NOT NULL THEN 
                GREATEST(0, DATEDIFF(DATE_ADD(COALESCE(p.data_criacao, p.created_at), INTERVAL p.limite_dias_venda DAY), NOW()))
            ELSE NULL 
        END as dias_restantes_venda,
        
        CASE 
            WHEN p.limite_dias_venda IS NOT NULL THEN
                LEAST(100, (DATEDIFF(NOW(), COALESCE(p.data_criacao, p.created_at)) / p.limite_dias_venda) * 100)
            ELSE 0
        END as progresso_tempo_venda
        
    FROM produtos p
    WHERE p.status IN ('ativo', 'arquivado') 
    AND (p.limite_dias_venda IS NULL OR DATEDIFF(NOW(), COALESCE(p.data_criacao, p.created_at)) <= p.limite_dias_venda)
    ORDER BY p.valor_investimento ASC";
    
    $stmt_produtos = $conn->prepare($query_produtos);
    $stmt_produtos->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $produtos_disponiveis = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $produtos_disponiveis = [];
}

// Buscar investimentos do usuário (usando tabela investimentos)
$investimentos = [];
$totalProdutos = 0;
$totalRendaDiaria = 0;
$totalRendaTotal = 0;

try {
    $query_investimentos = "SELECT i.*, p.titulo, p.foto, p.robot_number 
                           FROM investimentos i 
                           LEFT JOIN produtos p ON i.produto_id = p.id 
                           WHERE i.usuario_id = ? 
                           ORDER BY i.data_investimento DESC";
    $stmt_investimentos = $conn->prepare($query_investimentos);
    $stmt_investimentos->execute([$_SESSION['user_id']]);
    $investimentos = $stmt_investimentos->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($investimentos as $investimento) {
        $totalProdutos++;
        $totalRendaDiaria += $investimento['renda_diaria'];
        $totalRendaTotal += $investimento['renda_total'];
    }
} catch (Exception $e) {
    $investimentos = [];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?> - Investimentos</title>
    
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
            padding: 20px 0 80px 0;
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

        .container {
            max-width: 400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Toggle Buttons */
        .toggle-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }

        .toggle-btn {
            flex: 1;
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 12px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .toggle-btn.active {
            background: linear-gradient(135deg, var(--secondary-color), var(--info-color));
            border-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .toggle-btn:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stats-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px 15px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--success-color), var(--info-color));
        }

        .stats-card i {
            font-size: 24px;
            color: var(--success-color);
            margin-bottom: 10px;
        }

        .stats-card p {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .stats-card span {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-color);
            display: block;
        }

        /* Content Sections */
        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        /* Product Cards */
        .product-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--success-color), #059669);
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .product-content {
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        /* Foto do produto */
        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: cover;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .robot-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--success-color), #059669);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: bold;
            flex-shrink: 0;
            position: relative;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .robot-icon::before {
            content: 'R';
            font-family: 'Inter', sans-serif;
        }

        .robot-number {
            position: absolute;
            bottom: -2px;
            right: -2px;
            background: #059669;
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 4px;
            font-weight: bold;
        }

        .product-info {
            flex: 1;
            color: var(--text-color);
        }

        .product-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--success-color);
            margin-bottom: 12px;
            line-height: 1.2;
        }

        .product-details-list {
            margin-bottom: 15px;
        }

        .product-detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .detail-label {
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }

        .detail-value {
            color: var(--success-color);
            font-weight: 600;
        }

        .detail-value.price {
            font-size: 16px;
        }

        .invest-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
        }

        .invest-btn:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .invest-btn:disabled {
            background: #6B7280;
            cursor: not-allowed;
            transform: none;
        }

        /* Barra de Progresso ÚNICA */
        .progress-container {
            margin-top: 15px;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 12px;
        }

        .progress-label {
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }

        .progress-value {
            color: var(--warning-color);
            font-weight: 600;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 1s ease-in-out;
            position: relative;
            background: linear-gradient(90deg, var(--warning-color), var(--error-color));
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Investment Cards */
        .investment-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .investment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--warning-color), var(--success-color));
        }

        .investment-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }

        .investment-details h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 15px;
        }

        .investment-details p {
            margin-bottom: 10px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .investment-details strong {
            color: var(--success-color);
            font-weight: 600;
        }

        /* Status Badge */
        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--success-color);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.concluido {
            background: var(--warning-color);
        }

        .status-badge.cancelado {
            background: var(--error-color);
        }

        /* Cycle Button */
        .cycle-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--secondary-color), var(--info-color));
            border: none;
            color: var(--text-color);
            padding: 15px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            margin-bottom: 25px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .cycle-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            margin: 40px 0;
        }

        .empty-state i {
            font-size: 64px;
            color: rgba(255, 255, 255, 0.3);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: var(--text-color);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 20px;
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--blur-bg);
            backdrop-filter: blur(25px);
            border-top: 1px solid var(--border-color);
            padding: 15px 0;
            display: flex;
            justify-content: space-around;
            z-index: 100;
        }

        .bottom-nav a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 8px 12px;
            border-radius: var(--border-radius-sm);
        }

        .bottom-nav a:hover,
        .bottom-nav a.active {
            color: var(--success-color);
            background: rgba(16, 185, 129, 0.15);
        }

        .bottom-nav a i {
            font-size: 20px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .container {
                padding: 0 15px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Toggle Buttons -->
        <div class="toggle-buttons">
            <button class="toggle-btn active" id="btn-produtos" onclick="toggleView('produtos')">
                <i class="fas fa-store"></i>
                Produtos Disponíveis
            </button>
            <button class="toggle-btn" id="btn-meus-produtos" onclick="toggleView('meus-produtos')">
                <i class="fas fa-wallet"></i>
                Meus Investimentos
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stats-card">
                <i class="fas fa-box"></i>
                <p>Investimentos</p>
                <span><?= $totalProdutos ?></span>
            </div>
            <div class="stats-card">
                <i class="fas fa-dollar-sign"></i>
                <p>Renda Diária</p>
                <span>R$ <?= number_format($totalRendaDiaria, 2, ',', '.') ?></span>
            </div>
            <div class="stats-card">
                <i class="fas fa-chart-line"></i>
                <p>Renda Total</p>
                <span>R$ <?= number_format($totalRendaTotal, 2, ',', '.') ?></span>
            </div>
        </div>

        <!-- Produtos Disponíveis -->
        <div id="produtos-section" class="content-section active">
            <?php if ($produtos_disponiveis): ?>
                <?php foreach ($produtos_disponiveis as $produto): ?>
                    <?php 
                    $robotNum = str_replace('R', '', $produto['robot_number_safe']);
                    $progressoTempo = $produto['progresso_tempo_venda'];
                    $diasRestantesVenda = $produto['dias_restantes_venda'];
                    
                    // Determinar se usar foto ou ícone do robô
                    $usarFoto = !empty($produto['foto']) && $produto['foto'] !== 'produto-default.jpg' && $produto['foto'] !== 'default.jpg';
                    $caminhoFoto = $usarFoto ? "../assets/images/produtos/" . $produto['foto'] : "";
                    ?>
                    <div class="product-card" onclick="openProductModal(<?= htmlspecialchars(json_encode($produto)) ?>)">
                        <div class="product-content">
                            <?php if ($usarFoto): ?>
                                <img src="<?= $caminhoFoto ?>" alt="Produto" class="product-image" 
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="robot-icon" style="display: none;">
                                    <div class="robot-number"><?= $robotNum ?></div>
                                </div>
                            <?php else: ?>
                                <div class="robot-icon">
                                    <div class="robot-number"><?= $robotNum ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-info">
                                <div class="product-title"><?= htmlspecialchars($produto['titulo']) ?></div>
                                
                                <div class="product-details-list">
                                    <div class="product-detail-item">
                                        <span class="detail-label">Preço do produto</span>
                                        <span class="detail-value price">R$ <?= number_format($produto['valor_investimento'], 2, ',', '.') ?></span>
                                    </div>
                                    <div class="product-detail-item">
                                        <span class="detail-label">Rendimento diário</span>
                                        <span class="detail-value">R$ <?= number_format($produto['renda_diaria'], 2, ',', '.') ?></span>
                                    </div>
                                    <div class="product-detail-item">
                                        <span class="detail-label">Duração</span>
                                        <span class="detail-value"><?= $produto['duracao_dias_safe'] ?> dias</span>
                                    </div>
                                    <div class="product-detail-item">
                                        <span class="detail-label">Suas compras</span>
                                        <span class="detail-value"><?= $produto['compras_usuario'] ?>/<?= $produto['limite_por_pessoa'] ?></span>
                                    </div>
                                </div>
                                
                                <!-- Barra de progresso ÚNICA - APENAS TEMPO -->
                                <?php if ($produto['limite_dias_venda']): ?>
                                    <div class="progress-container">
                                        <div class="progress-info">
                                            <span class="progress-label">Tempo de venda</span>
                                            <span class="progress-value">
                                                <?= $diasRestantesVenda ?> dias restantes
                                            </span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= min(100, max(0, $progressoTempo)) ?>%"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <button class="invest-btn" onclick="event.stopPropagation(); purchaseProduct(<?= htmlspecialchars(json_encode($produto)) ?>)" 
                                        <?= ($produto['pode_comprar'] <= 0 || ($diasRestantesVenda !== null && $diasRestantesVenda <= 0)) ? 'disabled' : '' ?>>
                                    <i class="fas fa-shopping-cart"></i>
                                    <?php if ($diasRestantesVenda !== null && $diasRestantesVenda <= 0): ?>
                                        Prazo Expirado
                                    <?php elseif ($produto['pode_comprar'] <= 0): ?>
                                        Limite Atingido
                                    <?php else: ?>
                                        Investir Agora (<?= $produto['pode_comprar'] ?> restantes)
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-store-slash"></i>
                    <h3>Nenhum Produto Disponível</h3>
                    <p>No momento não há produtos disponíveis para investimento.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Meus Produtos -->
        <div id="meus-produtos-section" class="content-section">
            <button class="cycle-btn" onclick="verificarCiclo()">
                <i class="fas fa-sync-alt"></i>
                Verificar Ciclo de Rendimento
            </button>

            <?php if ($investimentos): ?>
                <?php foreach ($investimentos as $investimento): ?>
                    <div class="investment-card">
                        <span class="status-badge <?= strtolower($investimento['status']) ?>">
                            <?= ucfirst($investimento['status']) ?>
                        </span>
                        
                        <div class="investment-details">
                            <h2><?= htmlspecialchars($investimento['titulo'] ?? 'Robô de Trading') ?></h2>
                            
                            <p>Investimento: <strong>R$ <?= number_format($investimento['valor_investido'], 2, ',', '.') ?></strong></p>
                            <p>Dias Restantes: <strong><?= $investimento['dias_restantes'] ?> dias</strong></p>
                            <p>Renda Diária: <strong>R$ <?= number_format($investimento['renda_diaria'], 2, ',', '.') ?></strong></p>
                            <p>Renda Total: <strong>R$ <?= number_format($investimento['renda_total'], 2, ',', '.') ?></strong></p>
                            <p>Data: <strong><?= date('d/m/Y H:i', strtotime($investimento['data_investimento'])) ?></strong></p>
                            
                            <?php if ($investimento['data_vencimento']): ?>
                            <p>Vencimento: <strong><?= date('d/m/Y', strtotime($investimento['data_vencimento'])) ?></strong></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-pie"></i>
                    <h3>Nenhum Investimento</h3>
                    <p>Você ainda não possui investimentos ativos. Adquira um produto na aba "Produtos Disponíveis"!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <nav class="bottom-nav">
        <a href="../inicio/"><i class="fas fa-home"></i> Início</a>
        <a href="./" class="active"><i class="fas fa-wallet"></i> Investimentos</a>
        <a href="../team/"><i class="fas fa-users"></i> Equipe</a>
        <a href="../perfil/"><i class="fas fa-user"></i> Perfil</a>
    </nav>

    <script>
        // Função para alternar entre visualizações
        function toggleView(view) {
            const btnProdutos = document.getElementById('btn-produtos');
            const btnMeusProdutos = document.getElementById('btn-meus-produtos');
            const produtosSection = document.getElementById('produtos-section');
            const meusProdutosSection = document.getElementById('meus-produtos-section');
            
            if (view === 'produtos') {
                btnProdutos.classList.add('active');
                btnMeusProdutos.classList.remove('active');
                produtosSection.classList.add('active');
                meusProdutosSection.classList.remove('active');
            } else {
                btnProdutos.classList.remove('active');
                btnMeusProdutos.classList.add('active');
                produtosSection.classList.remove('active');
                meusProdutosSection.classList.add('active');
            }
        }

        // Função para comprar produto COM VERIFICAÇÃO DE LIMITE POR PESSOA
        function purchaseProduct(produto) {
            if (!produto || produto.pode_comprar <= 0) {
                return;
            }
            
            // Verificar se o prazo de venda não expirou
            if (produto.dias_restantes_venda !== null && produto.dias_restantes_venda <= 0) {
                Swal.fire({
                    icon: 'error',
                    title: '⏰ Prazo Expirado',
                    text: 'O prazo para compra deste produto já expirou.',
                    confirmButtonText: 'Entendi'
                });
                return;
            }
            
            Swal.fire({
                title: '💰 Confirmar Investimento',
                html: `
                    <div style="text-align: left;">
                        <h4>${produto.titulo}</h4>
                        <p><strong>Investimento:</strong> R$ ${parseFloat(produto.valor_investimento).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                        <p><strong>Renda Diária:</strong> R$ ${parseFloat(produto.renda_diaria).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                        <p><strong>Duração:</strong> ${produto.duracao_dias_safe} dias</p>
                        <p><strong>Suas compras:</strong> ${produto.compras_usuario}/${produto.limite_por_pessoa}</p>
                        <p><strong>Pode comprar mais:</strong> ${produto.pode_comprar} vez(es)</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, Investir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    processarCompra(produto);
                }
            });
        }

        // Função para processar compra
        function processarCompra(produto) {
            fetch('processar_investimento.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    produto_id: produto.id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro de conexão', 'error');
            });
        }

        // Função para verificar ciclo
        function verificarCiclo() {
            fetch('processar_ciclo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro de conexão', 'error');
            });
        }

        // Auto-refresh da página a cada 10 minutos
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 600000);
    </script>

</body>
</html>