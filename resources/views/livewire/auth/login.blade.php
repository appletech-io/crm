@php
    use App\Models\Company;
    use App\Models\User;

    $isDemo = app()->environment(['demo', 'DEMO', 'Demo']);

    $roleLabel = fn (User $user): string => match (true) {
        $user->hasRole('admin') => 'Admin',
        $user->hasRole('consultant') => 'Consultant',
        $user->hasRole('resourcer') => 'Resourcer',
        $user->hasRole('compliance') => 'Compliance',
        $user->hasRole('candidate') => 'Candidate portal',
        $user->hasRole('client') => 'Client portal',
        default => 'User',
    };

    // Every user of every company, grouped for the quick-login picker below
    // — staff and portal accounts alike, site_admin excluded (it has its
    // own single quick-login link instead, since there's normally only one).
    $quickLoginCompanies = $isDemo
        ? Company::withoutGlobalScope('company')
            ->with(['users' => fn ($query) => $query->withoutGlobalScope('company')
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'site_admin'))
                ->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Company $company): bool => $company->users->isNotEmpty())
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'users' => $company->users->map(fn (User $user): array => [
                    'id' => $user->id,
                    'label' => "{$user->name} ({$roleLabel($user)})",
                ])->values()->all(),
            ])
            ->values()
        : collect();

    $siteAdmin = $isDemo
        ? User::withoutGlobalScope('company')->role('site_admin')->first()
        : null;
@endphp

<x-layouts::auth.card :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        @if ($isDemo)
            <div class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                {{ __('Demo credentials are pre-filled — just click Log in.') }}
            </div>

            @if ($quickLoginCompanies->isNotEmpty() || $siteAdmin)
                <div
                    x-data="{ companyId: '', userId: '', companies: @js($quickLoginCompanies) }"
                    class="flex flex-col gap-3 rounded-lg border border-gray-200 p-4 dark:border-white/10"
                >
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Quick login') }}</p>

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <select
                            x-model="companyId"
                            @change="userId = ''"
                            class="w-full rounded-md border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            <option value="">{{ __('Choose a company…') }}</option>
                            <template x-for="company in companies" x-bind:key="company.id">
                                <option x-bind:value="company.id" x-text="company.name"></option>
                            </template>
                        </select>

                        <select
                            x-model="userId"
                            x-bind:disabled="! companyId"
                            class="w-full rounded-md border-gray-300 text-sm disabled:opacity-50 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            <option value="">{{ __('Choose a user…') }}</option>
                            <template x-for="user in (companies.find((c) => c.id == companyId)?.users ?? [])" x-bind:key="user.id">
                                <option x-bind:value="user.id" x-text="user.label"></option>
                            </template>
                        </select>
                    </div>

                    <form method="POST" action="{{ route('demo-quick-login') }}">
                        @csrf
                        <input type="hidden" name="user_id" x-bind:value="userId">
                        <flux:button type="submit" variant="filled" class="w-full" x-bind:disabled="! userId">
                            {{ __('Log in as selected user') }}
                        </flux:button>
                    </form>

                    @if ($siteAdmin)
                        <form method="POST" action="{{ route('demo-quick-login') }}">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $siteAdmin->id }}">
                            <flux:button type="submit" variant="ghost" class="w-full">
                                {{ __('Log in as Site Admin') }}
                            </flux:button>
                        </form>
                    @endif
                </div>

                <div class="relative flex items-center py-1">
                    <div class="grow border-t border-gray-200 dark:border-white/10"></div>
                    <span class="mx-3 text-xs text-gray-400 dark:text-gray-500">{{ __('or log in manually') }}</span>
                    <div class="grow border-t border-gray-200 dark:border-white/10"></div>
                </div>
            @endif
        @endif

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- EmailTemplate Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email', $isDemo ? 'admin@applebough.test' : null)"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    :value="$isDemo ? 'password' : null"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth.card>
