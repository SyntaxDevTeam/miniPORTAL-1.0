(() => {
  const shell = document.querySelector('.app-shell');
  const buttons = document.querySelectorAll('[data-menu-toggle]');
  const isInitiallyExpanded = shell?.dataset.menuState === 'expanded';

  const setExpanded = (expanded) => {
    if (!shell) return;
    shell.classList.toggle('menu-expanded', expanded);
    shell.classList.toggle('menu-collapsed', !expanded);
    document.documentElement.dataset.sidebar = expanded ? 'expanded' : 'collapsed';
    buttons.forEach((button) => {
      button.setAttribute('aria-expanded', String(expanded));
      const label = button.querySelector('[data-toggle-label]');
      if (label) label.textContent = expanded ? 'Zwiń menu' : 'Rozwiń menu';
    });
  };

  if (shell) setExpanded(isInitiallyExpanded);
  buttons.forEach((button) => button.addEventListener('click', () => {
    setExpanded(!shell.classList.contains('menu-expanded'));
  }));

  const overlay = document.querySelector('.search-overlay');
  const searchTriggers = document.querySelectorAll('[data-search-trigger]');
  const searchInput = overlay?.querySelector('input');
  const searchModal = overlay?.querySelector('.search-modal');

  if (searchInput && searchModal) {
    searchInput.setAttribute('name', 'q');
    searchInput.setAttribute('hx-get', 'partials/search-suggestions.html');
    searchInput.setAttribute('hx-trigger', 'keyup changed delay:180ms');
    searchInput.setAttribute('hx-target', '#search-results');
    if (!document.getElementById('search-results')) {
      const results = document.createElement('div');
      results.id = 'search-results';
      const hint = searchModal.querySelector('.search-hint');
      searchModal.insertBefore(results, hint || null);
    }
  }

  const openSearch = () => {
    if (!overlay) return;
    overlay.classList.add('open', 'is-open');
    overlay.setAttribute('aria-hidden', 'false');
    window.setTimeout(() => searchInput?.focus(), 30);
  };
  const closeSearch = () => {
    if (!overlay) return;
    overlay.classList.remove('open', 'is-open');
    overlay.setAttribute('aria-hidden', 'true');
  };

  searchTriggers.forEach((node) => node.addEventListener('click', openSearch));
  overlay?.addEventListener('mousedown', (event) => { if (event.target === overlay) closeSearch(); });
  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      openSearch();
    }
    if (event.key === 'Escape') closeSearch();
  });

  const ensureHtmx = () => {
    if (window.htmx || document.querySelector('script[data-miniportal-htmx]')) return;
    const script = document.createElement('script');
    script.src = 'https://unpkg.com/htmx.org@2.0.4';
    script.defer = true;
    script.dataset.miniportalHtmx = 'true';
    script.addEventListener('load', () => {
      if (window.htmx) window.htmx.process(document.body);
    });
    document.head.appendChild(script);
  };
  ensureHtmx();

  if (document.querySelector('.admin-main') && !document.getElementById('admin-detail')) {
    const detail = document.createElement('div');
    detail.id = 'admin-detail';
    detail.className = 'detail-popover';
    document.body.appendChild(detail);
    const detailsButton = [...document.querySelectorAll('.small-button')].find((button) => button.textContent.includes('Szczegóły'));
    if (detailsButton) {
      detailsButton.setAttribute('hx-get', 'partials/admin-detail.html');
      detailsButton.setAttribute('hx-target', '#admin-detail');
      detailsButton.setAttribute('hx-swap', 'innerHTML');
    }
  }

  document.body.addEventListener('htmx:afterSwap', (event) => {
    if (event.detail.target?.id === 'admin-detail') event.detail.target.classList.add('is-visible');
  });
})();
