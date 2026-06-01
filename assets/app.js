// Ajuda simples para a interface do encurtador
document.addEventListener('DOMContentLoaded', function () {
  const shortUrlLink = document.querySelector('.short-url a');
  if (!shortUrlLink) return;

  // Adiciona botão de copiar quando o link curto aparece
  const container = document.querySelector('.short-url');
  const copyBtn = document.createElement('button');
  copyBtn.textContent = 'Copiar';
  copyBtn.style.marginLeft = '12px';
  copyBtn.style.padding = '8px 12px';
  copyBtn.style.borderRadius = '8px';
  copyBtn.style.border = 'none';
  copyBtn.style.background = '#10b981';
  copyBtn.style.color = '#fff';
  copyBtn.style.cursor = 'pointer';

  copyBtn.addEventListener('click', function () {
    const url = shortUrlLink.href;
    if (navigator.clipboard) {
      navigator.clipboard.writeText(url).then(() => {
        copyBtn.textContent = 'Copiado!';
        setTimeout(() => copyBtn.textContent = 'Copiar', 1500);
      });
    }
  });

  container.appendChild(copyBtn);
});
