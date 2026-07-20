@if (session()->has('impersonator_id'))
    <div class="flex items-center justify-center gap-1.5 bg-amber-400/60 px-4 py-2 text-sm font-medium text-amber-950">
        <span>
            Viewing as {{ auth()->user()->name }}
            ({{ auth()->user()->hasRole('admin') ? 'Admin' : 'Consultant' }})
            at {{ auth()->user()->company?->name }}
        </span>

        <form method="POST" action="{{ route('impersonate.stop') }}">
            @csrf

            <button type="submit" class="underline hover:no-underline">
                Exit
            </button>
        </form>
    </div>
@endif
