<div
    x-data="{
        helpOpen: false,
        historyOpen: false,
        listening: false,
        recognition: null,
        base: '',
        toggleDictation() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

            if (! SpeechRecognition) {
                alert('Speech to text is not supported in this browser. Try Chrome or Edge.');
                return;
            }

            if (this.recognition) {
                this.recognition.stop();
                return;
            }

            const input = $refs.promptInput;
            this.base = input.value ? input.value.trim() + ' ' : '';

            const recognition = new SpeechRecognition();
            recognition.lang = 'en-GB';
            recognition.interimResults = true;
            recognition.continuous = true;

            recognition.onresult = (event) => {
                let interim = '';

                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const chunk = event.results[i][0].transcript;

                    if (event.results[i].isFinal) {
                        this.base += chunk.trim() + ' ';
                    } else {
                        interim += chunk;
                    }
                }

                input.value = (this.base + interim).trim();
                input.dispatchEvent(new Event('input', { bubbles: true }));
            };

            recognition.onend = () => {
                this.recognition = null;
                this.listening = false;
            };

            recognition.onerror = (event) => {
                this.recognition = null;
                this.listening = false;

                if (event.error === 'no-speech') {
                    alert('No speech was detected. Check your microphone is working and try again.');
                } else if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                    alert('Microphone access was blocked. Check your browser is allowed to use the microphone on this site and try again.');
                } else if (event.error !== 'aborted') {
                    alert('Speech recognition error: ' + event.error);
                }
            };

            this.recognition = recognition;
            this.listening = true;

            try {
                recognition.start();
            } catch (e) {
                this.recognition = null;
                this.listening = false;
                alert('Could not start speech recognition: ' + e.message);
            }
        },
    }"
    class="flex {{ $isPopup ? 'h-full w-full' : 'h-full max-h-[44rem] w-full max-w-2xl' }} flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl shadow-zinc-950/10 dark:border-white/10 dark:bg-zinc-900"
