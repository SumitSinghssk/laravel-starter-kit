<x-admin.filter.bar :action="route('admin.testimonials.index')" search="Search by name, company or text…">
    <x-admin.filter.select
        name="rating"
        label="Rating"
        icon="star"
        :options="collect(\App\Models\Testimonial::RATINGS)->map(fn ($label, $stars) => $stars . ' ★ · ' . $label)"
    />
    <x-admin.filter.select name="featured" label="Featured" icon="sparkles" :options="['yes' => 'Featured', 'no' => 'Not featured']" />
    <x-admin.filter.select name="status" label="Status" icon="circle-dot" :options="\App\Enums\CommonStatusEnum::dotOptions()" />
</x-admin.filter.bar>
