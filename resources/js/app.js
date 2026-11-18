import './bootstrap';
import Alpine from 'alpinejs';
import { aikcholPdfViewer } from './pdf-viewer';

window.Alpine = Alpine;
window.aikcholPdfViewer = aikcholPdfViewer;

Alpine.data('aikcholPdfViewer', aikcholPdfViewer);
Alpine.start();
