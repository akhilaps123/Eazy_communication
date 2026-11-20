/**
 * About Us page interactions
 * (moved unchanged from an inline <script> in the original about.html)
 */
document.addEventListener('DOMContentLoaded', () => {
  const slider = document.getElementById('aboutCardsSlider');
  const dots = document.querySelectorAll('#aboutSliderDots .about-dot');
  if (!slider || !dots.length) return;

  const cards = slider.querySelectorAll('.about-card');

  // Update active dot on scroll
  let isScrolling;
  slider.addEventListener('scroll', () => {
    window.clearTimeout(isScrolling);
    isScrolling = setTimeout(() => {
      const scrollLeft = slider.scrollLeft;
      const cardWidth = cards[0] ? cards[0].offsetWidth + 16 : 300;
      const activeIndex = Math.min(Math.round(scrollLeft / cardWidth), cards.length - 1);
      dots.forEach((dot, idx) => {
        dot.classList.toggle('active', idx === activeIndex);
      });
    }, 50);
  }, { passive: true });

  // Click dot to navigate
  dots.forEach((dot, idx) => {
    dot.addEventListener('click', () => {
      if (cards[idx]) {
        cards[idx].scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
      }
    });
  });

  // Mouse drag to scroll
  let isDown = false;
  let startX, scrollLeftStart;

  slider.addEventListener('mousedown', (e) => {
    isDown = true;
    slider.classList.add('grabbing');
    startX = e.pageX - slider.offsetLeft;
    scrollLeftStart = slider.scrollLeft;
  });

  slider.addEventListener('mouseleave', () => {
    isDown = false;
    slider.classList.remove('grabbing');
  });

  slider.addEventListener('mouseup', () => {
    isDown = false;
    slider.classList.remove('grabbing');
  });

  slider.addEventListener('mousemove', (e) => {
    if (!isDown) return;
    e.preventDefault();
    const x = e.pageX - slider.offsetLeft;
    const walk = (x - startX) * 1.5;
    slider.scrollLeft = scrollLeftStart - walk;
  });
});
