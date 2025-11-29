const tryResolve = (base, relative) => {
  if (!base) return null;
  try {
    return new URL(relative, base);
  } catch {
    return null;
  }
};

const authScriptUrl = (() => {
  try {
    const scriptEl = document.currentScript || document.querySelector('script[src*="auth/auth.js"]');
    return scriptEl ? new URL(scriptEl.src, window.location.href) : null;
  } catch {
    return null;
  }
})();

const authBaseUrl = authScriptUrl ? tryResolve(authScriptUrl, './') : null; // .../php/auth/
let phpBaseUrl = authBaseUrl ? tryResolve(authBaseUrl, '../') : null; // .../php/
if (phpBaseUrl && !/\/php\/?$/i.test(phpBaseUrl.pathname)) {
  const fallbackPhp = tryResolve(authBaseUrl, '../../php/');
  if (fallbackPhp) {
    phpBaseUrl = fallbackPhp;
  }
}
const projectRootUrl = phpBaseUrl ? tryResolve(phpBaseUrl, '../') : (authBaseUrl ? tryResolve(authBaseUrl, '../') : null);
let websiteBaseUrl = projectRootUrl ? tryResolve(projectRootUrl, 'website/') : null;
if (!websiteBaseUrl) {
  websiteBaseUrl = authBaseUrl ? tryResolve(authBaseUrl, '../') : null;
}

const buildAbsolutePath = (baseUrl, relativePath) => {
  if (!relativePath) return relativePath;
  if (/^https?:/i.test(relativePath)) return relativePath;
  if (baseUrl) {
    try {
      const cleaned = relativePath.replace(/^\/+/,'');
      const url = new URL(cleaned, baseUrl);
      return url.pathname + url.search + url.hash;
    } catch {}
  }
  return relativePath;
};

function getRelativePath(type, path) {
  switch (type) {
    case 'php':
      return buildAbsolutePath(phpBaseUrl, path);
    case 'web':
      return buildAbsolutePath(websiteBaseUrl, path);
    case 'auth':
    case 'self':
    default:
      return buildAbsolutePath(authBaseUrl, path);
  }
}

function configureAuthFormEndpoints(root = document) {
  const loginForm = root.querySelector('#loginForm');
  const signupForm = root.querySelector('#signupForm');
  if (loginForm) {
    loginForm.setAttribute('action', getRelativePath('php', 'auth/login.php'));
  }
  if (signupForm) {
    signupForm.setAttribute('action', getRelativePath('php', 'auth/register.php'));
  }
}

function ensureAuthStyles() {
  const styleId = 'qb-auth-styles';
  if (document.getElementById(styleId)) return;
  const href = getRelativePath('auth', 'auth.css');
  const link = document.createElement('link');
  link.id = styleId;
  link.rel = 'stylesheet';
  link.href = href;
  document.head.appendChild(link);
}

// Load auth modal dynamically
// We use a dynamic path to find auth.html regardless of where this script is called from
const authHtmlPath = getRelativePath('auth', 'auth.html');
ensureAuthStyles();

fetch(authHtmlPath)
  .then(r => r.text())
  .then(html => {
    const container = document.getElementById('authContainer');
    if (container) {
      // Strip any stray <script>/<link> tags to avoid duplicate loads
      const temp = document.createElement('div');
      temp.innerHTML = html;
      temp.querySelectorAll('script, link[rel="stylesheet"]').forEach(node => node.remove());
      container.innerHTML = temp.innerHTML;
      configureAuthFormEndpoints(container);
      initAuthScripts();
    }
  })
  .catch(err => console.error('Failed to load auth modal:', err));

