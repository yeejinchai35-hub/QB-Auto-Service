// footer.js
const __footerScriptUrl = (() => {
  try {
    const scriptEl = document.currentScript || document.querySelector('script[src*="header&footer/footer.js"]');
    return scriptEl ? new URL(scriptEl.src, window.location.href) : null;
  } catch {
    return null;
  }
})();
const __footerWebsiteBase = __footerScriptUrl ? new URL('../', __footerScriptUrl) : null;

const buildFooterWebsitePath = (targetPath) => {
  if (!__footerWebsiteBase || !targetPath) return targetPath;
  try {
    const cleaned = targetPath.replace(/^\/+/, '');
    const url = new URL(cleaned, __footerWebsiteBase);
    return url.pathname;
  } catch {
    return targetPath;
  }
};

const normalizeFooterLinks = (container) => {
  if (!container || !__footerWebsiteBase) return;
  const anchors = container.querySelectorAll('a[href^="../"]');
  anchors.forEach((anchor) => {
    const raw = anchor.getAttribute('href') || '';
    const cleaned = raw.replace(/^(\.\.\/)+/g, '');
    if (!cleaned) return;
    anchor.setAttribute('href', buildFooterWebsitePath(cleaned));
  });
};

const rewriteFooterAssets = (container) => {
  if (!container) return;
  const projectRootBase = __footerWebsiteBase ? new URL('../', __footerWebsiteBase) : null;
  const fix = (val) => {
    if (!val) return val;
    if (/^https?:/i.test(val) || val.startsWith('/')) return val;
    if (/^(\.\.\/)+/.test(val) && projectRootBase) {
      const cleaned = val.replace(/^(\.\.\/)+/, '');
      try { return new URL(cleaned, projectRootBase).pathname; } catch { return val; }
    }
    if (/^images\//i.test(val) && projectRootBase) {
      try { return new URL(val, projectRootBase).pathname; } catch { return val; }
    }
    if (__footerWebsiteBase) {
      try { return new URL(val.replace(/^\.\//,''), __footerWebsiteBase).pathname; } catch { return val; }
    }
    return val;
  };
  const assetNodes = container.querySelectorAll('img[src], link[rel="stylesheet"][href], script[src]');
  assetNodes.forEach(node => {
    const attr = node.tagName === 'LINK' ? 'href' : 'src';
    const orig = node.getAttribute(attr);
    const fixed = fix(orig);
    if (fixed && fixed !== orig) node.setAttribute(attr, fixed);
  });
};

(function loadFooter() {
  const container = document.getElementById('footerContainer');
  if (!container) return;

  const candidatePaths = [
    'header&footer/footer.html',
    '/project/Capstone-Car-Service-Draft4/website/header&footer/footer.html'
  ];

  (async () => {
    let loaded = false;
    for (const path of candidatePaths) {
      const resolvedPath = buildFooterWebsitePath(path) || path;
      try {
        const res = await fetch(resolvedPath);
        if (res.ok) {
          container.innerHTML = await res.text();
          normalizeFooterLinks(container);
          rewriteFooterAssets(container);
          const y = document.getElementById('year');
          if (y) y.textContent = new Date().getFullYear();
          loaded = true;
          break;
        } else {
          console.warn('[footer] 404 for', resolvedPath);
        }
      } catch (e) {
        console.warn('[footer] fetch failed for', resolvedPath, e);
      }
    }
    if (!loaded) {
      console.error('[footer] Failed to load footer after trying candidates:', candidatePaths);
    }
  })();
})();

// Initialization function for any dynamic footer logic
function initFooterScripts() {
    // Set the current year dynamically in the copyright notice
    const yearSpan = document.getElementById('year');
    if (yearSpan) {
        yearSpan.textContent = new Date().getFullYear();
    }
    
    // Note: The footer links may need similar 'active' class logic as header.js
    // if you want them highlighted, but usually footer links don't require it.
    // If you need it, add it here.
}