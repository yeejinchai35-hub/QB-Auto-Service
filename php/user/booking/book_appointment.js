const bookingScriptMeta = (() => {
  try {
    const scriptEl = document.currentScript || document.querySelector('script[src*="book_appointment.js"]');
    if (!scriptEl) return {};
    const scriptUrl = new URL(scriptEl.src, window.location.href);
    return {
      scriptUrl,
      bookingBase: new URL('./', scriptUrl),
      phpBase: new URL('../../', scriptUrl)
    };
  } catch {
    return {};
  }
})();

const buildBookingPath = (baseUrl, relativePath) => {
  if (!baseUrl || !relativePath) return null;
  try {
    if (/^https?:/i.test(relativePath)) return relativePath;
    const cleaned = relativePath.replace(/^\/+/, '');
    const url = new URL(cleaned, baseUrl);
    return url.pathname + url.search + url.hash;
  } catch {
    return null;
  }
};

const CHECK_LOGIN_ENDPOINT = buildBookingPath(bookingScriptMeta.phpBase, 'auth/check_login.php')
  || '../../php/auth/check_login.php';
const BOOK_APPOINTMENT_ENDPOINT = buildBookingPath(bookingScriptMeta.bookingBase, 'book_appointment.php')
  || '../../php/user/booking/book_appointment.php';

