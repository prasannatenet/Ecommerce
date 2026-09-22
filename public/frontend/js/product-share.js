/* =====================================================================
 * PRODUCT SHARE — global product sharing behaviour
 *
 * Any element with class "product-share-btn" is a share trigger:
 *   data-share-url   (optional) URL to share, defaults to current page
 *   data-share-title (optional) text/title to share
 *
 * On mobile devices with the Web Share API the native OS share sheet
 * opens so the user can share directly to any installed app or contact.
 * Otherwise the reusable share modal (#shareModal-product) is shown and
 * synced with the clicked product's URL/title.
 *
 * The modal partial is: resources/views/frontend/partials/share-modal.blade.php
 * ===================================================================== */
(function () {
    'use strict';

    var MODAL_ID = 'shareModal-product';

    function getShareModal() {
        return document.getElementById(MODAL_ID);
    }

    function openShareModal() {
        var modal = getShareModal();
        if (!modal || !window.bootstrap) return;
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    /* Sync the reusable modal with the product that was clicked
     * (listing pages share many products with one modal). */
    function syncShareModal(url, title) {
        var modal = getShareModal();
        if (!modal) return;

        var preview = modal.querySelector('.share-link-preview');
        if (preview) preview.textContent = url;

        var encUrl = encodeURIComponent(url);
        var encTitle = encodeURIComponent(title);

        modal.querySelectorAll('.share-btn[data-network]').forEach(function (link) {
            link.dataset.url = encUrl;
            if (link.dataset.title !== undefined) link.dataset.title = encTitle;
        });

        var mail = modal.querySelector('.share-btn-mail');
        if (mail) {
            mail.href = 'mailto:?subject=' + encTitle + '&body=' + encUrl;
        }
    }

    /* Build per-network share URLs. Inputs are already URL-encoded. */
    function buildShareUrl(network, url, text) {
        switch (network) {
            case 'whatsapp':  return 'https://wa.me/?text=' + text + '%20' + url;
            case 'facebook':  return 'https://www.facebook.com/sharer/sharer.php?u=' + url;
            case 'twitter':   return 'https://twitter.com/intent/tweet?text=' + text + '&url=' + url;
            case 'telegram':  return 'https://t.me/share/url?url=' + url + '&text=' + text;
            default:          return '';
        }
    }

    /* --- Share trigger buttons (detail page + product cards) ------------- */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.product-share-btn');
        if (!btn) return;

        var url = btn.dataset.shareUrl || window.location.href;
        var title = btn.dataset.shareTitle || document.title;

        if (navigator.share) {
            navigator.share({ title: title, text: title, url: url }).catch(function (err) {
                // User cancelled (AbortError) — do nothing. Other failures
                // fall back to the in-page share modal.
                if (!err || err.name !== 'AbortError') {
                    syncShareModal(url, title);
                    openShareModal();
                }
            });
        } else {
            syncShareModal(url, title);
            openShareModal();
        }
    });

    /* --- Social network buttons inside the share modal ------------------- */
    document.addEventListener('click', function (e) {
        var link = e.target.closest('.share-btn[data-network]');
        if (!link) return;

        e.preventDefault();
        var shareUrl = buildShareUrl(link.dataset.network, link.dataset.url || '', link.dataset.title || '');
        if (!shareUrl) return;

        window.open(shareUrl, '_blank', 'noopener,width=600,height=520');
    });

    /* --- Copy link button with visual feedback --------------------------- */
    document.addEventListener('click', function (e) {
        var copyBtn = e.target.closest('.share-copy-btn');
        if (!copyBtn) return;

        var modal = copyBtn.closest('.modal');
        var preview = modal ? modal.querySelector('.share-link-preview') : null;
        var url = (preview && preview.textContent.trim()) || window.location.href;
        var originalHtml = copyBtn.innerHTML;

        function markCopied(ok) {
            copyBtn.innerHTML = ok
                ? '<i class="bi bi-check-lg"></i> Copied!'
                : '<i class="bi bi-x-lg"></i> Copy failed';
            setTimeout(function () { copyBtn.innerHTML = originalHtml; }, 2000);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url)
                .then(function () { markCopied(true); })
                .catch(function () { markCopied(false); });
        } else {
            // Legacy fallback for non-secure contexts
            var ta = document.createElement('textarea');
            ta.value = url;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
            document.body.removeChild(ta);
            markCopied(ok);
        }
    });
})();
