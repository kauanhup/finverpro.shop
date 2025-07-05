<?php
// /roleta/includes/functions.php

function loadRoletaConfig() {
   $pdo = getDBConnection();
   $stmt = $pdo->query("SELECT * FROM roleta WHERE id = 1");
   $config = $stmt->fetch(PDO::FETCH_ASSOC);
   
   if (!$config || !$config['roleta_ativa']) {
      throw new Exception("A roleta está temporariamente indisponível.");
   }
   
   return $config;
}

function createUserSpinRecord($user_id) {
   $pdo = getDBConnection();
   $stmt = $pdo->prepare("INSERT INTO roleta_giros_usuario (usuario_id, giros_disponiveis, giros_hoje, data_reset_diario) VALUES (?, 0, 0, CURDATE())");
   $stmt->execute([$user_id]);
}

function resetDailyIfNeeded($user_spins, $user_id) {
   if ($user_spins['data_reset_diario'] != date('Y-m-d')) {
      $pdo = getDBConnection();
      $stmt = $pdo->prepare("UPDATE roleta_giros_usuario SET giros_hoje = 0, data_reset_diario = CURDATE() WHERE usuario_id = ?");
      $stmt->execute([$user_id]);
      $user_spins['giros_hoje'] = 0;
   }
   return $user_spins;
}

function loadUserSpins($user_id) {
   $pdo = getDBConnection();
   $stmt = $pdo->prepare("SELECT * FROM roleta_giros_usuario WHERE usuario_id = ?");
   $stmt->execute([$user_id]);
   $user_spins = $stmt->fetch(PDO::FETCH_ASSOC);
   
   if (!$user_spins) {
      createUserSpinRecord($user_id);
      return loadUserSpins($user_id);
   }
   
   return resetDailyIfNeeded($user_spins, $user_id);
}

