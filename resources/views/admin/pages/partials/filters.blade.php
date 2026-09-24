<x-admin.filter.bar :action="route('admin.pages.index')" search="Search pages by title or content…">
    <x-admin.filter.select name="status" label="Status" icon="circle-dot" :options="\App\Enums\CommonStatusEnum::dotOptions()" />
</x-admin.filter.bar>
