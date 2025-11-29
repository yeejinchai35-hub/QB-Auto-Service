// header.js
const __headerScriptUrl = (() => {
  try {
    const scriptEl = document.currentScript || document.querySelector('script[src*="header&footer/header.js"]');
    return scriptEl ? new URL(scriptEl.src, window.location.href) : null;
  } catch {
    return null;
  }
})();
const __headerWebsiteBase = __headerScriptUrl ? new URL('../', __headerScriptUrl) : null;

const buildWebsitePath = (targetPath) => {
  if (!__headerWebsiteBase || !targetPath) return targetPath;
  try {
    const cleaned = targetPath.replace(/^\/+/, '');
    const url = new URL(cleaned, __headerWebsiteBase);
    return url.pathname;
  } catch {
    return targetPath;
  }
};

const normalizeHeaderLinks = (container) => {
  if (!container || !__headerWebsiteBase) return;
  const anchors = container.querySelectorAll('a[href^="../"]');
  anchors.forEach((anchor) => {
    const raw = anchor.getAttribute('href') || '';
    const cleaned = raw.replace(/^(\.\.\/)+/g, '');
    if (!cleaned) return;
    anchor.setAttribute('href', buildWebsitePath(cleaned));
  });
};

// Rewrite asset paths (img/src, link/href, script/src) to correct absolute project paths
const rewriteHeaderAssets = (container) => {
  if (!container) return;
  const projectRootBase = __headerWebsiteBase ? new URL('../', __headerWebsiteBase) : null; // /project/Capstone-Car-Service-Draft4/
  const fix = (val) => {
    if (!val) return val;
    if (/^https?:/i.test(val) || val.startsWith('/') ) return val; // already absolute
    // Handle leading ../ segments -> project root
    if (/^(\.\.\/)+/.test(val) && projectRootBase) {
      const cleaned = val.replace(/^(\.\.\/)+/,'');
      try { return new URL(cleaned, projectRootBase).pathname; } catch { return val; }
    }
    // Root images shortcut: images/logo/...
    if (/^images\//i.test(val) && projectRootBase) {
      try { return new URL(val, projectRootBase).pathname; } catch { return val; }
    }
    // Default: relative to website base
    if (__headerWebsiteBase) {
      try { return new URL(val.replace(/^\.\//,''), __headerWebsiteBase).pathname; } catch { return val; }
    }
    return val;
  };
  const assetNodes = container.querySelectorAll('img[src], link[rel="stylesheet"][href], script[src]');
  assetNodes.forEach(node => {
    const attr = node.tagName === 'LINK' ? 'href' : 'src';
    const orig = node.getAttribute(attr);
    const fixed = fix(orig);
    if (fixed && fixed !== orig) {
      node.setAttribute(attr, fixed);
    }
  });
};

(function loadHeader() {
  if (window.__headerLoadedAttempted) return;
  window.__headerLoadedAttempted = true;
  const container = document.getElementById('headerContainer');
  if (!container) return;

  // We already have the base pointing at /website/ so we should NOT prefix '../'
  // Keep a minimal ordered fallback list.
  const candidatePaths = [
    'header&footer/header.html',
    '/project/Capstone-Car-Service-Draft4/website/header&footer/header.html'
  ];

  (async () => {
    let loaded = false;
    for (const path of candidatePaths) {
      const resolvedPath = buildWebsitePath(path) || path;
      try {
        const res = await fetch(resolvedPath);
        if (res.ok) {
          const html = await res.text();
          container.innerHTML = html;
          normalizeHeaderLinks(container);
          rewriteHeaderAssets(container);
          window.dispatchEvent(new CustomEvent('qb:header-loaded', {
            detail: { container, path: resolvedPath }
          }));
          initHeaderScripts();
          loaded = true;
          break;
        } else {
          console.warn('[header] 404 for', resolvedPath);
        }
      } catch (e) {
        console.warn('[header] fetch failed for', resolvedPath, e);
      }
    }
    if (!loaded) {
      console.error('[header] Failed to load header after trying candidates:', candidatePaths);
    }
  })();
})();


// Header interactions: scroll shadow, active nav highlighting, mobile collapse, and login/modal routing
// Expose init so other dynamic injections (e.g. SPA) can re-run safely
function initHeaderScripts() {
  if (window.__headerInitialized) return; // guard against re-init
  window.__headerInitialized = true;
  const header = document.querySelector('header.qb-header');
  const navbar = document.getElementById('mainNavbar');
  const navLinks = document.querySelectorAll('#mainNavbar .nav-link');
  const loginBtn = document.getElementById('loginBtn');

  // 1) Scroll effect: add .scrolled when page is offset
  let lastScrollY = window.scrollY || 0;
  let lastDirection = null;
  let ticking = false;
  let menuOpen = false;

  const evaluateScroll = () => {
    if (!header) { ticking = false; return; }
    const y = window.scrollY || 0;
    const diff = y - lastScrollY;
    const scrolledPast = y > 10;
    header.classList.toggle('scrolled', scrolledPast);

    if (!menuOpen && scrolledPast) {
      if (diff > 6 && lastDirection !== 'down') {
        header.classList.add('hide-down');
        header.classList.remove('show-up');
        lastDirection = 'down';
      } else if (diff < -6 && lastDirection !== 'up') {
        header.classList.remove('hide-down');
        header.classList.add('show-up');
        lastDirection = 'up';
      }
    } else {
      header.classList.remove('hide-down');
      header.classList.add('show-up');
    }

    lastScrollY = y;
    ticking = false;
  };

  const onScroll = () => {
    if (!ticking) {
      ticking = true;
      requestAnimationFrame(evaluateScroll);
    }
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  evaluateScroll();

  // Expose a reusable activateNavLink function (works on any page)
  function activateNavLink(arg) {
    // Allow: activateNavLink('services.html') or activateNavLink({pageName:'services.html', exact:true})
    const opts = typeof arg === 'string' ? { pageName: arg } : (arg || {});
    const pageMap = (window.navPageMap || {}); // optional mapping: {'dashboard.php':'home.html'}

    const normalize = (p) => {
      if (!p) return '';
      const base = p.split('/').pop().replace(/[?#].*/, '');
      return base.toLowerCase();
    };

    const stripExt = (p) => p.replace(/\.[a-z0-9]+$/, '');
    const currentRaw = opts.pageName || location.pathname;
    let target = normalize(currentRaw);
    if (pageMap[target]) target = normalize(pageMap[target]);
    const targetNoExt = stripExt(target);

    const links = document.querySelectorAll('#mainNavbar .nav-link');
    links.forEach(l => l.classList.remove('active'));

    let matched = false;
    links.forEach(l => {
      // Priority: data-page attribute, then href file
      const dataPage = normalize(l.getAttribute('data-page'));
      const hrefFile = normalize(l.getAttribute('href'));
      const hrefNoExt = stripExt(hrefFile);
      const dataNoExt = stripExt(dataPage);

      const exact = opts.exact !== false; // default true
      let isMatch = false;
      if (dataPage) {
        isMatch = exact ? (dataPage === target) : (dataNoExt === targetNoExt);
      } else if (hrefFile) {
        isMatch = exact ? (hrefFile === target) : (hrefNoExt === targetNoExt);
      }

      if (!matched && isMatch) {
        l.classList.add('active');
        matched = true;
      }
    });
    // Fallback: if nothing matched and target ends with 'index', try home.html
    if (!matched && /index$/i.test(targetNoExt)) {
      links.forEach(l => {
        if (normalize(l.getAttribute('href')) === 'home.html') {
          l.classList.add('active');
          matched = true;
        }
      });
    }
  }
  // Attach globally so other pages/scripts can call after dynamic injections
  window.activateNavLink = activateNavLink;

  // 2) Mark active nav link based on current page
  activateNavLink();
  // Update highlight on history navigation (if SPA-like behavior introduced later)
  window.addEventListener('popstate', () => activateNavLink());

  // 3) Collapse mobile menu when a nav item is clicked
  navLinks.forEach((a) => {
    a.addEventListener('click', () => {
      if (!navbar) return;
      // Guard if Bootstrap is not loaded
      if (!window.bootstrap || !window.bootstrap.Collapse) return;
      const collapse = window.bootstrap.Collapse.getOrCreateInstance(navbar, { toggle: false });
      if (window.innerWidth < 992) collapse.hide();
    });
  });

  // 4) Login button: open inline modal if present, else navigate to auth.html
  if (loginBtn) {
    loginBtn.addEventListener('click', (e) => {
      // If already logged in, let auth.js manage logout handler
      try {
        if (typeof window.__isLoggedIn === 'function' && window.__isLoggedIn()) {
          return; // no-op; auth.js attached a capture listener
        }
      } catch {}
      const modal = document.getElementById('authModal');
      if (modal) {
        e.preventDefault();
        modal.style.display = 'flex';
      } else {
        window.location.href = 'auth.html';
      }
    });
  }

  // 5) Track mobile navbar open/close to prevent hiding header while menu is open
  if (navbar) {
    navbar.addEventListener('shown.bs.collapse', () => { menuOpen = true; header && header.classList.remove('hide-down'); });
    navbar.addEventListener('hidden.bs.collapse', () => { menuOpen = false; evaluateScroll(); });
  }
}
window.initHeaderScripts = initHeaderScripts;
