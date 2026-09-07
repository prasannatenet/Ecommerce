<p style="color:var(--df-text-secondary); font-size:0.88rem; margin-bottom:20px; max-width:800px;">
    Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.
</p>

<!-- Trigger Button -->
<button type="button" class="df-btn df-btn-sm" style="background:#ef4444; color:#fff;" data-bs-toggle="modal" data-bs-target="#confirmUserDeletionModal">
    Delete Account
</button>

<!-- Modal -->
<div class="modal fade" id="confirmUserDeletionModal" tabindex="-1" aria-labelledby="confirmUserDeletionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="{{ route('profile.destroy') }}" class="modal-content" style="border-radius:12px; border:none; box-shadow:var(--df-shadow-lg);">
            @csrf
            @method('delete')

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="confirmUserDeletionModalLabel" style="font-weight:700;">Are you sure you want to delete your account?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-3">
                <p style="font-size:0.9rem; color:var(--df-text-secondary); margin-bottom:16px;">
                    Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.
                </p>

                <div>
                    <label for="password" class="df-form-label sr-only">Password</label>
                    <input id="password" name="password" type="password" class="df-form-control {{ $errors->userDeletion->has('password') ? 'is-invalid' : '' }}" placeholder="Password">

                    @if($errors->userDeletion->has('password'))
                        <div class="text-danger mt-1" style="font-size:0.85rem;">{{ $errors->userDeletion->first('password') }}</div>
                    @endif
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="df-btn df-btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="df-btn" style="background:#ef4444; color:#fff;">Delete Account</button>
            </div>
        </form>
    </div>
</div>

@if($errors->userDeletion->isNotEmpty())
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('confirmUserDeletionModal'));
            myModal.show();
        });
    </script>@endif
