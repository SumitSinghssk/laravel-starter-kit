<div
    x-data="flashMessages()"
    x-init="init()"
    class="admin-theme pointer-events-none fixed right-4 bottom-4 z-100 flex w-[calc(100%-2rem)] max-w-96 flex-col-reverse gap-2.5 sm:right-6 sm:bottom-6"
    aria-live="polite"
    aria-label="Notifications"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="toast.visible"
            x-transition:enter="transition duration-300 ease-out"
            x-transition:enter-start="translate-y-3 scale-[0.98] opacity-0"
            x-transition:enter-end="translate-y-0 scale-100 opacity-100"
            x-transition:leave="transition duration-200 ease-in"
            x-transition:leave-start="translate-x-0 opacity-100"
            x-transition:leave-end="translate-x-6 opacity-0"
            class="group pointer-events-auto relative flex items-start gap-3 overflow-hidden rounded-xl border border-slate-200 bg-white p-3.5 shadow-lg dark:border-slate-700 dark:bg-slate-900"
        >
            <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                :class="{
                    'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400': toast.type === 'success',
                    'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-400': toast.type === 'error',
                    'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400': toast.type === 'warning',
                    'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400': toast.type === 'info',
                }"
            >
                <x-admin.icon name="check-circle" x-show="toast.type === 'success'" class="h-4.5 w-4.5" />
                <x-admin.icon name="alert-circle" x-show="toast.type === 'error'" class="h-4.5 w-4.5" />
                <x-admin.icon name="alert-triangle" x-show="toast.type === 'warning'" class="h-4.5 w-4.5" />
                <x-admin.icon name="info" x-show="toast.type === 'info'" class="h-4.5 w-4.5" />
            </div>

            <div class="min-w-0 flex-1 pt-0.5">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white" x-text="toast.title"></h3>
                <p class="mt-0.5 text-sm leading-relaxed text-slate-600 dark:text-slate-400" x-text="toast.message"></p>

                <template x-if="toast.errors && toast.errors.length">
                    <ul class="mt-2 space-y-1 border-t border-slate-100 pt-2 dark:border-slate-800">
                        <template x-for="err in toast.errors" :key="err">
                            <li class="flex items-start gap-2 text-xs text-red-600 dark:text-red-400">
                                <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-red-500"></span>
                                <span x-text="err"></span>
                            </li>
                        </template>
                    </ul>
                </template>
            </div>

            <button
                x-on:click="remove(toast.id)"
                type="button"
                aria-label="Dismiss notification"
                class="shrink-0 cursor-pointer rounded-md p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
            >
                <x-admin.icon name="x" class="h-4 w-4" />
            </button>

            <div class="absolute right-0 bottom-0 left-0 h-0.5 bg-slate-100 dark:bg-slate-800">
                <div
                    class="h-full transition-all ease-linear"
                    :class="{
                        'bg-emerald-500': toast.type === 'success',
                        'bg-red-500': toast.type === 'error',
                        'bg-amber-500': toast.type === 'warning',
                        'bg-blue-500': toast.type === 'info',
                    }"
                    :style="{ width: toast.progress + '%', transitionDuration: '100ms' }"
                ></div>
            </div>
        </div>
    </template>
</div>

@push('scripts')
    <script defer>
        function flashMessages() {
            return {
                toasts: [],
                duration: 5000,

                init() {
                    if (window.__toastInitialized) return;
                    window.__toastInitialized = true;

                    window.toast = (type, message, title = null, errors = []) => {
                        this.add(type, title ?? this.titleFor(type), message, errors);
                    };

                    @if(session('success')) this.add('success', 'Success', @json(session('success'))); @endif
                    @if(session('error'))   this.add('error', 'Error', @json(session('error'))); @endif
                    @if(session('warning')) this.add('warning', 'Warning', @json(session('warning'))); @endif
                    @if(session('info'))    this.add('info', 'Information', @json(session('info'))); @endif
                    @if($errors->any())     this.add('error', 'Validation Error', 'Please check the form for errors.', @json($errors->all())); @endif
                    window.addEventListener('flash', (e) => {
                        this.add(e.detail.type, e.detail.title ?? this.titleFor(e.detail.type), e.detail.message, e.detail.errors ?? []);
                    });
                },

                titleFor(type) {
                    return { success: 'Success', error: 'System Error', warning: 'Attention', info: 'Information' }[type] ?? 'Notice';
                },

                add(type, title, message, errors = []) {
                    const id = Date.now() + Math.random();
                    this.toasts.push({ id, type, title, message, errors, visible: true, progress: 100 });

                    this.$nextTick(() => {
                        const start = Date.now();
                        const interval = setInterval(() => {
                            const elapsed = Date.now() - start;
                            const toast = this.toasts.find(t => t.id === id);

                            if (!toast) {
                                clearInterval(interval);
                                return;
                            }

                            toast.progress = Math.max(0, 100 - (elapsed / this.duration) * 100);

                            if (elapsed >= this.duration) {
                                clearInterval(interval);
                                this.remove(id);
                            }
                        }, 50);
                    });
                },

                remove(id) {
                    const toast = this.toasts.find(t => t.id === id);
                    if (toast) {
                        toast.visible = false;
                        setTimeout(() => {
                            this.toasts = this.toasts.filter(t => t.id !== id);
                        }, 300);
                    }
                }
            }
        }
    </script>
@endpush
