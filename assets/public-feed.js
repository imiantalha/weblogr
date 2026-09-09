document.addEventListener('DOMContentLoaded', () => {
  const grid = document.querySelector('[data-public-feed]');
  const sentinel = document.querySelector('[data-feed-sentinel]');

  if (!grid || !sentinel) return;

  document.querySelector('[data-server-pagination]')?.remove();

  let nextPage = Number(grid.dataset.nextPage || 0);
  let loading = false;
  let retry = false;

  const status = document.createElement('p');
  status.className = 'feed-status';
  status.setAttribute('aria-live', 'polite');
  sentinel.appendChild(status);

  const params = new URLSearchParams({
    per_page: grid.dataset.perPage || '12',
  });

  const search = grid.dataset.search || '';
  const category = grid.dataset.category || '';

  if (search) params.set('search', search);
  if (category) params.set('category', category);

  const load = async () => {
    if (loading || !nextPage) return;

    loading = true;
    retry = false;
    status.textContent = 'Loading more stories…';

    try {
      params.set('page', String(nextPage));

      const response = await fetch(
        `api/public-posts.php?${params.toString()}`,
        {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
          cache: 'no-store',
        }
      );

      if (!response.ok) throw new Error('Request failed');

      const data = await response.json();

      if (!Array.isArray(data.items)) {
        throw new Error('Invalid response');
      }

      data.items.forEach((html) => {
        grid.insertAdjacentHTML('beforeend', html);
      });

      nextPage = Number(data.next_page || 0);
      grid.dataset.nextPage = String(nextPage);
      status.textContent = nextPage ? 'Scroll for more stories.' : '';

      if (!nextPage) sentinel.remove();
    } catch (error) {
      retry = true;
      status.textContent = 'Could not load more stories.';

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'button button-secondary';
      button.textContent = 'Try again';
      button.addEventListener(
        'click',
        () => {
          button.remove();
          load();
        },
        { once: true }
      );

      sentinel.appendChild(button);
    } finally {
      loading = false;
    }
  };

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) load();
      },
      { rootMargin: '600px 0px' }
    );

    observer.observe(sentinel);
  } else {
    const button = document.createElement('button');
    button.className = 'button button-secondary';
    button.type = 'button';
    button.textContent = 'Load more stories';
    button.addEventListener('click', load);
    sentinel.replaceChildren(button);
  }
});
