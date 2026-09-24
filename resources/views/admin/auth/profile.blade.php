<x-admin :breadcrumb="[
    ['label' => 'Profile'],
    ]">
    @php
        $canUpdate = auth()
            ->user()
            ->can("profile.update");
        $canUpdatePassword = auth()
            ->user()
            ->can("profile.update-password");
        $me = auth()->user();
        $avatarUrl = $me->avatar ? asset("storage/" . $me->avatar) : null;
        $initials = collect(explode(" ", trim($me->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode("");

        $sections = [["id" => "profile", "label" => "Profile details", "icon" => "user", "text" => "Name, photo and bio"], ["id" => "social", "label" => "Social profiles", "icon" => "share", "text" => "Links shown on your author page"]];

        if ($canUpdatePassword) {
            $sections[] = ["id" => "password", "label" => "Password", "icon" => "lock", "text" => "Keep your account secure"];
        }

        $sections[] = ["id" => "two-factor", "label" => "Two-factor sign-in", "icon" => "shield-check", "text" => $me->hasTwoFactor() ? "On" : "Off: add a second step"];
        $sections[] = ["id" => "sessions", "label" => "Where you're signed in", "icon" => "monitor", "text" => "Devices and sign-outs"];
    @endphp

    <x-admin.page-header title="Account settings" description="Manage your profile, public links and sign-in password." icon="user-circle" />

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside class="space-y-4 lg:sticky lg:top-0">
            <div class="rounded-xl border border-slate-200/80 bg-white p-4 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center gap-3">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-50 text-sm font-semibold text-blue-700 ring-2 ring-white dark:bg-blue-500/10 dark:text-blue-300 dark:ring-slate-900"
                    >
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="" class="h-full w-full object-cover" />
                        @else
                            {{ $initials ?: "?" }}
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $me->name }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $me->email }}</p>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-1.5 border-t border-slate-100 pt-3 dark:border-slate-800">
                    @forelse ($me->getRoleNames() as $roleName)
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 capitalize dark:bg-blue-500/10 dark:text-blue-300"
                        >
                            <x-admin.icon name="shield" class="h-3 w-3" />
                            {{ $roleName }}
                        </span>
                    @empty
                        <span class="text-xs text-slate-400">No role assigned</span>
                    @endforelse
                </div>

                @if ($me->created_at)
                    <p class="mt-3 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <x-admin.icon name="calendar" class="h-3.5 w-3.5" />
                        Member since {{ $me->created_at->format("d M Y") }}
                    </p>
                @endif
            </div>

            <nav
                aria-label="Account sections"
                class="hidden flex-col gap-1 rounded-xl border border-slate-200/80 bg-white p-1.5 shadow-xs lg:flex dark:border-slate-800 dark:bg-slate-900"
            >
                @foreach ($sections as $section)
                    <a
                        href="#{{ $section["id"] }}"
                        class="group flex shrink-0 items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                    >
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 group-hover:text-blue-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:group-hover:text-blue-400"
                        >
                            <x-admin.icon :name="$section['icon']" class="h-3.5 w-3.5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate">{{ $section["label"] }}</span>
                            <span class="hidden truncate text-xs font-normal text-slate-400 lg:block">{{ $section["text"] }}</span>
                        </span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="min-w-0 space-y-6">
            <form
                @if ($canUpdate)
                    method="POST"
                    action="{{ route("admin.profile.update") }}"
                    x-data="{ submitting: false }"
                    x-on:submit="submitting = true"
                    enctype="multipart/form-data"
                @endif
                class="space-y-6"
            >
                @csrf
                @method("PATCH")

                <x-admin.card
                    id="profile"
                    title="Profile details"
                    text="How you appear across the admin and on your posts."
                    icon="user"
                    class="scroll-mt-24"
                >
                    @unless ($canUpdate)
                        <x-slot:extra>
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >
                                <x-admin.icon name="lock" class="h-3 w-3" />
                                Read-only
                            </span>
                        </x-slot>
                    @endunless

                    <div class="space-y-6">
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center"
                            x-data="{
                                preview:
                                    '{{ auth()->user()->avatar ? asset("storage/" . auth()->user()->avatar) : "" }}',
                                removeAvatar: false,
                                handleFileChange(event) {
                                    const file = event.target.files[0]
                                    if (file) {
                                        this.preview = URL.createObjectURL(file)
                                        this.removeAvatar = false
                                    }
                                },
                                clearPreview() {
                                    this.preview = ''
                                    this.removeAvatar = true
                                    this.$refs.file.value = ''
                                },
                            }"
                        >
                            <input type="file" name="avatar" accept="image/*" class="hidden" x-ref="file" x-on:change="handleFileChange" />

                            <input type="hidden" name="remove_avatar" :value="removeAvatar ? 1 : 0" />

                            <button
                                type="button"
                                x-on:click="$refs.file.click()"
                                aria-label="Change photo"
                                class="group relative flex h-20 w-20 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-full bg-slate-100 ring-4 ring-slate-50 transition focus:outline-none focus-visible:ring-blue-500/30 dark:bg-slate-800 dark:ring-slate-800/60"
                            >
                                <template x-if="preview">
                                    <img :src="preview" alt="" class="h-full w-full object-cover" />
                                </template>

                                <template x-if="!preview">
                                    <x-admin.icon name="user" class="h-8 w-8 text-slate-400" />
                                </template>

                                <span
                                    class="absolute inset-0 flex items-center justify-center bg-slate-900/45 text-white opacity-0 transition-opacity group-hover:opacity-100"
                                >
                                    <x-admin.icon name="camera" class="h-5 w-5" />
                                </span>
                            </button>

                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900 dark:text-white">Profile photo</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">A square JPG, PNG or WebP works best.</p>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <x-admin.button type="button" variant="secondary" size="sm" icon="upload" x-on:click="$refs.file.click()">
                                        Upload photo
                                    </x-admin.button>
                                    <template x-if="preview">
                                        <x-admin.button type="button" variant="ghost" size="sm" icon="trash" x-on:click="clearPreview()">
                                            Remove
                                        </x-admin.button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2 dark:border-slate-800">
                            <x-admin.form.input
                                name="name"
                                label="Full name"
                                :value="auth()->user()->name"
                                placeholder="Your Name"
                                :disabled="! $canUpdate"
                            >
                                <x-slot:leftIcon>
                                    <x-admin.icon name="user" class="h-4 w-4" />
                                </x-slot>
                            </x-admin.form.input>

                            <x-admin.form.input
                                type="email"
                                name="email"
                                label="Email address"
                                disabled
                                :value="auth()->user()->email"
                                hint="Ask another administrator to change your email address."
                            >
                                <x-slot:leftIcon>
                                    <x-admin.icon name="mail" class="h-4 w-4" />
                                </x-slot>
                            </x-admin.form.input>
                        </div>

                        <x-admin.form.textarea
                            name="bio"
                            label="Bio"
                            rows="3"
                            :value="auth()->user()->bio"
                            placeholder="Write something about yourself..."
                            :disabled="! $canUpdate"
                        />
                    </div>
                </x-admin.card>

                <x-admin.card
                    id="social"
                    title="Social profiles"
                    text="Full URLs, e.g. https://linkedin.com/in/you."
                    icon="share"
                    class="scroll-mt-24"
                >
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        @foreach (["twitter" => "Twitter", "linkedin" => "LinkedIn", "github" => "GitHub", "instagram" => "Instagram", "facebook" => "Facebook", "youtube" => "YouTube"] as $network => $networkLabel)
                            <x-admin.form.input
                                type="url"
                                name="social_links[{{ $network }}]"
                                :label="$networkLabel"
                                :value="auth()->user()->social_links[$network] ?? ''"
                                :placeholder="$networkLabel . ' URL'"
                                :disabled="! $canUpdate"
                            >
                                <x-slot:leftIcon>
                                    <x-admin.icon name="link" class="h-4 w-4" />
                                </x-slot>
                            </x-admin.form.input>
                        @endforeach
                    </div>
                </x-admin.card>

                @if ($canUpdate)
                    <div class="flex justify-end">
                        <x-admin.button icon="check">
                            <span x-text="submitting ? 'Saving…' : 'Save profile'">Save profile</span>
                        </x-admin.button>
                    </div>
                @endif
            </form>

            @can("profile.update-password")
                <form
                    method="POST"
                    action="{{ route("admin.update-password") }}"
                    x-data="{ submitting: false }"
                    x-on:submit="submitting = true"
                >
                    @csrf
                    @method("PUT")

                    <x-admin.card
                        id="password"
                        title="Password"
                        text="Use a long, random password you don't use anywhere else."
                        icon="lock"
                        class="scroll-mt-24"
                    >
                        <div class="space-y-5">
                            <x-admin.form.input
                                type="password"
                                name="current_password"
                                label="Current password"
                                autocomplete="current-password"
                                placeholder="••••••••"
                                :error="$errors->updatePassword->first('current_password') ?: false"
                            >
                                <x-slot:leftIcon>
                                    <x-admin.icon name="key" class="h-4 w-4" />
                                </x-slot>
                            </x-admin.form.input>

                            <div class="grid grid-cols-1 gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2 dark:border-slate-800">
                                <x-admin.form.input
                                    type="password"
                                    name="password"
                                    label="New password"
                                    autocomplete="new-password"
                                    placeholder="••••••••"
                                    :error="$errors->updatePassword->first('password') ?: false"
                                >
                                    <x-slot:leftIcon>
                                        <x-admin.icon name="lock" class="h-4 w-4" />
                                    </x-slot>
                                </x-admin.form.input>

                                <x-admin.form.input
                                    type="password"
                                    name="password_confirmation"
                                    label="Confirm new password"
                                    autocomplete="new-password"
                                    placeholder="••••••••"
                                    :error="$errors->updatePassword->first('password_confirmation') ?: false"
                                >
                                    <x-slot:leftIcon>
                                        <x-admin.icon name="lock" class="h-4 w-4" />
                                    </x-slot>
                                </x-admin.form.input>
                            </div>

                            <div
                                class="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                            >
                                <p class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                    <x-admin.icon name="shield-check" class="h-3.5 w-3.5" />
                                    You need your current password to set a new one.
                                </p>
                                <x-admin.button icon="key">
                                    <span x-text="submitting ? 'Updating…' : 'Update password'">Update password</span>
                                </x-admin.button>
                            </div>
                        </div>
                    </x-admin.card>
                </form>
            @endcan

            <x-admin.card
                id="two-factor"
                title="Two-factor sign-in"
                text="A code from your phone after your password, so a stolen password isn't enough."
                icon="shield-check"
                class="scroll-mt-24"
            >
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span
                            @class([
                                "flex h-10 w-10 shrink-0 items-center justify-center rounded-full",
                                "bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400" => $me->hasTwoFactor(),
                                "bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400" => ! $me->hasTwoFactor(),
                            ])
                        >
                            <x-admin.icon :name="$me->hasTwoFactor() ? 'shield-check' : 'shield'" class="h-4.5 w-4.5" />
                        </span>
                        <div class="text-sm">
                            <p class="font-medium text-slate-900 dark:text-white">{{ $me->hasTwoFactor() ? "On" : "Off" }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $me->hasTwoFactor() ? "Since " . $me->two_factor_confirmed_at->format("d M Y") : "Takes about a minute with a free authenticator app." }}
                            </p>
                        </div>
                    </div>
                    <x-admin.button
                        :href="route('admin.two-factor.show')"
                        :variant="$me->hasTwoFactor() ? 'secondary' : 'primary'"
                        :icon="$me->hasTwoFactor() ? 'settings' : 'shield-check'"
                    >
                        {{ $me->hasTwoFactor() ? "Manage" : "Set up" }}
                    </x-admin.button>
                </div>
            </x-admin.card>

            @php($otherSessions = $sessions->where("is_current", false)->count())
            <div
                x-data="{
                    ask: null,
                    open(action, title) {
                        this.ask = { action, title }
                        this.$dispatch('open-modal', 'session-password')
                        this.$nextTick(() =>
                            setTimeout(
                                () => document.getElementById('session-password')?.focus(),
                                50,
                            ),
                        )
                    },
                }"
                x-on:ask-session-password.window="open($event.detail.action, $event.detail.title)"
                @if ($errors->sessions->any())
                    x-init="
                        open(
                            {{ \Illuminate\Support\Js::from(old("_session_action", route("admin.profile.sessions.destroy-others"))) }},
                            {{ \Illuminate\Support\Js::from(old("_session_title", "Sign out of all other devices?")) }},
                        )
                    "
                @endif
            >
                <x-admin.card
                    id="sessions"
                    title="Where you're signed in"
                    text="If you see a device you don't recognise, sign it out and change your password."
                    icon="monitor"
                    class="scroll-mt-24"
                >
                    @if ($canUpdate && $otherSessions)
                        <x-slot:actions>
                            <x-admin.button
                                type="button"
                                size="sm"
                                variant="danger-outline"
                                icon="log-out"
                                x-on:click="open({{ \Illuminate\Support\Js::from(route('admin.profile.sessions.destroy-others')) }}, 'Sign out of all other devices?')"
                            >
                                Sign out other devices
                            </x-admin.button>
                        </x-slot>
                    @endif

                    @include(
                        "admin.partials.sessions-list",
                        [
                            "sessions" => $sessions,
                            "endUrl" => fn ($key) => $canUpdate
                                ? route("admin.profile.sessions.destroy", $key)
                                : null,
                            "confirm" => "password",
                        ]
                    )
                </x-admin.card>

                <x-modal name="session-password" maxWidth="md">
                    <form method="POST" x-bind:action="ask?.action" x-data="{ busy: false }" x-on:submit="busy = true" class="p-6">
                        @csrf
                        @method("DELETE")
                        <input type="hidden" name="_session_action" x-bind:value="ask?.action" />
                        <input type="hidden" name="_session_title" x-bind:value="ask?.title" />

                        <div class="flex items-start gap-4">
                            <span
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600 ring-8 ring-red-50/60 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/5"
                            >
                                <x-admin.icon name="log-out" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1 pt-0.5">
                                <h2 class="text-base font-semibold text-slate-900 dark:text-white" x-text="ask?.title"></h2>
                                <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                                    Enter your password to confirm. This device stays signed in.
                                </p>

                                <div class="mt-4">
                                    <x-admin.form.input
                                        type="password"
                                        name="password"
                                        id="session-password"
                                        label="Your password"
                                        autocomplete="current-password"
                                        :error="$errors->sessions->first('password')"
                                        required
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-admin.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'session-password')">
                                Cancel
                            </x-admin.button>
                            <x-admin.button variant="danger" icon="log-out" x-bind:disabled="busy">
                                <span x-text="busy ? 'Signing out…' : 'Sign out'">Sign out</span>
                            </x-admin.button>
                        </div>
                    </form>
                </x-modal>
            </div>
        </div>
    </div>
</x-admin>
