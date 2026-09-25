<x-admin.filter.bar :action="route('admin.users.index')" search="Search users by name or email…">
    <x-admin.filter.select
        name="role"
        label="Role"
        icon="shield"
        :options="$roles->mapWithKeys(fn ($role) => [$role->name => \Illuminate\Support\Str::headline($role->name)])->all()"
    />

    <x-admin.filter.select name="status" label="Status" icon="circle-dot" :options="\App\Enums\CommonStatusEnum::dotOptions()" />

    <x-admin.filter.select name="access" label="Sign-in" icon="lock" :options="['locked' => ['label' => 'Locked', 'dot' => 'bg-red-500']]" />
</x-admin.filter.bar>
