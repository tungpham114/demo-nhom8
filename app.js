(() => {
  const menu = document.querySelector('.menu-toggle');
  if (menu) menu.addEventListener('click', () => {
    const open = document.body.classList.toggle('nav-open');
    menu.setAttribute('aria-expanded', String(open));
  });
  const boxes = [...document.querySelectorAll('.compare-box')];
  const tray = document.querySelector('#compare-tray');
  let ids = [];
  function updateCompare() {
    boxes.forEach(box => { box.checked = ids.includes(box.value); box.disabled = ids.length >= 3 && !box.checked; });
    if (tray) tray.hidden = ids.length === 0;
    const count = document.querySelector('#compare-count');
    if (count) count.textContent = String(ids.length);
  }
  boxes.forEach(box => box.addEventListener('change', () => {
    ids = box.checked ? [...ids, box.value] : ids.filter(id => id !== box.value);
    updateCompare();
  }));
  document.querySelector('#compare-go')?.addEventListener('click', () => {
    if (ids.length) location.href = `index.php?page=compare&ids=${encodeURIComponent(ids.join(','))}`;
  });
  document.querySelectorAll('[data-share]').forEach(link => link.addEventListener('click', async event => {
    event.preventDefault();
    try { await navigator.clipboard.writeText(location.href); link.textContent = 'Đã sao chép liên kết ✓'; }
    catch { prompt('Sao chép liên kết tour:', location.href); }
  }));
})();
