<?php

   session_start(); // Inicia a sessão

   
   // Verifica se o usuário está logado
   if (!isset($_SESSION['user_id'])) {
      // Se não estiver logado, redireciona para a página de login
      header('Location: ../');
      exit(); // Encerra o script
   }
   
   // Incluir o arquivo de conexão com o banco de dados
   require '../bank/db.php';
   
   // Consulta as colunas logo e tela_login na tabela personalizar_imagens
   $pdo = getDBConnection();
   $stmt = $pdo->query("SELECT logo, inicio FROM personalizar_imagens LIMIT 1");
   $result = $stmt->fetch(PDO::FETCH_ASSOC);
   
   // Define valores padrão caso não encontre no banco
   $logo = $result['logo'] ?? '3.png';
   $inicio = $result['inicio'] ?? '2.jpg';
   
   // Criar a conexão
   try {
      $conn = getDBConnection(); // Chama a função para obter a conexão
   } catch (Exception $e) {
      die("Erro de conexão: " . $e->getMessage()); // Mensagem de erro
   }
   
   // Conexão com o banco de dados
   $pdo = getDBConnection();
   
   // Consulta TODAS as configurações de texto (incluindo ticker)
   $stmt = $pdo->query("SELECT * FROM configurar_textos LIMIT 1");
   $config = $stmt->fetch(PDO::FETCH_ASSOC);
   
   // Define valores padrão caso não encontre no banco
   $linkSuporte = $config['link_suporte'] ?? '/';
   $popUp = $config['pop_up'] ?? '';
   $anuncio = $config['anuncio'] ?? '';
   $titulo_site = $config['titulo_site'] ?? '';
   $descricao_site = $config['descricao_site'] ?? '';
   $keywords_site = $config['keywords_site'] ?? '';
   $link_site = $config['link_site'] ?? '';
   
   // CONFIGURAÇÕES DO POP-UP DINÂMICAS
   $popup_titulo = $config['popup_titulo'] ?? 'Notificação';
   $popup_imagem = $config['popup_imagem'] ?? 'icon.svg';
   $popup_botao_texto = $config['popup_botao_texto'] ?? 'Fechar';
   $popup_ativo = $config['popup_ativo'] ?? 1;
   $popup_delay = $config['popup_delay'] ?? 3000;
   
   // MONTAR MENSAGENS ATIVAS DO TICKER (DINÂMICO)
   $tickerMessages = [];
   for ($i = 1; $i <= 20; $i++) {
       if (($config["ticker_ativo_$i"] ?? 0) && !empty($config["ticker_msg_$i"])) {
           $tickerMessages[] = [
               'mensagem' => $config["ticker_msg_$i"],
               'icone' => $config["ticker_icon_$i"] ?? 'fas fa-star'
           ];
       }
   }
   
   // Se não houver mensagens ativas, usar mensagens padrão
   if (empty($tickerMessages)) {
       $tickerMessages = [
           ['mensagem' => '🔥 Sistema de investimentos ativo!', 'icone' => 'fas fa-fire'],
           ['mensagem' => '💰 Plataforma segura e confiável', 'icone' => 'fas fa-chart-line'],
           ['mensagem' => '🏆 Milhares de investidores satisfeitos', 'icone' => 'fas fa-trophy']
       ];
   }
   
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

   // Buscar dados do usuário logado
   $user_id = $_SESSION['user_id'];
   $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :user_id");
   $stmt->execute([':user_id' => $user_id]);
   $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
   
   $saldo_usuario = $usuario['saldo'] ?? 0;
   $nome_usuario = $usuario['nome'] ?? 'Usuário';
   
   ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Meta Tags -->
    <title><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="title" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="description" content="<?= htmlspecialchars($descricao_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?= htmlspecialchars($keywords_site, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="canonical" href="<?= htmlspecialchars($link_site, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($link_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?= htmlspecialchars($descricao_site, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= htmlspecialchars($link_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="twitter:description" content="<?= htmlspecialchars($descricao_site, ENT_QUOTES, 'UTF-8'); ?>">
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
            padding: 0 0 90px 0;
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
            padding: 0 15px;
        }

        /* TICKER HORIZONTAL - NO TOPO ABSOLUTO (MARKETING DINÂMICO) */
        .ticker-top {
            background: linear-gradient(135deg, var(--success-color), #059669);
            padding: 8px 0;
            overflow: hidden;
            position: relative;
            width: 100%;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(16, 185, 129, 0.3);
        }

        .ticker-content {
            display: flex;
            animation: scroll-left 25s linear infinite;
            white-space: nowrap;
        }

        .ticker-item {
            padding: 0 40px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 6px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .ticker-item i {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.9);
        }

        @keyframes scroll-left {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        /* Header */
        .header {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary-color), var(--info-color));
        }

        .header h1 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 4px;
            background: linear-gradient(135deg, var(--text-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .language {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 8px;
        }

        /* Banner */
        .banner {
            background: var(--blur-bg);
            backdrop-filter: blur(25px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: all 0.4s ease;
        }

        .banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--success-color), var(--info-color), var(--purple-color));
            animation: gradientShift 3s ease-in-out infinite;
        }

        .banner::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 120px;
            height: 120px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .banner:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3);
        }

        .banner-content {
            position: relative;
            z-index: 1;
        }

        .banner h2 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.8);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .balance {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 6px;
            background: linear-gradient(135deg, var(--success-color), var(--info-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer 2s ease-in-out infinite;
        }

        .balance-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Section */
        .section {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--text-color);
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
            backdrop-filter: blur(25px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            text-decoration: none;
            color: var(--text-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--btn-gradient);
            transition: height 0.3s ease;
        }

        .action-btn.recarregar {
            --btn-gradient: linear-gradient(90deg, var(--warning-color), var(--orange-color));
        }

        .action-btn.retirar {
            --btn-gradient: linear-gradient(90deg, var(--success-color), #059669);
        }

        .action-btn:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
        }

        .action-btn:hover::before {
            height: 6px;
        }

        .action-content {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .action-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .action-subtitle {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .action-icon {
            font-size: 24px;
        }

        .action-btn.recarregar .action-icon {
            color: var(--warning-color);
        }

        .action-btn.retirar .action-icon {
            color: var(--success-color);
        }

        /* Recommendations (INVESTIMENTOS POPULARES) */
        .recommendations {
            background: var(--blur-bg);
            backdrop-filter: blur(25px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .recommendations::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--purple-color), var(--pink-color));
            animation: gradientShift 4s ease-in-out infinite;
        }

        .recommendations:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3);
        }

        .recommendation-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
        }

        .recommendation-item:last-child {
            border-bottom: none;
            padding-bottom: 15px;
        }

        .recommendation-item:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(10px) scale(1.02);
            box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.2);
        }

        .rec-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--info-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: white;
            font-size: 18px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 16px -4px rgba(59, 130, 246, 0.4);
        }

        .recommendation-item:hover .rec-icon {
            transform: rotate(10deg) scale(1.1);
            box-shadow: 0 12px 24px -6px rgba(59, 130, 246, 0.6);
        }

        .rec-content {
            flex: 1;
        }

        .rec-name {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .rec-stats {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
        }

        .rec-price {
            color: rgba(255, 255, 255, 0.7);
        }

        .rec-percentage {
            color: var(--success-color);
            font-weight: 600;
        }

        .rec-amount {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 4px 12px -2px rgba(16, 185, 129, 0.4);
            transition: all 0.3s ease;
        }

        .recommendation-item:hover .rec-amount {
            transform: scale(1.05);
            box-shadow: 0 6px 16px -2px rgba(16, 185, 129, 0.6);
        }

        /* Services Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .service-item {
            background: var(--blur-bg);
            backdrop-filter: blur(25px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px 15px;
            text-decoration: none;
            color: var(--text-color);
            text-align: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .service-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--service-color);
            transition: height 0.3s ease;
        }

        .service-item:nth-child(1) { 
            --service-color: var(--info-color); 
        }
        .service-item:nth-child(2) { 
            --service-color: var(--success-color);
        }
        .service-item:nth-child(3) { 
            --service-color: var(--purple-color);
        }

        .service-item:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3);
        }

        .service-item:hover::before {
            height: 6px;
        }

        .service-icon {
            width: 55px;
            height: 55px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 22px;
            transition: all 0.4s ease;
        }

        .service-item:nth-child(1) .service-icon {
            background: rgba(59, 130, 246, 0.2);
            color: var(--info-color);
        }

        .service-item:nth-child(2) .service-icon {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
        }

        .service-item:nth-child(3) .service-icon {
            background: rgba(139, 92, 246, 0.2);
            color: var(--purple-color);
        }

        .service-item:hover .service-icon {
            transform: rotate(15deg) scale(1.1);
        }

        .service-title {
            font-size: 12px;
            font-weight: 600;
            line-height: 1.3;
        }

        /* Feature Cards */
        .feature-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 25px;
        }

        .feature-card {
            border-radius: var(--border-radius);
            padding: 25px 15px;
            text-decoration: none;
            color: white;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .feature-card:nth-child(1) {
            background: linear-gradient(135deg, var(--pink-color), #F472B6);
            box-shadow: 0 8px 25px -5px rgba(236, 72, 153, 0.4);
        }

        .feature-card:nth-child(2) {
            background: linear-gradient(135deg, var(--orange-color), #FB923C);
            box-shadow: 0 8px 25px -5px rgba(249, 115, 22, 0.4);
        }

        .feature-card:nth-child(3) {
            background: linear-gradient(135deg, var(--info-color), #60A5FA);
            box-shadow: 0 8px 25px -5px rgba(59, 130, 246, 0.4);
        }

        .feature-card:hover {
            transform: translateY(-10px) scale(1.05);
            box-shadow: 0 25px 50px -10px rgba(0, 0, 0, 0.4);
        }

        .feature-icon {
            font-size: 36px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.2) rotate(10deg);
        }

        .feature-text {
            font-size: 14px;
            font-weight: 600;
            line-height: 1.2;
        }

        /* Online Indicator */
        .online-indicator {
            position: fixed;
            bottom: 100px;
            right: 20px;
            background: var(--success-color);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: var(--shadow);
            z-index: 100;
        }

        .online-dot {
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        /* Bottom Navigation - VOLTA AO NORMAL (4 BOTÕES EM LINHA) */
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
            gap: 4px;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 8px 12px;
            border-radius: var(--border-radius-sm);
        }

        .bottom-nav a.active {
            color: var(--success-color);
            background: rgba(16, 185, 129, 0.15);
        }

        .bottom-nav a:hover {
            color: var(--secondary-color);
            background: rgba(51, 93, 103, 0.15);
        }

        .bottom-nav a i {
            font-size: 20px;
        }

        /* Popup */
        .popup {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
        }

        .popup-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--blur-bg);
            backdrop-filter: blur(25px);
            border: 1px solid var(--border-color);
            padding: 30px;
            width: 95%;
            max-width: 500px;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow-lg);
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            color: var(--text-color);
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: var(--error-color);
        }

        .popup-content img {
            width: 100%;
            height: auto;
            max-height: 200px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 20px;
        }

        .popup-content h2 {
            color: var(--text-color);
            margin-bottom: 15px;
            font-size: 22px;
            font-weight: 700;
        }

        .popup-content p {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .popup-content button {
            background: linear-gradient(135deg, var(--secondary-color), var(--info-color));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .popup-content button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* Animations */
        @keyframes gradientShift {
            0%, 100% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
        }

        @keyframes shimmer {
            0%, 100% {
                background-position: -200% 0;
            }
            50% {
                background-position: 200% 0;
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            33% {
                transform: translateY(-10px) rotate(5deg);
            }
            66% {
                transform: translateY(-5px) rotate(-5deg);
            }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        /* Responsive adjustments */
        @media (max-width: 380px) {
            .feature-cards {
                grid-template-columns: 1fr;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }

            .ticker-item {
                padding: 0 25px;
                font-size: 11px;
            }
        }
    </style>
</head>

<body>
    <!-- TICKER HORIZONTAL - DINÂMICO DO BANCO -->
    <div class="ticker-top">
        <div class="ticker-content">
            <?php foreach ($tickerMessages as $msg): ?>
            <div class="ticker-item">
                <i class="<?= htmlspecialchars($msg['icone'], ENT_QUOTES, 'UTF-8') ?>"></i>
                <?= htmlspecialchars($msg['mensagem'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Popup DINÂMICO -->
    <?php if ($popup_ativo): ?>
    <div id="popup" class="popup">
        <div class="popup-content">
            <span class="close-btn" onclick="closePopup()">&times;</span>
            <img src="../assets/images/popup/<?= htmlspecialchars($popup_imagem, ENT_QUOTES, 'UTF-8') ?>" alt="Popup Image">
            <h2><?= htmlspecialchars($popup_titulo, ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars($popUp, ENT_QUOTES, 'UTF-8') ?></p>
            <button onclick="closePopup()"><?= htmlspecialchars($popup_botao_texto, ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>
    <?php endif; ?>

    <div class="container">
        <!-- Header (SEM LOGO) -->
        <header class="header">
            <h1><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="language">
                <i class="fas fa-globe"></i>
                Português (Brasil)
            </div>
        </header>

        <!-- Banner/Balance -->
        <div class="banner">
            <div class="banner-content">
                <h2>Saldo Atual</h2>
                <div class="balance">R$ <?= number_format($saldo_usuario, 2, ',', '.'); ?></div>
                <div class="balance-label">Disponível para investir</div>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div class="section">
            <h3 class="section-title">Ações Rápidas</h3>
            <div class="action-buttons">
                <a href="../realizar/pix/" class="action-btn recarregar">
                    <div class="action-content">
                        <div class="action-title">DEPOSITAR</div>
                        <div class="action-subtitle">Adicionar fundos</div>
                    </div>
                    <i class="fas fa-plus-circle action-icon"></i>
                </a>
                <a href="../retirar/dinheiro/" class="action-btn retirar">
                    <div class="action-content">
                        <div class="action-title">RETIRAR</div>
                        <div class="action-subtitle">Sacar dinheiro</div>
                    </div>
                    <i class="fas fa-hand-holding-usd action-icon"></i>
                </a>
            </div>
        </div>

        <!-- Investimentos Populares (DINÂMICOS DO BANCO) -->
        <?php
        // Buscar os 3 primeiros produtos disponíveis
        try {
            $sql = "SELECT id, titulo, valor_investimento, renda_diaria, receita_total, duracao_dias 
                    FROM produtos 
                    WHERE status = 'ativo' 
                    ORDER BY valor_investimento ASC 
                    LIMIT 3";
            $result = $conn->query($sql);
            
            if ($result && $result->rowCount() > 0) {
                $products = $result->fetchAll(PDO::FETCH_ASSOC);
                echo '<div class="section">
                        <h3 class="section-title">Investimentos Populares</h3>
                        <div class="recommendations">';
                
                $icons = ['fas fa-seedling', 'fas fa-chart-line', 'fas fa-rocket'];
                
                foreach ($products as $index => $row) {
                    // Calcular percentual de lucro
                    $percentual = round((($row['receita_total'] - $row['valor_investimento']) / $row['valor_investimento']) * 100, 1);
                    $icon = $icons[$index] ?? 'fas fa-coins';
                    
                    echo '
                    <div class="recommendation-item" onclick="window.location.href=\'../investimentos/\'">
                        <div class="rec-icon">
                            <i class="' . $icon . '"></i>
                        </div>
                        <div class="rec-content">
                            <div class="rec-name">' . htmlspecialchars($row['titulo']) . '</div>
                            <div class="rec-stats">
                                <span class="rec-price">R$ ' . number_format($row['valor_investimento'], 2, ',', '.') . '</span>
                                <span class="rec-percentage">+' . $percentual . '%</span>
                            </div>
                        </div>
                        <div class="rec-amount">R$ ' . number_format($row['receita_total'], 2, ',', '.') . '</div>
                    </div>';
                }
                
                echo '</div></div>';
            }
        } catch (Exception $e) {
            // Se der erro, não mostra nada (seção fica oculta)
            error_log("Erro ao buscar produtos: " . $e->getMessage());
        }
        ?>

        <!-- Serviços -->
        <div class="section">
            <h3 class="section-title">Serviços</h3>
            <div class="services-grid">
                <a href="../checklist/" class="service-item">
                    <div class="service-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="service-title">CHECK IN</div>
                </a>
                <a href="../relatorios/" class="service-item">
                    <div class="service-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="service-title">Relatórios</div>
                </a>
                <a href="<?= htmlspecialchars($linkSuporte, ENT_QUOTES, 'UTF-8'); ?>" class="service-item">
                    <div class="service-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="service-title">SUPORTE</div>
                </a>
            </div>
        </div>

        <!-- Feature Cards -->
        <div class="feature-cards">
            <a href="../bonus/" class="feature-card">
                <div class="feature-icon">🎁</div>
                <div class="feature-text">Bônus</div>
            </a>
            <a href="../team/" class="feature-card">
                <div class="feature-icon">👥</div>
                <div class="feature-text">Equipe</div>
            </a>
            <a href="../roleta/" class="feature-card">
                <div class="feature-icon">🎰</div>
                <div class="feature-text">roleta</div>
            </a>
        </div>

        <!-- Online Indicator -->
        <div class="online-indicator">
            <div class="online-dot"></div>
            ONLINE
        </div>
    </div>

    <!-- Bottom Navigation - NAVEGAÇÃO NORMAL (4 BOTÕES EM LINHA) -->
    <nav class="bottom-nav">
        <a href="#" class="active">
            <i class="fas fa-home"></i>
             inicio
        </a>
        <a href="../investimentos/">
            <i class="fas fa-wallet"></i>
            investimentos
        </a>
        <a href="../team/">
            <i class="fas fa-users"></i>
            equipe
        </a>
        <a href="../perfil/">
            <i class="fas fa-user"></i>
            meu
        </a>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($popup_ativo): ?>
            // Mostrar pop-up apenas se estiver ativo no banco
            if (!localStorage.getItem('popupClosed')) {
                setTimeout(function() {
                    document.getElementById('popup').style.display = 'block';
                }, <?= $popup_delay ?>);
            }
            <?php endif; ?>
        });
        
        function closePopup() {
            document.getElementById('popup').style.display = 'none';
            localStorage.setItem('popupClosed', 'true');
        }

        // Toast notifications
        const urlParams = new URLSearchParams(window.location.search);
        const message = urlParams.get('message');
        const toastType = urlParams.get('toastType');
        
        if (message && toastType) {
            showToast(message, toastType, 5000);
            const cleanUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }

        function showToast(message, type, duration) {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${type === 'danger' ? 'linear-gradient(135deg, #EF4444, #DC2626)' : 'linear-gradient(135deg, #10B981, #059669)'};
                color: white;
                border-radius: 12px;
                z-index: 1001;
                opacity: 0;
                transition: all 0.3s ease;
                font-weight: 600;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.15);
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => toast.style.opacity = '1', 10);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => document.body.removeChild(toast), 300);
            }, duration);
        }
    </script>
</body>
</html>