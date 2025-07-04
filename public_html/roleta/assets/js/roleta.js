class RoletaCosmic {
   constructor() {
      this.isSpinning = false;
      this.currentRotation = 0;
      this.userData = window.roletaData;
      
      console.log('🚀 Roleta Cosmic iniciada');
      console.log('👤 Dados do usuário:', this.userData);
      
      this.init();
   }
   
   init() {
      this.bindEvents();
   }
   
   bindEvents() {
      const spinBtn = document.getElementById('spinBtn');
      if (spinBtn) {
         console.log('✅ Botão encontrado!');
         spinBtn.addEventListener('click', () => {
            console.log('🎯 Botão clicado!');
            this.spin();
         });
      } else {
         console.error('❌ Botão não encontrado!');
      }
      
      // Shortcut com Space
      document.addEventListener('keydown', (e) => {
         if (e.code === 'Space' && !this.isSpinning) {
            e.preventDefault();
            console.log('⌨️ Space pressionado!');
            this.spin();
         }
      });
   }
   
   async spin() {
      console.log('🎲 Função spin chamada!');
      
      if (this.isSpinning) {
         console.log('⏳ Já está girando...');
         this.showAlert('⏳ Aguarde a roleta parar!');
         return;
      }
      
      if (this.userData.userSpins.giros_disponiveis <= 0) {
         console.log('❌ Sem giros disponíveis');
         this.showAlert('❌ Sem giros disponíveis!');
         return;
      }
      
      if (this.userData.userSpins.giros_hoje >= this.userData.config.limite_giros_dia) {
         console.log('❌ Limite diário atingido');
         this.showAlert('❌ Limite diário atingido!');
         return;
      }
      
      try {
         console.log('🚀 Iniciando giro...');
         this.startSpin();
         const result = await this.makeRequest();
         this.processSpin(result);
      } catch (error) {
         console.error('💥 Erro no giro:', error);
         this.handleError(error);
      }
   }
   
   startSpin() {
      this.isSpinning = true;
      this.updateUI('spinning');
   }
   
   async makeRequest() {
      console.log('📡 Fazendo requisição...');
      
      const response = await fetch('api/girar.php', {
         method: 'POST',
         headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
      });
      
      console.log('📥 Response status:', response.status);
      
      if (!response.ok) {
         throw new Error(`HTTP ${response.status}`);
      }
      
      const result = await response.json();
      
      // ✅ DEBUG COMPLETO
      console.log('🔍 Resposta completa da API:', result);
      console.log('🎁 Campo premio:', result.premio);
      console.log('✅ Success:', result.success);
      console.log('📊 Giros restantes:', result.giros_restantes);
      console.log('📅 Giros hoje:', result.giros_hoje);
      
      return result;
   }
   
   processSpin(result) {
      console.log('⚡ Processando resultado...');
      
      if (!result.success) {
         throw new Error(result.error || 'Erro desconhecido');
      }
      
      // ✅ VERIFICAÇÃO ROBUSTA
      if (!result.premio) {
         console.error('❌ Premio não encontrado:', result);
         throw new Error('Prêmio não encontrado na resposta');
      }
      
      if (!result.premio.id) {
         console.error('❌ Premio sem ID:', result.premio);
         throw new Error('Prêmio sem ID válido');
      }
      
      console.log('🎯 Prêmio válido encontrado:', result.premio);
      
      this.animateWheel(result.premio);
      this.updateData(result);
      
      setTimeout(() => {
         this.finishSpin(result.premio);
      }, 4000);
   }
   
   animateWheel(premio) {
      console.log('🌀 Animando roleta para prêmio:', premio.id);
      
      const segmentAngle = 360 / 8;
      const targetAngle = (premio.id - 1) * segmentAngle;
      const randomOffset = (Math.random() - 0.5) * segmentAngle * 0.5;
      const totalRotation = this.currentRotation + 1800 + (360 - targetAngle) + randomOffset;
      
      console.log('🎯 Rotação calculada:', totalRotation);
      
      const wheel = document.getElementById('cosmicWheel');
      if (wheel) {
         wheel.style.transform = `rotate(${totalRotation}deg)`;
         this.currentRotation = totalRotation % 360;
         console.log('✅ Roleta animada');
      } else {
         console.error('❌ Elemento wheel não encontrado');
      }
   }
   
   finishSpin(premio) {
      console.log('🏁 Finalizando giro');
      
      this.isSpinning = false;
      this.updateUI('idle');
      this.showResult(premio);
      this.addToHistory(premio);
   }
   
   updateUI(state) {
      const btn = document.getElementById('spinBtn');
      const status = document.getElementById('status-text');
      
      if (state === 'spinning') {
         if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<div class="center-icon">🌀</div><div class="center-text">GIRANDO</div>';
         }
         if (status) status.textContent = 'Girando através do cosmos...';
      } else {
         if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<div class="center-icon">🚀</div><div class="center-text">GIRAR</div>';
         }
         if (status) status.textContent = 'Clique para girar através do cosmos!';
      }
   }
   
   updateData(result) {
      console.log('📊 Atualizando dados da interface');
      
      this.userData.userSpins.giros_disponiveis = result.giros_restantes;
      this.userData.userSpins.giros_hoje = result.giros_hoje;
      
      const girosEl = document.getElementById('giros-disponiveis');
      const hojeEl = document.getElementById('giros-hoje');
      
      if (girosEl) girosEl.textContent = result.giros_restantes;
      if (hojeEl) hojeEl.textContent = result.giros_hoje;
   }
   
   showResult(premio) {
      console.log('🎉 Mostrando resultado:', premio);
      
      let icon, title, message;
      
      if (premio.tipo === 'dinheiro') {
         icon = 'success';
         title = '🎉 Parabéns!';
         message = `Você ganhou R$ ${parseFloat(premio.valor).toFixed(2).replace('.', ',')}!`;
      } else if (premio.tipo === 'produto') {
         icon = 'success';
         title = '🎁 Parabéns!';
         message = `Você ganhou: ${premio.nome}!`;
      } else {
         icon = 'info';
         title = '😔 Que pena!';
         message = premio.nome;
      }
      
      if (typeof Swal !== 'undefined') {
         Swal.fire({ 
            icon, 
            title, 
            text: message, 
            confirmButtonText: '✨ Continuar' 
         });
      } else {
         alert(`${title}\n${message}`);
      }
   }
   
   addToHistory(premio) {
      console.log('📝 Adicionando ao histórico:', premio);
      
      const historyList = document.getElementById('history-list');
      if (!historyList) {
         console.log('❌ Lista de histórico não encontrada');
         return;
      }
      
      // Remover mensagem de "nenhum giro"
      const emptyMessage = historyList.querySelector('.empty-history');
      if (emptyMessage) {
         emptyMessage.remove();
      }
      
      // Criar novo item
      const historyItem = document.createElement('div');
      const typeClass = premio.tipo === 'dinheiro' ? 'success' : (premio.tipo === 'produto' ? 'warning' : 'neutral');
      const iconClass = premio.tipo === 'dinheiro' ? 'fa-dollar-sign' : (premio.tipo === 'produto' ? 'fa-gift' : 'fa-times');
      const badgeText = premio.tipo === 'dinheiro' ? `+R$ ${parseFloat(premio.valor).toFixed(2).replace('.', ',')}` : (premio.tipo === 'produto' ? '🎁' : '--');
      
      const now = new Date();
      const timeString = now.toLocaleDateString('pt-BR').slice(0, 5) + ' ' + now.toLocaleTimeString('pt-BR').slice(0, 5);
      
      historyItem.className = `history-item ${typeClass}`;
      historyItem.innerHTML = `
         <div class="history-icon ${typeClass}">
            <i class="fas ${iconClass}"></i>
         </div>
         <div class="history-content">
            <strong>${premio.nome}</strong>
            <small>${timeString}</small>
         </div>
         <div class="history-badge ${typeClass}">
            ${badgeText}
         </div>
      `;
      
      // Adicionar no início da lista
      historyList.insertBefore(historyItem, historyList.firstChild);
      
      // Manter apenas os últimos 3 itens
      const items = historyList.querySelectorAll('.history-item');
      if (items.length > 3) {
         for (let i = 3; i < items.length; i++) {
            items[i].remove();
         }
      }
      
      console.log('✅ Item adicionado ao histórico');
   }
   
   showAlert(message) {
      if (typeof Swal !== 'undefined') {
         Swal.fire({ 
            icon: 'warning', 
            title: message,
            confirmButtonText: 'OK'
         });
      } else {
         alert(message);
      }
   }
   
   handleError(error) {
      console.error('💥 Erro completo:', error);
      this.isSpinning = false;
      this.updateUI('idle');
      this.showAlert('❌ Erro: ' + error.message);
   }
}

// ===== INICIALIZAÇÃO =====
document.addEventListener('DOMContentLoaded', () => {
   console.log('📄 DOM carregado, iniciando roleta...');
   
   // Verificar se dados estão disponíveis
   if (typeof window.roletaData === 'undefined') {
      console.error('❌ Dados da roleta não encontrados!');
      return;
   }
   
   // Inicializar roleta
   new RoletaCosmic();
   
   console.log('✅ Roleta totalmente carregada!');
});