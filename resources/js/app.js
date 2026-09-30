import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';
import * as lucide from 'lucide';

import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';

import '@fontsource/playfair-display/500.css';
import '@fontsource/playfair-display/600.css';
import '@fontsource/playfair-display/700.css';

window.Alpine = Alpine;
window.lucide = {
    // Wrap it so the icons object is always passed correctly,
    // matching exactly what your one-time initial render does.
    createIcons: () => createIcons({ icons }),
};

Alpine.start();

// Initial render of all server-rendered icons
window.lucide.createIcons();