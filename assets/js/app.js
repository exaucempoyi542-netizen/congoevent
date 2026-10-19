document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('[data-nav-toggle]');
  const menu = document.querySelector('[data-nav]');
  const closeBtn = document.querySelector('[data-nav-close]');
  const overlay = document.querySelector('[data-nav-overlay]');
  const header = document.querySelector('.site-header');

  const setMenuOpen = (open) => {
    if (!menu) return;
    menu.classList.toggle('is-open', open);
    menu.setAttribute('aria-hidden', open ? 'false' : 'true');
    if (toggle) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
    }
    document.body.classList.toggle('nav-open', open);
    if (header) header.classList.toggle('menu-open', open);
    if (overlay) {
      overlay.hidden = !open;
      overlay.classList.toggle('is-visible', open);
    }
  };

  setMenuOpen(false);

  toggle?.addEventListener('click', () => {
    setMenuOpen(!menu.classList.contains('is-open'));
  });

  closeBtn?.addEventListener('click', () => setMenuOpen(false));
  overlay?.addEventListener('click', () => setMenuOpen(false));

  menu?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setMenuOpen(false));
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) setMenuOpen(false);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') setMenuOpen(false);
  });

  if (header && header.classList.contains('header-home')) {
    const onScroll = () => {
      header.classList.toggle('is-scrolled', window.scrollY > 36);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  document.querySelectorAll('[data-auto-dismiss]').forEach((el) => {
    setTimeout(() => {
      el.style.transition = 'opacity 0.4s ease';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 400);
    }, 4500);
  });

  const debut = document.querySelector('#date_debut');
  const fin = document.querySelector('#date_fin');
  if (debut && fin) {
    const syncMin = () => {
      if (debut.value) {
        fin.min = debut.value;
        if (fin.value && fin.value < debut.value) {
          fin.value = debut.value;
        }
      }
    };
    debut.addEventListener('change', syncMin);
    syncMin();
  }

  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (e) => {
      const msg = el.getAttribute('data-confirm') || 'Confirmer cette action ?';
      if (!window.confirm(msg)) {
        e.preventDefault();
      }
    });
  });

  // Carrousel hero (accueil + fiche salle)
  document.querySelectorAll('[data-hero-slider]').forEach((slider) => {
    const slides = [...slider.querySelectorAll('.hero-slide')];
    if (slides.length < 2) return;

    const root = slider.closest('.hero, .detail-hero, .lp-hero') || document;
    const dots = [...root.querySelectorAll('[data-hero-dot]')];
    const prevBtn = root.querySelector('[data-hero-prev]');
    const nextBtn = root.querySelector('[data-hero-next]');
    let index = 0;
    let timer;

    const goTo = (next) => {
      slides[index]?.classList.remove('is-active');
      dots[index]?.classList.remove('is-active');
      index = (next + slides.length) % slides.length;
      slides[index]?.classList.add('is-active');
      dots[index]?.classList.add('is-active');
    };

    const start = () => {
      clearInterval(timer);
      timer = setInterval(() => goTo(index + 1), 5000);
    };

    dots.forEach((dot) => {
      dot.addEventListener('click', () => {
        goTo(Number(dot.dataset.heroDot) || 0);
        start();
      });
    });

    prevBtn?.addEventListener('click', () => {
      goTo(index - 1);
      start();
    });
    nextBtn?.addEventListener('click', () => {
      goTo(index + 1);
      start();
    });

    start();
  });

  // Chat : pièces jointes + message vocal
  document.querySelectorAll('[data-chat-compose]').forEach((form) => {
    const fileInput = form.querySelector('[data-chat-file]');
    const fileName = form.querySelector('[data-chat-file-name]');
    const micBtn = form.querySelector('[data-chat-mic]');
    const micLabel = form.querySelector('[data-chat-mic-label]');
    const recStatus = form.querySelector('[data-chat-rec-status]');
    const textArea = form.querySelector('[data-chat-text]');
    let recorder = null;
    let chunks = [];
    let recording = false;

    const box = document.querySelector('[data-chat-scroll]');
    if (box) box.scrollTop = box.scrollHeight;

    fileInput?.addEventListener('change', () => {
      const f = fileInput.files && fileInput.files[0];
      if (fileName) {
        if (f) {
          fileName.hidden = false;
          fileName.textContent = f.name;
        } else {
          fileName.hidden = true;
          fileName.textContent = '';
        }
      }
    });

    const stopRecording = async () => {
      if (!recorder || recorder.state === 'inactive') return;
      await new Promise((resolve) => {
        recorder.onstop = resolve;
        recorder.stop();
      });
      recording = false;
      micBtn?.classList.remove('is-recording');
      if (micLabel) micLabel.textContent = 'Message vocal';
      if (recStatus) recStatus.hidden = true;
      recorder.stream?.getTracks()?.forEach((t) => t.stop());

      const mime = recorder.mimeType || 'audio/webm';
      const blob = new Blob(chunks, { type: mime });
      chunks = [];
      if (blob.size < 500) return;

      const ext = mime.includes('ogg') ? 'ogg' : mime.includes('mp4') ? 'm4a' : 'webm';
      const file = new File([blob], `vocal_${Date.now()}.${ext}`, { type: mime });
      const dt = new DataTransfer();
      dt.items.add(file);
      if (fileInput) {
        fileInput.files = dt.files;
        fileInput.dispatchEvent(new Event('change'));
      }
      if (fileName) {
        fileName.hidden = false;
        fileName.textContent = '🎤 Message vocal prêt';
      }
    };

    micBtn?.addEventListener('click', async () => {
      if (recording) {
        await stopRecording();
        return;
      }
      if (!navigator.mediaDevices?.getUserMedia) {
        alert('L’enregistrement vocal n’est pas supporté sur ce navigateur.');
        return;
      }
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
          ? 'audio/webm;codecs=opus'
          : MediaRecorder.isTypeSupported('audio/webm')
            ? 'audio/webm'
            : '';
        recorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
        chunks = [];
        recorder.ondataavailable = (e) => {
          if (e.data && e.data.size > 0) chunks.push(e.data);
        };
        recorder.start();
        recording = true;
        micBtn.classList.add('is-recording');
        if (micLabel) micLabel.textContent = 'Stop';
        if (recStatus) recStatus.hidden = false;
      } catch (err) {
        alert('Autorisez l’accès au micro pour envoyer un message vocal.');
      }
    });

    form.addEventListener('submit', (e) => {
      const hasText = (textArea?.value || '').trim().length > 0;
      const hasFile = fileInput?.files && fileInput.files.length > 0;
      if (!hasText && !hasFile) {
        e.preventDefault();
        alert('Écrivez un message ou joignez un fichier / vocal.');
      }
    });
  });

  // Lecteur vocal style WhatsApp
  const fmtVoiceTime = (sec) => {
    if (!Number.isFinite(sec) || sec < 0) return '0:00';
    const s = Math.floor(sec);
    const m = Math.floor(s / 60);
    const r = String(s % 60).padStart(2, '0');
    return `${m}:${r}`;
  };

  let activeVoice = null;

  document.querySelectorAll('[data-chat-voice]').forEach((wrap) => {
    const audio = wrap.querySelector('[data-chat-voice-audio]');
    const btn = wrap.querySelector('[data-chat-voice-play]');
    const timeEl = wrap.querySelector('[data-chat-voice-time]');
    const wave = wrap.querySelector('[data-chat-voice-wave]');
    const bars = wave ? Array.from(wave.querySelectorAll('span')) : [];
    if (!audio || !btn) return;

    let duration = 0;

    const paintWave = () => {
      if (!bars.length || !duration) return;
      const ratio = Math.min(1, (audio.currentTime || 0) / duration);
      const filled = Math.floor(ratio * bars.length);
      bars.forEach((bar, i) => bar.classList.toggle('is-played', i < filled));
    };

    const showTime = () => {
      if (!timeEl) return;
      if (wrap.classList.contains('is-playing')) {
        timeEl.textContent = fmtVoiceTime(audio.currentTime);
      } else if (duration > 0) {
        timeEl.textContent = fmtVoiceTime(duration);
      } else {
        timeEl.textContent = '0:00';
      }
    };

    const stopVoice = () => {
      audio.pause();
      wrap.classList.remove('is-playing');
      btn.setAttribute('aria-label', 'Lire le message vocal');
      if (activeVoice === wrap) activeVoice = null;
      showTime();
      paintWave();
    };

    audio.addEventListener('loadedmetadata', () => {
      duration = audio.duration;
      if (Number.isFinite(duration) && duration > 0) showTime();
    });

    audio.addEventListener('timeupdate', () => {
      paintWave();
      showTime();
    });

    audio.addEventListener('ended', () => {
      audio.currentTime = 0;
      stopVoice();
      paintWave();
    });

    btn.addEventListener('click', async () => {
      if (wrap.classList.contains('is-playing')) {
        stopVoice();
        return;
      }
      if (activeVoice && activeVoice !== wrap) {
        const otherAudio = activeVoice.querySelector('[data-chat-voice-audio]');
        otherAudio?.pause();
        activeVoice.classList.remove('is-playing');
        const otherBtn = activeVoice.querySelector('[data-chat-voice-play]');
        otherBtn?.setAttribute('aria-label', 'Lire le message vocal');
      }
      try {
        await audio.play();
        wrap.classList.add('is-playing');
        btn.setAttribute('aria-label', 'Pause');
        activeVoice = wrap;
        showTime();
      } catch (err) {
        alert('Impossible de lire ce message vocal.');
      }
    });

    wave?.addEventListener('click', (e) => {
      if (!duration || !Number.isFinite(duration)) return;
      const rect = wave.getBoundingClientRect();
      const ratio = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
      audio.currentTime = ratio * duration;
      paintWave();
      showTime();
    });
  });

  // Animations d'apparition au scroll
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduceMotion && 'IntersectionObserver' in window) {
    const revealTargets = document.querySelectorAll(
      '.section-head, .split-copy, .split-media, .form-panel, .chat-app, .page-intro, .venue-card, .step, .home-section .section-cta'
    );
    revealTargets.forEach((el, i) => {
      if (el.classList.contains('venue-card')) return; // déjà animé au load
      el.classList.add('reveal');
      if (i % 3 === 1) el.classList.add('reveal-delay-1');
      if (i % 3 === 2) el.classList.add('reveal-delay-2');
    });
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
    );
    document.querySelectorAll('.reveal').forEach((el) => io.observe(el));
  }
});

