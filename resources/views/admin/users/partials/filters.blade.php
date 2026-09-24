<x-admin.filter.bar :action="route('admin.users.index')" search="Search users by name or email…">
    <x-admin.filter.select
        name="role"
        label="Role"
        icon="shield"
        :options="$roles->mapWithKeys(fn ($role) => [$role->name => \Illuminate\Support\Str::headline($role->name)])->all()"
    />

    <x-admin.filter.select name="status" label="Status" icon="circle-dot" :options="\App\Enums\CommonStatusEnum::dotOptions()" />
</x-admin.filter.bar>
