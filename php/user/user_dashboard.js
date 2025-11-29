/* =========================================
   User Dashboard - Interaction Logic
   ========================================= */

(function () {
    const doc = document;

    const updatePanelToggleLabel = (button, expanded) => {
        if (!button) return;
        const openLabel = button.dataset.labelOpen || button.textContent.trim();
        const closeLabel = button.dataset.labelClose || openLabel;
        button.textContent = expanded ? closeLabel : openLabel;
    };


    // -----------------------------------------
    // Tabs (sidebar + main header)
    // -----------------------------------------
    const tabButtons = doc.querySelectorAll('[data-tab-link]');
    const tabPanels = doc.querySelectorAll('[data-tab-panel]');

    const setActiveTab = (slug) => {
        if (!slug) return;
        tabButtons.forEach((btn) => {
            const isActive = btn.dataset.tabLink === slug;
            btn.classList.toggle('active', isActive);
        });
        tabPanels.forEach((panel) => {
            const isActive = panel.dataset.tabPanel === slug;
            panel.classList.toggle('active', isActive);
        });
    };

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => setActiveTab(button.dataset.tabLink));
    });

    // --- NEW LOGIC: Check server input first ---
    const serverTabInput = doc.getElementById('serverActiveTab');
    if (serverTabInput && serverTabInput.value) {
        setActiveTab(serverTabInput.value);
    } else if (tabPanels.length) {
        // Fallback to first tab if no server instruction
        const activePanel = doc.querySelector('.tab-panel.active');
        if (!activePanel) {
            const firstPanel = tabPanels[0].dataset.tabPanel;
            setActiveTab(firstPanel);
        }
    }

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => setActiveTab(button.dataset.tabLink));
    });

    if (tabPanels.length) {
        const activePanel = doc.querySelector('.tab-panel.active');
        if (!activePanel) {
            const firstPanel = tabPanels[0].dataset.tabPanel;
            setActiveTab(firstPanel);
        }
    }

    // -----------------------------------------
    // Collapsible panel for adding vehicles
    // -----------------------------------------
    doc.querySelectorAll('[data-panel-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = doc.getElementById(button.dataset.panelToggle);
            if (!target) return;
            const expanded = target.classList.toggle('active');
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            updatePanelToggleLabel(button, expanded);
        });
    });

    doc.querySelectorAll('[data-panel-cancel]').forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.panelCancel;
            const panel = targetId ? doc.getElementById(targetId) : button.closest('.panel-collapsible');
            if (!panel) return;
            panel.classList.remove('active');
            const toggleBtn = targetId ? doc.querySelector(`[data-panel-toggle="${targetId}"]`) : null;
            toggleBtn?.setAttribute('aria-expanded', 'false');
            if (toggleBtn) updatePanelToggleLabel(toggleBtn, false);
            const form = button.closest('form');
            form?.reset();
        });
    });

    // -----------------------------------------
    // Vehicle row edit toggles
    // -----------------------------------------
    doc.querySelectorAll('[data-vehicle-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const row = doc.getElementById(button.dataset.vehicleToggle);
            row?.classList.toggle('editing');
        });
    });

    doc.querySelectorAll('[data-vehicle-cancel]').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('.vehicle-item');
            row?.classList.remove('editing');
        });
    });

    // -----------------------------------------
    // Flash messages auto-dismiss
    // -----------------------------------------
    doc.querySelectorAll('[data-flash]').forEach((flash) => {
        const timer = setTimeout(() => {
            flash.classList.add('fade');
            setTimeout(() => flash.remove(), 300);
        }, 5000);

        flash.querySelectorAll('[data-flash-dismiss]').forEach((btn) => {
            btn.addEventListener('click', () => {
                clearTimeout(timer);
                flash.classList.add('fade');
                setTimeout(() => flash.remove(), 150);
            });
        });
    });

    // -----------------------------------------
    // Password visibility toggles
    // -----------------------------------------
    doc.querySelectorAll('.password-toggle').forEach((toggleBtn) => {
        toggleBtn.addEventListener('click', () => {
            const targetId = toggleBtn.getAttribute('data-target');
            const input = doc.getElementById(targetId);
            if (!input) return;
            const isPassword = input.getAttribute('type') === 'password';
            input.setAttribute('type', isPassword ? 'text' : 'password');
            toggleBtn.classList.toggle('bx-show', !isPassword);
            toggleBtn.classList.toggle('bx-hide', isPassword);
        });
    });

    // -----------------------------------------
    // Appointment modal service controls
    // -----------------------------------------
    const editModal = doc.getElementById('editApptModal');
    const serviceContainer = doc.getElementById('modalServiceContainer');
    const addSvcBtn = doc.getElementById('modalAddServiceBtn');

    const availableServices = [
        'Coolant Fluid Service', 'Engine Oil & Filter Change', 'Brake Fluid Flush',
        'Transmission Fluid Service', 'Air Conditioning Re-gas', 'Engine Treatment/Tuning',
        'Battery Replacement', 'Brake Pad Replacement', 'Disc Brake Replacement',
        'Brake Shoe Replacement', 'Caliper Service', 'Wiper Blade Replacement',
        'Tyre Installation', 'Wheel Alignment', 'Wheel Balancing'
    ];

    const createServiceRow = (selectedValue = '') => {
        if (!serviceContainer) return;

        // Flex layout: select grows, button sits to the right (stack on small screens)
        const row = doc.createElement('div');
        row.className = 'modal-service-row';

        const select = doc.createElement('select');
        select.name = 'service_type[]';
        select.required = true;
        select.className = 'form-select';
        select.style.minWidth = '0';

        select.innerHTML = ['<option value="">Select Service...</option>', ...availableServices.map((svc) => {
            const selected = svc === selectedValue ? 'selected' : '';
            return `<option value="${svc}" ${selected}>${svc}</option>`;
        })].join('');

        const removeBtn = doc.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-danger';
        removeBtn.textContent = 'Remove';
        removeBtn.setAttribute('aria-label', 'Remove service');
        removeBtn.addEventListener('click', () => row.remove());

        row.appendChild(select);
        row.appendChild(removeBtn);

        serviceContainer.appendChild(row);
    };

    if (editModal && serviceContainer) {
        editModal.addEventListener('show.bs.modal', (event) => {
            const btn = event.relatedTarget;
            if (!btn) return;

            editModal.querySelector('#editApptId').value = btn.getAttribute('data-id') || '';
            editModal.querySelector('#editDate').value = btn.getAttribute('data-date') || '';
            editModal.querySelector('#editTime').value = btn.getAttribute('data-time') || '';
            editModal.querySelector('#editNotes').value = btn.getAttribute('data-notes') || '';

            serviceContainer.innerHTML = '';
            const rawServices = btn.getAttribute('data-services') || '';
            const services = rawServices.split('||').filter(Boolean);
            if (services.length === 0) {
                createServiceRow();
            } else {
                services.forEach((svc) => createServiceRow(svc.trim()));
            }
        });
    }

    addSvcBtn?.addEventListener('click', () => createServiceRow());

    // -----------------------------------------
    // Profile photo chooser + preview
    // -----------------------------------------
    const photoInput = doc.getElementById('profile_photo_input');
    const chosenFileName = doc.getElementById('chosenFileName');
    const uploadBtn = doc.getElementById('uploadPhotoBtn');
    const uploadForm = doc.getElementById('uploadPhotoForm');
    const photoPreview = doc.getElementById('photoPreview');
    const originalPreviewHTML = photoPreview ? photoPreview.innerHTML : '';
    let _previewURL = null;

    const resetPreview = () => {
        if (!photoPreview) return;
        if (_previewURL) { URL.revokeObjectURL(_previewURL); _previewURL = null; }
        photoPreview.innerHTML = originalPreviewHTML;
        if (chosenFileName) chosenFileName.textContent = 'No file chosen';
        if (uploadBtn) uploadBtn.disabled = true;
    };

    if (photoInput) {
        photoInput.addEventListener('change', () => {
            const file = photoInput.files && photoInput.files[0];
            if (!file) { resetPreview(); return; }
            if (chosenFileName) chosenFileName.textContent = file.name;
            if (uploadBtn) uploadBtn.disabled = false;

            if (_previewURL) URL.revokeObjectURL(_previewURL);
            _previewURL = URL.createObjectURL(file);

            if (photoPreview) {
                photoPreview.innerHTML = '';
                const img = doc.createElement('img');
                img.src = _previewURL;
                img.alt = 'Preview';
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '50%';
                img.style.border = '1px solid rgba(255,255,255,0.16)';
                photoPreview.appendChild(img);
            }
        });
    }

    if (uploadForm && uploadBtn) {
        uploadForm.addEventListener('submit', () => {
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'UPLOADING...';
        });
    }

    // -----------------------------------------
    // Cancellation with Reason Prompt
    // -----------------------------------------
    doc.addEventListener('click', (e) => {
        // Check if the clicked element is a submit button inside a .cancel-form
        if (e.target && e.target.matches('.cancel-form button[type="submit"]')) {
            e.preventDefault(); // Stop immediate submission
            
            const form = e.target.closest('form');
            const reason = prompt("Please provide a reason for cancellation (optional):");

            // If user clicked 'Cancel' in the prompt window, stop everything
            if (reason === null) return; 

            // Put the reason into the hidden input
            const reasonInput = form.querySelector('input[name="cancel_reason"]');
            if (reasonInput) {
                reasonInput.value = reason;
            }

            // Submit the form programmatically
            form.submit();
        }
    });

    /* =========================================
   Contact Details Edit Toggle
   ========================================= */
    const editContactBtn = doc.getElementById('editContactBtn');
    const cancelContactBtn = doc.getElementById('cancelContactBtn');
    const contactFormFields = doc.getElementById('contactFormFields');
    const contactFormActions = doc.getElementById('contactFormActions');
    const contactDetailsForm = doc.getElementById('contactDetailsForm');
    
    // Store original values for reset on cancel
    const originalValues = {};
    if (contactDetailsForm) {
        contactDetailsForm.querySelectorAll('input, select').forEach(input => {
            originalValues[input.name] = input.value;
        });
    }

    const setFormState = (isEditing) => {
        if (!contactFormFields || !contactFormActions || !editContactBtn) return;
        
        // Toggle the 'disabled' attribute on the fieldset
        contactFormFields.disabled = !isEditing;
        
        // Show/hide action buttons
        contactFormActions.classList.toggle('hidden', !isEditing);
        
        // Toggle the Edit button visibility/label
        editContactBtn.classList.toggle('hidden', isEditing);
    };

    const resetForm = () => {
        if (!contactDetailsForm) return;
        // Reset form fields to their original PHP-rendered values
        contactDetailsForm.querySelectorAll('input, select').forEach(input => {
            if (originalValues.hasOwnProperty(input.name)) {
                input.value = originalValues[input.name];
            }
        });
        setFormState(false);
    };

    // Initialize the form state to non-editing (already set in PHP/HTML, but safe to enforce)
    setFormState(false);

    // Event Listeners
    if (editContactBtn) {
        editContactBtn.addEventListener('click', () => setFormState(true));
    }

    if (cancelContactBtn) {
        cancelContactBtn.addEventListener('click', () => resetForm());
    }

    // After form submission (on page load if submission occurred), ensure it is reset
    // This is handled implicitly by the PHP re-rendering and the initial setFormState(false)
})();