document.addEventListener('DOMContentLoaded', () => {

  // ============================================================
  // ABAS
  // ============================================================
  const tabButtons = document.querySelectorAll('.tab-btn');
  const tabPanels  = document.querySelectorAll('.tab-panel');

  function activateTab(name) {
    tabButtons.forEach(b => b.classList.toggle('active', b.dataset.tab === name));
    tabPanels.forEach(p => p.classList.toggle('active', p.dataset.panel === name));
    try { localStorage.setItem('adminTab', name); } catch (e) {}
  }

  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => activateTab(btn.dataset.tab));
  });

  let savedTab = null;
  try { savedTab = localStorage.getItem('adminTab'); } catch (e) {}
  if (savedTab && document.querySelector(`.tab-btn[data-tab="${savedTab}"]`)) {
    activateTab(savedTab);
  }

  // ============================================================
  // PICKER DE ÍCONES
  // ============================================================
  const ICONS = [
    'instagram','whatsapp','linkedin','github','email','twitter','youtube','telegram',
    'tiktok','discord','twitch','threads','bluesky','spotify','pix','apple',
    'facebook','pinterest','snapchat','reddit','mastodon','signal','vimeo','dribbble',
    'behance','medium','substack','patreon','kofi','calendly','link','globe','map',
    'phone','message','cart','store','book','camera','music','video','file','download',
    'heart','star','bolt','gift','ticket','calendar','clock','user','users','briefcase',
    'search','settings','check','arrow-up-right'
  ];

  function updateIconPreview(inputEl) {
    const preview = inputEl.closest('.icon-field')?.querySelector('.icon-preview');
    if (!preview) return;

    const v = inputEl.value.trim();
    preview.innerHTML = '';

    if (!v) { preview.textContent = '+'; preview.classList.add('is-empty'); return; }
    preview.classList.remove('is-empty');

    if (/^https?:\/\//i.test(v)) {
      const img = document.createElement('img');
      img.src = v;
      img.alt = '';
      img.onerror = () => { preview.textContent = '⚠'; };
      preview.appendChild(img);
    } else {
      const slug = v.toLowerCase().replace(/[^a-z0-9_-]/g, '');
      const img = document.createElement('img');
      img.src = `../assets/icons/${slug}.svg`;
      img.alt = v;
      img.onerror = () => { preview.textContent = '?'; };
      preview.appendChild(img);
    }
  }

  function buildPicker(inputEl) {
    const wrap = document.createElement('div');
    wrap.className = 'icon-picker-pop';

    ICONS.forEach(name => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'icon-pick';
      b.dataset.value = name;
      b.title = name;

      const img = document.createElement('img');
      img.src = `../assets/icons/${name}.svg`;
      img.alt = name;
      img.onerror = () => { img.style.display = 'none'; };
      b.appendChild(img);

      b.setAttribute('aria-label', name);

      b.addEventListener('click', () => {
        inputEl.value = name;
        updateIconPreview(inputEl);
        wrap.classList.remove('open');
      });

      wrap.appendChild(b);
    });

    const custom = document.createElement('input');
    custom.type = 'text';
    custom.placeholder = 'ou cole uma URL de imagem…';
    custom.className = 'icon-pick-custom';
    custom.addEventListener('change', () => {
      if (custom.value.trim() !== '') {
        inputEl.value = custom.value.trim();
        updateIconPreview(inputEl);
        wrap.classList.remove('open');
      }
    });
    wrap.appendChild(custom);

    return wrap;
  }

  document.querySelectorAll('.icon-field').forEach(field => {
    const input   = field.querySelector('input[name="icon"]');
    const trigger = field.querySelector('.icon-trigger');
    if (!input || !trigger) return;

    const pop = buildPicker(input);
    field.appendChild(pop);

    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      document.querySelectorAll('.icon-picker-pop.open').forEach(p => {
        if (p !== pop) p.classList.remove('open');
      });
      pop.classList.toggle('open');
    });

    updateIconPreview(input);
  });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.icon-field')) {
      document.querySelectorAll('.icon-picker-pop.open').forEach(p => p.classList.remove('open'));
    }
  });

  // ============================================================
  // PREVIEW QR PIX (Copia e Cola tem prioridade; senão usa chave digitada)
  // ============================================================
  const pixForm = document.getElementById('pix-preview-form');
  if (pixForm) {
    const imgEl    = document.getElementById('pix-qr-preview');
    const codeEl   = document.getElementById('pix-payload-out');
    const statusEl = document.getElementById('pix-status');

    let debounceTimer;
    let lastPayload = null;

    function setStatus(kind, text) {
      if (!statusEl) return;
      if (!text) { statusEl.hidden = true; return; }
      statusEl.hidden = false;
      statusEl.className = 'pix-status ' + kind;
      statusEl.textContent = text;
    }
    function clearPreview() {
      if (imgEl)  { imgEl.removeAttribute('src'); imgEl.style.display = 'none'; }
      if (codeEl) codeEl.value = '';
      lastPayload = null;
    }

    async function updatePixPreview() {
      const raw  = pixForm.querySelector('[name="pix_payload"]')?.value.trim() || '';
      const key  = pixForm.querySelector('[name="pix_key"]')?.value.trim()  || '';
      const name = pixForm.querySelector('[name="pix_name"]')?.value.trim() || 'RECEBEDOR';
      const city = pixForm.querySelector('[name="pix_city"]')?.value.trim() || 'CIDADE';

      if (!raw && !key) { clearPreview(); setStatus('', ''); return; }

      try {
        const fd = new FormData();
        if (raw) {
          fd.append('pix_payload', raw);
        } else {
          fd.append('pix_key', key);
          fd.append('pix_name', name);
          fd.append('pix_city', city);
        }

        const res  = await fetch('pix_preview.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.ok) {
          if (data.payload !== lastPayload) {
            lastPayload = data.payload;
            if (imgEl) { imgEl.src = data.qr; imgEl.style.display = 'block'; }
            if (codeEl) codeEl.value = data.payload;
          }
          if (raw) {
            const who = [data.name, data.city].filter(Boolean).join(' — ');
            setStatus('ok', '✓ Código válido. Chave: ' + data.key + (who ? '  ·  ' + who : ''));
          } else {
            setStatus('', '');
          }
        } else {
          clearPreview();
          setStatus('err', data.error || 'Não foi possível ler o código.');
        }
      } catch (err) {
        setStatus('err', 'Erro de conexão ao validar o código.');
      }
    }

    pixForm.querySelectorAll('input, textarea').forEach(i => {
      i.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(updatePixPreview, 350);
      });
    });

    const firstInput = pixForm.querySelector('[name="pix_payload"]');
    const firstKey   = pixForm.querySelector('[name="pix_key"]');
    if ((firstInput && firstInput.value.trim() !== '') || (firstKey && firstKey.value.trim() !== '')) {
      updatePixPreview();
    }
  }

  // ============================================================
  // MÍDIA — link OU arquivo, com pré-visualização imediata
  // ============================================================
  const blobCache = new WeakMap(); // <input type=file> -> { file, url }

  // "uploads/x.jpg" (arquivo já enviado) precisa de "../" porque o painel está em /admin
  function resolveSrc(v) {
    v = (v || '').trim();
    if (!v) return '';
    if (/^(https?:)?\/\//i.test(v) || v.startsWith('data:') || v.startsWith('blob:')) return v;
    return '../' + v.replace(/^\/+/, '');
  }
  function fileBlobUrl(fileInput) {
    const f = fileInput && fileInput.files && fileInput.files[0];
    if (!f) return '';
    const c = blobCache.get(fileInput);
    if (c && c.file === f) return c.url;
    if (c) URL.revokeObjectURL(c.url);
    const url = URL.createObjectURL(f);
    blobCache.set(fileInput, { file: f, url });
    return url;
  }
  // arquivo escolhido tem prioridade sobre o link digitado (igual ao servidor)
  function mediaSrc(textInput, fileInput) {
    return fileBlobUrl(fileInput) || resolveSrc(textInput && textInput.value);
  }
  function cssUrl(u) { return u ? "url('" + u.replace(/'/g, '%27') + "')" : ''; }
  function fmtSize(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }

  // Mostra "Arquivo escolhido: nome (tamanho)" abaixo do campo
  document.addEventListener('change', (e) => {
    const inp = e.target;
    if (!(inp instanceof HTMLInputElement) || inp.type !== 'file') return;
    const note = inp.id ? document.querySelector('.file-name[data-for="' + inp.id + '"]') : null;
    if (!note) return;
    const f = inp.files && inp.files[0];
    note.textContent = f ? '📎 ' + f.name + ' (' + fmtSize(f.size) + ') — será enviado ao clicar em Salvar.' : '';
  });

  // ============================================================
  // BANNER — tipos, slides dinâmicos e preview
  // ============================================================
  (function () {
    const form = document.getElementById('banner-form');
    if (!form) return;

    const typeRadios   = form.querySelectorAll('input[name="banner_type"]');
    const panes        = form.querySelectorAll('.banner-pane');
    const preview      = document.getElementById('banner-preview');
    const inputImg     = document.getElementById('banner_url');
    const fileImg      = document.getElementById('banner_file');
    const inputVideo   = document.getElementById('banner_video_url');
    const fileVideo    = document.getElementById('banner_video_file');
    const inputHeight  = document.getElementById('banner_height');
    const inputOverlay = form.querySelector('input[name="banner_overlay"]');
    const slidesList   = document.getElementById('slides-list');
    const slideAddBtn  = document.getElementById('slide-add');
    const speedSelect  = document.getElementById('banner_slide_speed');

    const IMG_ACCEPT = 'image/jpeg,image/png,image/webp,image/gif';
    let slideshowTimer = null;

    function currentType() {
      for (const r of typeRadios) if (r.checked) return r.value;
      return 'none';
    }

    function applyType() {
      const t = currentType();
      document.querySelectorAll('.banner-type-opt').forEach(el => {
        el.classList.toggle('active', el.querySelector('input').value === t);
      });
      panes.forEach(p => { p.style.display = p.dataset.pane === t ? '' : 'none'; });
      updatePreview();
    }
    typeRadios.forEach(r => r.addEventListener('change', applyType));

    function slideRows() { return slidesList ? Array.from(slidesList.querySelectorAll('.slide-row')) : []; }

    function refreshSlideThumbs() {
      slideRows().forEach(row => {
        const src = mediaSrc(row.querySelector('input[type="text"]'), row.querySelector('input[type="file"]'));
        const th = row.querySelector('.slide-thumb');
        if (th) th.style.backgroundImage = cssUrl(src);
      });
    }

    function renumberSlides() {
      const rows = slideRows();
      rows.forEach((row, i) => {
        row.querySelector('.slide-num').textContent = i + 1;
        const btn = row.querySelector('.slide-remove');
        if (btn) btn.disabled = rows.length <= 1;
      });
    }

    slideAddBtn?.addEventListener('click', () => {
      if (!slidesList) return;
      const row = document.createElement('div');
      row.className = 'slide-row';
      row.innerHTML = `
        <span class="slide-num">?</span>
        <span class="slide-thumb"></span>
        <input type="text" name="banner_slides[]" placeholder="Link https://… ou envie →" autocomplete="off">
        <label class="btn-file btn-file-sm" title="Enviar imagem">📁
          <input type="file" name="banner_slide_files[]" accept="${IMG_ACCEPT}">
        </label>
        <button type="button" class="icon-btn slide-remove" title="Remover">✕</button>
      `;
      slidesList.appendChild(row);
      renumberSlides();
      row.querySelector('input[type="text"]').focus();
    });

    slidesList?.addEventListener('click', (e) => {
      if (e.target.closest('.slide-remove')) {
        const row = e.target.closest('.slide-row');
        if (slideRows().length > 1) {
          row.remove();
          renumberSlides();
          refreshSlideThumbs();
          updatePreview();
        } else {
          // última linha: só limpa
          row.querySelector('input[type="text"]').value = '';
          row.querySelector('input[type="file"]').value = '';
          refreshSlideThumbs();
          updatePreview();
        }
      }
    });

    function onSlideChange() {
      clearTimeout(slidesList._t);
      slidesList._t = setTimeout(() => { refreshSlideThumbs(); updatePreview(); }, 250);
    }
    slidesList?.addEventListener('input', onSlideChange);
    slidesList?.addEventListener('change', onSlideChange);

    function clearTimer() {
      if (slideshowTimer) { clearInterval(slideshowTimer); slideshowTimer = null; }
    }

    function heightPx() {
      return ({ sm: '120px', md: '180px', lg: '240px' })[inputHeight?.value] || '180px';
    }

    function msg(text, color) {
      return '<div style="display:grid;place-items:center;height:100%;color:' + (color || '#7C9385') + ';font-size:13px;text-align:center;padding:0 12px">' + text + '</div>';
    }

    function updatePreview() {
      clearTimer();
      if (!preview) return;

      const t = currentType();
      preview.innerHTML = '';
      preview.style.backgroundImage = '';
      preview.style.height = heightPx();
      preview.style.boxShadow = inputOverlay?.checked ? 'inset 0 0 0 2000px rgba(0,0,0,.35)' : '';

      if (t === 'none') { preview.innerHTML = msg('Sem banner'); return; }

      if (t === 'image') {
        const src = mediaSrc(inputImg, fileImg);
        if (!src) { preview.innerHTML = msg('Cole um link ou envie uma imagem'); return; }
        preview.style.backgroundImage = cssUrl(src);
        return;
      }

      if (t === 'video') {
        const src = mediaSrc(inputVideo, fileVideo);
        if (!src) { preview.innerHTML = msg('Cole um link ou envie um vídeo'); return; }
        // Link do YouTube: mostra a miniatura do vídeo no preview
        const ytm = String(src).match(/^https?:\/\/(?:(?:www\.|m\.|music\.)?youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/|v\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/);
        if (ytm) {
          preview.style.backgroundImage = cssUrl('https://img.youtube.com/vi/' + ytm[1] + '/hqdefault.jpg');
          const tag = document.createElement('span');
          tag.textContent = '▶ YouTube';
          tag.style.cssText = 'position:absolute;left:8px;bottom:8px;background:rgba(0,0,0,.65);color:#fff;font-size:12px;font-weight:600;padding:3px 9px;border-radius:999px;';
          preview.appendChild(tag);
          return;
        }
        const v = document.createElement('video');
        v.src = src; v.muted = true; v.loop = true; v.autoplay = true; v.playsInline = true;
        v.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:10px;';
        v.onerror = () => { preview.innerHTML = msg('Não foi possível carregar o vídeo', '#c0392b'); };
        preview.appendChild(v);
        const p = v.play(); if (p && p.catch) p.catch(() => {});
        return;
      }

      if (t === 'slideshow') {
        const urls = slideRows()
          .map(row => mediaSrc(row.querySelector('input[type="text"]'), row.querySelector('input[type="file"]')))
          .filter(Boolean);

        if (urls.length === 0) { preview.innerHTML = msg('Adicione links ou envie imagens'); return; }

        urls.forEach((url, i) => {
          const div = document.createElement('div');
          div.className = 'preview-slide' + (i === 0 ? ' active' : '');
          div.style.backgroundImage = cssUrl(url);
          preview.appendChild(div);
        });

        if (urls.length > 1) {
          let idx = 0;
          const speed = parseInt(speedSelect?.value || '4000', 10);
          const slides = preview.querySelectorAll('.preview-slide');
          slideshowTimer = setInterval(() => {
            slides[idx].classList.remove('active');
            idx = (idx + 1) % slides.length;
            slides[idx].classList.add('active');
          }, speed);
        }
      }
    }

    [inputImg, inputVideo].forEach(inp => inp?.addEventListener('input', () => {
      clearTimeout(inp._t); inp._t = setTimeout(updatePreview, 300);
    }));
    [fileImg, fileVideo].forEach(inp => inp?.addEventListener('change', updatePreview));
    inputHeight?.addEventListener('change', updatePreview);
    inputOverlay?.addEventListener('change', updatePreview);
    speedSelect?.addEventListener('change', updatePreview);

    applyType();
    renumberSlides();
    refreshSlideThumbs();
  })();

  // ============================================================
  // LINKS — destaque + banner do botão (miniatura ao vivo)
  // ============================================================
  document.querySelectorAll('.link-extra').forEach(box => {
    const text  = box.querySelector('.cover-input');
    const file  = box.querySelector('.cover-file');
    const thumb = box.querySelector('.cover-thumb');
    const check = box.querySelector('input[name="featured"]');
    const label = box.querySelector('summary');

    function refresh() {
      if (thumb) {
        const cur = mediaSrc(text, file);
        const ytm = String(cur).match(/^https?:\/\/(?:(?:www\.|m\.|music\.)?youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/|v\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/);
        const isVid = !ytm && /\.(mp4|m4v|webm|ogv)(\?.*)?$/i.test(String(cur));
        thumb.classList.toggle('is-video', !!(ytm || isVid));
        thumb.style.backgroundImage = ytm ? cssUrl('https://img.youtube.com/vi/' + ytm[1] + '/hqdefault.jpg')
                                          : (isVid ? '' : cssUrl(cur));
        thumb.classList.toggle('empty', !cur);
      }
      if (label && check) {
        label.innerHTML = '⭐ Destaque' + (check.checked ? ' <em>(ativo)</em>' : '');
      }
    }
    text?.addEventListener('input', refresh);
    file?.addEventListener('change', refresh);
    check?.addEventListener('change', refresh);
    refresh();
  });

});