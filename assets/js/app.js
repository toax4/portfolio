// Custom Cursor Logic
const cursor = document.getElementById('cursor');
const hoverElements = document.querySelectorAll('.cursor-hover, a, button, input, textarea');

document.addEventListener('mousemove', (e) => {
    cursor.style.left = e.clientX + 'px';
    cursor.style.top = e.clientY + 'px';
    cursor.style.transform = `translate(-50%, -50%)`;
});

// Add hover effect to cursor
hoverElements.forEach(el => {
    el.addEventListener('mouseenter', () => {
        // cursor.style.width = '60px';
        // cursor.style.height = '60px';
        cursor.style.backgroundColor = '#FBFF48'; // Neo Yellow
        cursor.style.mixBlendMode = 'normal';
        cursor.style.border = '2px solid black';
    });
    el.addEventListener('mouseleave', () => {
        cursor.style.width = '24px';
        cursor.style.height = '24px';
        cursor.style.backgroundColor = '#fff';
        cursor.style.mixBlendMode = 'difference';
        cursor.style.border = 'none';
    });
});

// Scroll Reveal Logic
const revealElements = document.querySelectorAll('.reveal');
const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('active');
        }
    });
}, { threshold: 0.1 });

revealElements.forEach(el => revealObserver.observe(el));

// Scroll Progress Bar
window.onscroll = function () {
    let winScroll = document.body.scrollTop || document.documentElement.scrollTop;
    let height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    let scrolled = (winScroll / height) * 100;
    document.getElementById("progressBar").style.width = scrolled + "%";
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.markdown-content h2').forEach(h2 => {
        h2.classList.add('text-2xl', 'md\:text-3xl', 'font-black', 'uppercase', 'mt-3');
    });
    document.querySelectorAll('.markdown-content h3').forEach(h3 => {
        h3.classList.add('text-xl', 'md\:text-2xl', 'font-black', 'uppercase', 'mt-3');
    });
    document.querySelectorAll('.markdown-content ul').forEach(ul => {
        ul.classList.add('list-disc', 'list-inside');
    });
    // ton code ici
});