>
    <div class="flex shrink-0 items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-white/10">
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-600/10 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-400">
                <flux:icon.sparkles variant="micro" />
            </span>
            <div>
                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Ask Assistant') }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Ask about your bookings, clients, and candidates.') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            @if ($isPopup)
                <a
                    href="{{ route('ask-assistant') }}"
                    title="{{ __('Expand to full page') }}"
                    class="flex h-5 w-5 items-center justify-center rounded-full text-zinc-400 transition hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300"
                >
                    <span class="sr-only">{{ __('Expand to full page') }}</span>
                    <flux:icon.arrows-pointing-out variant="mini" />
                </a>
            @endif

            <button
                type="button"
                x-on:click="helpOpen = true"
                aria-label="{{ __('What can I ask?') }}"
                class="flex h-5 w-5 items-center justify-center rounded-full text-zinc-400 transition hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300"
            >
                <flux:icon.information-circle variant="mini" />
            </button>

            @if ($this->conversationHistory->isNotEmpty())
                <button
                    type="button"
                    x-on:click="historyOpen = true"
                    aria-label="{{ __('Earlier chats') }}"
                    title="{{ __('Earlier chats') }}"
                    class="flex h-5 w-5 items-center justify-center rounded-full text-zinc-400 transition hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300"
                >
                    <flux:icon.clock variant="mini" />
                </button>
            @endif

            <button
                type="button"
                wire:click="clearChat"
                @if (empty($messages)) disabled @endif
                class="text-xs font-medium text-zinc-400 transition hover:text-zinc-600 disabled:cursor-not-allowed disabled:opacity-40 dark:text-zinc-500 dark:hover:text-zinc-300"
            >
                {{ __('Clear chat') }}
            </button>
        </div>
    </div>

    {{--
        A plain Alpine overlay rather than <flux:modal> — Flux's modal needs
        its own JS runtime (@fluxScripts), which is loaded on this
        component's own full-page layout but not in the Filament admin
        panel this same component is also embedded in as the floating
        popup, so a Flux modal here would throw "fluxModal is not defined"
        there. This has no such dependency, so it works in both places.
    --}}
    <div
        x-show="helpOpen"
        x-on:keydown.escape.window="helpOpen = false"
        class="fixed inset-0 z-10 flex items-center justify-center bg-zinc-950/50 p-4"
        style="display: none"
    >
        <div
            x-show="helpOpen"
            x-on:click.outside="helpOpen = false"
            x-transition
            class="max-h-[80vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-lg ring ring-zinc-950/5 dark:bg-zinc-800 dark:ring-white/10"
        >
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('What can I ask?') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('A few example prompts for your active sector — mix and match filters in one sentence and the assistant will work out which of these to use.') }}
                    </flux:text>
                </div>

                <button
                    type="button"
                    x-on:click="helpOpen = false"
                    aria-label="{{ __('Close') }}"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-800/5 hover:text-zinc-800 dark:hover:bg-white/15 dark:hover:text-white"
                >
                    <flux:icon.x-mark variant="mini" />
                </button>
            </div>

            <div class="space-y-4">
                @foreach ($this->promptExamples() as $category => $examples)
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-zinc-400 uppercase dark:text-zinc-500">
                            {{ $category }}
                        </p>
                        <ul class="mt-1.5 space-y-1">
                            @foreach ($examples as $example)
                                <li class="text-sm text-zinc-700 dark:text-zinc-300">
                                    "{{ $example }}"
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- A plain Alpine overlay for the same reason as the help modal above. --}}
    @if ($this->conversationHistory->isNotEmpty())
        <div
            x-show="historyOpen"
            x-on:keydown.escape.window="historyOpen = false"
            class="fixed inset-0 z-10 flex items-center justify-center bg-zinc-950/50 p-4"
            style="display: none"
        >
            <div
                x-show="historyOpen"
                x-on:click.outside="historyOpen = false"
                x-transition
                class="max-h-[80vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-lg ring ring-zinc-950/5 dark:bg-zinc-800 dark:ring-white/10"
            >
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Earlier chats') }}</flux:heading>
                        <flux:text class="mt-1">
                            {{ __('Pick up any of your recent conversations for this sector.') }}
                        </flux:text>
                    </div>

                    <button
                        type="button"
                        x-on:click="historyOpen = false"
                        aria-label="{{ __('Close') }}"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-800/5 hover:text-zinc-800 dark:hover:bg-white/15 dark:hover:text-white"
                    >
                        <flux:icon.x-mark variant="mini" />
                    </button>
                </div>

                <ul class="space-y-1">
                    @foreach ($this->conversationHistory as $conversation)
                        <li>
                            <button
                                type="button"
                                wire:click="loadConversation({{ \Illuminate\Support\Js::from($conversation->id) }})"
                                x-on:click="historyOpen = false"
                                @class([
                                    'flex w-full items-baseline justify-between gap-4 rounded-lg px-3 py-2 text-left transition',
                                    'bg-emerald-50 dark:bg-emerald-400/10' => $conversation->id === $conversationId,
                                    'hover:bg-zinc-800/5 dark:hover:bg-white/5' => $conversation->id !== $conversationId,
                                ])
                            >
                                <span class="text-sm text-zinc-700 dark:text-zinc-200">{{ $conversation->title }}</span>
                                <span class="shrink-0 text-xs text-zinc-400 dark:text-zinc-500">
                                    {{ $conversation->updated_at->diffForHumans() }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div
        x-ref="scroller"
        x-on:message-added.window="$nextTick(() => $refs.scroller.scrollTop = $refs.scroller.scrollHeight)"
        class="flex flex-1 flex-col gap-4 overflow-y-auto px-5 py-6"
    >
        @forelse ($messages as $message)
            <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                @if ($message['role'] !== 'user')
                    <span class="mt-1 mr-2 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-600/10 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-400">
                        <flux:icon.sparkles variant="micro" />
                    </span>
                @endif

                <div
                    class="max-w-[75%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed {{ $message['role'] === 'user'
                        ? 'rounded-tr-sm bg-emerald-600 text-white whitespace-pre-line'
                        : 'rounded-tl-sm bg-zinc-100 text-zinc-900 dark:bg-white/5 dark:text-zinc-100 [&_a]:text-emerald-700 [&_a]:underline dark:[&_a]:text-emerald-400 [&_ul]:list-disc [&_ul]:pl-4 [&_li]:mb-1 last:[&_li]:mb-0 [&_p]:mb-2 last:[&_p]:mb-0' }}"
                >@if ($message['role'] === 'user'){{ $message['content'] }}@else{!! \Illuminate\Support\Str::markdown($message['content'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}@endif</div>
            </div>
        @empty
            <div class="flex flex-1 flex-col items-center justify-center gap-5 py-8 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-600/10 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-400">
                    <flux:icon.sparkles variant="outline" class="h-6 w-6" />
                </span>

                <p class="max-w-xs text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Ask about your bookings, clients, or candidates — or try one of these:') }}
                </p>

                <div class="flex flex-wrap justify-center gap-2">
                    @foreach ($this->suggestedPrompts() as $suggestion)
                        <button
                            type="button"
                            wire:click="useSuggestion({{ \Illuminate\Support\Js::from($suggestion) }})"
                            class="rounded-full border border-zinc-200 px-3 py-1.5 text-xs text-zinc-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-white/5"
                        >
                            {{ $suggestion }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endforelse

        @if ($moreResultsAvailable)
            <div class="flex justify-start pl-8" wire:loading.remove wire:target="respond">
                <button
                    type="button"
                    x-on:click="$wire.showMore().then(() => $wire.respond())"
                    class="rounded-full border border-zinc-200 px-3 py-1.5 text-xs text-zinc-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-white/5"
                >
                    {{ __('Show me more') }}
                </button>
            </div>
        @endif

        <div wire:loading wire:target="respond" class="flex justify-start">
            <span class="mt-1 mr-2 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-600/10 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-400">
                <flux:icon.sparkles variant="micro" />
            </span>
            <div class="flex items-center gap-1 rounded-2xl rounded-tl-sm bg-zinc-100 px-4 py-3 dark:bg-white/5">
                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-zinc-400 [animation-delay:-0.3s]"></span>
                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-zinc-400 [animation-delay:-0.15s]"></span>
                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-zinc-400"></span>
            </div>
        </div>
    </div>

    <form
        x-on:submit.prevent="recognition && recognition.stop(); $wire.send().then(() => $wire.respond())"
        class="flex shrink-0 gap-2 border-t border-zinc-200 p-4 dark:border-white/10"
    >
        <div class="relative flex-1">
            <input
                type="text"
                wire:model="prompt"
                x-ref="promptInput"
                placeholder="{{ __('Ask a question…') }}"
                autocomplete="off"
                class="w-full rounded-full border-zinc-300 py-2 pr-10 pl-4 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-100"
            />

            <button
                type="button"
                x-on:click="toggleDictation"
                :class="listening ? 'text-red-500 animate-pulse' : 'text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300'"
                class="absolute top-1/2 right-2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full transition"
                aria-label="{{ __('Dictate using speech to text') }}"
                title="{{ __('Dictate using speech to text (click again to stop)') }}"
            >
                <flux:icon.microphone variant="micro" />
            </button>
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="send, respond"
            class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-emerald-500 disabled:opacity-60"
        >
            {{ __('Send') }}
        </button>
    </form>
</div>
