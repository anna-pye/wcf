/**
 * @file
 * Stallion gallery + video lightbox slideshow (GLightbox).
 */
(function (Drupal, once) {
  'use strict';

  /** @type {Set<string>} */
  const initializedGroups = new Set();

  /**
   * Initialises GLightbox for one media group on a stallion page.
   *
   * @param {string} group
   *   GLightbox data-gallery group id.
   */
  function initMediaLightbox(group) {
    if (typeof GLightbox !== 'function' || initializedGroups.has(group)) {
      return;
    }

    initializedGroups.add(group);

    GLightbox({
      selector: `.glightbox[data-gallery="${group}"]`,
      touchNavigation: true,
      loop: true,
      closeOnOutsideClick: true,
      keyboardNavigation: true,
      zoomable: true,
      draggable: true,
      autoplayVideos: true,
    });
  }

  Drupal.behaviors.wcfStallionGalleryLightbox = {
    attach(context) {
      once('wcf-stallion-media-lightbox', '[data-gallery-group]', context).forEach((root) => {
        const group = root.getAttribute('data-gallery-group');
        if (group) {
          initMediaLightbox(group);
        }
      });
    },
  };
})(Drupal, once);
