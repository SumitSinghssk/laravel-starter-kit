<x-admin.filter.bar :action="route('admin.blogs.index')" search="Search posts by title or excerpt…">
    <x-admin.filter.select name="status" label="Status" icon="circle-dot" :options="\App\Enums\CommonStatusEnum::dotOptions()" />

    <x-admin.filter.select name="category_id" label="Category" icon="tag" :options="$categories->pluck('name', 'id')->all()" />
</x-admin.filter.bar>
