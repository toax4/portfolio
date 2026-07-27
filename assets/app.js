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
