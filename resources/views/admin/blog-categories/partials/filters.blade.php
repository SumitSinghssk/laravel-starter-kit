<x-admin.filter.bar :action="route('admin.blog-categories.index')" search="Search categories by name or description…">
    <x-admin.filter.select name="status" label="Status" icon="circle-dot" :options="\App\Enums\CommonStatusEnum::dotOptions()" />
</x-admin.filter.bar>
