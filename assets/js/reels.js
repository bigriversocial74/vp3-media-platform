(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const app = document.querySelector('[data-reels-app]');
  const profileForm = document.querySelector('[data-viewer-profile-form]');

  const api = async (url, payload = null, method = 'POST') => {
    const options = { method, headers: { Accept: 'application/json' } };
    if (payload !== null) {
      options.headers['Content-Type'] = 'application/json';
      options.headers['X-CSRF-Token'] = csrf;
      options.body = JSON.stringify(payload);
    }
    const response = await fetch(url, options);
    return response.json();
  };

  if (profileForm) {
    profileForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const status = profileForm.querySelector('[data-viewer-profile-status]');
      status.textContent = 'Saving…';
      try {
        const result = await api('api/v1/viewer/profile.php', Object.fromEntries(new FormData(profileForm)));
        status.textContent = result.ok ? 'Profile saved.' : (result.error?.message || 'Profile could not be saved.');
      } catch {
        status.textContent = 'Profile could not be saved.';
      }
    });
  }

  if (!app) return;

  const viewport = app.querySelector('[data-reels-viewport]');
  const template = document.querySelector('[data-reel-template]');
  const loader = app.querySelector('[data-reels-loader]');
  let nextCursor = app.dataset.nextCursor || '';
  let loading = false;
  let activeReel = null;
  let activeStartedAt = 0;

  const sessionId = (() => {
    const key = 'vp3_reels_session';
    let value = localStorage.getItem(key);
    if (!value) {
      value = crypto.randomUUID?.() || `${Date.now()}-${Math.random()}`;
      localStorage.setItem(key, value);
    }
    return value;
  })();

  const finishView = (reel, skipped = false) => {
    if (!reel?.dataset.publicationUuid || !activeStartedAt) return;
    const video = reel.querySelector('video');
    const elapsed = Math.max(0, Math.round((Date.now() - activeStartedAt) / 1000));
    const watched = video ? Math.max(elapsed, Math.round(video.currentTime || 0)) : elapsed;
    const duration = video?.duration || 0;
    api('api/v1/viewer/view.php', {
      publication_uuid: reel.dataset.publicationUuid,
      watch_seconds: watched,
      completed: duration > 0 && watched >= duration * 0.9,
      skipped: skipped && watched < 3,
    }).catch(() => {});
    activeStartedAt = 0;
  };

  const activate = (reel) => {
    if (activeReel !== reel) finishView(activeReel, true);
    activeReel = reel;
    activeStartedAt = Date.now();

    document.querySelectorAll('[data-reel] video').forEach((video) => {
      if (video.closest('[data-reel]') !== reel) {
        video.pause();
        video.currentTime = 0;
        video.closest('.reel-stage')?.classList.remove('is-playing');
      }
    });

    const video = reel.querySelector('video');
    video?.play().then(() => reel.querySelector('.reel-stage')?.classList.add('is-playing')).catch(() => {});
    const nextVideo = reel.nextElementSibling?.querySelector?.('video');
    if (nextVideo && nextVideo.preload !== 'auto') {
      nextVideo.preload = 'auto';
      nextVideo.load();
    }
  };

  const visibilityObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting && entry.intersectionRatio > 0.68) activate(entry.target);
    });
  }, { root: viewport, threshold: 0.68 });

  const publicEngagement = (reel, engagementType) => {
    fetch('api/v1/clips/engage.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        publication_uuid: reel.dataset.publicationUuid,
        session_id: sessionId,
        engagement_type: engagementType,
      }),
    }).catch(() => {});
  };

  const handleAction = async (button, reel) => {
    const type = button.dataset.action;
    if (type === 'share') {
      publicEngagement(reel, 'share');
      const url = `${location.origin}${location.pathname.replace(/clips\.php$/, 'clip.php')}?id=${encodeURIComponent(reel.dataset.publicationUuid)}`;
      if (navigator.share) await navigator.share({ title: reel.querySelector('h2')?.textContent || 'VP3 Clip', url });
      else await navigator.clipboard.writeText(url);
      button.classList.add('active');
      return;
    }
    if (type === 'report') {
      const reason = prompt('Report reason: copyright, harassment, adult_content, violence, spam, misleading, or other');
      if (!reason) return;
      const response = await fetch('api/v1/clips/report.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ publication_uuid: reel.dataset.publicationUuid, session_id: sessionId, reason, details: '' }),
      });
      button.classList.toggle('active', response.ok);
      return;
    }
    const result = await api('api/v1/viewer/action.php', {
      type,
      publication_uuid: reel.dataset.publicationUuid,
      creator_uuid: reel.dataset.creatorUuid,
      show_uuid: reel.dataset.showUuid,
    });
    if (result.ok) button.classList.toggle('active', Boolean(result.data.active));
  };

  const bindReel = (reel) => {
    if (reel.dataset.bound) return;
    reel.dataset.bound = '1';
    visibilityObserver.observe(reel);

    const video = reel.querySelector('video');
    const stage = reel.querySelector('.reel-stage');
    stage?.addEventListener('click', (event) => {
      if (event.target.closest('a,button:not(.reel-center-play)') || !video) return;
      if (video.paused) video.play(); else video.pause();
      stage.classList.toggle('is-playing', !video.paused);
    });
    video?.addEventListener('timeupdate', () => {
      const progress = reel.querySelector('.reel-progress i');
      if (progress && video.duration) progress.style.width = `${Math.min(100, video.currentTime / video.duration * 100)}%`;
    });
    reel.querySelectorAll('[data-action]').forEach((button) => {
      button.addEventListener('click', () => handleAction(button, reel).catch(() => {}));
    });
    reel.querySelector('.reel-destination')?.addEventListener('click', () => publicEngagement(reel, 'open_destination'));
  };

  const createReel = (clip) => {
    const reel = template.content.firstElementChild.cloneNode(true);
    reel.dataset.publicationUuid = clip.publication_uuid || '';
    reel.dataset.creatorUuid = clip.creator_uuid || '';
    reel.dataset.showUuid = clip.show_uuid || '';

    const video = reel.querySelector('video');
    const poster = reel.querySelector('.reel-poster');
    if (clip.source_media_url) video.src = clip.source_media_url; else video.remove();
    if (clip.poster_url) poster.src = clip.poster_url; else poster.remove();
    reel.querySelector('[data-title]').textContent = clip.title || '';
    reel.querySelector('[data-caption]').textContent = clip.caption || '';

    const creator = reel.querySelector('[data-creator-link]');
    creator.textContent = `${clip.creator_name || 'VP3 Creator'} ✓`;
    creator.href = `creator.php?slug=${encodeURIComponent(clip.creator_slug || '')}`;
    const show = reel.querySelector('[data-show-link]');
    show.textContent = `${clip.show_title || 'Open show'} →`;
    show.href = `show.php?slug=${encodeURIComponent(clip.show_slug || '')}`;
    reel.querySelector('[data-destination]').href = clip.destination_url || '#';

    const states = {
      like: clip.liked,
      save: clip.saved,
      follow_creator: clip.follows_creator,
      follow_show: clip.follows_show,
    };
    Object.entries(states).forEach(([action, active]) => {
      reel.querySelector(`[data-action="${action}"]`)?.classList.toggle('active', Boolean(active));
    });
    return reel;
  };

  const loadMore = async () => {
    if (loading || !nextCursor) return;
    loading = true;
    loader.hidden = false;
    try {
      const query = new URLSearchParams({ feed: app.dataset.feed, cursor: nextCursor, limit: '8' });
      const result = await api(`api/v1/viewer/feed.php?${query}`, null, 'GET');
      if (result.ok) {
        result.data.items.forEach((clip) => {
          const reel = createReel(clip);
          viewport.insertBefore(reel, loader);
          bindReel(reel);
        });
        nextCursor = result.data.next_cursor || '';
        app.dataset.nextCursor = nextCursor;
      }
    } finally {
      loading = false;
      loader.hidden = true;
    }
  };

  document.querySelectorAll('[data-reel]').forEach(bindReel);
  viewport.addEventListener('scroll', () => {
    const threshold = viewport.scrollHeight - viewport.clientHeight * 1.5;
    if (viewport.scrollTop + viewport.clientHeight > threshold) loadMore();
  });
  document.addEventListener('keydown', (event) => {
    if (!['ArrowDown', 'ArrowUp', 'j', 'k', ' '].includes(event.key) || !activeReel) return;
    if (event.key === ' ') {
      event.preventDefault();
      activeReel.querySelector('.reel-center-play')?.click();
      return;
    }
    const reels = [...document.querySelectorAll('[data-reel]')];
    const index = Math.max(0, reels.indexOf(activeReel));
    const next = event.key === 'ArrowUp' || event.key === 'k' ? reels[index - 1] : reels[index + 1];
    if (next) {
      event.preventDefault();
      next.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
  window.addEventListener('beforeunload', () => finishView(activeReel, false));
})();
