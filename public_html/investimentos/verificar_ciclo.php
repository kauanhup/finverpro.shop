<?php
   require '../bank/db.php';
   $conn = getDBConnection();
   session_start();
   
   $user_id = $_SESSION['user_id'];
   
   // Consulta para buscar todos os investimentos do usuário com detalhes do produto e do saldo
   $sql_investor = "SELECT i.*, p.renda_diaria, u.saldo 
                    FROM investidores i
                    JOIN produtos p ON i.produto_investido = p.id
                    JOIN usuarios u ON i.id_usuario = u.id
                    WHERE i.id_usuario = :user_id";
   $stmt = $conn->prepare($sql_investor);
   $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   
   if ($stmt->rowCount() > 0) {
       $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
       $total_rendimento = 0;
       $ciclos_atualizados = 0;
   
       foreach ($investments as $investment) {
           $ultimo_ciclo = $investment['ultimo_ciclo'] ?? $investment['data_investimento'];
           $data_ultimo_ciclo = new DateTime($ultimo_ciclo);
           $now = new DateTime();
   
           // Calcula a diferença em horas
           $interval = $data_ultimo_ciclo->diff($now);
           $horas_passadas = $interval->h + ($interval->days * 24);
   
           // Verifica se já passaram 24 horas para este investimento
           if ($horas_passadas >= 24) {
               $novo_ciclo = $investment['ciclo_rendimento'] + 1;
               $novo_renda_total = $investment['renda_total'] + $investment['renda_diaria'];
               $total_rendimento += $investment['renda_diaria'];
               $ciclos_atualizados++;
   
               // Atualiza os dados para este investimento na tabela de investidores
               $sql_update_investor = "UPDATE investidores 
                                       SET ciclo_rendimento = :novo_ciclo, renda_total = :novo_renda_total, ultimo_ciclo = NOW()
                                       WHERE id = :id_investor";
               $stmt_update_investor = $conn->prepare($sql_update_investor);
               $stmt_update_investor->bindParam(':novo_ciclo', $novo_ciclo, PDO::PARAM_INT);
               $stmt_update_investor->bindParam(':novo_renda_total', $novo_renda_total);
               $stmt_update_investor->bindParam(':id_investor', $investment['id'], PDO::PARAM_INT);
               $stmt_update_investor->execute();
           }
       }
   
       // Se algum ciclo foi atualizado, soma o total de rendimento ao saldo do usuário
       if ($ciclos_atualizados > 0) {
           $novo_saldo_usuario = $investment['saldo'] + $total_rendimento;
   
           // Atualiza o saldo do usuário
           $sql_update_user = "UPDATE usuarios SET saldo = :novo_saldo WHERE id = :user_id";
           $stmt_update_user = $conn->prepare($sql_update_user);
           $stmt_update_user->bindParam(':novo_saldo', $novo_saldo_usuario);
           $stmt_update_user->bindParam(':user_id', $user_id, PDO::PARAM_INT);
           $stmt_update_user->execute();
   
           echo "Ciclos atualizados com sucesso! Rendimento total de $total_rendimento adicionado.";
       } else {
           echo "Nenhum investimento completou o ciclo de 24 horas.";
       }
   
   } else {
       echo "Nenhum investimento encontrado.";
   }
   
   $conn = null;
   ?>