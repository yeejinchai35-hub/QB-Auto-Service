// Services page specific JS (currently no extra behaviors).
// This file exists as a placeholder for future enhancements.
document.addEventListener('DOMContentLoaded', ()=>{
  // Reserved for Services-only interactions.

  // Make service cards keyboard-accessible if they act like links
  const cards = document.querySelectorAll('.service-card');
  cards.forEach(card => {
    // If the card contains a link, allow Enter key on the card to activate it
    const link = card.querySelector('a.card-link');
    if (link) {
      card.setAttribute('tabindex', '0');
      card.setAttribute('role', 'group');
      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          link.click();
        }
      });
    }
  });
});
