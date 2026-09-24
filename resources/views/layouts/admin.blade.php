@props(['breadcrumb' => [], 'title' => null])

@php
    $pageTitle =
        $title ?:
        (collect($breadcrumb)
            ->pluck('label')
            ->filter()
            ->reverse()
            ->implode(' · ') ?:
        (request()->is('admin/login')
            ? 'Login'
            : 'Dashboard'));
@endphp

<x-app>
    @push('heads')
        <meta name="robots" content="noindex, nofollow" />
        <title>{{ $pageTitle }} · {{ \App\Helpers\Settings::appName() }} Admin</title>
        <script>
            (() => {
                const root = document.documentElement;
                const theme = localStorage.getItem('admin-theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = theme ? theme === 'dark' : prefersDark;

                root.classList.toggle('dark', isDark);
                root.style.colorScheme = isDark ? 'dark' : 'light';

                if (localStorage.getItem('admin-sidebar') === 'collapsed') root.dataset.sidebar = 'collapsed';
            })();
        </script>
    @endpush

    @push('head-scripts')
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
        @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @endpush

    @if (request()->is('admin/login'))
        <div class="admin-theme min-h-screen bg-white text-slate-700 dark:bg-slate-950 dark:text-slate-300">
            {{ $slot }}
            <div id="admin-portal" class="relative z-[120]"></div>
        </div>
    @else
        <div
            x-data="{
                sidebarOpen: false,
                desktop: window.matchMedia('(min-width: 1024px)').matches,
                collapsed: document.documentElement.dataset.sidebar === 'collapsed',
                toggleCollapsed() {
                    this.collapsed = ! this.collapsed
                    document.documentElement.dataset.sidebar = this.collapsed
                        ? 'collapsed'
                        : ''
                    try {
                        localStorage.setItem(
                            'admin-sidebar',
                            this.collapsed ? 'collapsed' : 'expanded',
                        )
                    } catch (e) {}
                },
            }"
            x-on:resize.window="
                desktop = window.matchMedia('(min-width: 1024px)').matches
                if (desktop) sidebarOpen = false
            "
            x-on:keydown.escape.window="sidebarOpen = false"
            x-effect="
                document.documentElement.classList.toggle(
                    'overflow-hidden',
                    sidebarOpen && ! desktop,
                )
            "
            class="admin-theme relative min-h-screen overflow-hidden bg-slate-100/80 text-slate-700 dark:bg-black dark:text-slate-300"
        >
            <div
                x-show="sidebarOpen"
                x-cloak
                x-transition.opacity.duration.200ms
                x-on:click="sidebarOpen = false"
                class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-[2px] lg:hidden"
            ></div>

            @include('layouts.partials.admin.sidebar')

            <div class="lg:collapsed:pl-17 flex h-screen flex-col transition-[padding] duration-200 lg:pl-62">
                <div
                    class="flex h-screen flex-1 flex-col overflow-hidden bg-slate-50 lg:my-2 lg:h-[calc(100vh-1rem)] lg:rounded-2xl lg:border lg:border-slate-200/80 lg:shadow-xs dark:bg-slate-950 dark:lg:border-slate-800/80"
                >
                    <div class="shrink-0">
                        @include('layouts.partials.admin.header', ['breadcrumb' => $breadcrumb ?? []])
                    </div>

                    <main class="flex-1 overflow-x-hidden overflow-y-auto p-4">
                        {{ $slot }}
                    </main>

                    <footer
                        class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-slate-200/70 p-2 text-xs text-slate-400 dark:border-slate-800/70 dark:text-slate-500"
                    >
                        <span>© {{ date('Y') }} {{ \App\Helpers\Settings::appName() }}</span>
                        <span class="hidden items-center gap-1.5 sm:inline-flex">
                            Press
                            <kbd
                                class="rounded border border-slate-200 bg-white px-1 font-sans text-[10px] font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-900"
                            >
                                Ctrl K
                            </kbd>
                            to jump anywhere
                        </span>
                    </footer>
                </div>
            </div>

            @include('layouts.partials.admin.command-palette')

            <div id="admin-portal" class="relative z-120"></div>
        </div>

        @push('scripts')
            <script src="{{ asset('plugins/tinymce/tinymce.min.js') }}"></script>
            <style>
                .tox-promotion,
                .tox-statusbar__branding {
                    display: none;
                }

                .admin-theme .tox-tinymce {
                    border-radius: 10px;
                    border-color: var(--color-slate-200);
                }

                .dark .admin-theme .tox-tinymce {
                    border-color: var(--color-slate-700);
                }
            </style>
            <script defer>
                function initTinyMCE(isDark) {
                    let content = '';

                    if (tinymce.get()) {
                        const editor = tinymce.activeEditor;
                        if (editor) {
                            content = editor.getContent();
                        }
                        tinymce.remove();
                    }

                    tinymce.init({
                        license_key: 'gpl',
                        selector: 'textarea.tinymce',
                        height: 550,
                        convert_urls: false,

                        skin: isDark ? 'oxide-dark' : 'oxide',
                        content_css: isDark ? 'dark' : '{{ Vite::asset('resources/css/website.css') }}',

                        plugins: ['lists', 'code', 'image', 'media', 'link', 'table', 'accordion', 'charmap', 'emoticons', 'advlist'],

                        toolbar: 'undo redo | blocks | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist',

                        body_class: 'modern-content p-4',

                        setup: function (editor) {
                            editor.on('init', function () {
                                if (content) {
                                    editor.setContent(content);
                                }
                            });
                        },

                        images_upload_handler: function (blobInfo, progress) {
                            return new Promise((resolve, reject) => {
                                let xhr = new XMLHttpRequest();
                                xhr.open('POST', '{{ route('admin.images.store') }}');
                                xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');

                                xhr.upload.onprogress = function (e) {
                                    progress((e.loaded / e.total) * 100);
                                };

                                xhr.onload = function () {
                                    if (xhr.status === 403) {
                                        reject('CSRF token mismatch.');
                                        return;
                                    }

                                    if (xhr.status < 200 || xhr.status >= 300) {
                                        reject('HTTP Error: ' + xhr.status);
                                        return;
                                    }

                                    let json;
                                    try {
                                        json = JSON.parse(xhr.responseText);
                                    } catch (e) {
                                        reject('Invalid JSON: ' + xhr.responseText);
                                        return;
                                    }

                                    if (!json || typeof json.location !== 'string') {
                                        reject('Invalid response format');
                                        return;
                                    }

                                    resolve(json.location);
                                };

                                xhr.onerror = function () {
                                    reject('Image upload failed.');
                                };

                                let formData = new FormData();
                                formData.append('file', blobInfo.blob(), blobInfo.filename());

                                xhr.send(formData);
                            });
                        },
                    });
                }

                document.addEventListener('DOMContentLoaded', function () {
                    const isDark = document.documentElement.classList.contains('dark');

                    initTinyMCE(isDark);

                    window.addEventListener('admin-theme-changed', function (e) {
                        initTinyMCE(e.detail.isDark);
                    });
                });
            </script>
        @endpush

        <x-admin.toast />
    @endif
</x-app>
