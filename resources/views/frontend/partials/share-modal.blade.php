{{--
    Reusable product share modal.
    Expects: $shareUrl (string, absolute), $shareTitle (string, product name).
    Optional: $modalId (suffix when more than one modal on a page, default 'product').
--}}
@php
    $modalId = $modalId ?? 'product';
    $shareUrl = $shareUrl ?? url()->current();
    $shareTitle = $shareTitle ?? '';
@endphp
<div class="modal fade" id="shareModal-{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-share me-2"></i>Share Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3 text-truncate share-link-preview">{{ $shareUrl }}</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="#" class="btn btn-outline-success share-btn"
                        data-network="whatsapp"
                        data-url="{{ rawurlencode($shareUrl) }}"
                        data-title="{{ rawurlencode($shareTitle) }}"
                        title="Share on WhatsApp">
                        <i class="bi bi-whatsapp"></i> WhatsApp
                    </a>
                    <a href="#" class="btn btn-outline-primary share-btn"
                        data-network="facebook"
                        data-url="{{ rawurlencode($shareUrl) }}"
                        title="Share on Facebook">
                        <i class="bi bi-facebook"></i> Facebook
                    </a>
                    <a href="#" class="btn btn-outline-dark share-btn"
                        data-network="twitter"
                        data-url="{{ rawurlencode($shareUrl) }}"
                        data-title="{{ rawurlencode($shareTitle) }}"
                        title="Share on X">
                        <i class="bi bi-twitter-x"></i> X
                    </a>
                    <a href="#" class="btn btn-outline-info share-btn"
                        data-network="telegram"
                        data-url="{{ rawurlencode($shareUrl) }}"
                        data-title="{{ rawurlencode($shareTitle) }}"
                        title="Share on Telegram">
                        <i class="bi bi-telegram"></i> Telegram
                    </a>
                    <a href="mailto:?subject={{ rawurlencode($shareTitle) }}&body={{ rawurlencode($shareUrl) }}"
                        class="btn btn-outline-secondary share-btn share-btn-mail"
                        title="Share via Email">
                        <i class="bi bi-envelope"></i> Email
                    </a>
                    <button type="button" class="btn btn-outline-dark share-copy-btn">
                        <i class="bi bi-link-45deg"></i> Copy Link
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
