const featuredImageClass = 'image_featured';
const imageScalableClass = 'image-scalable';
const scaleImageClass = 'image-scale';
const pageHasLoaded = 'DOMContentLoaded';
const imageAltClass = 'img_alt';

const bodyEl = document.body;
const baseURL = bodyEl ? (bodyEl.dataset.baseurl || '/') : '/';

let themeURL = bodyEl ? (bodyEl.dataset.themeurl || '') : '';
if (themeURL !== '' && !themeURL.endsWith('/')) {
  themeURL += '/';
}

const iconsPath = 'icons/';
const goBackClass = 'button_back';
const lineClass = '.line';
