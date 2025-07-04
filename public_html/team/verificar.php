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
   
   function verificarConvidados($userId, $conn) {
       try {
           $stmt = $conn->prepare("SELECT codigo_referencia FROM usuarios WHERE id = ?");
           $stmt->execute([$userId]);
           $user = $stmt->fetch(PDO::FETCH_ASSOC);
   
           if (!$user) {
               throw new Exception("Usuário não encontrado.");
           }
   
           $codigoReferencia = $user['codigo_referencia'];
   
           // Obtém os usuários que usaram o código de referência (Nível 1)
           $stmt = $conn->prepare("SELECT id, codigo_referencia FROM usuarios WHERE referencia_convite = ?");
           $stmt->execute([$codigoReferencia]);
           $convidadosNivel1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
   
           $quantidadeConvidadosNivel1 = count($convidadosNivel1);
           $quantidadeConvidadosValidadosNivel1 = 0;
           $totalComissaoNivel1 = 0;
   
           $quantidadeConvidadosNivel2 = 0;
           $quantidadeConvidadosValidadosNivel2 = 0;
           $totalComissaoNivel2 = 0;
   
           foreach ($convidadosNivel1 as $convidadoNivel1) {
               $convidadoId = $convidadoNivel1['id'];
               $codigoReferenciaNivel1 = $convidadoNivel1['codigo_referencia'];
   
               // Calcula a soma dos depósitos aprovados e o número de depósitos para cada convidado de Nível 1
               $stmt = $conn->prepare("SELECT valor FROM pagamentos WHERE user_id = ? AND status = 'Aprovado'");
               $stmt->execute([$convidadoId]);
               $pagamentosAprovados = $stmt->fetchAll(PDO::FETCH_ASSOC);
   
               foreach ($pagamentosAprovados as $pagamento) {
                   $comissao = $pagamento['valor'] * 0.3;
                   $totalComissaoNivel1 += $comissao;
                   $quantidadeConvidadosValidadosNivel1++; // Conta cada pagamento aprovado como validado
               }
   
               // Obtém e valida os convidados de Nível 2
               $stmtNivel2 = $conn->prepare("SELECT id FROM usuarios WHERE referencia_convite = ?");
               $stmtNivel2->execute([$codigoReferenciaNivel1]);
               $convidadosNivel2 = $stmtNivel2->fetchAll(PDO::FETCH_ASSOC);
   
               $quantidadeConvidadosNivel2 += count($convidadosNivel2);
   
               foreach ($convidadosNivel2 as $convidadoNivel2) {
                   $convidadoIdNivel2 = $convidadoNivel2['id'];
   
                   // Calcula a soma dos depósitos aprovados e o número de depósitos para cada convidado de Nível 2
                   $stmt = $conn->prepare("SELECT valor FROM pagamentos WHERE user_id = ? AND status = 'Aprovado'");
                   $stmt->execute([$convidadoIdNivel2]);
                   $pagamentosAprovadosNivel2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
   
                   foreach ($pagamentosAprovadosNivel2 as $pagamentoNivel2) {
                       $comissaoNivel2 = $pagamentoNivel2['valor'] * 0.04; // Comissão de 4% para Nível 2
                       $totalComissaoNivel2 += $comissaoNivel2;
                       $quantidadeConvidadosValidadosNivel2++; // Conta cada pagamento aprovado como validado
                   }
               }
           }
   
           // Verifica se já existe um registro na tabela niveis_convite para o usuário
           $stmt = $conn->prepare("SELECT COUNT(*) FROM niveis_convite WHERE user_id = ?");
           $stmt->execute([$userId]);
           $existeRegistro = $stmt->fetchColumn() > 0;
   
           if ($existeRegistro) {
               // Atualiza o registro existente
               $stmt = $conn->prepare("UPDATE niveis_convite SET nivel_1 = ?, total_nivel1 = ?, nivel_2 = ?, total_nivel2 = ? WHERE user_id = ?");
               $stmt->execute([$quantidadeConvidadosNivel1, $totalComissaoNivel1, $quantidadeConvidadosNivel2, $totalComissaoNivel2, $userId]);
           } else {
               // Insere um novo registro se não existir
               $stmt = $conn->prepare("INSERT INTO niveis_convite (user_id, nivel_1, total_nivel1, nivel_2, total_nivel2) VALUES (?, ?, ?, ?, ?)");
               $stmt->execute([$userId, $quantidadeConvidadosNivel1, $totalComissaoNivel1, $quantidadeConvidadosNivel2, $totalComissaoNivel2]);
           }
   
           // Atualiza o saldo de comissão do usuário logado
           $saldoTotalComissao = $totalComissaoNivel1 + $totalComissaoNivel2;
           $stmt = $conn->prepare("UPDATE usuarios SET saldo_comissao = ? WHERE id = ?");
           $stmt->execute([$saldoTotalComissao, $userId]);
   
           return [
               'quantidadeConvidadosNivel1' => $quantidadeConvidadosNivel1,
               'quantidadeConvidadosValidadosNivel1' => $quantidadeConvidadosValidadosNivel1,
               'totalComissaoNivel1' => $totalComissaoNivel1,
               'quantidadeConvidadosNivel2' => $quantidadeConvidadosNivel2,
               'quantidadeConvidadosValidadosNivel2' => $quantidadeConvidadosValidadosNivel2,
               'totalComissaoNivel2' => $totalComissaoNivel2,
               'totalConvidados' => $quantidadeConvidadosNivel1 + $quantidadeConvidadosNivel2,
               'totalComissao' => $totalComissaoNivel1 + $totalComissaoNivel2,
               'totalConvidadosValidados' => $quantidadeConvidadosValidadosNivel1 + $quantidadeConvidadosValidadosNivel2
           ];
       } catch (Exception $e) {
           return ['error' => $e->getMessage()];
       }
   }
   
   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
       $resultado = verificarConvidados($_SESSION['user_id'], $conn);
   
       // Verifica se há erro
       if (isset($resultado['error'])) {
           // Caso haja erro, você pode redirecionar para uma página de erro ou exibir uma mensagem personalizada
           header('Location: ./');
           exit();
       } else {
           // Se não houver erro, redireciona de volta para a página inicial (./)
           header('Location: ./');
           exit();
       }
   }
   ?>