function initAuthScripts() {
  const modal = document.getElementById('authModal');
  const loginForm = document.getElementById('loginForm');
  const signupForm = document.getElementById('signupForm');
  const showSignupLink = document.getElementById('showSignup');
  const showLoginLink  = document.getElementById('showLogin');
  const forgotForm = document.getElementById('forgotForm');
  const showForgotLink = document.getElementById('showForgot');
  const showLoginFromForgot = document.getElementById('showLoginFromForgot');
  const formTitle = document.getElementById('formTitle');

  // Toggle forms
  function showSignup() {
    if (!signupForm || !loginForm) return;
    loginForm.style.display = 'none';
    signupForm.style.display = 'block';
    if (formTitle) formTitle.textContent = 'Sign Up';
  }
  function showLogin() {
    if (!signupForm || !loginForm) return;
    signupForm.style.display = 'none';
    if (forgotForm) forgotForm.style.display = 'none';
    loginForm.style.display = 'block';
    if (formTitle) formTitle.textContent = 'Login';
  }
  function showForgot() {
    if (!forgotForm || !loginForm) return;
    loginForm.style.display = 'none';
    if (signupForm) signupForm.style.display = 'none';
    forgotForm.style.display = 'block';
    if (formTitle) formTitle.textContent = 'Reset Password';
  }
  showSignupLink?.addEventListener('click', e => { e.preventDefault(); showSignup(); });
  showLoginLink?.addEventListener('click', e => { e.preventDefault(); showLogin(); });
  showForgotLink?.addEventListener('click', e => { e.preventDefault(); showForgot(); });
  showLoginFromForgot?.addEventListener('click', e => { e.preventDefault(); showLogin(); });


  let loggedIn = false;
  let role = null; // 'user' | 'admin' | null

  const dispatchAuthChange = () => {
    try { window.__isLoggedIn = () => loggedIn; } catch(_) {}
    window.dispatchEvent(new CustomEvent('auth:changed', {
      detail: { loggedIn, role }
    }));
  };
  dispatchAuthChange();

  function ensureBlurOverlay() {
    let overlay = document.getElementById('blurOverlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'blurOverlay';
      overlay.style.position = 'fixed';
      overlay.style.inset = '0';
      overlay.style.backdropFilter = 'blur(6px)';
      overlay.style.webkitBackdropFilter = 'blur(6px)';
      overlay.style.background = 'rgba(0,0,0,0.08)';
      overlay.style.zIndex = '999';
      overlay.style.pointerEvents = 'auto';
      document.body.appendChild(overlay);
    }
    return overlay;
  }

  function blurPage() {
    ensureBlurOverlay();
    if (modal) {
      modal.style.display = 'flex';
      modal.style.zIndex = '1000';
    }
  }

  function unblurPage() {
    const overlay = document.getElementById('blurOverlay');
    if (overlay) overlay.remove();
  }

  function updateLoginButton(attempt = 0) {
    const btn = document.getElementById('loginBtn');
    if (!btn) {
      if (attempt < 5) setTimeout(() => updateLoginButton(attempt + 1), 150 * (attempt + 1));
      return;
    }
    const parent = btn.parentNode;
    if (!parent) return;
    const clone = btn.cloneNode(true);
    parent.replaceChild(clone, btn);
    const freshBtn = document.getElementById('loginBtn');
    if (!freshBtn) return;

    if (loggedIn) {
      freshBtn.textContent = 'Logout';
      freshBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        const logoutPath = getRelativePath('php', 'auth/logout.php');
        try { await fetch(logoutPath); } catch {}
        loggedIn = false;
        dispatchAuthChange();
        
        // --- CHANGED: Redirect to home page with status parameter ---
        // Instead of staying on profile.html or user_dashboard, go to Home with message
        window.location.href = getRelativePath('web', 'home/home.html?status=logged_out');
      });
    } else {
      freshBtn.textContent = 'Login / Sign Up';
      freshBtn.addEventListener('click', (e) => {
        e.preventDefault();
        blurPage();
      });
    }
  }

  // Normalize all Dashboard links across header/footer based on session state
  function updateDashboardLinks() {
    const navLinks = Array.from(document.querySelectorAll('a.nav-link'));
    if (!navLinks.length) return;

    const userDashPath = getRelativePath('php', 'user/user_dashboard.php');
    const adminDashPath = getRelativePath('php', 'admin/dashboard/admin_dashboard.php');
    const profilePath = getRelativePath('web', 'profile/profile.html');
    const userProgressPath = getRelativePath('php', 'user/progress/progress.php');
    const adminProgressPath = getRelativePath('php', 'admin/dashboard/services/manage_progress.php');
    const publicProgressPath = getRelativePath('web', 'progress/progress.html');

    const dashboardLinks = navLinks.filter(a => /dashboard/i.test(a.textContent || ''));
    const dashboardHref = loggedIn
      ? (role === 'admin' ? adminDashPath : userDashPath)
      : profilePath;
    dashboardLinks.forEach(a => a.setAttribute('href', dashboardHref));

    const progressLinks = navLinks.filter(a => /progress/i.test(a.textContent || ''));
    const progressHref = loggedIn
      ? (role === 'admin' ? adminProgressPath : userProgressPath)
      : publicProgressPath;
    progressLinks.forEach(a => a.setAttribute('href', progressHref));

    // SPECIAL CASE: If we are ON the profile.html page and logged in, redirect immediately
    if (window.location.pathname.endsWith('profile.html') && loggedIn) {
      window.location.replace(role === 'admin' ? adminDashPath : userDashPath);
    }
  }

  // Check PHP session login state
  async function initLoginState() {
    const checkLoginPath = getRelativePath('php', 'auth/check_login.php');
    try {
      const resp = await fetch(checkLoginPath);
      const data = await resp.json();
      loggedIn = data.loggedIn;
      role = data.role || null;

      updateLoginButton();
      updateDashboardLinks();
      dispatchAuthChange();

      // On profile.html or progress.html (public placeholders), if not logged in auto-open modal + blur
      if ((window.location.pathname.endsWith('profile.html') || window.location.pathname.endsWith('progress.html')) && !loggedIn) {
        blurPage();
      }
    } catch(err) {
      console.error('Login check failed:', err);
    }
  }

  initLoginState();
  window.addEventListener('qb:header-loaded', () => {
    updateLoginButton();
    updateDashboardLinks();
  });

  // Login form submission
  if (loginForm) {
    loginForm.addEventListener('submit', async e => {
      e.preventDefault();
      const formData = new FormData(loginForm);
      let result = { success: false, message: 'Error' };
      const loginPath = getRelativePath('php', 'auth/login.php');
      
      try {
        const resp = await fetch(loginPath, { method: 'POST', body: formData });
        result = await resp.json();
      } catch(err) { console.error(err); alert('Login error'); return; }

      if (result.success) {
        loggedIn = true;
        role = result.role || null;
        dispatchAuthChange();
        unblurPage();
        if (modal) modal.style.display = 'none';
        
        // Redirect to Dashboard
        const userDashPath = getRelativePath('php', 'user/user_dashboard.php');
        const adminDashPath = getRelativePath('php', 'admin/dashboard/admin_dashboard.php');
        window.location.href = role === 'admin' ? adminDashPath : userDashPath;
      } else {
        alert('❌ ' + result.message);
      }
    });
  }

  // Signup form submission (AJAX)
  if (signupForm) {
    signupForm.addEventListener('submit', async e => {
      e.preventDefault();
      const formData = new FormData(signupForm);
      let result = { success: false, message: 'Error' };
      const registerPath = getRelativePath('php', 'auth/register.php');

      try {
        const resp = await fetch(registerPath, { method: 'POST', body: formData });
        const ct = (resp.headers.get('content-type') || '').toLowerCase();
        if (ct.includes('application/json')) {
          result = await resp.json();
        } else {
          const text = await resp.text();
          if (resp.ok) {
            result = { success: true, message: 'Signup successful! You may now log in.' };
          } else {
            throw new Error('Non-JSON error response');
          }
        }
      } catch(err) { console.error(err); alert('Signup failed. Please try again.'); return; }

      if (result.success) {
        alert('Signup successful! You may now log in.');
        showLogin();
        return;
      } else {
        alert('❌ ' + result.message);
      }
    });
  }

  // Forgot password submission
  if (forgotForm) {
    forgotForm.addEventListener('submit', async e => {
      e.preventDefault();
      const formData = new FormData(forgotForm);
      let result = { success:false, message:'Error' };
      const forgotPath = getRelativePath('php', 'auth/request_password_reset.php');
      
      try {
        const resp = await fetch(forgotPath, { method:'POST', body: formData });
        result = await resp.json();
      } catch(err) { console.error(err); alert('Reset request failed'); return; }
      alert(result.message);
      if (result.success) {
        showLogin();
      }
    });
  }

  // Password show/hide toggle for signup
  // Password show/hide toggle logic
  const togglePw = () => {
    // 1. Login Form Toggle
    const loginCb = document.getElementById('toggleLoginPw');
    const loginPw = document.getElementById('login_password');

    if (loginCb && loginPw) {
      loginCb.addEventListener('change', e => {
        loginPw.type = e.target.checked ? 'text' : 'password';
      });
    }

    // 2. Signup Form Toggle
    const signupCb = document.getElementById('togglePw');
    const signupPw = document.getElementById('password');
    const signupCpw = document.getElementById('confirm_password');

    if (signupCb && signupPw && signupCpw) {
      signupCb.addEventListener('change', e => {
        const type = e.target.checked ? 'text' : 'password';
        signupPw.type = type;
        signupCpw.type = type;
      });
    }
  };
  togglePw();

  function closeAuthModal() {
    if (modal) modal.style.display = 'none';
    // Keep blur on profile.html when user still not logged in (placeholder state)
      const path = window.location.pathname || '';
      const isSpecialPage = path.endsWith('profile.html') || path.endsWith('progress.html');
      // Do not unblur if on profile page and not logged in
      // Just make overlay non-interactive so they can't click anything but header
      const overlay = document.getElementById('blurOverlay');
      if (overlay) {
        overlay.style.pointerEvents = 'none'; 
      }
      if (!isSpecialPage || loggedIn) {
        unblurPage();
      }
  }

  // Close modal (X button)
  modal?.querySelector('.close')?.addEventListener('click', closeAuthModal);
  // Click outside modal
  window.addEventListener('click', e => { if (e.target === modal) { closeAuthModal(); } });
  // Escape key
  window.addEventListener('keydown', e => { if (e.key === 'Escape' && modal?.style.display === 'flex') { closeAuthModal(); } });
}