function loadUserHistory($user_id) {
   $pdo = getDBConnection();
   $stmt = $pdo->prepare("SELECT * FROM roleta_historico WHERE usuario_id = ? ORDER BY data_giro DESC LIMIT 3");
   $stmt->execute([$user_id]);
   return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function validateSpin($user_spins, $config) {
   if ($user_spins['giros_disponiveis'] <= 0) {
      throw new Exception('Você não possui giros disponíveis!');
   }
   
   if ($user_spins['giros_hoje'] >= $config['limite_giros_dia']) {
      throw new Exception('Limite diário de giros atingido!');
   }
}

function getPremiosArray($config) {
   $premios = [];
   for ($i = 1; $i <= 8; $i++) {
      $premios[] = [
         'id' => $i,
         'nome' => $config["premio_{$i}_nome"],
         'tipo' => $config["premio_{$i}_tipo"],
         'valor' => $config["premio_{$i}_valor"],
         'cor' => $config["premio_{$i}_cor"],
         'chance' => $config["premio_{$i}_chance"]
      ];
   }
   return $premios;
}

function sortearPremio($config) {
   $premios = getPremiosArray($config);
   $random = mt_rand(1, 10000) / 100;
   $acumulado = 0;
   
   foreach ($premios as $premio) {
      $acumulado += $premio['chance'];
      if ($random <= $acumulado) {
         return $premio;
      }
   }
   
   return $premios[2]; // Fallback
}

function updateUserSpins($user_id) {
   $pdo = getDBConnection();
   $stmt = $pdo->prepare("UPDATE roleta_giros_usuario SET giros_disponiveis = giros_disponiveis - 1, giros_hoje = giros_hoje + 1, ultimo_giro = NOW(), total_giros_historico = total_giros_historico + 1 WHERE usuario_id = ?");
   $stmt->execute([$user_id]);
}

function saveToHistory($user_id, $premio) {
   $pdo = getDBConnection();
   $stmt = $pdo->prepare("INSERT INTO roleta_historico (usuario_id, premio_numero, premio_nome, premio_tipo, premio_valor, origem_giro, ip_address, data_giro) VALUES (?, ?, ?, ?, ?, 'manual', ?, NOW())");
   $stmt->execute([$user_id, $premio['id'], $premio['nome'], $premio['tipo'], $premio['valor'], $_SERVER['REMOTE_ADDR']]);
}

function updateWallet($user_id, $valor) {
   $pdo = getDBConnection();
   // CORREÇÃO: Tabela 'carteira' → 'carteiras' e usar saldo_principal
   $stmt = $pdo->prepare("UPDATE carteiras SET saldo_principal = saldo_principal + ? WHERE usuario_id = ?");
   $stmt->execute([$valor, $user_id]);
}

function processarGiro($user_id, $premio, $user_spins) {
   $pdo = getDBConnection();
   $pdo->beginTransaction();
   
   try {
      // Decrementar giros
      updateUserSpins($user_id);
      
      // Salvar histórico
      saveToHistory($user_id, $premio);
      
      // Adicionar prêmio se dinheiro
      if ($premio['tipo'] === 'dinheiro' && $premio['valor'] > 0) {
         updateWallet($user_id, $premio['valor']);
      }
      
      $pdo->commit();
      
      return [
         'giros_restantes' => $user_spins['giros_disponiveis'] - 1,
         'giros_hoje' => $user_spins['giros_hoje'] + 1
      ];
      
   } catch(Exception $e) {
      $pdo->rollBack();
      throw $e;
   }
}

// ✨ FUNÇÃO COM TEXTOS MELHORADOS
function renderWheelSegments($config) {
   $segments = [
      ['d' => 'M 160,160 L 160,10 A 150,150 0 0,1 266.27,53.73 Z', 'x' => 200, 'y' => 55, 'rotate' => 22.5],
      ['d' => 'M 160,160 L 266.27,53.73 A 150,150 0 0,1 310,160 Z', 'x' => 275, 'y' => 95, 'rotate' => 67.5],
      ['d' => 'M 160,160 L 310,160 A 150,150 0 0,1 266.27,266.27 Z', 'x' => 275, 'y' => 225, 'rotate' => 112.5],
      ['d' => 'M 160,160 L 266.27,266.27 A 150,150 0 0,1 160,310 Z', 'x' => 200, 'y' => 265, 'rotate' => 157.5],
      ['d' => 'M 160,160 L 160,310 A 150,150 0 0,1 53.73,266.27 Z', 'x' => 120, 'y' => 265, 'rotate' => 202.5],
      ['d' => 'M 160,160 L 53.73,266.27 A 150,150 0 0,1 10,160 Z', 'x' => 45, 'y' => 225, 'rotate' => 247.5],
      ['d' => 'M 160,160 L 10,160 A 150,150 0 0,1 53.73,53.73 Z', 'x' => 45, 'y' => 95, 'rotate' => 292.5],
      ['d' => 'M 160,160 L 53.73,53.73 A 150,150 0 0,1 160,10 Z', 'x' => 120, 'y' => 55, 'rotate' => 337.5]
   ];
   
   for($i = 1; $i <= 8; $i++) {
      $seg = $segments[$i-1];
      $nome = $config["premio_{$i}_nome"];
      $cor = $config["premio_{$i}_cor"];
      
      echo '<path d="' . $seg['d'] . '" fill="' . $cor . '"/>';
      
      // ✨ TEXTOS MELHORADOS - Fonte maior + sombra forte + posição otimizada
      echo '<text x="' . $seg['x'] . '" y="' . $seg['y'] . '" fill="white" font-family="Inter" font-size="14" font-weight="900" text-anchor="middle" transform="rotate(' . $seg['rotate'] . ' ' . $seg['x'] . ' ' . $seg['y'] . ')" style="text-shadow: 3px 3px 6px rgba(0,0,0,0.9), -1px -1px 2px rgba(0,0,0,0.8), 0 0 12px rgba(255,255,255,0.4);">';
      
      $palavras = explode(' ', $nome);
      if (count($palavras) <= 2) {
         echo '<tspan x="' . $seg['x'] . '" dy="0">' . htmlspecialchars($palavras[0]) . '</tspan>';
         if (isset($palavras[1])) {
            echo '<tspan x="' . $seg['x'] . '" dy="15">' . htmlspecialchars($palavras[1]) . '</tspan>';
         }
      } else {
         echo '<tspan x="' . $seg['x'] . '" dy="0">' . htmlspecialchars(substr($nome, 0, 9)) . '</tspan>';
         echo '<tspan x="' . $seg['x'] . '" dy="15">' . htmlspecialchars(substr($nome, 9)) . '</tspan>';
      }
      
      echo '</text>';
   }
}

function renderInfoItems($config) {
   echo '<div class="info-item">';
   echo '<i class="fas fa-dollar-sign"></i>';
   echo '<div class="content">';
   echo '<strong>Investindo</strong>';
   echo '<small>Invista R$ ' . number_format($config['valor_minimo_investimento'], 0) . '+ e ganhe ' . $config['giros_por_investimento'] . ' giro(s)</small>';
   echo '</div>';
   echo '</div>';
   
   echo '<div class="info-item">';
   echo '<i class="fas fa-users"></i>';
   echo '<div class="content">';
   echo '<strong>Indicações</strong>';
   echo '<small>Seu indicado investe = ' . $config['giros_por_indicacao'] . ' giro(s)</small>';
   echo '</div>';
   echo '</div>';
   
   echo '<div class="info-item">';
   echo '<i class="fas fa-gift"></i>';
   echo '<div class="content">';
   echo '<strong>Promoções</strong>';
   echo '<small>Eventos especiais</small>';
   echo '</div>';
   echo '</div>';
}

function renderHistoryItems($historico) {
   if (empty($historico)) {
      echo '<div class="empty-history">';
      echo '<i class="fas fa-inbox"></i>';
      echo '<h3>Nenhum giro realizado</h3>';
      echo '<p>Você ainda não realizou nenhum giro</p>';
      echo '</div>';
   } else {
      foreach ($historico as $item) {
         $typeClass = $item['premio_tipo'] === 'dinheiro' ? 'success' : ($item['premio_tipo'] === 'produto' ? 'warning' : 'neutral');
         $iconClass = $item['premio_tipo'] === 'dinheiro' ? 'fa-dollar-sign' : ($item['premio_tipo'] === 'produto' ? 'fa-gift' : 'fa-times');
         $badgeText = $item['premio_tipo'] === 'dinheiro' ? '+R$ ' . number_format($item['premio_valor'], 2, ',', '.') : ($item['premio_tipo'] === 'produto' ? '🎁' : '--');
         
         echo '<div class="history-item ' . $typeClass . '">';
         echo '<div class="history-icon ' . $typeClass . '">';
         echo '<i class="fas ' . $iconClass . '"></i>';
         echo '</div>';
         echo '<div class="history-content">';
         echo '<strong>' . htmlspecialchars($item['premio_nome']) . '</strong>';
         echo '<small>' . date('d/m H:i', strtotime($item['data_giro'])) . '</small>';
         echo '</div>';
         echo '<div class="history-badge ' . $typeClass . '">';
         echo $badgeText;
         echo '</div>';
         echo '</div>';
      }
   }
}
?>