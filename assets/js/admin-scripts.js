// NDS Loader Utility
// Usage:
// 1) Inline loader near text: NDSLoader.inline(buttonEl)
// 2) Overlay loader on container: const stop = NDSLoader.overlay(containerEl); stop() to remove
// 3) Table row loader: const stop = NDSLoader.row(trEl); stop() to remove

window.NDSLoader = (function() {
  function createSpinner(size = 20, border = 2) {
    const el = document.createElement('span');
    el.className = 'nds-spinner';
    el.style.display = 'inline-block';
    el.style.width = size + 'px';
    el.style.height = size + 'px';
    el.style.border = border + 'px solid #c7d2fe';
    el.style.borderTopColor = '#4f46e5';
    el.style.borderRadius = '50%';
    el.style.animation = 'nds-spin 0.8s linear infinite';
    return el;
  }

  // 1) Inline loader
  function inline(target, opts) {
    const options = Object.assign({size: 16, border: 2, gap: 8}, opts || {});
    if (!target) return () => {};
    const spinner = createSpinner(options.size, options.border);
    const gap = document.createElement('span');
    gap.style.display = 'inline-block';
    gap.style.width = options.gap + 'px';
    target.insertAdjacentElement('afterend', gap);
    gap.insertAdjacentElement('afterend', spinner);
    return function stop() {
      spinner.remove();
      gap.remove();
    };
  }

  // 2) Overlay loader
  function overlay(container, opts) {
    const options = Object.assign({backdrop: 'rgba(255,255,255,0.6)', blur: '4px'}, opts || {});
    if (!container) return () => {};
    const oldPos = container.style.position;
    if (getComputedStyle(container).position === 'static') {
      container.style.position = 'relative';
    }
    const mask = document.createElement('div');
    mask.className = 'nds-overlay-mask';
    Object.assign(mask.style, {
      position: 'absolute', inset: '0', background: options.backdrop,
      backdropFilter: `blur(${options.blur})`, display: 'flex', alignItems: 'center', justifyContent: 'center',
      zIndex: 999
    });
    const spinner = createSpinner(36, 3);
    mask.appendChild(spinner);
    container.appendChild(mask);
    return function stop() {
      mask.remove();
      container.style.position = oldPos;
    };
  }

  // 3) Row loader
  function row(tr, opts) {
    const options = Object.assign({backdrop: 'rgba(255,255,255,0.7)', blur: '2px'}, opts || {});
    if (!tr) return () => {};
    tr.classList.add('nds-row-blur');
    const td = tr.querySelector('td') || tr.appendChild(document.createElement('td'));
    const oldPos = tr.style.position;
    if (getComputedStyle(tr).position === 'static') tr.style.position = 'relative';
    const mask = document.createElement('div');
    Object.assign(mask.style, {
      position: 'absolute', inset: '0', background: options.backdrop,
      backdropFilter: `blur(${options.blur})`, display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 5
    });
    const spinner = createSpinner(24, 2);
    mask.appendChild(spinner);
    tr.appendChild(mask);
    return function stop() {
      tr.classList.remove('nds-row-blur');
      mask.remove();
      tr.style.position = oldPos;
    };
  }

  // Inject keyframes once
  (function ensureSpin() {
    if (document.getElementById('nds-spin-style')) return;
    const s = document.createElement('style');
    s.id = 'nds-spin-style';
    s.textContent = '@keyframes nds-spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}';
    document.head.appendChild(s);
  })();

  return { inline, overlay, row };
})();





