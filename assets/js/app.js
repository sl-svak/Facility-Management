document.addEventListener('DOMContentLoaded', function() {
    // 1. Ovládání bočního menu na mobilu
    const menuToggle = document.getElementById('menuToggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
    }
});

// 2. Registrace Service Workeru pro PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js').catch(err => console.log('SW chyba:', err));
    });
}

// 3. Globální haptická odezva a blokace tlačítek při odesílání formulářů
document.addEventListener('submit', function(e) {
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    
    if (submitBtn && !submitBtn.disabled) {
        if (navigator.vibrate) navigator.vibrate([100]);
        submitBtn.dataset.originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="material-symbols-outlined" style="vertical-align: middle; animation: spin 1s linear infinite;">sync</span> Ukládám...';
        submitBtn.classList.add('btn-disabled');
        setTimeout(() => submitBtn.disabled = true, 50);
    }
});
