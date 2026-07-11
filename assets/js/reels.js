(() => {
  const app = document.querySelector('[data-reels-app]');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const sessionId = (() => { const key='vp3_viewer_session_id'; let value=localStorage.getItem(key); if(!value){ value=(crypto.randomUUID?crypto.randomUUID():`${Date.now()}-${Math.random()}`); localStorage.setItem(key,value); } return value; })();
  const profileForm = document.querySelector('[data-viewer-profile-form]');
  if (profileForm) {
    profileForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const status = profileForm.querySelector('[data-viewer-profile-status]');
      status.textContent = 'Saving…';
      const payload = Object.fromEntries(new FormData(profileForm).entries());
      const response = await fetch('api/v1/viewer/profile.php', {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(payload)});
      const data = await response.json().catch(() => ({}));
      status.textContent = data.ok ? 'Profile saved.' : (data.error?.message || 'Profile could not be saved.');
    });
  }
  if (!app) return;
  const viewport = app.querySelector('[data-reels-viewport]');
  const template = document.querySelector('[data-reel-template]');
  let nextCursor = app.dataset.nextCursor || '';
  let loading = false;
  let activeReel = null;
  let activeStarted = 0;

  const api = async (path, payload = null, method = 'POST') => {
    const options = {method,headers:{'Accept':'application/json'}};
    if (payload !== null) { options.headers['Content-Type']='application/json'; options.headers['X-CSRF-Token']=csrf; options.body=JSON.stringify(payload); }
    const response = await fetch(path, options);
    return response.json();
  };

  const playReel = (reel) => {
    if (activeReel && activeReel !== reel) finishView(activeReel, true);
    activeReel = reel; activeStarted = Date.now();
    document.querySelectorAll('[data-reel] video').forEach((video) => { if (video.closest('[data-reel]') !== reel) { video.pause(); video.currentTime = 0; video.closest('.reel-stage')?.classList.remove('is-playing'); } });
    const video = reel.querySelector('video');
    if (video) video.play().then(() => reel.querySelector('.reel-stage')?.classList.add('is-playing')).catch(() => {});
    const nextVideo = reel.nextElementSibling?.querySelector?.('video');
    if (nextVideo && nextVideo.preload !== 'auto') { nextVideo.preload = 'auto'; nextVideo.load(); }
  };

  const finishView = (reel, skipped = false) => {
    if (!reel?.dataset.publicationUuid || !activeStarted) return;
    const video = reel.querySelector('video');
    const elapsed = Math.max(0, Math.round((Date.now() - activeStarted) / 1000));
    const watched = video ? Math.max(elapsed, Math.round(video.currentTime || 0)) : elapsed;
    const duration = video?.duration || 0;
    api('api/v1/viewer/view.php',{publication_uuid:reel.dataset.publicationUuid,watch_seconds:watched,completed:duration>0&&watched>=duration*.9,skipped:skipped&&watched<3}).catch(()=>{});
    activeStarted = 0;
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => { if (entry.isIntersecting && entry.intersectionRatio > .68) playReel(entry.target); });
  }, {root:viewport,threshold:[.68]});

  const bindReel = (reel) => {
    if (reel.dataset.bound) return; reel.dataset.bound='1'; observer.observe(reel);
    const video = reel.querySelector('video'); const stage = reel.querySelector('.reel-stage');
    stage?.addEventListener('click', (event) => { if (event.target.closest('a,button:not(.reel-center-play)')) return; if (!video) return; if (video.paused) video.play(); else video.pause(); stage.classList.toggle('is-playing',!video.paused); });
    video?.addEventListener('timeupdate',()=>{const bar=reel.querySelector('.reel-progress i');if(bar&&video.duration)bar.style.width=`${Math.min(100,(video.currentTime/video.duration)*100)}%`;});
    reel.querySelectorAll('[data-action]').forEach((button) => button.addEventListener('click', async () => {
      const action = button.dataset.action;
      if (action === 'share') { api('api/v1/clips/engage.php',{publication_uuid:reel.dataset.publicationUuid,session_id:sessionId,engagement_type:'share'}).catch(()=>{}); const url=`${location.origin}${location.pathname.replace(/clips\.php$/,'clip.php')}?id=${encodeURIComponent(reel.dataset.publicationUuid)}`; if(navigator.share)await navigator.share({title:reel.querySelector('h2')?.textContent||'VP3 Clip',url});else await navigator.clipboard.writeText(url); button.classList.add('active'); return; }
      if (action === 'report') { const reason=prompt('Report reason: copyright, harassment, adult_content, violence, spam, misleading, or other'); if(!reason)return; const result=await fetch('api/v1/clips/report.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({publication_uuid:reel.dataset.publicationUuid,session_id:sessionId,reason,details:''})}); button.classList.toggle('active',result.ok); return; }
      const payload={type:action,publication_uuid:reel.dataset.publicationUuid,creator_uuid:reel.dataset.creatorUuid,show_uuid:reel.dataset.showUuid};
      const result=await api('api/v1/viewer/action.php',payload); if(result.ok)button.classList.toggle('active',!"result.data.active);
    }));
    reel.querySelector('.reel-destination')?.addEventListener('click',()=>api('api/v1/clips/engage.php',{publication_uuid:reel.dataset.publicationUuid,session_id:sessionId,engagement_type:'open_destination'}).catch(()=>{}));
  };

  document.querySelectorAll('[data-reel]').forEach(bindReel);

  const createReel = (clip) => {
    const node = template.content.firstElementChild.cloneNode(true);
    node.dataset.publicationUuid=clip.publication_uuid||'';node.dataset.creatorUuid=clip.creator_uuid||'';node.dataset.showUuid=clip.show_uuid||'';
    const video=node.querySelector('video');const poster=node.querySelector('.reel-poster');
    if(clip.source_media_url){video.src=clip.source_media_url;}else video.remove();
    if(clip.poster_url){poster.src=clip.poster_url;}else poster.remove();
    node.querySelector('[data-title]').textContent=clip.title||'';node.querySelector('[data-caption]').textContent=clip.caption||'';
    const creator=node.querySelector('[data-creator-link]');creator.textContent=`${clip.creator_name||'VP3 Creator'} ✓`;creator.href=`creator.php?slug=${encodeURIComponent(clip.creator_slug||'')}`;
    const show=node.querySelector('[data-show-link]');show.textContent=`${clip.show_title||'Open show'} →`;show.href=`show.php?slug=${encodeURIComponent(clip.show_slug||'')}`;
    const destination=node.querySelector('[data-destination]');destination.href=clip.destination_url||'#';
    node.querySelector('[data-action="like"]')?.classList.toggle('active',!!clip.liked);node.querySelector('[data-action="save"]')?.classList.toggle('active',!!clip.saved);node.querySelector('[data-action="follow_creator"]')?.classList.toggle('active',!!clip.follows_creator);node.querySelector('[data-action="follow_show"]')?.classList.toggle('active',!!clip.follows_show);
    return node;
  };

  const loadMore = async () => {
    if (loading || !nextCursor) return; loading=true; const loader=app.querySelector('[data-reels-loader]');loader.hidden=false;
    try{const query=new URLSearchParams({feed:app.dataset.feed,cursor:nextCursor,limit:'8'});const result=await api(`api/v1/viewer/feed.php?${query}` ,null,'GET');if(result.ok){result.data.items.forEach((clip)=>{const reel=createReel(clip);viewport.insertBefore(reel,loader);bindReel(reel);});nextCursor=result.data.next_cursor||'';app.dataset.nextCursor=nextCursor;}}finally{loading=false;loader.hidden=true;}
  };
  viewport.addEventListener('scroll',()=>{if(viewport.scrollTop+viewport.clientHeight>viewport.scrollHeight-viewport.clientHeight*1.5)loadMore();});
  document.addEventListener('keydown',(event)=>{if(!['ArrowDown','ArrowUp','j','k',' '].includes(event.key))return;const reels=[...document.querySelectorAll('[data-reel]')];const index=Math.max(0,reels.indexOf(activeReel));if(event.key===' '){event.preventDefault();activeReel?.querySelector('.reel-center-play')?.click();return;}const next=event.key==='ArrowUp'||event.key==='k'?reels[index-1]:reels[index+1];if(next){event.preventDefault();next.scrollIntoView({behavior:'smooth',block:'start'});}});
  window.addEventListener('beforeunload',()=>finishView(activeReel,false));
})();
