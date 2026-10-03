import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// C17 slice R2: the ID-card reader loads only on a form that offers it.
if (document.querySelector('[data-id-scan]')) {
    import('./id-scan/scan.js').then((module) => module.start());
}
