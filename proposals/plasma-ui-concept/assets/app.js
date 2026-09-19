(() => {
  'use strict';

  const shell = document.querySelector('.app-shell');
  const menuButtons = document.querySelectorAll('[data-menu-toggle]');
  const storageKey = shell?.classList.contains('admin-shell')
    ? 'miniportal.prototype.admin-sidebar'
    : 'miniportal.prototype.public-sidebar';
  let searchReturnTarget = null;

  const readPreference = (key) => {
    try {
      return window.localStorage.getItem(key);
    } catch (_error) {
      return null;
    }
  };

  const writePreference = (key, value) => {
    try {
      window.localStorage.setItem(key, value);
    } catch (_error) {
      // The prototype remains usable when storage is blocked.
    }
  };

  const setExpanded = (expanded, persist = true) => {
    if (!shell) return;

    shell.classList.toggle('menu-expanded', expanded);
    shell.classList.toggle('menu-collapsed', !expanded);
    document.documentElement.dataset.sidebar = expanded ? 'expanded' : 'collapsed';

    menuButtons.forEach((button) => {
      button.setAttribute('aria-expanded', String(expanded));
      const label = button.querySelector('[data-toggle-label]');
      if (label) label.textContent = expanded ? 'Zwiń menu' : 'Rozwiń menu';
    });

    if (persist && storageKey) writePreference(storageKey, expanded ? 'expanded' : 'collapsed');
  };

  if (shell) {
    const savedState = storageKey ? readPreference(storageKey) : null;
    const initialState = savedState ?? shell.dataset.menuState ?? 'collapsed';
    setExpanded(initialState === 'expanded', false);
  }

  menuButtons.forEach((button) => button.addEventListener('click', () => {
    setExpanded(!shell?.classList.contains('menu-expanded'));
  }));
  document.querySelector('[data-menu-backdrop]')?.addEventListener('click', () => setExpanded(false));

  const overlay = document.querySelector('.search-overlay');
  const searchTriggers = document.querySelectorAll('[data-search-trigger]');
  const searchInput = overlay?.querySelector('.search-input');

  const openSearch = (trigger) => {
    if (!overlay) return;
    searchReturnTarget = trigger instanceof HTMLElement ? trigger : document.activeElement;
    overlay.removeAttribute('hidden');
    overlay.classList.add('is-open');
    document.body.classList.add('modal-open');
    window.setTimeout(() => searchInput?.focus(), 30);
  };

  const closeSearch = () => {
    if (!overlay) return;
    overlay.classList.remove('is-open');
    overlay.setAttribute('hidden', '');
    document.body.classList.remove('modal-open');
    if (searchReturnTarget instanceof HTMLElement) searchReturnTarget.focus();
  };

  searchTriggers.forEach((trigger) => trigger.addEventListener('click', () => openSearch(trigger)));
  overlay?.querySelector('[data-search-close]')?.addEventListener('click', closeSearch);
  overlay?.addEventListener('mousedown', (event) => {
    if (event.target === overlay) closeSearch();
  });

  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      openSearch(document.activeElement);
    }
    if (event.key === 'Escape') {
      closeSearch();
      document.getElementById('admin-detail')?.classList.remove('is-visible');
    }
  });

  const setCalmMode = (enabled) => {
    document.documentElement.classList.toggle('calm-mode', enabled);
    document.querySelectorAll('[data-calm-toggle]').forEach((button) => {
      button.setAttribute('aria-pressed', String(enabled));
      button.setAttribute('title', enabled ? 'Włącz pełne efekty świetlne' : 'Ogranicz efekty świetlne');
    });
    writePreference('miniportal.prototype.calm-mode', enabled ? 'true' : 'false');
  };

  setCalmMode(readPreference('miniportal.prototype.calm-mode') === 'true');
  document.querySelectorAll('[data-calm-toggle]').forEach((button) => {
    button.addEventListener('click', () => setCalmMode(!document.documentElement.classList.contains('calm-mode')));
  });

  const clock = document.querySelector('[data-clock]');
  const clockDate = document.querySelector('[data-clock-date]');
  const updateClock = () => {
    const now = new Date();
    if (clock) clock.textContent = new Intl.DateTimeFormat('pl-PL', { hour: '2-digit', minute: '2-digit' }).format(now);
    if (clockDate) {
      clockDate.textContent = new Intl.DateTimeFormat('pl-PL', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
      }).format(now);
    }
  };
  updateClock();
  if (clock) window.setInterval(updateClock, 30_000);

  document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;

    if (event.target.closest('[data-detail-close]')) {
      document.getElementById('admin-detail')?.classList.remove('is-visible');
    }

    const demoNav = event.target.closest('.nav-item[href="#"]');
    if (demoNav) {
      event.preventDefault();
      demoNav.closest('nav')?.querySelectorAll('.nav-item').forEach((item) => {
        item.classList.toggle('active', item === demoNav);
        if (item === demoNav) item.setAttribute('aria-current', 'page');
        else item.removeAttribute('aria-current');
      });
      if (window.matchMedia('(max-width: 760px)').matches) setExpanded(false);
    }
  });

  document.body.addEventListener('htmx:beforeRequest', (event) => {
    event.detail.elt?.setAttribute('aria-busy', 'true');
  });
  document.body.addEventListener('htmx:afterRequest', (event) => {
    event.detail.elt?.removeAttribute('aria-busy');
  });
  document.body.addEventListener('htmx:afterSwap', (event) => {
    if (event.detail.target?.id === 'admin-detail') {
      event.detail.target.classList.add('is-visible');
      event.detail.target.querySelector('button')?.focus();
    }
  });
  document.body.addEventListener('htmx:responseError', () => {
    const status = document.querySelector('[data-demo-status]');
    if (status) status.textContent = 'Nie udało się pobrać fragmentu demonstracyjnego.';
  });
})();
