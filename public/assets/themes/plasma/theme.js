(() => {
  'use strict';

  const shell = document.querySelector('.app-shell');
  if (!shell) return;

  const menuButtons = document.querySelectorAll('[data-menu-toggle]');
  const storageKey = shell.classList.contains('admin-shell')
    ? 'miniportal.plasma.admin-sidebar'
    : 'miniportal.plasma.public-sidebar';
  let searchReturnTarget = null;

  const readPreference = (key) => {
    try { return window.localStorage.getItem(key); } catch (_error) { return null; }
  };
  const writePreference = (key, value) => {
    try { window.localStorage.setItem(key, value); } catch (_error) { /* Optional enhancement. */ }
  };

  const setExpanded = (expanded, persist = true) => {
    shell.classList.toggle('menu-expanded', expanded);
    shell.classList.toggle('menu-collapsed', !expanded);
    menuButtons.forEach((button) => {
      button.setAttribute('aria-expanded', String(expanded));
      const label = button.querySelector('[data-toggle-label]');
      if (label) label.textContent = expanded ? 'Zwiń menu' : 'Rozwiń menu';
    });
    if (persist) writePreference(storageKey, expanded ? 'expanded' : 'collapsed');
  };

  const savedState = readPreference(storageKey);
  setExpanded((savedState ?? shell.dataset.menuState ?? 'expanded') === 'expanded', false);
  menuButtons.forEach((button) => button.addEventListener('click', () => {
    setExpanded(!shell.classList.contains('menu-expanded'));
  }));
  document.querySelector('[data-menu-backdrop]')?.addEventListener('click', () => setExpanded(false));

  const overlay = document.querySelector('.search-overlay');
  const searchInput = overlay?.querySelector('.search-input');
  const searchLinks = Array.from(overlay?.querySelectorAll('.search-suggestions a') ?? []);

  const closeSearch = () => {
    if (!overlay) return;
    overlay.classList.remove('is-open');
    overlay.setAttribute('hidden', '');
    document.body.classList.remove('modal-open');
    if (searchReturnTarget instanceof HTMLElement) searchReturnTarget.focus();
  };
  const openSearch = (trigger) => {
    if (!overlay) return;
    searchReturnTarget = trigger instanceof HTMLElement ? trigger : document.activeElement;
    overlay.removeAttribute('hidden');
    overlay.classList.add('is-open');
    document.body.classList.add('modal-open');
    window.setTimeout(() => searchInput?.focus(), 30);
  };

  document.querySelectorAll('[data-search-trigger]').forEach((trigger) => {
    trigger.addEventListener('click', () => openSearch(trigger));
  });
  overlay?.querySelector('[data-search-close]')?.addEventListener('click', closeSearch);
  overlay?.addEventListener('mousedown', (event) => { if (event.target === overlay) closeSearch(); });
  searchInput?.addEventListener('input', () => {
    const query = searchInput.value.trim().toLocaleLowerCase('pl');
    searchLinks.forEach((link) => { link.hidden = query !== '' && !link.textContent.toLocaleLowerCase('pl').includes(query); });
  });

  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      openSearch(document.activeElement);
    }
    if (event.key === 'Escape') closeSearch();
    if (event.key === 'Tab' && overlay?.classList.contains('is-open')) {
      const focusable = Array.from(overlay.querySelectorAll('button,input,a:not([hidden])'));
      if (focusable.length === 0) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });

  const setCalmMode = (enabled) => {
    document.documentElement.classList.toggle('calm-mode', enabled);
    document.querySelectorAll('[data-calm-toggle]').forEach((button) => {
      button.setAttribute('aria-pressed', String(enabled));
      button.setAttribute('aria-label', enabled ? 'Włącz pełne efekty świetlne' : 'Ogranicz efekty świetlne');
    });
    writePreference('miniportal.plasma.calm-mode', enabled ? 'true' : 'false');
  };
  setCalmMode(readPreference('miniportal.plasma.calm-mode') === 'true');
  document.querySelectorAll('[data-calm-toggle]').forEach((button) => {
    button.addEventListener('click', () => setCalmMode(!document.documentElement.classList.contains('calm-mode')));
  });

  document.querySelectorAll('[data-confirmation-required="true"]').forEach((action) => {
    action.addEventListener('click', (event) => {
      if (!window.confirm('Czy na pewno chcesz wykonać tę operację?')) event.preventDefault();
    });
  });
})();
