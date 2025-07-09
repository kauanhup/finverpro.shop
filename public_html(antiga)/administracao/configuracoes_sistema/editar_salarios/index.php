<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
   header('Location: ../../../');
   exit();
}

// Incluir o arquivo de conexão com o banco de dados
require '../../bank/db.php';

// Criar a conexão
try {
   $conn = getDBConnection();
} catch (Exception $e) {
   die("Erro de conexão: " . $e->getMessage());
}

// Obtém o id do usuário logado
$user_id = $_SESSION['user_id']; 

// Consultar a tabela 'usuarios' para verificar o cargo do usuário
$sql = "SELECT cargo FROM usuarios WHERE id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Se o usuário não for encontrado ou o cargo não for 'admin', redireciona
if (!$user || $user['cargo'] !== 'admin') {
   header('Location: ../../');
   exit();
}

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create_level':
                $stmt = $conn->prepare("
                    INSERT INTO salary_levels (level_code, level_name, level_description, min_people, 
                                             min_team_value, monthly_salary, icon, color, is_active, sort_order) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['level_code'],
                    $_POST['level_name'],
                    $_POST['level_description'],
                    $_POST['min_people'],
                    $_POST['min_team_value'],
                    $_POST['monthly_salary'],
                    $_POST['icon'],
                    $_POST['color'],
                    $_POST['is_active'] ? 1 : 0,
                    $_POST['sort_order']
                ]);
                echo json_encode(['success' => true, 'message' => 'Nível criado com sucesso!']);
                break;
                
            case 'update_level':
                $stmt = $conn->prepare("
                    UPDATE salary_levels 
                    SET level_name = ?, level_description = ?, min_people = ?, min_team_value = ?, 
                        monthly_salary = ?, icon = ?, color = ?, is_active = ?, sort_order = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['level_name'],
                    $_POST['level_description'],
                    $_POST['min_people'],
                    $_POST['min_team_value'],
                    $_POST['monthly_salary'],
                    $_POST['icon'],
                    $_POST['color'],
                    $_POST['is_active'] ? 1 : 0,
                    $_POST['sort_order'],
                    $_POST['level_id']
                ]);
                echo json_encode(['success' => true, 'message' => 'Nível atualizado com sucesso!']);
                break;
                
            case 'delete_level':
                $stmt = $conn->prepare("DELETE FROM salary_levels WHERE id = ? AND is_default = FALSE");
                $stmt->execute([$_POST['level_id']]);
                echo json_encode(['success' => true, 'message' => 'Nível excluído com sucesso!']);
                break;
                
            case 'approve_request':
                $conn->beginTransaction();
                
                // Buscar dados da solicitação
                $stmt = $conn->prepare("SELECT * FROM salary_requests WHERE id = ?");
                $stmt->execute([$_POST['request_id']]);
                $request = $stmt->fetch();
                
                // Aprovar solicitação
                $stmt = $conn->prepare("
                    UPDATE salary_requests 
                    SET status = 'aprovado', response_date = NOW(), admin_id = ?, admin_notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $_POST['admin_notes'], $_POST['request_id']]);
                
                // Criar pagamento
                $stmt = $conn->prepare("
                    INSERT INTO salary_payments (user_id, request_id, level_id, level_code, amount, released_by, admin_notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $request['user_id'],
                    $request['id'],
                    $request['level_id'],
                    $request['level_code'],
                    $request['requested_amount'],
                    $user_id,
                    $_POST['admin_notes']
                ]);
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Solicitação aprovada e salário liberado!']);
                break;
                
            case 'reject_request':
                $stmt = $conn->prepare("
                    UPDATE salary_requests 
                    SET status = 'rejeitado', response_date = NOW(), admin_id = ?, admin_notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $_POST['admin_notes'], $_POST['request_id']]);
                echo json_encode(['success' => true, 'message' => 'Solicitação rejeitada!']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit();
}