document.addEventListener("DOMContentLoaded", () => {
  const bookingForm = document.getElementById("bookingForm");
  const addServiceBtn = document.getElementById("addServiceBtn");
  const serviceContainer = document.getElementById("serviceContainer");
  const headingLoginBtn = document.getElementById("openLoginFromHeading");
  const loginNote = document.querySelector('.booking-header .login-note');

  // --- NEW: Date Picker Logic (Tomorrow onwards only) ---
  const dateInput = document.querySelector('input[name="date"]');
  if (dateInput) {
      const today = new Date();
      const tomorrow = new Date(today);
      tomorrow.setDate(tomorrow.getDate() + 1); // Add 1 day
      
      // Format to YYYY-MM-DD
      const yyyy = tomorrow.getFullYear();
      const mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
      const dd = String(tomorrow.getDate()).padStart(2, '0');
      
      dateInput.min = `${yyyy}-${mm}-${dd}`;
  }

  // --- NEW: Auto-fill User Data (Name & Phone) ---
  // This helps ensure the phone number matches the account
    if (typeof window.__isLoggedIn === 'function' && window.__isLoggedIn()) {
      fetch(CHECK_LOGIN_ENDPOINT)
          .then(res => res.json())
          .then(data => {
              if (data.loggedIn) {
                  const nameField = document.querySelector('input[name="name"]');
                  const phoneField = document.querySelector('input[name="phone"]');
                  // We assume the check_login.php returns name/phone in 'user' object
                  // You might need to ensure check_login.php returns these details
                  if (nameField && data.user?.full_name) nameField.value = data.user.full_name;
                  if (phoneField && data.user?.phone) phoneField.value = data.user.phone;
                  
                  // Optional: Make them read-only to force match?
                  // phoneField.readOnly = true; 
              }
          })
          .catch(err => console.log('Auto-fill error:', err));
  }

  if (!bookingForm || !serviceContainer) return;

  function isLoggedIn() {
    return typeof window.__isLoggedIn === 'function' ? window.__isLoggedIn() : false;
  }

  // ============================================================
  //  SUBMIT LOGIC
  // ============================================================
  if (bookingForm) {
    bookingForm.onsubmit = async (e) => {
      e.preventDefault();

      if (!isLoggedIn()) {
        alert("⚠️ Please log in before booking!");
        const loginBtn = document.getElementById("loginBtn");
        if (loginBtn) loginBtn.click();
        return;
      }

      const formData = new FormData(bookingForm);
      const submitBtn = bookingForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.textContent;

      try {
        submitBtn.disabled = true;
        submitBtn.textContent = "Processing...";

        const response = await fetch(BOOK_APPOINTMENT_ENDPOINT, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          alert("✅ " + result.message);
          bookingForm.reset();
          if (serviceContainer) {
            serviceContainer.innerHTML = '';
            createServiceDropdown();
            updateDropdowns();
          }
        } else {
          alert("❌ " + result.message);
        }

      } catch (error) {
        console.error('Error:', error);
        alert("❌ Network error. Please check your connection.");
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      }
    };
  }

  // ... (Keep the existing Service Dropdown logic below here unchanged) ...
  const allServices = [
    "Coolant Fluid Service", "Engine Oil & Filter Change", "Brake Fluid Flush",
    "Transmission Fluid Service", "Air Conditioning Re-gas", "Engine Treatment/Tuning",
    "Battery Replacement", "Brake Pad Replacement", "Disc Brake Replacement",
    "Brake Shoe Replacement", "Caliper Service", "Wiper Blade Replacement",
    "Tyre Installation", "Wheel Alignment", "Wheel Balancing"
  ];

  function createServiceDropdown(selected = []) {
    const wrapper = document.createElement("div");
    wrapper.className = "service-select-wrapper";
    const select = document.createElement("select");
    select.name = "service_type[]";
    select.required = true;
    select.className = "service-select";
    select.innerHTML = `<option value="">-- Select a Service --</option>`;
    const removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.textContent = "✖";
    removeBtn.className = "remove-service-btn";
    select.addEventListener("change", updateDropdowns);
    wrapper.appendChild(select);
    wrapper.appendChild(removeBtn);
    serviceContainer.appendChild(wrapper);
  }

  if (serviceContainer) {
    serviceContainer.addEventListener('click', (e) => {
      if (e.target && e.target.classList.contains('remove-service-btn')) {
        const row = e.target.closest('.service-select-wrapper');
        if (serviceContainer.children.length > 1) {
            if (row) row.remove();
            updateDropdowns();
        } else {
            const sel = row.querySelector('select');
            if (sel) sel.value = "";
            updateDropdowns();
        }
      }
    });
  }

  function updateDropdowns() {
    const selects = Array.from(serviceContainer.querySelectorAll("select"));
    const chosen = selects.map(s => s.value).filter(v => v);
    selects.forEach(sel => {
      const current = sel.value;
      sel.innerHTML = `<option value="">-- Select a Service --</option>`;
      allServices.forEach(service => {
        if (!chosen.includes(service) || service === current) {
          const opt = document.createElement("option");
          opt.textContent = service;
          opt.value = service;
          if (service === current) opt.selected = true;
          sel.appendChild(opt);
        }
      });
    });
    if (selects.length === 0) createServiceDropdown();
  }

  if (addServiceBtn) {
    addServiceBtn.addEventListener("click", () => {
      const selected = Array.from(serviceContainer.querySelectorAll("select")).map(s => s.value).filter(v => v);
      if (selected.length < allServices.length) {
        createServiceDropdown(selected);
        updateDropdowns();
      } else {
        alert("You have selected all available services!");
      }
    });
  }

  if (serviceContainer.children.length === 0) {
    createServiceDropdown();
  }
  updateDropdowns();

  // (Login button logic remains the same)
  if (headingLoginBtn) {
    headingLoginBtn.addEventListener('click', () => {
      const loginBtn = document.getElementById('loginBtn');
      if (loginBtn) loginBtn.click();
    });
  }
  
  function refreshLoginNote() {
    const logged = typeof window.__isLoggedIn === 'function' && window.__isLoggedIn();
    if (loginNote) {
      const btn = loginNote.querySelector('#openLoginFromHeading');
      const textSpan = loginNote.querySelector('span');
      if (logged) {
        if (textSpan) textSpan.textContent = "You're logged in — go ahead and book your service.";
        if (btn) btn.style.display = 'none';
      } else {
        if (textSpan) textSpan.textContent = "Please log in before booking an appointment.";
        if (btn) btn.style.display = '';
      }
    }
  }
  refreshLoginNote();
  window.addEventListener('auth:changed', refreshLoginNote);
});