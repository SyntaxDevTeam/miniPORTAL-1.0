(() => {
  const shell = document.querySelector('.app-shell');
  const buttons = document.querySelectorAll('[data-menu-toggle]');
  const key = 'miniportal.sidebar.expanded';

  const setExpanded = (expanded) => {
    if (!shell) return;
    shell.classList.toggle('menu-expanded', expanded);
    document.documentElement.dataset.sidebar = expanded ? 'expanded' : 'collapsed';
    buttons.forEach((button) => {
      button.setAttribute('aria-expanded', String(expanded));
      const label = button.querySelector('[data-toggle-label]');
      if (label) label.textContent = expanded ? 'Zwiń menu' : 'Rozwiń menu';
    });
    try { localStorage.setItem(key, expanded ? '1' : '0'); } catch (_) {}
  };

  if (shell) {
    const fixed = shell.dataset.menuState;
    if (fixed === 'expanded') setExpanded(true);
    else if (fixed === 'collapsed') setExpanded(false);
    else {
      let stored = null;
      try { stored = localStorage.getItem(key); } catch (_) {}
      setExpanded(stored === '1');
    }
  }

  buttons.forEach((button) => button.addEventListener('click', () => {
    if (!shell) return;
    setExpanded(!shell.classList.contains('menu-expanded'));
  }));

  const overlay = document.querySelector('.search-overlay');
  const searchTriggers = document.querySelectorAll('[data-search-trigger]');
  const searchInput = overlay?.querySelector('input');
  const openSearch = () => {
    if (!overlay) return;
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
    window.setTimeout(() => searchInput?.focus(), 30);
  };
  const closeSearch = () => {
    if (!overlay) return;
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
  };
  searchTriggers.forEach(el => el.addEventListener('click', openSearch));
  overlay?.addEventListener('mousedown', (e) => { if (e.target === overlay) closeSearch(); });
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openSearch(); }
    if (e.key === 'Escape') closeSearch();
  });

  const calmToggle = document.querySelector('[data-calm-toggle]');
  calmToggle?.addEventListener('click', () => {
    document.body.classList.toggle('calmer');
  });
})();
