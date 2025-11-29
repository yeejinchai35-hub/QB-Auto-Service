// Home page: Popular Services left/right controls
(function(){
  document.addEventListener('DOMContentLoaded', () => {
    const wrapper = document.querySelector('.popular-services-wrapper');
    const carousel = document.querySelector('.popular-services-carousel');
    if (!carousel || !wrapper) return;

    // Accessibility enhancements
    carousel.setAttribute('role', 'list');
    wrapper.setAttribute('role', 'region');
    wrapper.setAttribute('aria-label', 'Popular services carousel');
    // Make wrapper focusable for keyboard scrolling
    if (!wrapper.hasAttribute('tabindex')) wrapper.setAttribute('tabindex', '0');

    const prevBtn = wrapper.querySelector('.popular-prev');
    const nextBtn = wrapper.querySelector('.popular-next');
    if (prevBtn) prevBtn.setAttribute('aria-label', 'Previous services');
    if (nextBtn) nextBtn.setAttribute('aria-label', 'Next services');

    function getStep(){
      const card = carousel.querySelector('.popular-service-item');
      if (!card) return 300; // fallback
      const style = getComputedStyle(carousel);
      const gap = parseInt(style.gap) || 16;
      return card.offsetWidth + gap;
    }

    function scrollByDir(dir){
      carousel.scrollBy({ left: dir * getStep(), behavior: 'smooth' });
    }

    function updateArrows(){
      const maxScroll = carousel.scrollWidth - carousel.clientWidth - 1;
      if (prevBtn) prevBtn.disabled = carousel.scrollLeft <= 0;
      if (nextBtn) nextBtn.disabled = carousel.scrollLeft >= maxScroll;
    }

    prevBtn && prevBtn.addEventListener('click', () => { scrollByDir(-1); });
    nextBtn && nextBtn.addEventListener('click', () => { scrollByDir(1); });

    // Keyboard support (Arrow, Home, End, PageUp/PageDown)
    wrapper.addEventListener('keydown', (e) => {
      switch(e.key){
        case 'ArrowLeft': e.preventDefault(); scrollByDir(-1); break;
        case 'ArrowRight': e.preventDefault(); scrollByDir(1); break;
        case 'Home': e.preventDefault(); carousel.scrollTo({ left: 0, behavior: 'smooth' }); break;
        case 'End': e.preventDefault(); carousel.scrollTo({ left: carousel.scrollWidth, behavior: 'smooth' }); break;
        case 'PageUp': e.preventDefault(); scrollByDir(-2); break;
        case 'PageDown': e.preventDefault(); scrollByDir(2); break;
        default: break;
      }
    });

    carousel.addEventListener('scroll', updateArrows);
    window.addEventListener('resize', updateArrows);
    updateArrows();
  });
})();
