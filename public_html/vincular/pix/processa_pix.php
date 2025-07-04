<?php
   session_start();
   header('Content-Type: application/json');
   
   // Verifica se o usuário está logado
   if (!isset($_SESSION['user_id'])) {
       echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
       exit();
   }
   
   // Incluir o arquivo de conexão com o banco de dados
   require '../../bank/db.php';
   
   try {
       $conn = getDBConnection();
   
       // Recebe os dados JSON
       $data = json_decode(file_get_contents("php://input"), true);
       $tipo_pix = $data['tipo_pix'];
       $nome_titular = $data['nome_titular'];
       $chave_pix = $data['chave_pix'];
   
       // Remove formatação se o tipo for CPF ou Celular
       if ($tipo_pix === 'cpf' || $tipo_pix === 'celular') {
           $chave_pix = preg_replace('/\D/', '', $chave_pix); // Remove tudo que não é dígito
       }
   
       // Prepara a consulta SQL para inserção dos dados
       $sql = "UPDATE usuarios SET tipo_pix = :tipo_pix, nome_titular = :nome_titular, chave_pix = :chave_pix WHERE id = :user_id";
       $stmt = $conn->prepare($sql);
   
       // Associa os valores e executa a consulta
       $stmt->bindParam(':tipo_pix', $tipo_pix);
       $stmt->bindParam(':nome_titular', $nome_titular);
       $stmt->bindParam(':chave_pix', $chave_pix);
       $stmt->bindParam(':user_id', $_SESSION['user_id']);
   
       if ($stmt->execute()) {
           echo json_encode(['success' => true]);
       } else {
           echo json_encode(['success' => false, 'message' => 'Erro ao salvar a chave Pix.']);
       }
   } catch (Exception $e) {
       echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco de dados.']);
   }
   ?>