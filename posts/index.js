function confirmDelete() {
  return confirm('Are you sure you want to delete this post?');
}

function confirmLogout() {
  return confirm('Are you sure you want to logout?');
}

function showFeedToast(message) {
  let toast = document.querySelector('.feed-toast');

  if (!toast) {
    toast = document.createElement('div');
    toast.className = 'feed-toast';
    document.body.appendChild(toast);
  }

  toast.textContent = message;
  toast.classList.add('show');
  clearTimeout(window.__feedToastTimer);
  window.__feedToastTimer = setTimeout(
    () => toast.classList.remove('show'),
    2200
  );
}

function likeBlog(blogId, csrfToken, button) {
  const body = new URLSearchParams({
    blog_id: String(blogId),
    csrf_token: csrfToken,
  });

  if (button) button.disabled = true;

  fetch('../comments/likes.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: body.toString(),
  })
    .then(async (response) => {
      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || 'Unable to update the reaction.');
      }

      const count = document.getElementById(`like-count-${blogId}`);

      if (count) count.textContent = data.likes;

      if (button) {
        const liked = Boolean(data.liked);
        button.classList.toggle('liked', liked);
        button.setAttribute('aria-label', liked ? 'Unlike post' : 'Like post');
        button.setAttribute('aria-pressed', liked ? 'true' : 'false');

        const icon = button.querySelector('i');

        if (icon) {
          icon.classList.toggle('fa-beat', liked);

          if (liked) {
            setTimeout(() => icon.classList.remove('fa-beat'), 500);
          }
        }
      }

      showFeedToast(data.liked ? 'Story liked' : 'Like removed');
    })
    .catch((error) => showFeedToast(error.message))
    .finally(() => {
      if (button) button.disabled = false;
    });
}
