@php
    use App\Enums\EnquiryStatus;
    use App\Models\Enquiry;

    $value = fn (string $field) => $enquiry?->field($field) ?? '';
    $creating = $enquiry === null;
    $showSource = $creating || $enquiry->is_manual;
    $userOptions = $creating ? $assignees->mapWithKeys(fn ($user) => [$user->id => $user->id === auth()->id() ? $user->name . ' (you)' : $user->name])->all() : [];
@endphp

<x-admin.form-grid>
    <x-admin.card title="Contact" text="Who got in touch. Add an email or a phone number so you can get back to them." icon="user">
        <div class="space-y-5">
            <x-admin.form.input name="name" label="Name" :value="$value('name')" placeholder="e.g. Priya Nair" autocomplete="off">
                <x-slot:leftIcon>
                    <x-admin.icon name="user" class="h-4 w-4" />
                </x-slot>
            </x-admin.form.input>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-admin.form.input type="email" name="email" label="Email" :value="$value('email')" placeholder="name@company.com" autocomplete="off">
                    <x-slot:leftIcon>
                        <x-admin.icon name="mail" class="h-4 w-4" />
                    </x-slot>
                </x-admin.form.input>

                <x-admin.form.input type="tel" name="phone" label="Phone" :value="$value('phone')" placeholder="+91 98765 43210" autocomplete="off">
                    <x-slot:leftIcon>
                        <x-admin.icon name="phone" class="h-4 w-4" />
                    </x-slot>
                </x-admin.form.input>
            </div>

            <x-admin.form.input name="company" label="Company" :value="$value('company')" placeholder="Optional">
                <x-slot:leftIcon>
                    <x-admin.icon name="building" class="h-4 w-4" />
                </x-slot>
            </x-admin.form.input>
        </div>
    </x-admin.card>

    <x-admin.card title="Enquiry" text="What they asked about." icon="message">
        <div class="space-y-5">
            <x-admin.form.input name="subject" label="Subject" :value="$value('subject')" placeholder="e.g. Quote for a new website" />

            <x-admin.form.textarea
                name="message"
                label="Message"
                :value="$value('message')"
                rows="6"
                required
                placeholder="Write down what they said: what they need, budget, timing…"
            />
        </div>
    </x-admin.card>

    <x-slot:aside>
        @if ($showSource)
            <x-admin.card title="Where it came from" icon="globe">
                <x-admin.form.select
                    name="source"
                    label="Source"
                    :options="Enquiry::MANUAL_SOURCES"
                    :value="old('source', $enquiry?->source ?? 'phone-call')"
                    required
                />
            </x-admin.card>
        @endif

        @if ($creating)
            <x-admin.card title="Handling" text="You can change all of this later." icon="user-check">
                <div class="space-y-5">
                    <x-admin.form.select
                        name="status"
                        label="Status"
                        :options="collect([EnquiryStatus::NEW, EnquiryStatus::IN_PROGRESS])->mapWithKeys(fn ($s) => [$s->value => ['label' => $s->label(), 'dot' => $s->dot(), 'description' => $s->description()]])->all()"
                        :value="old('status', EnquiryStatus::IN_PROGRESS->value)"
                    />

                    <x-admin.form.select
                        name="assigned_to"
                        label="Assign to"
                        :options="['' => 'No one yet'] + $userOptions"
                        :value="old('assigned_to', auth()->id())"
                        placeholder="No one yet"
                    />

                    <x-admin.form.date-picker
                        name="follow_up_at"
                        label="Follow up on"
                        :value="old('follow_up_at')"
                        :min="now()->toDateString()"
                        placeholder="No follow-up"
                        hint="It shows as due in the list on this day."
                    />

                    <x-admin.form.textarea name="note" label="First note" rows="3" placeholder="Optional: anything the team should know" />
                </div>
            </x-admin.card>
        @endif
    </x-slot>
</x-admin.form-grid>