// Buscar dados para as abas
$levels = $conn->query("SELECT * FROM salary_levels ORDER BY sort_order, id")->fetchAll();
$pending_requests = $conn->query("
    SELECT sr.*, sl.level_name, sl.icon, sl.color, u.nome as user_name, u.email
    FROM salary_requests sr
    JOIN salary_levels sl ON sr.level_id = sl.id
    JOIN usuarios u ON sr.user_id = u.id
    WHERE sr.status = 'pendente'
    ORDER BY sr.request_date ASC
")->fetchAll();

$payments = $conn->query("
    SELECT sp.*, sl.level_name, sl.icon, u.nome as user_name
    FROM salary_payments sp
    JOIN salary_levels sl ON sp.level_id = sl.id
    JOIN usuarios u ON sp.user_id = u.id
    ORDER BY sp.release_date DESC
    LIMIT 50
")->fetchAll();

// Estatísticas
$stats = [
    'total_levels' => $conn->query("SELECT COUNT(*) FROM salary_levels WHERE is_active = 1")->fetchColumn(),
    'pending_requests' => $conn->query("SELECT COUNT(*) FROM salary_requests WHERE status = 'pendente'")->fetchColumn(),
    'total_payments' => $conn->query("SELECT COUNT(*) FROM salary_payments")->fetchColumn(),
    'total_available' => $conn->query("SELECT SUM(amount) FROM salary_payments WHERE status = 'disponivel'")->fetchColumn() ?: 0
];
?>

<!doctype html>
<html lang="pt-BR">

<head>
   <title>Editor de Salários - Dashboard</title>
   <meta charset="utf-8" />
   <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
   <meta http-equiv="X-UA-Compatible" content="IE=edge" />
   <link rel="icon" href="../../assets/images/favicon.svg" type="image/x-icon" />
   <link rel="stylesheet" href="../../assets/fonts/inter/inter.css" id="main-font-link" />
   <link rel="stylesheet" href="../../assets/fonts/tabler-icons.min.css" />
   <link rel="stylesheet" href="../../assets/fonts/feather.css" />
   <link rel="stylesheet" href="../../assets/fonts/fontawesome.css" />
   <link rel="stylesheet" href="../../assets/fonts/material.css" />
   <link rel="stylesheet" href="../../assets/css/style.css" id="main-style-link" />
   <link rel="stylesheet" href="../../assets/css/style-preset.css" />
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="dark">

   <!-- [ Sidebar Menu ] start -->
   <nav class="pc-sidebar">
      <div class="navbar-wrapper">
         <div class="m-header">
            <a href="../../dashboard/" class="b-brand text-primary">
               <img src="../../assets/images/logo-dark.svg" class="img-fluid logo-lg" alt="logo" />
            </a>
         </div>
         <div class="navbar-content">
            <div class="card pc-user-card">
               <div class="card-body">
                  <div class="d-flex align-items-center">
                     <div class="flex-shrink-0">
                        <img src="../../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar wid-45 rounded-circle" />
                     </div>
                     <div class="flex-grow-1 ms-3 me-2">
                        <h6 class="mb-0">Administrador</h6>
                        <small>Editor de Salários</small>
                     </div>
                     <a class="btn btn-icon btn-link-secondary avtar" data-bs-toggle="collapse" href="#pc_sidebar_userlink">
                        <svg class="pc-icon">
                           <use xlink:href="#custom-sort-outline"></use>
                        </svg>
                     </a>
                  </div>
                  <div class="collapse pc-user-links" id="pc_sidebar_userlink">
                     <div class="pt-3">
                        <a href="../../sair/">
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
                  <a href="../../dashboard/" class="pc-link">
                     <span class="pc-micon">
                        <svg class="pc-icon">
                           <use xlink:href="#custom-status-up"></use>
                        </svg>
                     </span>
                     <span class="pc-mtext">Dashboard</span>
                  </a>
               </li>
               <li class="pc-item pc-caption">
                  <label>Configurações</label>
               </li>
               <li class="pc-item">
                  <a href="../" class="pc-link">
                     <span class="pc-micon">
                        <i class="ti ti-settings"></i>
                     </span>
                     <span class="pc-mtext">Configurações Geral</span>
                  </a>
               </li>
               <li class="pc-item active">
                  <a href="#" class="pc-link">
                     <span class="pc-micon">
                        <i class="ti ti-cash"></i>
                     </span>
                     <span class="pc-mtext">Editor de Salários</span>
                  </a>
               </li>
            </ul>
         </div>
      </div>
   </nav>
   <!-- [ Sidebar Menu ] end -->

   <!-- [ Header Topbar ] start -->
   <header class="pc-header">
      <div class="header-wrapper">
         <div class="me-auto pc-mob-drp">
            <ul class="list-unstyled">
               <li class="pc-h-item pc-sidebar-collapse">
                  <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
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
                     aria-expanded="false">
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
                  </div>
               </li>
               <li class="dropdown pc-h-item header-user-profile">
                  <img src="../../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar" />
               </li>
            </ul>
         </div>
      </div>
   </header>
   <!-- [ Header ] end -->

   <!-- [ Main Content ] start -->
   <div class="pc-container">
      <div class="pc-content">
         
         <!-- Welcome Banner -->
         <div class="row">
            <div class="col-12">
               <div class="card welcome-banner bg-blue-800">
                  <div class="card-body">
                     <div class="row">
                        <div class="col-sm-6">
                           <div class="p-4">
                              <h2 class="text-white">💰 Editor de Salários</h2>
                              <p class="text-white">Configure níveis, aprove solicitações e gerencie o sistema MLM de salários.</p>
                              <a href="../" class="btn btn-outline-light">← Voltar às Configurações</a>
                           </div>
                        </div>
                        <div class="col-sm-6 text-center">
                           <div class="img-welcome-banner">
                              <img src="../../assets/images/widget/welcome-banner.png" alt="img" class="img-fluid">
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <!-- Stats Cards -->
         <div class="row">
            <div class="col-lg-3 col-md-6">
               <div class="card">
                  <div class="card-body">
                     <div class="row align-items-center">
                        <div class="col-8">
                           <h3 class="mb-1"><?= $stats['total_levels'] ?></h3>
                           <p class="text-muted mb-0">Níveis Ativos</p>
                        </div>
                        <div class="col-4 text-end">
                           <i class="ti ti-trophy text-warning f-36"></i>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
            <div class="col-lg-3 col-md-6">
               <div class="card">
                  <div class="card-body">
                     <div class="row align-items-center">
                        <div class="col-8">
                           <h3 class="mb-1"><?= $stats['pending_requests'] ?></h3>
                           <p class="text-muted mb-0">Solicitações Pendentes</p>
                        </div>
                        <div class="col-4 text-end">
                           <i class="ti ti-clock text-warning f-36"></i>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
            <div class="col-lg-3 col-md-6">
               <div class="card">
                  <div class="card-body">
                     <div class="row align-items-center">
                        <div class="col-8">
                           <h3 class="mb-1"><?= $stats['total_payments'] ?></h3>
                           <p class="text-muted mb-0">Total de Liberações</p>
                        </div>
                        <div class="col-4 text-end">
                           <i class="ti ti-check-circle text-success f-36"></i>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
            <div class="col-lg-3 col-md-6">
               <div class="card">
                  <div class="card-body">
                     <div class="row align-items-center">
                        <div class="col-8">
                           <h3 class="mb-1">R$ <?= number_format($stats['total_available'], 2, ',', '.') ?></h3>
                           <p class="text-muted mb-0">Valor Disponível</p>
                        </div>
                        <div class="col-4 text-end">
                           <i class="ti ti-cash text-success f-36"></i>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <!-- Main Content Tabs -->
         <div class="row">
            <div class="col-12">
               <div class="card">
                  <div class="card-header">
                     <ul class="nav nav-tabs" id="salaryTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                           <button class="nav-link active" id="levels-tab" data-bs-toggle="tab" data-bs-target="#levels" type="button" role="tab">
                              <i class="ti ti-trophy me-2"></i>Configurar Níveis
                           </button>
                        </li>
                        <li class="nav-item" role="presentation">
                           <button class="nav-link" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests" type="button" role="tab">
                              <i class="ti ti-clock me-2"></i>Solicitações
                              <?php if ($stats['pending_requests'] > 0): ?>
                                 <span class="badge bg-warning ms-2"><?= $stats['pending_requests'] ?></span>
                              <?php endif; ?>
                           </button>
                        </li>
                        <li class="nav-item" role="presentation">
                           <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                              <i class="ti ti-cash me-2"></i>Histórico de Liberações
                           </button>
                        </li>
                        <li class="nav-item" role="presentation">
                           <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab">
                              <i class="ti ti-chart-bar me-2"></i>Estatísticas
                           </button>
                        </li>
                     </ul>
                  </div>
                  <div class="card-body">
                     <div class="tab-content" id="salaryTabsContent">
                        
                        <!-- ABA 1: CONFIGURAR NÍVEIS -->
                        <div class="tab-pane fade show active" id="levels" role="tabpanel">
                           <div class="d-flex justify-content-between align-items-center mb-4">
                              <h5 class="mb-0">🏆 Níveis de Salário MLM</h5>
                              <button class="btn btn-primary" onclick="openLevelModal()">
                                 <i class="ti ti-plus me-2"></i>Novo Nível
                              </button>
                           </div>
                           
                           <div class="row">
                              <?php foreach ($levels as $level): ?>
                              <div class="col-lg-4 col-md-6 mb-4">
                                 <div class="card h-100" style="border-left: 4px solid <?= $level['color'] ?>">
                                    <div class="card-body">
                                       <div class="d-flex justify-content-between align-items-start mb-3">
                                          <div>
                                             <h6 class="mb-1">
                                                <?= $level['icon'] ?> <?= htmlspecialchars($level['level_name']) ?>
                                                <?php if ($level['is_default']): ?>
                                                   <span class="badge bg-secondary ms-2">Padrão</span>
                                                <?php endif; ?>
                                             </h6>
                                             <small class="text-muted"><?= htmlspecialchars($level['level_code']) ?></small>
                                          </div>
                                          <div class="dropdown">
                                             <button class="btn btn-link p-0" data-bs-toggle="dropdown">
                                                <i class="ti ti-dots-vertical"></i>
                                             </button>
                                             <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" onclick="editLevel(<?= $level['id'] ?>)">
                                                   <i class="ti ti-edit me-2"></i>Editar
                                                </a></li>
                                                <?php if (!$level['is_default']): ?>
                                                <li><a class="dropdown-item text-danger" onclick="deleteLevel(<?= $level['id'] ?>)">
                                                   <i class="ti ti-trash me-2"></i>Excluir
                                                </a></li>
                                                <?php endif; ?>
                                             </ul>
                                          </div>
                                       </div>
                                       
                                       <p class="text-muted small mb-3"><?= htmlspecialchars($level['level_description']) ?></p>
                                       
                                       <div class="mb-3">
                                          <div class="d-flex justify-content-between mb-2">
                                             <span>👥 Pessoas:</span>
                                             <strong><?= $level['min_people'] ?></strong>
                                          </div>
                                          <div class="d-flex justify-content-between mb-2">
                                             <span>💰 Valor Equipe:</span>
                                             <strong>R$ <?= number_format($level['min_team_value'], 0, ',', '.') ?></strong>
                                          </div>
                                          <div class="d-flex justify-content-between">
                                             <span>💸 Salário:</span>
                                             <strong class="text-success">R$ <?= number_format($level['monthly_salary'], 2, ',', '.') ?></strong>
                                          </div>
                                       </div>
                                       
                                       <div class="d-flex justify-content-between align-items-center">
                                          <span class="badge <?= $level['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                             <?= $level['is_active'] ? 'Ativo' : 'Inativo' ?>
                                          </span>
                                          <small class="text-muted">Ordem: <?= $level['sort_order'] ?></small>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                              <?php endforeach; ?>
                           </div>
                        </div>

                        <!-- ABA 2: SOLICITAÇÕES -->
                        <div class="tab-pane fade" id="requests" role="tabpanel">
                           <h5 class="mb-4">📩 Solicitações Pendentes</h5>
                           
                           <?php if (empty($pending_requests)): ?>
                              <div class="text-center py-5">
                                 <i class="ti ti-check-circle f-48 text-success mb-3"></i>
                                 <h6>Nenhuma solicitação pendente</h6>
                                 <p class="text-muted">Todas as solicitações foram processadas!</p>
                              </div>
                           <?php else: ?>
                              <div class="table-responsive">
                                 <table class="table table-hover">
                                    <thead>
                                       <tr>
                                          <th>Usuário</th>
                                          <th>Nível</th>
                                          <th>Valor</th>
                                          <th>Equipe</th>
                                          <th>Data</th>
                                          <th>Ações</th>
                                       </tr>
                                    </thead>
                                    <tbody>
                                       <?php foreach ($pending_requests as $request): ?>
                                       <tr>
                                          <td>
                                             <strong><?= htmlspecialchars($request['user_name']) ?></strong><br>
                                             <small class="text-muted"><?= htmlspecialchars($request['email']) ?></small>
                                          </td>
                                          <td>
                                             <span class="badge" style="background-color: <?= $request['color'] ?>">
                                                <?= $request['icon'] ?> <?= htmlspecialchars($request['level_name']) ?>
                                             </span>
                                          </td>
                                          <td>
                                             <strong class="text-success">R$ <?= number_format($request['requested_amount'], 2, ',', '.') ?></strong>
                                          </td>
                                          <td>
                                             <?= $request['current_team_people'] ?> pessoas<br>
                                             <small class="text-muted">R$ <?= number_format($request['current_team_value'], 0, ',', '.') ?></small>
                                          </td>
                                          <td>
                                             <?= date('d/m/Y H:i', strtotime($request['request_date'])) ?>
                                          </td>
                                          <td>
                                             <button class="btn btn-success btn-sm me-2" onclick="approveRequest(<?= $request['id'] ?>)">
                                                <i class="ti ti-check"></i> Aprovar
                                             </button>
                                             <button class="btn btn-danger btn-sm" onclick="rejectRequest(<?= $request['id'] ?>)">
                                                <i class="ti ti-x"></i> Rejeitar
                                             </button>
                                          </td>
                                       </tr>
                                       <?php endforeach; ?>
                                    </tbody>
                                 </table>
                              </div>
                           <?php endif; ?>
                        </div>

                        <!-- ABA 3: HISTÓRICO -->
                        <div class="tab-pane fade" id="payments" role="tabpanel">
                           <h5 class="mb-4">💰 Histórico de Liberações</h5>
                           
                           <div class="table-responsive">
                              <table class="table table-hover">
                                 <thead>
                                    <tr>
                                       <th>Usuário</th>
                                       <th>Nível</th>
                                       <th>Valor</th>
                                       <th>Status</th>
                                       <th>Liberação</th>
                                       <th>Transferência</th>
                                    </tr>
                                 </thead>
                                 <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr>
                                       <td><?= htmlspecialchars($payment['user_name']) ?></td>
                                       <td>
                                          <span class="badge bg-primary">
                                             <?= $payment['icon'] ?> <?= htmlspecialchars($payment['level_name']) ?>
                                          </span>
                                       </td>
                                       <td><strong class="text-success">R$ <?= number_format($payment['amount'], 2, ',', '.') ?></strong></td>
                                       <td>
                                          <span class="badge <?= $payment['status'] === 'disponivel' ? 'bg-warning' : 'bg-info' ?>">
                                             <?= $payment['status'] === 'disponivel' ? 'Disponível' : 'Transferido' ?>
                                          </span>
                                       </td>
                                       <td><?= date('d/m/Y H:i', strtotime($payment['release_date'])) ?></td>
                                       <td>
                                          <?php if ($payment['transfer_date']): ?>
                                             <?= date('d/m/Y H:i', strtotime($payment['transfer_date'])) ?>
                                          <?php else: ?>
                                             <span class="text-muted">-</span>
                                          <?php endif; ?>
                                       </td>
                                    </tr>
                                    <?php endforeach; ?>
                                 </tbody>
                              </table>
                           </div>
                        </div>

                        <!-- ABA 4: ESTATÍSTICAS -->
                        <div class="tab-pane fade" id="stats" role="tabpanel">
                           <h5 class="mb-4">📊 Estatísticas do Sistema</h5>
                           
                           <div class="row">
                              <div class="col-md-6">
                                 <div class="card">
                                    <div class="card-header">
                                       <h6>Usuários por Nível</h6>
                                    </div>
                                    <div class="card-body">
                                       <?php
                                       $user_stats = $conn->query("
                                           SELECT sl.level_name, sl.icon, sl.color, COUNT(DISTINCT u.id)as total_users
                                           FROM salary_levels sl
                                           LEFT JOIN (
                                               SELECT DISTINCT user_id, level_id FROM salary_payments
                                           ) sp ON sl.id = sp.level_id
                                           LEFT JOIN usuarios u ON sp.user_id = u.id
                                           WHERE sl.is_active = 1
                                           GROUP BY sl.id, sl.level_name, sl.icon, sl.color
                                           ORDER BY sl.sort_order
                                       ")->fetchAll();
                                       ?>
                                       <?php foreach ($user_stats as $stat): ?>
                                       <div class="d-flex justify-content-between align-items-center mb-3">
                                          <span><?= $stat['icon'] ?> <?= htmlspecialchars($stat['level_name']) ?></span>
                                          <span class="badge" style="background-color: <?= $stat['color'] ?>"><?= $stat['total_users'] ?> usuários</span>
                                       </div>
                                       <?php endforeach; ?>
                                    </div>
                                 </div>
                              </div>
                              
                              <div class="col-md-6">
                                 <div class="card">
                                    <div class="card-header">
                                       <h6>Valores por Status</h6>
                                    </div>
                                    <div class="card-body">
                                       <?php
                                       $value_stats = $conn->query("
                                           SELECT status, COUNT(*) as total_count, SUM(amount) as total_amount
                                           FROM salary_payments
                                           GROUP BY status
                                       ")->fetchAll();
                                       ?>
                                       <?php foreach ($value_stats as $stat): ?>
                                       <div class="d-flex justify-content-between align-items-center mb-3">
                                          <span><?= $stat['status'] === 'disponivel' ? '🟡 Disponível' : '🔵 Transferido' ?></span>
                                          <div class="text-end">
                                             <div><strong>R$ <?= number_format($stat['total_amount'], 2, ',', '.') ?></strong></div>
                                             <small class="text-muted"><?= $stat['total_count'] ?> liberações</small>
                                          </div>
                                       </div>
                                       <?php endforeach; ?>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>

                     </div>
                  </div>
               </div>
            </div>
         </div>

      </div>
   </div>

   <!-- Modal para Criar/Editar Nível -->
   <div class="modal fade" id="levelModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
         <div class="modal-content">
            <div class="modal-header">
               <h5 class="modal-title" id="levelModalTitle">
                  <i class="ti ti-plus me-2"></i>Novo Nível de Salário
               </h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="levelForm">
               <div class="modal-body">
                  <input type="hidden" id="levelId" name="level_id">
                  <input type="hidden" id="formAction" name="action" value="create_level">
                  
                  <div class="row">
                     <div class="col-md-6">
                        <div class="mb-3">
                           <label class="form-label">Código do Nível <span class="text-danger">*</span></label>
                           <input type="text" class="form-control" id="levelCode" name="level_code" required
                                  placeholder="Ex: BRONZE, PRATA, OURO">
                           <small class="form-text text-muted">Código único para identificar o nível</small>
                        </div>
                     </div>
                     <div class="col-md-6">
                        <div class="mb-3">
                           <label class="form-label">Nome do Nível <span class="text-danger">*</span></label>
                           <input type="text" class="form-control" id="levelName" name="level_name" required
                                  placeholder="Ex: Executivo Bronze">
                        </div>
                     </div>
                  </div>
                  
                  <div class="mb-3">
                     <label class="form-label">Descrição</label>
                     <textarea class="form-control" id="levelDescription" name="level_description" rows="2"
                               placeholder="Descrição detalhada do nível..."></textarea>
                  </div>
                  
                  <div class="row">
                     <div class="col-md-6">
                        <div class="mb-3">
                           <label class="form-label">Mínimo de Pessoas <span class="text-danger">*</span></label>
                           <input type="number" class="form-control" id="minPeople" name="min_people" required min="0"
                                  placeholder="0">
                        </div>
                     </div>
                     <div class="col-md-6">
                        <div class="mb-3">
                           <label class="form-label">Valor Mínimo da Equipe (R$) <span class="text-danger">*</span></label>
                           <input type="number" class="form-control" id="minTeamValue" name="min_team_value" 
                                  step="0.01" min="0" required placeholder="0.00">
                        </div>
                     </div>
                  </div>
                  
                  <div class="row">
                     <div class="col-md-6">
                        <div class="mb-3">
                           <label class="form-label">Salário Mensal (R$) <span class="text-danger">*</span></label>
                           <input type="number" class="form-control" id="monthlySalary" name="monthly_salary" 
                                  step="0.01" min="0" required placeholder="0.00">
                        </div>
                     </div>
                     <div class="col-md-6">
                        <div class="mb-3">
                           <label class="form-label">Ordem de Exibição</label>
                           <input type="number" class="form-control" id="sortOrder" name="sort_order" min="0" 
                                  value="0" placeholder="0">
                        </div>
                     </div>
                  </div>
                  
                  <div class="row">
                     <div class="col-md-6">
                        <div class="mb-3">
                           <label class="form-label">Ícone/Emoji</label>
                           <div class="input-group">
                              <input type="text" class="form-control" id="levelIcon" name="icon" 
                                     value="🏆" placeholder="🏆">
                              <button type="button" class="btn btn-outline-secondary" onclick="showEmojiPicker()">
                                 <i class="ti ti-mood-smile"></i>
                              </button>
                           </div>
                           <div class="emoji-picker mt-2" id="emojiPicker" style="display: none;">
                              <div class="d-flex flex-wrap gap-2">
                                 <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectEmoji('🌟')">🌟</button>
                                 <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectEmoji('🥉')">🥉</button>
                                 <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectEmoji('🥈')">🥈</button>
                                 <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectEmoji('🥇')">🥇</button>
                                 <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectEmoji('🏆')">🏆</button>
                                 <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectEmoji('👑')">👑</button>
                                 <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectEmoji('💎')">💎</button>
                                 <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectEmoji('⭐')">⭐</button>
                                 <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectEmoji('🚀')">🚀</button>
                                 <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectEmoji('💰')">💰</button>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div class="col-md-6">
                        <div class="mb-3">
                           <label class="form-label">Cor do Nível</label>
                           <div class="input-group">
                              <input type="color" class="form-control form-control-color" id="levelColor" 
                                     name="color" value="#10B981">
                              <input type="text" class="form-control" id="levelColorText" value="#10B981" 
                                     onchange="document.getElementById('levelColor').value = this.value">
                           </div>
                        </div>
                     </div>
                  </div>
                  
                  <div class="mb-3">
                     <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="isActive" name="is_active" checked>
                        <label class="form-check-label" for="isActive">
                           Nível Ativo
                        </label>
                     </div>
                  </div>
                  
                  <!-- Preview do Nível -->
                  <div class="mt-4">
                     <h6>Preview:</h6>
                     <div class="card" id="levelPreview" style="border-left: 4px solid #10B981; max-width: 300px;">
                        <div class="card-body">
                           <h6 class="mb-1">
                              <span id="previewIcon">🏆</span> <span id="previewName">Nome do Nível</span>
                           </h6>
                           <small class="text-muted" id="previewCode">CODIGO</small>
                           <div class="mt-2">
                              <div class="d-flex justify-content-between mb-1">
                                 <span>👥 Pessoas:</span>
                                 <strong id="previewPeople">0</strong>
                              </div>
                              <div class="d-flex justify-content-between mb-1">
                                 <span>💰 Valor Equipe:</span>
                                 <strong id="previewTeamValue">R$ 0</strong>
                              </div>
                              <div class="d-flex justify-content-between">
                                 <span>💸 Salário:</span>
                                 <strong class="text-success" id="previewSalary">R$ 0,00</strong>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                  <button type="submit" class="btn btn-primary">
                     <i class="ti ti-check me-2"></i>Salvar Nível
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>

   <!-- Modal para Aprovar/Rejeitar Solicitação -->
   <div class="modal fade" id="requestModal" tabindex="-1">
      <div class="modal-dialog">
         <div class="modal-content">
            <div class="modal-header">
               <h5 class="modal-title" id="requestModalTitle">
                  <i class="ti ti-check-circle me-2"></i>Processar Solicitação
               </h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="requestForm">
               <div class="modal-body">
                  <input type="hidden" id="requestId" name="request_id">
                  <input type="hidden" id="requestAction" name="action">
                  
                  <div class="mb-3">
                     <label class="form-label">Observações do Administrador</label>
                     <textarea class="form-control" id="adminNotes" name="admin_notes" rows="3"
                               placeholder="Adicione observações sobre sua decisão..."></textarea>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                  <button type="submit" class="btn" id="requestSubmitBtn">
                     <i class="ti ti-check me-2"></i>Confirmar
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>

   <!-- Scripts -->
   <script src="../../assets/js/plugins/popper.min.js"></script>
   <script src="../../assets/js/plugins/simplebar.min.js"></script>
   <script src="../../assets/js/plugins/bootstrap.min.js"></script>
   <script src="../../assets/js/fonts/custom-font.js"></script>
   <script src="../../assets/js/pcoded.js"></script>
   <script src="../../assets/js/plugins/feather.min.js"></script>

   <script>
      // Configurar tema
      layout_change('dark');

      // =====================================================
      // FUNÇÕES PARA NÍVEIS
      // =====================================================

      function openLevelModal(levelId = null) {
         const modal = new bootstrap.Modal(document.getElementById('levelModal'));
         const form = document.getElementById('levelForm');
         
         // Reset form
         form.reset();
         document.getElementById('levelId').value = '';
         document.getElementById('formAction').value = 'create_level';
         document.getElementById('levelModalTitle').innerHTML = '<i class="ti ti-plus me-2"></i>Novo Nível de Salário';
         
         if (levelId) {
            editLevel(levelId);
         } else {
            updatePreview();
         }
         
         modal.show();
      }

      function editLevel(levelId) {
         // Como não temos endpoint específico, vamos simular a edição
         // Em um sistema real, você faria uma requisição AJAX aqui
         
         // Buscar dados do nível do DOM (método simplificado)
         const levelCard = document.querySelector(`[onclick="editLevel(${levelId})"]`).closest('.card');
         
         if (levelCard) {
            // Extrair dados básicos do card
            const titleElement = levelCard.querySelector('h6');
            const icon = titleElement.textContent.charAt(0);
            const name = titleElement.textContent.trim().substring(2);
            
            // Preencher formulário (dados básicos)
            document.getElementById('levelId').value = levelId;
            document.getElementById('formAction').value = 'update_level';
            document.getElementById('levelIcon').value = icon;
            document.getElementById('levelName').value = name;
            
            // Atualizar título do modal
            document.getElementById('levelModalTitle').innerHTML = '<i class="ti ti-edit me-2"></i>Editar Nível de Salário';
            
            updatePreview();
            
            // Abrir modal
            const modal = new bootstrap.Modal(document.getElementById('levelModal'));
            modal.show();
         }
      }

      function deleteLevel(levelId) {
         Swal.fire({
            title: 'Excluir Nível?',
            text: 'Esta ação não pode ser desfeita!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
         }).then((result) => {
            if (result.isConfirmed) {
               // Enviar requisição para excluir
               fetch('', {
                  method: 'POST',
                  headers: {
                     'Content-Type': 'application/x-www-form-urlencoded',
                  },
                  body: `action=delete_level&level_id=${levelId}`
               })
               .then(response => response.json())
               .then(data => {
                  if (data.success) {
                     Swal.fire('Sucesso!', data.message, 'success');
                     setTimeout(() => location.reload(), 1500);
                  } else {
                     Swal.fire('Erro!', data.message, 'error');
                  }
               })
               .catch(error => {
                  console.error('Erro:', error);
                  Swal.fire('Erro!', 'Erro interno do servidor', 'error');
               });
            }
         });
      }

      // =====================================================
      // FUNÇÕES PARA SOLICITAÇÕES
      // =====================================================

      function approveRequest(requestId) {
         document.getElementById('requestId').value = requestId;
         document.getElementById('requestAction').value = 'approve_request';
         document.getElementById('requestModalTitle').innerHTML = '<i class="ti ti-check-circle me-2 text-success"></i>Aprovar Solicitação';
         document.getElementById('requestSubmitBtn').className = 'btn btn-success';
         document.getElementById('requestSubmitBtn').innerHTML = '<i class="ti ti-check me-2"></i>Aprovar';
         
         const modal = new bootstrap.Modal(document.getElementById('requestModal'));
         modal.show();
      }

      function rejectRequest(requestId) {
         document.getElementById('requestId').value = requestId;
         document.getElementById('requestAction').value = 'reject_request';
         document.getElementById('requestModalTitle').innerHTML = '<i class="ti ti-x-circle me-2 text-danger"></i>Rejeitar Solicitação';
         document.getElementById('requestSubmitBtn').className = 'btn btn-danger';
         document.getElementById('requestSubmitBtn').innerHTML = '<i class="ti ti-x me-2"></i>Rejeitar';
         
         const modal = new bootstrap.Modal(document.getElementById('requestModal'));
         modal.show();
      }

      // =====================================================
      // FUNÇÕES UTILITÁRIAS
      // =====================================================

      function updatePreview() {
         const icon = document.getElementById('levelIcon').value || '🏆';
         const name = document.getElementById('levelName').value || 'Nome do Nível';
         const code = document.getElementById('levelCode').value || 'CODIGO';
         const people = document.getElementById('minPeople').value || '0';
         const teamValue = parseFloat(document.getElementById('minTeamValue').value || 0);
         const salary = parseFloat(document.getElementById('monthlySalary').value || 0);
         const color = document.getElementById('levelColor').value || '#10B981';
         
         document.getElementById('previewIcon').textContent = icon;
         document.getElementById('previewName').textContent = name;
         document.getElementById('previewCode').textContent = code;
         document.getElementById('previewPeople').textContent = people;
         document.getElementById('previewTeamValue').textContent = 'R$ ' + teamValue.toLocaleString('pt-BR');
         document.getElementById('previewSalary').textContent = 'R$ ' + salary.toLocaleString('pt-BR', {minimumFractionDigits: 2});
         document.getElementById('levelPreview').style.borderLeftColor = color;
      }

      function showEmojiPicker() {
         const picker = document.getElementById('emojiPicker');
         picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
      }

      function selectEmoji(emoji) {
         document.getElementById('levelIcon').value = emoji;
         document.getElementById('emojiPicker').style.display = 'none';
         updatePreview();
      }

      // =====================================================
      // EVENT LISTENERS
      // =====================================================

      // Atualizar preview em tempo real
      document.addEventListener('DOMContentLoaded', function() {
         const inputs = ['levelIcon', 'levelName', 'levelCode', 'minPeople', 'minTeamValue', 'monthlySalary', 'levelColor'];
         inputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (input) {
               input.addEventListener('input', updatePreview);
            }
         });
         
         // Sincronizar color picker
         document.getElementById('levelColor').addEventListener('change', function() {
            document.getElementById('levelColorText').value = this.value;
            updatePreview();
         });
      });

      // Submit do formulário de nível
      document.getElementById('levelForm').addEventListener('submit', function(e) {
         e.preventDefault();
         
         const formData = new FormData(this);
         
         fetch('', {
            method: 'POST',
            body: formData
         })
         .then(response => response.json())
         .then(data => {
            if (data.success) {
               Swal.fire('Sucesso!', data.message, 'success');
               bootstrap.Modal.getInstance(document.getElementById('levelModal')).hide();
               setTimeout(() => location.reload(), 1500);
            } else {
               Swal.fire('Erro!', data.message, 'error');
            }
         })
         .catch(error => {
            console.error('Erro:', error);
            Swal.fire('Erro!', 'Erro interno do servidor', 'error');
         });
      });

      // Submit do formulário de solicitação
      document.getElementById('requestForm').addEventListener('submit', function(e) {
         e.preventDefault();
         
         const formData = new FormData(this);
         
         fetch('', {
            method: 'POST',
            body: formData
         })
         .then(response => response.json())
         .then(data => {
            if (data.success) {
               Swal.fire('Sucesso!', data.message, 'success');
               bootstrap.Modal.getInstance(document.getElementById('requestModal')).hide();
               setTimeout(() => location.reload(), 1500);
            } else {
               Swal.fire('Erro!', data.message, 'error');
            }
         })
         .catch(error => {
            console.error('Erro:', error);
            Swal.fire('Erro!', 'Erro interno do servidor', 'error');
         });
      });
   </script>

   <style>
      .emoji-picker {
         max-height: 150px;
         overflow-y: auto;
         border: 1px solid #dee2e6;
         border-radius: 8px;
         padding: 10px;
         background: #fff;
      }

      .form-control-color {
         width: 60px;
         height: 38px;
         border-radius: 6px;
      }

      /* Dark theme adjustments */
      [data-pc-theme="dark"] .emoji-picker {
         background: #2a3a47;
         border-color: #495057;
      }

      .nav-tabs .nav-link.active {
         background-color: var(--bs-primary);
         border-color: var(--bs-primary);
         color: white;
      }

      .table th {
         border-top: none;
         font-weight: 600;
      }

      .badge {
         font-size: 0.8em;
         padding: 6px 10px;
      }

      .modal-content {
         border-radius: 15px;
      }

      .card {
         transition: all 0.3s ease;
      }

      .card:hover {
         transform: translateY(-2px);
         box-shadow: 0 8px 25px rgba(0,0,0,0.15);
      }
   </style>

</body>
</html>