document.addEventListener('DOMContentLoaded', () => {
  const root = document.createElement('div');
  root.className = 'toast-root';
  root.setAttribute('aria-live', 'polite');
  root.setAttribute('aria-atomic', 'true');
  document.body.appendChild(root);

  const toast = (message, icon = 'fa-check-circle') => {
    const item = document.createElement('div');
    item.className = 'ui-toast';

    const iconEl = document.createElement('i');
    iconEl.className = `fas ${icon}`;
    iconEl.setAttribute('aria-hidden', 'true');

    const textEl = document.createElement('span');
    textEl.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss notification');

    const closeIcon = document.createElement('i');
    closeIcon.className = 'fas fa-times';
    closeIcon.setAttribute('aria-hidden', 'true');
    close.appendChild(closeIcon);
    close.addEventListener('click', () => item.remove());

    item.append(iconEl, textEl, close);
    root.appendChild(item);

    requestAnimationFrame(() => item.classList.add('show'));

    window.setTimeout(() => {
      item.classList.remove('show');
      window.setTimeout(() => item.remove(), 250);
    }, 3200);
  };

  document.querySelectorAll('[data-toast]').forEach((el) => {
    el.addEventListener('click', () => {
      toast(
        el.dataset.toast || 'Done',
        el.dataset.icon || 'fa-check-circle'
      );
    });
  });

  document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(
          button.dataset.copy || window.location.href
        );
        toast('Link copied to clipboard.', 'fa-link');
      } catch (_) {
        toast('Could not copy the link.', 'fa-exclamation-circle');
      }
    });
  });

  document.querySelectorAll('.story-card, .feature-card, .featured-card').forEach(
    (card) => {
      card.addEventListener('mouseenter', () => {
        card.classList.add('is-hovered');
      });

      card.addEventListener('mouseleave', () => {
        card.classList.remove('is-hovered');
      });
    }
  );

  document.querySelectorAll('[data-reveal]').forEach((element) => {
    if (!('IntersectionObserver' in window)) {
      element.classList.add('revealed');
      return;
    }

    const observer = new IntersectionObserver(
      (entries, obs) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('revealed');
            obs.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12 }
    );

    observer.observe(element);
  });

  document.querySelectorAll('[data-ui-toggle]').forEach((button) => {
    const target = document.querySelector(button.dataset.uiToggle);

    if (!target) return;

    button.addEventListener('click', () => {
      const expanded = target.classList.toggle('is-open');
      button.setAttribute('aria-expanded', String(expanded));
    });
  });

  const confirmForms = document.querySelectorAll('form[data-confirm]');

  if (confirmForms.length) {
    let pendingForm = null;

    const modal = document.createElement('div');
    modal.className = 'product-confirm-modal';
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = `
      <div
        class="product-confirm-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="product-confirm-title"
      >
        <div class="product-confirm-icon">
          <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        </div>
        <h2 id="product-confirm-title">Are you sure?</h2>
        <p class="product-confirm-message"></p>
        <div class="product-confirm-actions">
          <button type="button" class="secondary-button product-confirm-cancel">Cancel</button>
          <button type="button" class="danger-button product-confirm-submit">Continue</button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    const message = modal.querySelector('.product-confirm-message');
    const cancel = modal.querySelector('.product-confirm-cancel');
    const submit = modal.querySelector('.product-confirm-submit');

    const closeConfirm = () => {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      pendingForm = null;
    };

    confirmForms.forEach((form) => {
      form.addEventListener('submit', (event) => {
        if (pendingForm === form) return;

        event.preventDefault();
        pendingForm = form;
        message.textContent =
          form.dataset.confirm || 'This action cannot be undone.';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        cancel.focus();
      });
    });

    cancel.addEventListener('click', closeConfirm);

    submit.addEventListener('click', () => {
      if (!pendingForm) return;

      const form = pendingForm;
      pendingForm = null;
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      form.submit();
    });

    modal.addEventListener('click', (event) => {
      if (event.target === modal) closeConfirm();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && modal.classList.contains('is-open')) {
        closeConfirm();
      }
    });
  }

  window.WeblogrUI = { toast };
});
