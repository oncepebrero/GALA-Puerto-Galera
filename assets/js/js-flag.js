document.documentElement.className = 'js';
try { if (window.sessionStorage.getItem('gala-wipe')) document.documentElement.classList.add('wipe-pending'); } catch (e) {}
