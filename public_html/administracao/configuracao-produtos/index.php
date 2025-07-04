<?php
session_start();
require_once '../../bank/db.php';

// Verificar se o usuário está logado e é admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../");
    exit();
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id']; 

$sql = "SELECT cargo FROM usuarios WHERE id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['cargo'] !== 'admin') {
    header('Location: ../../');
    exit();
}

// Buscar todos os produtos
try {
    $query = "SELECT 
        *,
        COALESCE(limite_compras, 100) as limite_compras_safe,
        COALESCE(vendidos, 0) as vendidos_safe,
        (COALESCE(limite_compras, 100) - COALESCE(vendidos, 0)) as restantes,
        COALESCE(duracao_dias, validade, 30) as duracao_dias_safe,
        COALESCE(robot_number, CONCAT('R', id + 50)) as robot_number_safe,
        COALESCE(tipo_rendimento, 'diario') as tipo_rendimento_safe,
        CASE 
            WHEN limite_dias_venda IS NOT NULL THEN 
                GREATEST(0, DATEDIFF(DATE_ADD(COALESCE(data_criacao, created_at), INTERVAL limite_dias_venda DAY), NOW()))
            ELSE NULL 
        END as dias_restantes_venda
    FROM produtos 
    ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $produtos = [];
    $error_message = "Erro ao carregar produtos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
   <title>Gerenciar Produtos - Dashboard</title>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="author" content="seemniick" />
  <link rel="icon" href="../assets/images/favicon.svg" type="image/x-icon" />
  <link rel="stylesheet" href="../assets/css/plugins/style.css">
  <link rel="stylesheet" href="../assets/fonts/inter/inter.css" id="main-font-link" />
  <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" />
  <link rel="stylesheet" href="../assets/fonts/feather.css" />
  <link rel="stylesheet" href="../assets/fonts/fontawesome.css" />
  <link rel="stylesheet" href="../assets/fonts/material.css" />
  <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
  <link rel="stylesheet" href="../assets/css/style-preset.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="dark">

<style>
 .loader-container {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: #131920; 
      z-index: 9999;
      display: flex;
      justify-content: center;
      align-items: center;
      flex-direction: column; 
    }
    .loader {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background-color: transparent;
      border: 8px solid #ffffff; 
      border-top-color: #4680ff; 
      animation: spin 1s linear infinite;
      margin-bottom: 20px; 
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    .loading-text {
      color: #ffffff; 
      font-size: 16px;
    }
    .preview-img {
      max-width: 200px;
      max-height: 200px;
      border-radius: 8px;
      margin-top: 10px;
    }
    .produto-img {
      width: 50px;
      height: 50px;
      border-radius: 8px;
      object-fit: cover;
    }
    .robot-badge {
      width: 50px;
      height: 50px;
      background: linear-gradient(135deg, #4680ff, #0d6efd);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 12px;
    }
</style>

<script>
      window.addEventListener("load", function() {
    const loader = document.querySelector('.loader-container');
    loader.style.display = 'none';
    document.body.style.overflow = 'auto'; 
  });
</script>

<div class="loader-container">
  <div class="loader"></div>
  <div class="loading-text">Carregando recursos, aguarde um pouco...</div>
</div>

<nav class="pc-sidebar">
         <div class="navbar-wrapper">
            <div class="m-header">
               <a href="../dashboard/index.html" class="b-brand text-primary">
               <img src="../assets/images/logo-dark.svg" class="img-fluid logo-lg" alt="logo" />
               </a>
            </div>
            <div class="navbar-content">
               <div class="card pc-user-card">
                  <div class="card-body">
                     <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                           <img src="../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar wid-45 rounded-circle" />
                        </div>
                        <div class="flex-grow-1 ms-3 me-2">
                           <h6 class="mb-0">Administrador</h6>
                           <small>Controle Geral</small>
                        </div>
                        <a class="btn btn-icon btn-link-secondary avtar" data-bs-toggle="collapse" href="#pc_sidebar_userlink">
                           <svg class="pc-icon">
                              <use xlink:href="#custom-sort-outline"></use>
                           </svg>
                        </a>
                     </div>
                     <div class="collapse pc-user-links" id="pc_sidebar_userlink">
                        <div class="pt-3">
                           <a href="#!">
                           <i class="ti ti-power"></i>
                           <span>Sair</span>
                           </a>
                        </div>
                     </div>
                  </div>
               </div>
               <ul class="pc-navbar">
                  <li class="pc-item pc-caption">
                     <label>Navegação</label>
                  </li>
                  <li class="pc-item">
                     <a href="../dashboard/" class="pc-link">
                        <span class="pc-micon">
                           <svg class="pc-icon">
                              <use xlink:href="#custom-status-up"></use>
                           </svg>
                        </span>
                        <span class="pc-mtext">Dashboard</span>
                     </a>
                  </li>
                  <li class="pc-item ">
                     <a href="../usuarios/" class="pc-link">
                     <span class="pc-micon">
                     <i class="ti ti-users"></i>
                     </span>
                     <span class="pc-mtext">Usuarios</span>
                     </a>
                  </li>
                  <li class="pc-item">
                     <a href="../afiliados/" class="pc-link">
                     <span class="pc-micon">
                     <i class="ti ti-users"></i>
                     </span>
                     <span class="pc-mtext">Afiliados</span>
                     </a>
                  </li>
                  <li class="pc-item pc-caption">
                     <label>Financeiro</label>
                     <svg class="pc-icon">
                        <use xlink:href="#custom-presentation-chart"></use>
                     </svg>
                  </li>
                  <li class="pc-item">
                     <a href="../entradas-geral/" class="pc-link">
                     <span class="pc-micon">
                     <i class="ti ti-arrow-bar-up"></i>
                     </span>
                     <span class="pc-mtext">Entradas Geral</span>
                     </a>
                  </li>
                  <li class="pc-item">
                     <a href="../saidas-usuarios/" class="pc-link">
                     <span class="pc-micon">
                     <i class="ti ti-arrow-bar-to-down"></i>
                     </span>
                     <span class="pc-mtext">Saidas de Usuarios</span>
                     </a>
                  </li>
                  <li class="pc-item">
                     <a href="../saidas-afiliados/" class="pc-link">
                     <span class="pc-micon">
                     <i class="ti ti-arrow-bar-to-down"></i>
                     </span>
                     <span class="pc-mtext">Saidas de Afiliados</span>
                     </a>
                  </li>
                  <li class="pc-item pc-caption">
                     <label>Plataforma</label>
                     <svg class="pc-icon">
                        <use xlink:href="#custom-presentation-chart"></use>
                     </svg>
                  </li>
                  <li class="pc-item">
                     <a href="../transacao-investidores/" class="pc-link">
                     <span class="pc-micon">
                    <i class="ti ti-chart-bar"></i>
                     </span>
                     <span class="pc-mtext">Investidores</span>
                     </a>
                  </li>
                  <li class="pc-item">
                     <a href="index.php" class="pc-link">
                     <span class="pc-micon">
                     <i class="ti ti-shopping-cart"></i>
                     </span>
                     <span class="pc-mtext">Produtos</span>
                     </a>
                  </li>
                  <li class="pc-item">
                     <a href="../codigos/" class="pc-link">
                     <span class="pc-micon">
                     <i class="ti ti-gift"></i>
                     </span>
                     <span class="pc-mtext">Códigos</span>
                     </a>
                  </li>
                  <li class="pc-item pc-caption">
                     <label>Configurações</label>
                     <svg class="pc-icon">
                        <use xlink:href="#custom-presentation-chart"></use>
                     </svg>
                  </li>
                  <li class="pc-item">
                     <a href="../dashboard/" class="pc-link">
                     <span class="pc-micon">
                     <i class="ti ti-settings"></i>
                     </span>
                     <span class="pc-mtext">Configurações Geral</span>
                     </a>
                  </li>
                  <li class="pc-item">
                     <a href="../configuracao-pagamentos/" class="pc-link">
                     <span class="pc-micon">
                     <i class="ti ti-building-bank"></i>
                     </span>
                     <span class="pc-mtext">Config de Pagamento</span>
                     </a>
                  </li>
                  <li class="pc-item">
                     <a href="../dashboard/" class="pc-link">
                     <span class="pc-micon">
                     <i class="ti ti-news"></i>
                     </span>
                     <span class="pc-mtext">Config de WebHook</span>
                     </a>
                  </li>
                  <li class="pc-item">
                     <a href="../configuracao-ceo/" class="pc-link">
                     <span class="pc-micon">
                     <i class="ti ti-world"></i>
                     </span>
                     <span class="pc-mtext">Configuração de Seo</span>
                     </a>
                  </li>
                  <li class="pc-item pc-caption">
                     <label>Personalização</label>
                     <svg class="pc-icon">
                        <use xlink:href="#custom-presentation-chart"></use>
                     </svg>
                  </li>
                  <li class="pc-item">
                     <a href="../personalizacao-cores/" class="pc-link">
                     <span class="pc-micon">
                     <i class="ti ti-palette"></i>
                     </span>
                     <span class="pc-mtext">Personalização de Cores</span>
                     </a>
                  </li>
                  <li class="pc-item">
                     <a href="../personalizacao-textos/" class="pc-link">
                     <span class="pc-micon">
                     <i class="ti ti-file-text"></i>
                     </span>
                     <span class="pc-mtext">Personalização de Textos</span>
                     </a>
                  </li>
                  <li class="pc-item">
                     <a href="../personalizar-banners/" class="pc-link">
                     <span class="pc-micon">
                     <i class="ti ti-photo "></i>
                     </span>
                     <span class="pc-mtext">Person de Imagens</span>
                     </a>
                  </li>
               </ul>
            </div>
         </div>
      </nav>
      
      <header class="pc-header">
         <div class="header-wrapper">
            <div class="me-auto pc-mob-drp">
               <ul class="list-unstyled">
                  <li class="pc-h-item pc-sidebar-collapse">
                     <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
                     <i class="ti ti-menu-2"></i>
                     </a>
                  </li>
                  <li class="pc-h-item pc-sidebar-popup">
                     <a href="#" class="pc-head-link ms-0" id="mobile-collapse">
                     <i class="ti ti-menu-2"></i>
                     </a>
                  </li>
               </ul>
            </div>
            <div class="ms-auto">
               <ul class="list-unstyled">
                  <li class="dropdown pc-h-item">
                     <a
                        class="pc-head-link dropdown-toggle arrow-none me-0"
                        data-bs-toggle="dropdown"
                        href="#"
                        role="button"
                        aria-haspopup="false"
                        aria-expanded="false"
                        >
                        <svg class="pc-icon">
                           <use xlink:href="#custom-sun-1"></use>
                        </svg>
                     </a>
                     <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                        <a href="#!" class="dropdown-item" onclick="layout_change('dark')">
                           <svg class="pc-icon">
                              <use xlink:href="#custom-moon"></use>
                           </svg>
                           <span>Escuro</span>
                        </a>
                        <a href="#!" class="dropdown-item" onclick="layout_change('light')">
                           <svg class="pc-icon">
                              <use xlink:href="#custom-sun-1"></use>
                           </svg>
                           <span>Claro</span>
                        </a>
                        <a href="#!" class="dropdown-item" onclick="layout_change_default()">
                           <svg class="pc-icon">
                              <use xlink:href="#custom-setting-2"></use>
                           </svg>
                           <span>Padrão</span>
                        </a>
                     </div>
                  </li>
                  <li class="dropdown pc-h-item header-user-profile">
                     <img src="../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar" />
                  </li>
               </ul>
            </div>
         </div>
      </header>

  <div class="pc-container">
    <div class="pc-content">
      <div class="page-header">
        <div class="page-block">
          <div class="row align-items-center">
            <div class="col-md-12">
              <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                <li class="breadcrumb-item" aria-current="page">Gerenciar Produtos</li>
              </ul>
            </div>
            <div class="col-md-12">
              <div class="page-header-title">
                <h2 class="mb-0">🛍️ Gerenciar Produtos</h2>
                <p class="text-muted">Visualize, edite e gerencie todos os produtos de investimento</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Mensagens de erro -->
      <?php if (isset($error_message)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
         <i class="ti ti-alert-circle me-2"></i>
         <?= htmlspecialchars($error_message) ?>
         <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php endif; ?>

      <!-- Estatísticas rápidas -->
      <div class="row mb-4">
         <div class="col-md-3">
            <div class="card bg-primary text-white">
               <div class="card-body">
                  <div class="d-flex align-items-center">
                     <div class="flex-grow-1">
                        <h3 class="mb-0"><?= count($produtos) ?></h3>
                        <p class="mb-0 opacity-75">Total de Produtos</p>
                     </div>
                     <div class="flex-shrink-0">
                        <i class="ti ti-package f-36"></i>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         <div class="col-md-3">
            <div class="card bg-success text-white">
               <div class="card-body">
                  <div class="d-flex align-items-center">
                     <div class="flex-grow-1">
                        <h3 class="mb-0"><?= count(array_filter($produtos, fn($p) => $p['status'] === 'ativo')) ?></h3>
                        <p class="mb-0 opacity-75">Produtos Ativos</p>
                     </div>
                     <div class="flex-shrink-0">
                        <i class="ti ti-check f-36"></i>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         <div class="col-md-3">
            <div class="card bg-warning text-white">
               <div class="card-body">
                  <div class="d-flex align-items-center">
                     <div class="flex-grow-1">
                        <h3 class="mb-0"><?= count(array_filter($produtos, fn($p) => $p['status'] === 'arquivado')) ?></h3>
                        <p class="mb-0 opacity-75">Produtos Arquivados</p>
                     </div>
                     <div class="flex-shrink-0">
                        <i class="ti ti-archive f-36"></i>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         <div class="col-md-3">
            <div class="card bg-info text-white">
               <div class="card-body">
                  <div class="d-flex align-items-center">
                     <div class="flex-grow-1">
                        <h3 class="mb-0"><?= array_sum(array_column($produtos, 'vendidos_safe')) ?></h3>
                        <p class="mb-0 opacity-75">Total de Vendas</p>
                     </div>
                     <div class="flex-shrink-0">
                        <i class="ti ti-chart-line f-36"></i>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <!-- Ações rápidas -->
      <div class="row mb-4">
         <div class="col-12">
            <div class="card">
               <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                     <h5 class="mb-0">Ações Rápidas</h5>
                     <div>
                        <a href="criar.html" class="btn btn-primary me-2">
                           <i class="ti ti-plus me-2"></i>
                           Criar Novo Produto
                        </a>
                        <button class="btn btn-outline-secondary" onclick="location.reload()">
                           <i class="ti ti-refresh me-2"></i>
                           Atualizar Lista
                        </button>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <!-- Lista de produtos -->
      <div class="row">
         <div class="col-12">
            <div class="card">
               <div class="card-header">
                  <h5>Lista de Produtos</h5>
               </div>
               <div class="card-body p-0">
                  <div class="table-responsive">
                     <table class="table table-hover mb-0">
                        <thead class="table-dark">
                           <tr>
                              <th>Produto</th>
                              <th>Valores</th>
                              <th>Configurações</th>
                              <th>Vendas</th>
                              <th>Status</th>
                              <th>Ações</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php if (empty($produtos)): ?>
                           <tr>
                              <td colspan="6" class="text-center py-4">
                                 <div class="text-muted">
                                    <i class="ti ti-package f-48 d-block mb-2"></i>
                                    <p class="mb-0">Nenhum produto encontrado</p>
                                    <a href="criar.html" class="btn btn-primary mt-2">
                                       <i class="ti ti-plus me-2"></i>
                                       Criar Primeiro Produto
                                    </a>
                                 </div>
                              </td>
                           </tr>
                           <?php else: ?>
                           <?php foreach ($produtos as $produto): ?>
                           <tr>
                              <td>
                                 <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 me-3">
                                       <?php if (!empty($produto['foto']) && $produto['foto'] !== 'default.jpg'): ?>
                                       <img src="../assets/images/produtos/<?= $produto['foto'] ?>" alt="Produto" class="produto-img">
                                       <?php else: ?>
                                       <div class="robot-badge">
                                          <?= $produto['robot_number_safe'] ?>
                                       </div>
                                       <?php endif; ?>
                                    </div>
                                    <div>
                                       <h6 class="mb-1"><?= htmlspecialchars($produto['titulo']) ?></h6>
                                       <small class="text-muted">
                                          Robô: <?= $produto['robot_number_safe'] ?> | 
                                          ID: <?= $produto['id'] ?>
                                       </small>
                                    </div>
                                 </div>
                              </td>
                              <td>
                                 <div>
                                    <strong>Investimento:</strong> R$ <?= number_format($produto['valor_investimento'], 2, ',', '.') ?><br>
                                    <strong>Renda:</strong> R$ <?= number_format($produto['renda_diaria'], 2, ',', '.') ?>
                                    <?= $produto['tipo_rendimento_safe'] === 'diario' ? '/dia' : ' (final)' ?><br>
                                    <small class="text-muted">
                                       Total: R$ <?= number_format($produto['receita_total'], 2, ',', '.') ?>
                                    </small>
                                 </div>
                              </td>
                              <td>
                                 <div>
                                    <strong>Duração:</strong> <?= $produto['duracao_dias_safe'] ?> dias<br>
                                    <strong>Tipo:</strong> 
                                    <span class="badge bg-<?= $produto['tipo_rendimento_safe'] === 'diario' ? 'success' : 'info' ?>">
                                       <?= ucfirst($produto['tipo_rendimento_safe']) ?>
                                    </span><br>
                                    <small class="text-muted">
                                       Limite p/ pessoa: <?= $produto['limite_compras_safe'] ?>
                                    </small>
                                 </div>
                              </td>
                              <td>
                                 <div>
                                    <strong><?= $produto['vendidos_safe'] ?></strong> vendidos<br>
                                    <small class="text-muted">
                                       Limite: <?= $produto['limite_compras_safe'] ?> por pessoa
                                    </small>
                                    <div class="progress mt-1" style="height: 4px;">
                                       <?php 
                                       $percent = $produto['limite_compras_safe'] > 0 ? 
                                          min(100, ($produto['vendidos_safe'] / ($produto['limite_compras_safe'] * 10)) * 100) : 0;
                                       ?>
                                       <div class="progress-bar bg-success" style="width: <?= $percent ?>%"></div>
                                    </div>
                                 </div>
                              </td>
                              <td>
                                 <span class="badge bg-<?php 
                                    echo $produto['status'] === 'ativo' ? 'success' : 
                                        ($produto['status'] === 'arquivado' ? 'warning' : 'danger');
                                 ?>">
                                    <?= ucfirst($produto['status']) ?>
                                 </span>
                                 <?php if ($produto['dias_restantes_venda'] !== null): ?>
                                 <br><small class="text-muted">
                                    <?= $produto['dias_restantes_venda'] ?> dias restantes
                                 </small>
                                 <?php endif; ?>
                              </td>
                              <td>
                                 <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="verDetalhes(<?= $produto['id'] ?>)" title="Ver Detalhes">
                                       <i class="ti ti-eye"></i>
                                    </button>
                                    
                                    <div class="btn-group" role="group">
                                       <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                               data-bs-toggle="dropdown" title="Status">
                                          <i class="ti ti-settings"></i>
                                       </button>
                                       <ul class="dropdown-menu">
                                          <li><a class="dropdown-item" href="#" onclick="alterarStatus(<?= $produto['id'] ?>, 'ativo')">
                                             <i class="ti ti-check text-success me-2"></i>Ativar
                                          </a></li>
                                          <li><a class="dropdown-item" href="#" onclick="alterarStatus(<?= $produto['id'] ?>, 'arquivado')">
                                             <i class="ti ti-archive text-warning me-2"></i>Arquivar
                                          </a></li>
                                          <li><a class="dropdown-item" href="#" onclick="alterarStatus(<?= $produto['id'] ?>, 'inativo')">
                                             <i class="ti ti-x text-danger me-2"></i>Desativar
                                          </a></li>
                                       </ul>
                                    </div>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                            onclick="duplicarProduto(<?= $produto['id'] ?>)" title="Duplicar">
                                       <i class="ti ti-copy"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="excluirProduto(<?= $produto['id'] ?>, '<?= addslashes($produto['titulo']) ?>')" 
                                            title="Excluir">
                                       <i class="ti ti-trash"></i>
                                    </button>
                                 </div>
                              </td>
                              </tr>
                           <?php endforeach; ?>
                           <?php endif; ?>
                        </tbody>
                     </table>
                  </div>
               </div>
            </div>
         </div>
      </div>
    </div>
  </div>

  <footer class="pc-footer">
    <div class="footer-wrapper container-fluid">
       <div class="row">
          <div class="col my-1">
             <p class="m-0"
                >Feito com muito &#9829; por <a href="https://t.me/devcorr3" target="_blank">Correa</a></p
                >
          </div>
          <div class="col-auto my-1">
             <ul class="list-inline footer-link mb-0">
                <li class="list-inline-item"><a href="../../inicio">Inicio</a></li>
                <li class="list-inline-item"><a href="https://t.me/devcorr3" target="_blank">Support</a></li>
             </ul>
          </div>
       </div>
    </div>
 </footer>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
      // Função para ver detalhes do produto
      function verDetalhes(id) {
         fetch('gerenciar_produtos.php', {
            method: 'POST',
            headers: {
               'Content-Type': 'application/json',
            },
            body: JSON.stringify({
               acao: 'obter_detalhes',
               id: id
            })
         })
         .then(response => response.json())
         .then(data => {
            if (data.success) {
               const produto = data.produto;
               Swal.fire({
                  title: produto.titulo,
                  html: `
                     <div class="text-start">
                        <p><strong>Descrição:</strong> ${produto.descricao || 'Sem descrição'}</p>
                        <p><strong>Robot Number:</strong> ${produto.robot_number_safe}</p>
                        <p><strong>Valor Investimento:</strong> R$ ${parseFloat(produto.valor_investimento).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                        <p><strong>Renda Diária:</strong> R$ ${parseFloat(produto.renda_diaria).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                        <p><strong>Receita Total:</strong> R$ ${parseFloat(produto.receita_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                        <p><strong>Duração:</strong> ${produto.duracao_dias_safe} dias</p>
                        <p><strong>Tipo Rendimento:</strong> ${produto.tipo_rendimento_safe}</p>
                        <p><strong>Limite por Pessoa:</strong> ${produto.limite_compras_safe}</p>
                        <p><strong>Vendidos:</strong> ${produto.vendidos_safe}</p>
                        <p><strong>Status:</strong> ${produto.status}</p>
                     </div>
                  `,
                  confirmButtonText: 'Fechar',
                  width: '600px'
               });
            } else {
               Swal.fire('Erro', data.message, 'error');
            }
         })
         .catch(error => {
            console.error('Erro:', error);
            Swal.fire('Erro', 'Erro ao carregar detalhes do produto', 'error');
         });
      }

      // Função para alterar status
      function alterarStatus(id, novoStatus) {
         Swal.fire({
            title: 'Confirmar Alteração',
            text: `Deseja alterar o status para "${novoStatus}"?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, alterar',
            cancelButtonText: 'Cancelar'
         }).then((result) => {
            if (result.isConfirmed) {
               fetch('gerenciar_produtos.php', {
                  method: 'POST',
                  headers: {
                     'Content-Type': 'application/json',
                  },
                  body: JSON.stringify({
                     acao: 'alterar_status',
                     id: id,
                     status: novoStatus
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
                  Swal.fire('Erro', 'Erro ao alterar status', 'error');
               });
            }
         });
      }

      // Função para duplicar produto
      function duplicarProduto(id) {
         Swal.fire({
            title: 'Duplicar Produto',
            text: 'Deseja criar uma cópia deste produto?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, duplicar',
            cancelButtonText: 'Cancelar'
         }).then((result) => {
            if (result.isConfirmed) {
               fetch('gerenciar_produtos.php', {
                  method: 'POST',
                  headers: {
                     'Content-Type': 'application/json',
                  },
                  body: JSON.stringify({
                     acao: 'duplicar',
                     id: id
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
                  Swal.fire('Erro', 'Erro ao duplicar produto', 'error');
               });
            }
         });
      }

      // Função para excluir produto
      function excluirProduto(id, titulo) {
         Swal.fire({
            title: 'Excluir Produto',
            text: `Tem certeza que deseja excluir "${titulo}"? Esta ação não pode ser desfeita!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
         }).then((result) => {
            if (result.isConfirmed) {
               fetch('gerenciar_produtos.php', {
                  method: 'POST',
                  headers: {
                     'Content-Type': 'application/json',
                  },
                  body: JSON.stringify({
                     acao: 'excluir',
                     id: id
                  })
               })
               .then(response => response.json())
               .then(data => {
                  if (data.success) {
                     Swal.fire('Excluído', data.message, 'success').then(() => {
                        location.reload();
                     });
                  } else {
                     Swal.fire('Erro', data.message, 'error');
                  }
               })
               .catch(error => {
                  console.error('Erro:', error);
                  Swal.fire('Erro', 'Erro ao excluir produto', 'error');
               });
            }
         });
      }
</script>

 <script src="../assets/js/plugins/apexcharts.min.js"></script>
 <script src="../assets/js/pages/dashboard-default.js"></script>
 <script src="../assets/js/plugins/popper.min.js"></script>
 <script src="../assets/js/plugins/simplebar.min.js"></script>
 <script src="../assets/js/plugins/bootstrap.min.js"></script>
 <script src="../assets/js/fonts/custom-font.js"></script>
 <script src="../assets/js/pcoded.js"></script>
 <script src="../assets/js/plugins/feather.min.js"></script>
 <script>
      layout_change('dark');
</script>
 <script>
    layout_theme_contrast_change('false');
 </script>
 <script>
    change_box_container('false');
 </script>
 <script>
    layout_caption_change('true');
 </script>
 <script>
    layout_rtl_change('false');
 </script>
 <script>
    preset_change('preset-1');
 </script>
 <script>
    main_layout_change('vertical');
 </script>
</body>
</html>