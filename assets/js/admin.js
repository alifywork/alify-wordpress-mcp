document.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-copy]');
  if (!button) return;
  const el = document.querySelector(button.getAttribute('data-copy'));
  if (!el) return;
  try {
    await navigator.clipboard.writeText(el.textContent.trim());
    const old = button.textContent;
    button.textContent = 'Copied';
    setTimeout(() => { button.textContent = old; }, 1200);
  } catch (e) {
    window.prompt('Copy this value:', el.textContent.trim());
  }
});
