/* =========================================
   Admin Settings - Logic Script
   ========================================= */

document.addEventListener('DOMContentLoaded', function() {
    
    // ------------------------------------------------------
    // 1. Password Visibility Toggle
    // ------------------------------------------------------
    const toggles = document.querySelectorAll('.toggle-password');

    toggles.forEach(icon => {
        icon.addEventListener('click', function() {
            // Get the ID of the input this icon controls
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);

            if (input) {
                // Toggle the type attribute
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);

                // Toggle the icon class (Eye open / Eye slashed)
                this.classList.toggle('bx-show');
                this.classList.toggle('bx-hide');
            }
        });
    });

    // ------------------------------------------------------
    // 2. Alert Box Close Logic
    // ------------------------------------------------------
    const alertCloseBtns = document.querySelectorAll('.alert-close');
    
    alertCloseBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const alertBox = this.closest('.alert');
            if (alertBox) {
                // Add fade-out styles for a smooth exit
                alertBox.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                alertBox.style.opacity = '0';
                alertBox.style.transform = 'translateY(-10px)';
                
                // Remove the element from the DOM after the animation finishes
                setTimeout(() => {
                    alertBox.remove();
                }, 300);
            }
        });
    });

});