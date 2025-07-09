<!DOCTYPE html>
<html lang="pt-br">
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
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
      <link rel="stylesheet" href="../../assets/css/info-investimento.css">
   </head>
   <style></style>
   <body>
      <?php
         // Incluir o arquivo de conexão com o banco de dados
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
         
         // Chamar a função para obter a conexão com o banco de dados
         $conn = getDBConnection();
         
         // Verificar se o ID do produto foi enviado via GET ou POST
         if (isset($_GET['id'])) {
             $product_id = $_GET['id'];
         } elseif (isset($_POST['product_id'])) {
             $product_id = $_POST['product_id'];
         } else {
             echo "ID do produto não fornecido.";
             exit;
         }
         
         // Buscar o produto pelo ID
         $sql = "SELECT * FROM produtos WHERE id = :id";
         $stmt = $conn->prepare($sql);
         $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
         $stmt->execute();
         
         // Verificar se o produto foi encontrado
         if ($stmt->rowCount() > 0) {
             $product = $stmt->fetch(PDO::FETCH_ASSOC);
         ?>
      <style>
         :root {
         --background-color: <?= htmlspecialchars($cores['cor_1']) ?>;
         --text-color: <?= htmlspecialchars($cores['cor_2']) ?>;
         --primary-color: <?= htmlspecialchars($cores['cor_3']) ?>;
         --secondary-color: <?= htmlspecialchars($cores['cor_4']) ?>;
         --dark-background: <?= htmlspecialchars($cores['cor_5']) ?>;
         --font-family: Arial, sans-serif;
         }
      </style>
      <div class="container">
         <div class="image-container">
            <img src="../../assets/images/produtos/<?php echo htmlspecialchars($product['foto']); ?>" alt="<?php echo htmlspecialchars($product['titulo']); ?>">
            <div class="product-title"><?php echo htmlspecialchars($product['titulo']); ?></div>
            <div class="product-description">
               Invista no <?php echo htmlspecialchars($product['titulo']); ?> e decole rumo a retornos financeiros diários! Com um investimento de R$<?php echo number_format($product['valor_investimento'], 2, ',', '.'); ?>, você garante um retorno de R$<?php echo number_format($product['renda_diaria'], 2, ',', '.'); ?> por dia.
            </div>
         </div>
         <div class="details-container">
            <div class="details-title">Informações</div>
            <table class="details-table">
               <tr>
                  <th>Preço</th>
                  <td>R$ <?php echo number_format($product['valor_investimento'], 2, ',', '.'); ?></td>
               </tr>
               <tr>
                  <th>Renda Diária</th>
                  <td>R$ <?php echo number_format($product['renda_diaria'], 2, ',', '.'); ?></td>
               </tr>
               <tr>
                  <th>Validade</th>
                  <td><?php echo htmlspecialchars($product['validade']); ?> Dias</td>
               </tr>
               <tr>
                  <th>Receita Total</th>
                  <td>R$ <?php echo number_format($product['receita_total'], 2, ',', '.'); ?></td>
               </tr>
            </table>
            <form id="compraForm" method="POST">
               <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
               <button type="submit" class="confirm-btn">Confirmar</button>
            </form>
         </div>
      </div>
      <?php
         } else {
             echo "Produto não encontrado.";
         }
         
         $conn = null;
         ?>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <script>
         $(document).ready(function() {
             $('#compraForm').on('submit', function(e) {
                 e.preventDefault();
         
                 $.ajax({
                     url: 'concluir.php',
                     type: 'POST',
                     data: $(this).serialize(),
                     success: function(response) {
         
                         if (response.includes("sucesso")) {
                             Swal.fire({
                                 icon: 'success',
                                 title: 'Compra realizada!',
                                 text: 'Sua compra foi finalizada com sucesso.',
                                 confirmButtonText: 'OK',
                                 customClass: {
                                     popup: 'custom-swal-popup',
                                     confirmButton: 'custom-confirm-button'
                                 }
                             });
                         } else if (response.includes("insuficiente")) {
                             Swal.fire({
                                 icon: 'error',
                                 title: 'Saldo insuficiente!',
                                 text: 'Você precisa de saldo para realizar a compra.',
                                 confirmButtonText: 'OK',
                                 customClass: {
                                     popup: 'custom-swal-popup',
                                     confirmButton: 'custom-confirm-button'
                                 }
                             });
                         } else {
                             Swal.fire({
                                 icon: 'error',
                                 title: 'Erro',
                                 text: 'Ocorreu um erro ao processar sua compra.',
                                 confirmButtonText: 'OK',
                                 customClass: {
                                     popup: 'custom-swal-popup',
                                     confirmButton: 'custom-confirm-button'
                                 }
                             });
                         }
                     },
                     error: function() {
                         Swal.fire({
                             icon: 'error',
                             title: 'Erro',
                             text: 'Ocorreu um erro de conexão.',
                             confirmButtonText: 'OK',
                             customClass: {
                                 popup: 'custom-swal-popup',
                                 confirmButton: 'custom-confirm-button'
                             }
                         });
                     }
                 });
             });
         });
      </script>
      <nav class="bottom-nav">
         <a href="../../inicio/"><i class="fas fa-home"></i> Início</a>
         <a href="../../investimentos/"><i class="fas fa-wallet"></i> Investimentos</a>
         <a href="../../team/"><i class="fas fa-users"></i> Equipe</a>
         <a href="../../perfil/"><i class="fas fa-user"></i> Perfil</a>
      </nav>
   </body>
</html>