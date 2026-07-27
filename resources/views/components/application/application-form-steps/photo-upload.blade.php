<x-auth-header
    :title="__('Add Your Photo')"
    :description="__('Upload a photo or take one now using your camera.')"
/>

<div
    class="mt-6 flex flex-col gap-6"
    x-data="{
        mode: @js($this->existingPhotoUrl ? 'existing' : 'choose'),
        stream: null,
        cameraError: null,
        capturing: false,
        zoom: 1,

        cropAndContinue() {
            if (this.zoom === 1) {
                $wire.savePhoto();
                return;
            }

            try {
                const img = this.$refs.previewImg;
                const size = 400;
                const canvas = document.createElement('canvas');
                canvas.width = size;
                canvas.height = size;

                const coverScale = Math.max(size / img.naturalWidth, size / img.naturalHeight);
                const scale = coverScale * this.zoom;
                const drawWidth = img.naturalWidth * scale;
                const drawHeight = img.naturalHeight * scale;

                canvas.getContext('2d').drawImage(
                    img,
                    (size - drawWidth) / 2,
                    (size - drawHeight) / 2,
                    drawWidth,
                    drawHeight,
                );

                canvas.toBlob((blob) => {
                    if (! blob) {
                        $wire.savePhoto();
                        return;
                    }

                    const file = new File([blob], `photo-${Date.now()}.jpg`, { type: 'image/jpeg' });

                    this.capturing = true;

                    $wire.upload('photo', file, () => {
                        this.capturing = false;
                        this.zoom = 1;
                        $wire.savePhoto();
                    }, () => {
                        this.capturing = false;
                        this.cameraError = @js(__('Photo upload failed. Please try again.'));
                    });
                }, 'image/jpeg', 0.92);
            } catch (e) {
                // The preview image failed to export (e.g. a cross-origin storage
                // URL without CORS headers tainting the canvas) — fall back to
                // saving the photo as originally uploaded, unzoomed.
                $wire.savePhoto();
            }
        },

        async startCamera() {
            this.cameraError = null;

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                this.$refs.video.srcObject = this.stream;
                this.mode = 'camera';
            } catch (e) {
                this.cameraError = @js(__('Unable to access your camera. Please check permissions or upload a photo instead.'));
            }
        },

        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }
        },

        capture() {
            const video = this.$refs.video;
            const canvas = this.$refs.canvas;

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            canvas.toBlob((blob) => {
                const file = new File([blob], `photo-${Date.now()}.jpg`, { type: 'image/jpeg' });

                this.capturing = true;

                $wire.upload('photo', file, () => {
                    this.capturing = false;
                    this.zoom = 1;
                    this.stopCamera();
                    this.mode = 'choose';
                }, () => {
                    this.capturing = false;
                    this.cameraError = @js(__('Photo upload failed. Please try again.'));
                });
            }, 'image/jpeg', 0.92);
        },

        cancelCamera() {
            this.stopCamera();
            this.mode = 'choose';
        },

        destroy() {
            this.stopCamera();
        },
    }"
>
    @if ($photo)
        <div class="flex flex-col items-center gap-4">
            @if ($photo->isPreviewable())
                <div class="size-40 overflow-hidden rounded-full ring-4 ring-zinc-100 dark:ring-zinc-800">
                    <img
                        x-ref="previewImg"
                        src="{{ $photo->temporaryUrl() }}"
                        crossorigin="anonymous"
                        alt="{{ __('Candidate photo preview') }}"
                        class="size-full object-cover"
                        x-bind:style="'transform: scale(' + zoom + ')'"
                    />
                </div>

                <div class="flex w-full max-w-xs items-center gap-3">
                    <flux:icon.magnifying-glass-minus class="size-4 shrink-0 text-zinc-400" />
                    <input
                        type="range"
                        min="1"
                        max="3"
                        step="0.05"
                        x-model.number="zoom"
                        class="w-full accent-zinc-700 dark:accent-zinc-300"
                    />
                    <flux:icon.magnifying-glass-plus class="size-4 shrink-0 text-zinc-400" />
                </div>
            @else
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $photo->getClientOriginalName() }}</p>
            @endif

            @error('photo')
                <flux:error>{{ $message }}</flux:error>
            @enderror

            <flux:button type="button" variant="ghost" wire:click="$set('photo', null)" @click="zoom = 1">
                {{ __('Remove photo') }}
            </flux:button>
        </div>
    @else
        <div x-show="mode === 'existing'" class="flex flex-col items-center gap-4">
            <img src="{{ $this->existingPhotoUrl }}" alt="{{ __('Candidate photo') }}" class="size-40 rounded-full object-cover ring-4 ring-zinc-100 dark:ring-zinc-800" />

            <flux:button type="button" variant="ghost" @click="mode = 'choose'">
                {{ __('Replace photo') }}
            </flux:button>
        </div>

        <div x-show="mode === 'choose'">
            <div class="flex flex-col gap-4 sm:flex-row">
                <div class="relative flex flex-1 items-center justify-center rounded-lg border-2 border-dashed border-zinc-300 bg-zinc-50 px-6 py-10 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="text-center">
                        <svg class="mx-auto size-10 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                        </svg>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Click to select or drag and drop') }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ __('JPG or PNG up to 5MB') }}</p>
                        <input
                            id="photo"
                            type="file"
                            wire:model="photo"
                            accept="image/*"
                            class="absolute inset-0 cursor-pointer opacity-0"
                            @change="zoom = 1"
                        />
                    </div>
                </div>

                <button
                    type="button"
                    @click="startCamera()"
                    class="flex flex-1 flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-zinc-300 bg-zinc-50 px-6 py-10 text-center hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600"
                >
                    <svg class="mx-auto size-10 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.132.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.803-2.169a47.865 47.865 0 0 0-1.132-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.822 1.316Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                    </svg>
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Take a photo') }}</span>
                </button>
            </div>

            @error('photo')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <template x-if="cameraError">
                <p class="mt-2 text-sm text-red-600" x-text="cameraError"></p>
            </template>
        </div>

        <div x-show="mode === 'camera'" class="flex flex-col items-center gap-4">
            <video x-ref="video" autoplay playsinline muted class="w-full max-w-sm scale-x-[-1] rounded-lg bg-black"></video>
            <canvas x-ref="canvas" class="hidden"></canvas>

            <div class="flex w-full max-w-sm gap-3">
                <flux:button type="button" variant="ghost" class="flex-1" @click="cancelCamera()">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button type="button" variant="primary" class="flex-1" @click="capture()" x-bind:disabled="capturing">
                    <span x-show="!capturing">{{ __('Capture') }}</span>
                    <span x-show="capturing">{{ __('Uploading…') }}</span>
                </flux:button>
            </div>
        </div>
    @endif

    <flux:button
        type="button"
        variant="primary"
        class="w-full"
        @click="cropAndContinue()"
        wire:loading.attr="disabled"
        wire:target="savePhoto"
        x-bind:disabled="capturing || {{ (! $photo && ! $this->existingPhotoUrl) ? 'true' : 'false' }}"
    >
        <span x-show="!capturing">{{ __('Next') }}</span>
        <span x-show="capturing">{{ __('Uploading…') }}</span>
    </flux:button>
</div>
