<?php

namespace App\Services\Transfer\Types;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Models\EnquiryActivity;
use App\Services\Transfer\TransferType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EnquiryTransfer extends TransferType
{
    private ?array $fields = null;

    public function key(): string
    {
        return 'enquiries';
    }

    public function label(): string
    {
        return 'Enquiries';
    }

    public function singular(): string
    {
        return 'enquiry';
    }

    public function viewPermission(): string
    {
        return 'admin.enquiries.view';
    }

    public function listUrl(): string
    {
        return route('admin.enquiries.index');
    }

    public function columns(): array
    {
        $fixed = [
            'received' => ['When it arrived', false, ''],
            'reference' => ['Enquiry number', false, ''],
            'status' => ['Status', false, ''],
            'source' => ['Where it came from', false, ''],
            'page' => ['Page the form was sent from', false, ''],
            'assigned to' => ['Team member handling it', false, ''],
            'follow up' => ['Follow-up date', false, ''],
            'notes' => ['Number of notes', false, ''],
            'last note' => ['Most recent note', false, ''],
        ];

        foreach ($this->fields() as $field) {
            $fixed[$field] = ['Form field', false, ''];
        }

        return $fixed;
    }

    public function exportQuery(): Builder
    {
        return Enquiry::query()->with(['assignee:id,name', 'activities' => fn ($query) => $query->where('type', EnquiryActivity::NOTE)])->latest();
    }

    public function exportRow(Model $enquiry): array
    {
        $data = is_array($enquiry->data) ? $enquiry->data : [];
        $notes = $enquiry->activities;

        return [
            $enquiry->created_at,
            $enquiry->reference,
            EnquiryStatus::labelFor($enquiry->status),
            $enquiry->source_label,
            $enquiry->source_url,
            $enquiry->assignee?->name,
            $enquiry->follow_up_at?->toDateString(),
            $notes->count(),
            $notes->first()?->body,
            ...array_map(fn ($field) => is_scalar($data[$field] ?? null) || ($data[$field] ?? null) === null ? ($data[$field] ?? '') : json_encode($data[$field]), $this->fields()),
        ];
    }

    private function fields(): array
    {
        if ($this->fields !== null) {
            return $this->fields;
        }

        $seen = [];
        Enquiry::query()->select(['id', 'data'])->chunkById(500, function ($enquiries) use (&$seen) {
            foreach ($enquiries as $enquiry) {
                foreach (array_keys(is_array($enquiry->data) ? $enquiry->data : []) as $key) {
                    $seen[$key] = true;
                }
            }
        });

        $preferred = array_values(array_intersect(['name', 'email', 'phone', 'company', 'subject', 'message'], array_keys($seen)));

        return $this->fields = [...$preferred, ...array_values(array_diff(array_keys($seen), $preferred))];
    }
}
