(() => {
  const button = document.querySelector('[data-menu-toggle]');
  const menu = document.querySelector('[data-menu]');
  const header = document.querySelector('[data-site-header]');

  const closeMenu = () => {
    if (!button || !menu) return;
    button.setAttribute('aria-expanded', 'false');
    menu.classList.remove('is-open');
    document.body.classList.remove('menu-open');
  };

  button?.addEventListener('click', () => {
    const isOpen = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', String(!isOpen));
    menu?.classList.toggle('is-open', !isOpen);
    document.body.classList.toggle('menu-open', !isOpen);
  });

  menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
  window.addEventListener('resize', () => {
    if (window.innerWidth > 920) closeMenu();
  });
  window.addEventListener('scroll', () => header?.classList.toggle('is-scrolled', window.scrollY > 20), { passive: true });
})();
