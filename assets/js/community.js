(() => {
  const app = document.querySelector('[data-reels-app]');
  const drawer = document.querySelector('[data-community-drawer]');
  if (!app || !drawer) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const list = drawer.querySelector('[data-comment-list]');
  const form = drawer.querySelector('[data-comment-form]');
  const bodyInput = drawer.querySelector('[name="body"]');
  const parentInput = drawer.querySelector('[name="parent_uuid"]');
  const title = drawer.querySelector('[data-comment-title]');
  let publicationUuid = '';

  const api = async (path, payload = null, method = 'POST') => {
    const options = { method, headers: { Accept: 'application/json' } };
    if (payload !== null) {
      options.headers['Content-Type'] = 'application/json';
      options.headers['X-CSRF-Token'] = csrf;
      options.body = JSON.stringify(payload);
    }
    const response = await fetch(path, options);
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false) throw new Error(data.error?.message || 'Request failed.');
    return data.data ?? data;
  };

  const close = () => {
    drawer.hidden = true;
    drawer.classList.remove('open');
    document.body.classList.remove('community-open');
    publicationUuid = '';
    if (parentInput) parentInput.value = '';
  };

  const text = (tag, value, className = '') => {
    const node = document.createElement(tag);
    node.textContent = value || '';
    if (className) node.className = className;
    return node;
  };

  const render = (items) => {
    list.replaceChildren();
    if (!items.length) {
      list.append(text('p', 'Start the conversation.', 'comments-empty'));
      return;
    }
    const map = new Map();
    items.forEach((item) => {
      const article = document.createElement('article');
      article.className = `comment ${item.parent_uuid ? 'reply' : ''}`;
      article.dataset.commentUuid = item.comment_uuid;
      article.dataset.viewerUuid = item.viewer_uuid;

      const head = document.createElement('div');
      head.className = 'comment-head';
      const identity = text('strong', `${item.display_name} · @${item.handle}`);
      const time = text('small', item.created_at);
      head.append(identity, time);

      const body = text('p', item.body, 'comment-body');
      const actions = document.createElement('div');
      actions.className = 'comment-actions';

      const like = text('button', `♡ ${item.like_count || 0}`);
      like.type = 'button';
      like.dataset.commentAction = 'like';
      if (item.liked) like.classList.add('active');

      const reply = text('button', 'Reply');
      reply.type = 'button';
      reply.dataset.commentAction = 'reply';

      const report = text('button', 'Report');
      report.type = 'button';
      report.dataset.commentAction = 'report';

      const block = text('button', 'Block');
      block.type = 'button';
      block.dataset.commentAction = 'block';

      actions.append(like, reply);
      if (item.own) {
        const remove = text('button', 'Delete');
        remove.type = 'button';
        remove.dataset.commentAction = 'delete';
        actions.append(remove);
      } else {
        actions.append(report, block);
      }
      article.append(head, body, actions);
      map.set(item.comment_uuid, article);
      if (item.parent_uuid && map.has(item.parent_uuid)) {
        map.get(item.parent_uuid).append(article);
      } else {
        list.append(article);
      }
    });
  };

  const load = async () => {
    if (!publicationUuid) return;
    list.replaceChildren(text('p', 'Loading comments…', 'comments-loading'));
    try {
      const query = new URLSearchParams({ publication_uuid: publicationUuid, limit: '100' });
      const data = await api(`api/v1/viewer/comments.php?${query}`, null, 'GET');
      render(data.items || []);
    } catch (error) {
      list.replaceChildren(text('p', error.message, 'comments-error'));
    }
  };

  const open = async (reel) => {
    publicationUuid = reel.dataset.publicationUuid || '';
    title.textContent = reel.querySelector('h2')?.textContent || 'Comments';
    drawer.hidden = false;
    requestAnimationFrame(() => drawer.classList.add('open'));
    document.body.classList.add('community-open');
    await load();
  };

  document.addEventListener('click', async (event) => {
    const muteButton = event.target.closest('[data-mute-button]');
    if (muteButton) {
      const reel = muteButton.closest('[data-reel]');
      if (!reel) return;
      if (app.dataset.authenticated !== '1') {
        location.href = 'viewer-login.php?return=clips.php';
        return;
      }
      const targetType = reel.dataset.creatorUuid ? 'creator' : 'show';
      const targetUuid = targetType === 'creator' ? reel.dataset.creatorUuid : reel.dataset.showUuid;
      if (!targetUuid || !confirm(`Mute this ${targetType} from your VP3 feeds?`)) return;
      try {
        await api('api/v1/viewer/comment-action.php', { action: 'mute', target_type: targetType, target_uuid: targetUuid });
        const next = reel.nextElementSibling?.matches?.('[data-reel]') ? reel.nextElementSibling : reel.previousElementSibling;
        reel.remove();
        next?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (error) {
        alert(error.message);
      }
      return;
    }
    const button = event.target.closest('[data-comments-button]');
    if (button) {
      const reel = button.closest('[data-reel]');
      if (reel) open(reel);
      return;
    }
    if (event.target.closest('[data-community-close]') || event.target.matches('[data-community-drawer]')) {
      close();
    }
  });

  list.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-comment-action]');
    const comment = button?.closest('[data-comment-uuid]');
    if (!button || !comment) return;
    const action = button.dataset.commentAction;
    const commentUuid = comment.dataset.commentUuid;

    try {
      if (action === 'reply') {
        parentInput.value = commentUuid;
        bodyInput.focus();
        bodyInput.placeholder = 'Write a reply…';
        return;
      }
      if (action === 'report') {
        const reason = prompt('Reason: harassment, spam, hate, sexual_content, violence, misinformation, privacy, or other', 'other');
        if (!reason) return;
        await api('api/v1/viewer/comment-action.php', { action, comment_uuid: commentUuid, reason, details: '' });
      } else if (action === 'block') {
        if (!confirm('Block this viewer? Their comments will be hidden from you.')) return;
        await api('api/v1/viewer/comment-action.php', { action, viewer_uuid: comment.dataset.viewerUuid });
      } else {
        const result = await api('api/v1/viewer/comment-action.php', { action, comment_uuid: commentUuid });
        if (action === 'like') button.classList.toggle('active', !!result.active);
      }
      await load();
    } catch (error) {
      alert(error.message);
    }
  });

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!publicationUuid || !bodyInput.value.trim()) return;
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
      await api('api/v1/viewer/comments.php', {
        publication_uuid: publicationUuid,
        body: bodyInput.value,
        parent_uuid: parentInput.value || null
      });
      bodyInput.value = '';
      parentInput.value = '';
      bodyInput.placeholder = 'Add a comment…';
      await load();
    } catch (error) {
      alert(error.message);
    } finally {
      submit.disabled = false;
    }
  });
})();
