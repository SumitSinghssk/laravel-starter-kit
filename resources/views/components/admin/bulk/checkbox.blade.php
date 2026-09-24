@props([
    'value',
    'label' => '',
])

<td class="w-10 !pr-0">
    <input
        type="checkbox"
        value="{{ $value }}"
        x-model.number="selected"
        data-bulk-row
        class="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 focus:ring-blue-500/30 dark:border-slate-600 dark:bg-slate-800"
        aria-label="Select {{ $label }}"
    />
</td>
