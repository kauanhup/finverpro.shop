<?php

   session_start();

   
   if (!isset($_SESSION['user_id'])) {
       header('Location: ../');
       exit();
   }
   
   require '../../bank/db.php';
   
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
   $stmt = $pdo->query("SELECT logo, tela_retirada FROM personalizar_imagens LIMIT 1");
   $result = $stmt->fetch(PDO::FETCH_ASSOC);
   
   // Define valores padrão caso não encontre no banco
   $logo = $result['logo'] ?? '3.png';
   $tela_retirada = $result['tela_retirada'] ?? '1.jpg';
   
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
   
   $message = ""; // Variável para armazenar a mensagem
   
   try {
       $conn = getDBConnection();
   
       $user_id = $_SESSION['user_id'];
       $stmt = $conn->prepare("SELECT saldo_comissao, tipo_pix, chave_pix, nome_titular, telefone FROM usuarios WHERE id = :user_id");
       $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
       $stmt->execute();
       $userData = $stmt->fetch(PDO::FETCH_ASSOC);
   
       if (!$userData) {
           $message = "Swal.fire({ icon: 'error', title: 'Erro', text: 'Usuário não encontrado.', customClass: { popup: 'custom-swal-popup', confirmButton: 'custom-confirm-button' } });";
       }
   
       if ($_SERVER['REQUEST_METHOD'] === 'POST') {
           $valor_pix = $_POST['valor_pix'];
           $valor_descontado = $valor_pix * 0.92; // Calcula 90% do valor solicitado
   
           if ($userData['saldo_comissao'] >= $valor_pix) {
               $stmt = $conn->prepare("INSERT INTO saques_comissao (tipo_pix, chave_pix, nome_titular, user_id, valor, status, data, numero_telefone) VALUES (:tipo_pix, :chave_pix, :nome_titular, :user_id, :valor, 'Pendente', NOW(), :numero_telefone)");
               $stmt->bindParam(':tipo_pix', $userData['tipo_pix']);
               $stmt->bindParam(':chave_pix', $userData['chave_pix']);
               $stmt->bindParam(':nome_titular', $userData['nome_titular']);
               $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
               $stmt->bindParam(':valor', $valor_descontado); // Usa o valor descontado
               $stmt->bindParam(':numero_telefone', $userData['telefone']); // Adiciona o telefone
   
               if ($stmt->execute()) {
                   $novo_saldo = $userData['saldo_comissao'] - $valor_pix;
                   $updateSaldo = $conn->prepare("UPDATE usuarios SET saldo_comissao = :novo_saldo WHERE id = :user_id");
                   $updateSaldo->bindParam(':novo_saldo', $novo_saldo);
                   $updateSaldo->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                   $updateSaldo->execute();
   
                   $message = "Swal.fire({ icon: 'success', title: 'Saque solicitado!', text: 'Estamos processando seu saque, aguarde de 2 a 12 horas.', customClass: { popup: 'custom-swal-popup', confirmButton: 'custom-confirm-button' } });";
               } else {
                   $message = "Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao solicitar saque.', customClass: { popup: 'custom-swal-popup', confirmButton: 'custom-confirm-button' } });";
               }
           } else {
               $message = "Swal.fire({ icon: 'warning', title: 'Saldo insuficiente', text: 'Seu saldo é insuficiente para saque.', customClass: { popup: 'custom-swal-popup', confirmButton: 'custom-confirm-button' } });";
           }
       }
   } catch (Exception $e) {
       $message = "Swal.fire({ icon: 'error', title: 'Erro de conexão', text: 'Erro: " . $e->getMessage() . "', customClass: { popup: 'custom-swal-popup', confirmButton: 'custom-confirm-button' } });";
   }
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
      <link rel="apple-touch-icon" sizes="120x120" href="../../assets/images/favicon/apple-touch-icon.png">
      <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/favicon/favicon-32x32.png">
      <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/favicon/favicon-16x16.png">
      <link rel="manifest" href="../../assets/images/favicon/site.webmanifest">
      <meta name="msapplication-TileColor" content="#ffffff">
      <meta name="theme-color" content="#ffffff">
      <!-- Rotas -->
      <link rel="stylesheet" href="../../assets/css/withdraw.css">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   </head>
   <style>
      :root {
      --background-color: <?= htmlspecialchars($cores['cor_1']) ?>;
      --text-color: <?= htmlspecialchars($cores['cor_2']) ?>;
      --primary-color: <?= htmlspecialchars($cores['cor_3']) ?>;
      --secondary-color: <?= htmlspecialchars($cores['cor_4']) ?>;
      --dark-background: <?= htmlspecialchars($cores['cor_5']) ?>;
      --font-family: Arial, sans-serif;
      }
      .custom-confirm-button {
      background-color: #335D67 !important;
      border-radius: 5px !important;
      border-color: #fff;
      color: #fff !important;
      }
      .custom-confirm-button:focus,
      .custom-confirm-button:active {
      outline: none !important;
      box-shadow: none !important;
      border-color: #ff69b4 !important;
      }
      .custom-confirm-button:hover {
      background-color: #335D67 !important;
      color: #fff !important;
      border-color: #335D67 !important;
      }
      .container {
      margin-bottom: 100px; /* Aumente a margem inferior do último cartão */
      }
      .fixed-image{
      width: 360px;
      }
   </style>
   <body>
      <div class="container">
         <header>
            <img src="../../assets/images/icons/<?= htmlspecialchars($logo) ?>" alt="" class="logo-img">
            <p>Selecione o valor e insira os detalhes abaixo</p>
            <img src="../../assets/images/banners/<?= htmlspecialchars($tela_retirada) ?>" alt="" class="fixed-image">
         </header>
         <form method="POST" action="">
            <label for="chave-pix">Chave Pix:</label>
            <select id="chave-pix" disabled required>
               <option value="" disabled <?= empty($userData['tipo_pix']) ? 'selected' : ''; ?>>Selecione uma opção</option>
               <option value="cpf" <?= $userData['tipo_pix'] === 'cpf' ? 'selected' : ''; ?>>CPF</option>
               <option value="celular" <?= $userData['tipo_pix'] === 'celular' ? 'selected' : ''; ?>>Celular</option>
               <option value="email" <?= $userData['tipo_pix'] === 'email' ? 'selected' : ''; ?>>Email</option>
               <option value="chave-aleatoria" <?= $userData['tipo_pix'] === 'chave-aleatoria' ? 'selected' : ''; ?>>Chave Aleatória</option>
            </select>
            <label for="nome-titular">Nome do Titular:</label>
            <input type="text" id="nome-titular" value="<?= htmlspecialchars($userData['nome_titular']); ?>" disabled required>
            <label for="chave-pix-valor">Chave Pix:</label>
            <input type="text" id="chave-pix-valor" value="<?= htmlspecialchars($userData['chave_pix']); ?>" disabled required>
            <label for="valor_pix">Valor</label>
            <input type="number" name="valor_pix" id="valor_pix" min="30" max="9999" placeholder="Insira o valor de retirada" required>
            <button type="submit" class="invest-btn">Sacar</button>
         </form>
         <nav class="bottom-nav">
            <a href="../../inicio/"><i class="fas fa-home"></i> Início</a>
            <a href="../../investimentos/"><i class="fas fa-wallet"></i> Investimentos</a>
            <a href="../../team/"><i class="fas fa-users"></i> Equipe</a>
            <a href="../../perfil/"><i class="fas fa-user"></i> Perfil</a>
         </nav>
      </div>
      <?php if ($message): ?>
      <script>
         document.addEventListener("DOMContentLoaded", function() {
             <?= $message ?>
         });
      </script>
      <?php endif; ?>
   </body>
</html>