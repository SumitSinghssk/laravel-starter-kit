<x-admin.card id="settings-general" class="scroll-mt-24" title="General" text="The name used across the website and admin panel." icon="settings">
    <x-admin.form.input
        name="app_name"
        label="Application name"
        required
        :disabled="! $canUpdate"
        :value="$settings['app_name'] ?? ''"
        placeholder="e.g. My Awesome App"
        hint="Shown in page titles, emails and the admin sidebar."
    >
        <x-slot:leftIcon>
            <x-admin.icon name="building" class="h-4 w-4" />
        </x-slot>
    </x-admin.form.input>
</x-admin.card>
