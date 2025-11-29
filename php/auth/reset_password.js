document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('resetForm');
    const statusEl = document.getElementById('status');
    const togglePw = document.getElementById('togglePw');
    const newPw = document.getElementById('new_password');
    const confirmPw = document.getElementById('confirm_password');

    if (!form) return;

    // 1. Show/Hide Password Logic
    if (togglePw && newPw && confirmPw) {
        togglePw.addEventListener('change', (e) => {
            const type = e.target.checked ? 'text' : 'password';
            newPw.type = type;
            confirmPw.type = type;
        });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // 2. Client-Side Validation (Instant Feedback)
        if (newPw.value.length < 8) {
            statusEl.textContent = 'Password must be at least 8 characters long.';
            statusEl.className = 'error';
            return;
        }

        if (newPw.value !== confirmPw.value) {
            statusEl.textContent = 'Passwords do not match.';
            statusEl.className = 'error';
            return;
        }

        // UI Feedback
        const submitBtn = form.querySelector('button');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Updating...';
        
        statusEl.textContent = '';
        statusEl.className = '';

        const formData = new FormData(form);
        const loginUrl = form.getAttribute('data-login-url');

        try {
            const res = await fetch('', { 
                method: 'POST', 
                body: formData 
            });
            
            const data = await res.json();

            statusEl.textContent = data.message;
            statusEl.className = data.success ? 'success' : 'error';

            if (data.success) {
                setTimeout(() => { 
                    window.location.href = loginUrl; 
                }, 2000);
            } else {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }

        } catch (err) {
            console.error(err);
            statusEl.textContent = 'Network error. Please try again.';
            statusEl.className = 'error';
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
});