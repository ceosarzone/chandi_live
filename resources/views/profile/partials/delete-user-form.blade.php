<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Deactivate Account') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Deactivation disables sign-in and suspends your dealer profile. Operational, enquiry and billing history is retained. Contact the operator for a personal-data deletion request.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Deactivate Account') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-900">
                {{ __('Are you sure you want to deactivate your account?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('Enter your password to deactivate this account. Your dealer profile will be hidden and sign-in disabled. Billing and operational history is retained.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Deactivate Account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
