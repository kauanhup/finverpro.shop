<?php
   session_start(); // Inicia a sessão
   
   // Verifica se o usuário está logado
   if (!isset($_SESSION['user_id'])) {
      header('Location: ../'); // Redireciona para a página de login se não estiver logado
      exit(); // Encerra o script
   }
   
   // Inclui o arquivo de conexão com o banco de dados
   require '../bank/db.php';
   
   // Conexão com o banco de dados
   $pdo = getDBConnection();
   
   // Consulta as colunas link_suporte, pop_up e anuncio na tabela configurar_textos
   $stmt = $pdo->query("SELECT link_suporte, pop_up, anuncio, titulo_site, descricao_site, keywords_site, link_site FROM configurar_textos LIMIT 1");
   $result = $stmt->fetch(PDO::FETCH_ASSOC);
   
   // Define valores padrão caso não encontre no banco
   $linkSuporte = $result['link_suporte'] ?? '/';
   $popUp = $result['pop_up'] ?? '';
   $anuncio = $result['anuncio'] ?? '';
   $titulo_site = $result['titulo_site'] ?? '';
   $descricao_site = $result['descricao_site'] ?? '';
   $keywords_site = $result['keywords_site'] ?? '';
   $link_site = $result['link_site'] ?? '';
   
   // Consulta as colunas logo e tela_login na tabela personalizar_imagens
   $pdo = getDBConnection();
   $stmt = $pdo->query("SELECT logo FROM personalizar_imagens LIMIT 1");
   $result = $stmt->fetch(PDO::FETCH_ASSOC);
   
   // Define valores padrão caso não encontre no banco
   $logo = $result['logo'] ?? '3.png';
   
   // Consulta as cores do banco de dados
   $pdo = getDBConnection();
   $stmt = $pdo->query("SELECT cor_1, cor_2, cor_3, cor_4, cor_5 FROM personalizar_cores LIMIT 1");
   $cores = $stmt->fetch(PDO::FETCH_ASSOC);
   
   // Define as cores padrão caso nenhuma cor seja encontrada
   $defaultColors = [
   'cor_1' => '#121A1E',
   'cor_2' => 'white',
   'cor_3' => '#152731',
   'cor_4' => '#335D67',
   'cor_5' => '#152731',
   ];
   
   $cores = $cores ?: $defaultColors;
   
   try {
      $conn = getDBConnection(); // Conexão com o banco de dados
   } catch (Exception $e) {
      die("Erro de conexão: " . $e->getMessage()); // Erro de conexão
   }
   
   // Obtem o ID do usuário logado
   $userId = $_SESSION['user_id'];
   
   // Consulta o código de convite do usuário logado
   $stmt = $conn->prepare("SELECT codigo_referencia FROM usuarios WHERE id = :user_id");
   $stmt->bindParam(':user_id', $userId);
   $stmt->execute();
   $codigoReferencia = $stmt->fetchColumn();
   
   // Consulta os dados de pagamentos
   $pagamentos = $conn->prepare("SELECT status, data, valor FROM pagamentos WHERE user_id = :user_id ORDER BY data DESC");
   $pagamentos->bindParam(':user_id', $userId);
   $pagamentos->execute();
   $pagamentosResult = $pagamentos->fetchAll(PDO::FETCH_ASSOC);
   
   // Consulta os dados de saques
   $saques = $conn->prepare("SELECT status, data, valor FROM saques WHERE user_id = :user_id ORDER BY data DESC");
   $saques->bindParam(':user_id', $userId);
   $saques->execute();
   $saquesResult = $saques->fetchAll(PDO::FETCH_ASSOC);
   
   // Consulta os dados de convidados (usuários que usaram o código de convite do usuário logado)
   $convidados = $conn->prepare("SELECT telefone, data_criacao, valor_deposito FROM usuarios WHERE referencia_convite = :codigo_referencia ORDER BY data_criacao DESC");
   $convidados->bindParam(':codigo_referencia', $codigoReferencia);
   $convidados->execute();
   $convidadosResult = $convidados->fetchAll(PDO::FETCH_ASSOC);
   
   // ✅ CORREÇÃO: Consulta os dados de investimentos e produtos - CORRIGIDA
   $investimentos = $conn->prepare("
          SELECT i.data_investimento, p.titulo AS nome_produto, i.valor_investido AS valor_investimento
          FROM investimentos i
          JOIN produtos p ON i.produto_id = p.id
          WHERE i.usuario_id = :user_id
          ORDER BY i.data_investimento DESC
      ");
   $investimentos->bindParam(':user_id', $userId);
   $investimentos->execute();
   $investimentosResult = $investimentos->fetchAll(PDO::FETCH_ASSOC);

   function mascararTelefone($telefone)
   {
      // Remove caracteres não numéricos, caso existam
      $telefoneLimpo = preg_replace('/\D/', '', $telefone);
   
      // Verifica o tamanho para garantir que o número tenha pelo menos 10 dígitos
      if (strlen($telefoneLimpo) >= 10) {
         // Retorna o número mascarado no formato "XXX***XXXX"
         return substr($telefoneLimpo, 0, 7) . '***' . substr($telefoneLimpo, -3);
      }
      return $telefone; // Retorna o telefone sem mascarar se tiver menos de 10 dígitos
   }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Meta Tags -->
    <title><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?> - Relatórios</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="title" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="description" content="<?= htmlspecialchars($descricao_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?= htmlspecialchars($keywords_site, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="canonical" href="<?= htmlspecialchars($link_site, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="120x120" href="../assets/images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon/favicon-16x16.png">
    <link rel="manifest" href="../assets/images/favicon/site.webmanifest">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="theme-color" content="#ffffff">

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

        /* Header Simplificado - SEM LOGO */
        .header-section {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            padding: 30px 20px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary-color), var(--info-color));
        }

        .header-title {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-color);
            margin-bottom: 8px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header-subtitle {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
        }

        /* Tabs Navigation */
        .tabs-section {
            padding: 25px 20px 0;
        }

        .tabs-container {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 8px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }

        .tab {
            background: transparent;
            border: none;
            padding: 14px 8px;
            border-radius: var(--border-radius-sm);
            color: rgba(255, 255, 255, 0.7);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .tab.active {
            background: var(--secondary-color);
            color: white;
            box-shadow: 
                0 4px 15px rgba(51, 93, 103, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.1);
        }

        .tab:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
        }

        .tab i {
            font-size: 16px;
        }

        /* Content Section */
        .content-section {
            padding: 25px 20px;
        }

        .tab-content {
            display: none;
            animation: fadeInUp 0.6s ease-out;
        }

        .tab-content.active {
            display: block;
        }

        /* Record Cards */
        .record-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .record-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient);
        }

        /* Cores diferentes para cada tipo */
        #pagamentos .record-card { --gradient: linear-gradient(90deg, var(--success-color), #059669); }
        #saques .record-card { --gradient: linear-gradient(90deg, var(--warning-color), #D97706); }
        #convidados .record-card { --gradient: linear-gradient(90deg, var(--purple-color), #7C3AED); }
        #investimentos .record-card { --gradient: linear-gradient(90deg, var(--info-color), #1E40AF); }

        .record-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--secondary-color);
        }

        .record-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding: 0;
        }

        .record-item:last-child {
            margin-bottom: 0;
        }

        .record-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .record-value {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-color);
            text-align: right;
        }

        .record-value.status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .record-value.status.aprovado {
            background: var(--success-color);
            color: white;
        }

        .record-value.status.pendente {
            background: var(--warning-color);
            color: white;
        }

        .record-value.status.rejeitado {
            background: var(--error-color);
            color: white;
        }

        .record-value.valor {
            color: var(--success-color);
            font-weight: 700;
        }

        .record-value.telefone {
            font-family: 'Courier New', monospace;
            background: rgba(255, 255, 255, 0.1);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 13px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: rgba(255, 255, 255, 0.6);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.8);
        }

        .empty-state p {
            font-size: 14px;
            line-height: 1.5;
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

        .bottom-nav a.active,
        .bottom-nav a:hover {
            color: var(--secondary-color);
            background: rgba(51, 93, 103, 0.15);
        }

        .bottom-nav a i {
            font-size: 20px;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        .header-section,
        .tabs-section,
        .record-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .record-card:nth-child(2) { animation-delay: 0.1s; }
        .record-card:nth-child(3) { animation-delay: 0.2s; }
        .record-card:nth-child(4) { animation-delay: 0.3s; }

        /* Responsive */
        @media (max-width: 480px) {
            .header-section {
                padding: 20px 15px;
            }

            .tabs-section {
                padding: 20px 15px 0;
            }

            .content-section {
                padding: 20px 15px;
            }

            .tab {
                padding: 12px 6px;
                font-size: 12px;
            }

            .record-card {
                padding: 15px;
                margin-bottom: 12px;
            }

            .record-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }

            .record-value {
                text-align: left;
            }
        }

        /* Últimos ajustes para espaçamento */
        .record-card:last-child {
            margin-bottom: 100px;
        }
    </style>
</head>

<body>
    <!-- Header Simplificado -->
    <div class="header-section">
        <h1 class="header-title">Relatório Geral</h1>
        <p class="header-subtitle">Histórico completo das suas transações</p>
    </div>

    <!-- Tabs Navigation -->
    <div class="tabs-section">
        <div class="tabs-container">
            <button class="tab active" onclick="showTab('pagamentos')">
                <i class="fas fa-credit-card"></i>
                Pagamentos
            </button>
            <button class="tab" onclick="showTab('saques')">
                <i class="fas fa-wallet"></i>
                Saques
            </button>
            <button class="tab" onclick="showTab('convidados')">
                <i class="fas fa-users"></i>
                Convidados
            </button>
            <button class="tab" onclick="showTab('investimentos')">
                <i class="fas fa-chart-line"></i>
                Invs.
            </button>
        </div>
    </div>

    <!-- Content Section -->
    <div class="content-section">
        <!-- Pagamentos -->
        <div id="pagamentos" class="tab-content active">
            <?php if (empty($pagamentosResult)): ?>
                <div class="empty-state">
                    <i class="fas fa-credit-card"></i>
                    <h3>Nenhum pagamento encontrado</h3>
                    <p>Você ainda não possui histórico de pagamentos.<br>Faça seu primeiro depósito para começar a investir!</p>
                </div>
            <?php else: ?>
                <?php foreach ($pagamentosResult as $pagamento): ?>
                <div class="record-card">
                    <div class="record-item">
                        <div class="record-label">
                            <i class="fas fa-info-circle"></i>
                            Status
                        </div>
                        <div class="record-value status <?= strtolower($pagamento['status']) ?>">
                            <?= htmlspecialchars($pagamento['status']) ?>
                        </div>
                    </div>
                    <div class="record-item">
                        <div class="record-label">
                            <i class="fas fa-calendar-alt"></i>
                            Data/Hora
                        </div>
                        <div class="record-value">
                            <?= date('d/m/Y H:i', strtotime($pagamento['data'])) ?>
                        </div>
                    </div>
                    <div class="record-item">
                        <div class="record-label">
                            <i class="fas fa-dollar-sign"></i>
                            Valor
                        </div>
                        <div class="record-value valor">
                            R$ <?= number_format($pagamento['valor'], 2, ',', '.') ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Saques -->
        <div id="saques" class="tab-content">
            <?php if (empty($saquesResult)): ?>
                <div class="empty-state">
                    <i class="fas fa-wallet"></i>
                    <h3>Nenhum saque encontrado</h3>
                    <p>Você ainda não realizou nenhum saque.<br>Acumule lucros e retire quando desejar!</p>
                </div>
            <?php else: ?>
                <?php foreach ($saquesResult as $saque): ?>
                <div class="record-card">
                    <div class="record-item">
                        <div class="record-label">
                            <i class="fas fa-info-circle"></i>
                            Status
                        </div>
                        <div class="record-value status <?= strtolower($saque['status']) ?>">
                            <?= htmlspecialchars($saque['status']) ?>
                        </div>
                    </div>
                    <div class="record-item">
                        <div class="record-label">
                            <i class="fas fa-calendar-alt"></i>
                            Data/Hora
                        </div>
                        <div class="record-value">
                            <?= date('d/m/Y H:i', strtotime($saque['data'])) ?>
                        </div>
                    </div>
                    <div class="record-item">
                        <div class="record-label">
                            <i class="fas fa-dollar-sign"></i>
                            Valor
                        </div>
                        <div class="record-value valor">
                            R$ <?= number_format($saque['valor'], 2, ',', '.') ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Convidados -->
        <div id="convidados" class="tab-content">
            <?php if (empty($convidadosResult)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>Nenhum convidado encontrado</h3>
                    <p>Compartilhe seu link de convite e<br>comece a ganhar comissões por indicações!</p>
                </div>
            <?php else: ?>
                <?php foreach ($convidadosResult as $convidado): ?>
                <div class="record-card">
                    <div class="record-item">
                        <div class="record-label">
                            <i class="fas fa-phone"></i>
                            Telefone
                        </div>
                        <div class="record-value telefone">
                            <?= htmlspecialchars(mascararTelefone($convidado['telefone'])) ?>
                        </div>
                    </div>
                    <div class="record-item">
                        <div class="record-label">
                            <i class="fas fa-calendar-plus"></i>
                            Data Cadastro
                        </div>
                        <div class="record-value">
                            <?= date('d/m/Y H:i', strtotime($convidado['data_criacao'])) ?>
                        </div>
                    </div>
                    <!-- Valor do depósito comentado conforme original -->
                    <!-- <div class="record-item">
                        <div class="record-label">
                            <i class="fas fa-piggy-bank"></i>
                            Depósito
                        </div>
                        <div class="record-value valor">
                            R$ <?= number_format($convidado['valor_deposito'], 2, ',', '.') ?>
                        </div>
                    </div> -->
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Investimentos -->
        <div id="investimentos" class="tab-content">
            <?php if (empty($investimentosResult)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h3>Nenhum investimento encontrado</h3>
                    <p>Explore nossos produtos de investimento<br>e comece a fazer seu dinheiro render!</p>
                </div>
            <?php else: ?>
                <?php foreach ($investimentosResult as $investimento): ?>
                <div class="record-card">
                    <div class="record-item">
                        <div class="record-label">
                            <i class="fas fa-tag"></i>
                            Produto
                        </div>
                        <div class="record-value">
                            <?= htmlspecialchars($investimento['nome_produto']) ?>
                        </div>
                    </div>
                    <div class="record-item">
                        <div class="record-label">
                            <i class="fas fa-calendar-alt"></i>
                            Data/Hora
                        </div>
                        <div class="record-value">
                            <?= date('d/m/Y H:i', strtotime($investimento['data_investimento'])) ?>
                        </div>
                    </div>
                    <div class="record-item">
                        <div class="record-label">
                            <i class="fas fa-dollar-sign"></i>
                            Valor
                        </div>
                        <div class="record-value valor">
                            R$ <?= number_format($investimento['valor_investimento'], 2, ',', '.') ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="../inicio/">
            <i class="fas fa-home"></i>
            Início
        </a>
        <a href="../investimentos/">
            <i class="fas fa-wallet"></i>
            Investimentos
        </a>
        <a href="../team/">
            <i class="fas fa-users"></i>
            Equipe
        </a>
        <a href="../perfil/">
            <i class="fas fa-user"></i>
            Perfil
        </a>
    </nav>

    <script>
        // Função para trocar de aba
        function showTab(tabId) {
            // Remove active de todas as abas e conteúdos
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Adiciona active na aba clicada (usando event.target)
            event.target.closest('.tab').classList.add('active');
            // Adiciona active no conteúdo correspondente
            document.getElementById(tabId).classList.add('active');
        }

        // Efeito ripple nos botões
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', function(e) {
                    // Efeito ripple
                    const ripple = document.createElement('div');
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.3);
                        transform: scale(0);
                        animation: ripple 0.6s ease-out;
                        pointer-events: none;
                        width: 50px;
                        height: 50px;
                        left: 50%;
                        top: 50%;
                        margin-left: -25px;
                        margin-top: -25px;
                    `;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        });
    </script>
</body>
</html>