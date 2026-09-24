<?php

use App\Enums\EnquiryStatus;
use App\Mail\EnquiryReplyMail;
use App\Models\Enquiry;
use App\Models\EnquiryActivity;
use App\Models\User;
use App\Services\Transfer\Types\EnquiryTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function enquiryUser(array $permissions = ['view', 'edit', 'create', 'reply', 'delete'], string $role = 'super admin'): User
{
    $role = Role::findOrCreate($role, 'web');
    foreach ($permissions as $permission) {
        $role->givePermissionTo(Permission::findOrCreate("admin.enquiries.{$permission}", 'web'));
    }

    return tap(User::factory()->create(['status' => 'active']))->assignRole($role);
}

function websiteEnquiry(array $attributes = []): Enquiry
{
    return Enquiry::create([
        'source' => 'contact-form',
        'source_url' => 'https://myshop.test/contact',
        'data' => ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'message' => 'We need a new customer portal.', 'service' => 'Web app'],
        'status' => EnquiryStatus::NEW->value,
        ...$attributes,
    ]);
}

beforeEach(fn () => config(['mail.default' => 'array']));

test('the list shows status tabs and links each enquiry to its page', function () {
    $user = enquiryUser();
    $enquiry = websiteEnquiry();
    websiteEnquiry(['status' => EnquiryStatus::CLOSED->value, 'data' => ['name' => 'Closed Person', 'email' => 'c@example.com', 'message' => 'x']]);

    $this->actingAs($user)->get(route('admin.enquiries.index'))->assertOk()
        ->assertSee(route('admin.enquiries.show', $enquiry))
        ->assertSee('Follow-up due')
        ->assertSee('In progress')
        ->assertSee('Add enquiry');

    $this->get(route('admin.enquiries.index', ['status' => 'open']))->assertSee('Ada Lovelace')->assertDontSee('Closed Person');
    $this->get(route('admin.enquiries.index', ['status' => 'closed']))->assertDontSee('Ada Lovelace')->assertSee('Closed Person');

    $this->actingAs(User::factory()->create())->get(route('admin.enquiries.index'))->assertForbidden();
});

test('opening an enquiry marks it read and shows everything about it', function () {
    $user = enquiryUser();
    $enquiry = websiteEnquiry();

    $this->actingAs($user)->get(route('admin.enquiries.show', $enquiry))->assertOk()
        ->assertSee('We need a new customer portal.')
        ->assertSee('Web app')
        ->assertSee($enquiry->reference)
        ->assertSee('Reply by email')
        ->assertSee('Received from the')
        ->assertSee('Update status', false);

    expect($enquiry->fresh())->seen_by->toBe($user->id)->seen_at->not->toBeNull();

    $viewer = enquiryUser(['view'], 'enquiry viewer');
    $this->actingAs($viewer)->get(route('admin.enquiries.show', $enquiry))->assertOk()
        ->assertDontSee('Reply by email')->assertDontSee('Add note')->assertDontSee('Edit details');
});

test('staff can add an enquiry that came in by phone', function () {
    $user = enquiryUser();

    $this->actingAs($user)->get(route('admin.enquiries.create'))->assertOk()->assertSee('Phone call');

    $this->post(route('admin.enquiries.store'), [
        'name' => 'Ravi Kumar', 'phone' => '+91 98765 43210', 'message' => 'Wants a quote for 20 hoodies.',
        'source' => 'phone-call', 'status' => 'in_progress', 'assigned_to' => $user->id,
        'follow_up_at' => now()->addDays(2)->toDateString(), 'note' => 'Call back after lunch.',
    ])->assertRedirect();

    $enquiry = Enquiry::latest('id')->first();
    expect($enquiry)
        ->source->toBe('phone-call')
        ->status->toBe('in_progress')
        ->assigned_to->toBe($user->id)
        ->created_by->toBe($user->id)
        ->and($enquiry->field('phone'))->toBe('+91 98765 43210')
        ->and($enquiry->follow_up_at->toDateString())->toBe(now()->addDays(2)->toDateString())
        ->and($enquiry->activities->pluck('type')->all())->toEqualCanonicalizing([EnquiryActivity::CREATED, EnquiryActivity::NOTE]);

    $this->get(route('admin.enquiries.show', $enquiry))->assertSee('Call back after lunch.')->assertSee('added this enquiry')->assertSee('WhatsApp');
});

test('adding needs a way to reach them, a message and permission', function () {
    $user = enquiryUser();

    $this->actingAs($user)->post(route('admin.enquiries.store'), ['name' => 'No Contact', 'message' => '', 'source' => 'phone-call', 'status' => 'new'])
        ->assertSessionHasErrors(['email', 'phone', 'message']);
    $this->post(route('admin.enquiries.store'), ['email' => 'not-an-email', 'message' => 'hi', 'source' => 'fax', 'status' => 'closed'])
        ->assertSessionHasErrors(['email', 'source', 'status']);

    expect(Enquiry::count())->toBe(0);

    $this->actingAs(enquiryUser(['view'], 'enquiry viewer'))->get(route('admin.enquiries.create'))->assertForbidden();
});

test('changing status is recorded, and closing or pausing needs a note', function () {
    $user = enquiryUser();
    $enquiry = websiteEnquiry();

    $this->actingAs($user)->post(route('admin.enquiries.status', $enquiry), ['status' => 'closed'])->assertSessionHasErrors('note');
    $this->post(route('admin.enquiries.status', $enquiry), ['status' => 'on_hold', 'note' => ''])->assertSessionHasErrors('note');
    expect($enquiry->fresh()->status)->toBe('new');

    $this->post(route('admin.enquiries.status', $enquiry), ['status' => 'closed', 'note' => 'Won: signed the contract.'])
        ->assertRedirect(route('admin.enquiries.show', $enquiry).'#activity');

    $activity = $enquiry->activities()->first();
    expect($enquiry->fresh())->status->toBe('closed')->status_changed_at->not->toBeNull()
        ->and($activity)->type->toBe(EnquiryActivity::STATUS)->body->toBe('Won: signed the contract.')->meta->toBe(['from' => 'new', 'to' => 'closed']);

    $this->post(route('admin.enquiries.status', $enquiry), ['status' => 'in_progress'])->assertSessionHasNoErrors();
    $this->post(route('admin.enquiries.status', $enquiry), ['status' => 'in_progress', 'note' => 'Still waiting.']);
    expect($enquiry->activities()->count())->toBe(3)->and($enquiry->activities()->first()->type)->toBe(EnquiryActivity::NOTE);

    $this->post(route('admin.enquiries.status', $enquiry), ['status' => 'lost'])->assertSessionHasErrors('status');
    $this->actingAs(enquiryUser(['view'], 'enquiry viewer'))->post(route('admin.enquiries.status', $enquiry), ['status' => 'spam'])->assertForbidden();
});

test('notes are added, move a new enquiry to in progress, and only the author or a deleter can remove them', function () {
    $author = enquiryUser();
    $enquiry = websiteEnquiry();

    $this->actingAs($author)->post(route('admin.enquiries.notes.store', $enquiry), ['body' => ''])->assertSessionHasErrors('body');
    $this->post(route('admin.enquiries.notes.store', $enquiry), ['body' => "Called them.\nThey want a demo on Friday."]);

    $note = $enquiry->activities()->where('type', EnquiryActivity::NOTE)->first();
    expect($note->body)->toBe("Called them.\nThey want a demo on Friday.")->and($enquiry->fresh()->status)->toBe('in_progress');

    $colleague = enquiryUser(['view', 'edit'], 'enquiry editor');
    $this->actingAs($colleague)->delete(route('admin.enquiries.notes.destroy', [$enquiry, $note]))->assertForbidden();

    $statusChange = $enquiry->activities()->where('type', EnquiryActivity::STATUS)->first();
    $this->actingAs($author)->delete(route('admin.enquiries.notes.destroy', [$enquiry, $statusChange]))->assertNotFound();
    $this->delete(route('admin.enquiries.notes.destroy', [$enquiry, $note]))->assertRedirect();
    expect(EnquiryActivity::find($note->id))->toBeNull();
});

test('assigning and follow-ups are recorded and power the list filters', function () {
    $user = enquiryUser();
    $colleague = enquiryUser(['view'], 'enquiry viewer');
    $enquiry = websiteEnquiry();
    $other = websiteEnquiry(['data' => ['name' => 'Someone Else', 'email' => 'else@example.com', 'message' => 'x']]);

    $this->actingAs($user)->post(route('admin.enquiries.assign', $enquiry), ['assigned_to' => $user->id]);
    $this->post(route('admin.enquiries.assign', $other), ['assigned_to' => $colleague->id]);
    $this->post(route('admin.enquiries.assign', $other), ['assigned_to' => User::factory()->create()->id])->assertSessionHasErrors('assigned_to');
    $this->post(route('admin.enquiries.follow-up', $enquiry), ['follow_up_at' => now()->subDay()->toDateString()]);

    expect($enquiry->fresh())->assigned_to->toBe($user->id)->follow_up_state->toBe('overdue')
        ->and($enquiry->activities()->pluck('type')->all())->toContain(EnquiryActivity::ASSIGNED, EnquiryActivity::FOLLOW_UP);

    $this->get(route('admin.enquiries.index', ['assigned' => 'me']))->assertSee('Ada Lovelace')->assertDontSee('Someone Else');
    $this->get(route('admin.enquiries.index', ['assigned' => 'none']))->assertDontSee('Ada Lovelace')->assertDontSee('Someone Else');
    $this->get(route('admin.enquiries.index', ['follow_up' => 'due']))->assertSee('Ada Lovelace')->assertDontSee('Someone Else')->assertSee('Overdue');

    $optionFor = fn (string $url, User $person) => str_contains(
        str_replace([chr(92).'u0022', chr(92).'u0027'], ['"', "'"], $this->get($url)->getContent()),
        '"value":"'.$person->id.'","label":"'.$person->name,
    );
    expect($optionFor(route('admin.enquiries.show', $enquiry), $colleague))->toBeTrue()
        ->and($optionFor(route('admin.enquiries.index'), $colleague))->toBeTrue()
        ->and($optionFor(route('admin.enquiries.create'), $colleague))->toBeTrue();

    $this->post(route('admin.enquiries.follow-up', $enquiry), ['follow_up_at' => '']);
    $this->post(route('admin.enquiries.assign', $enquiry), ['assigned_to' => '']);
    expect($enquiry->fresh())->follow_up_at->toBeNull()->assigned_to->toBeNull();
});

test('a reply is emailed, logged and moves the enquiry to replied', function () {
    Mail::fake();
    $user = enquiryUser();
    $enquiry = websiteEnquiry(['data' => ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'subject' => 'Portal', 'message' => 'We need a new customer portal.']]);

    $this->actingAs($user)->post(route('admin.enquiries.reply', $enquiry), ['subject' => 'Re: Portal', 'body' => "Hi Ada,\n\nHappy to help.", 'quote' => '1'])
        ->assertRedirect(route('admin.enquiries.show', $enquiry).'#activity')
        ->assertSessionHas('success', 'Reply sent to ada@example.com.');

    Mail::assertSent(EnquiryReplyMail::class, function (EnquiryReplyMail $mail) use ($user) {
        $html = $mail->render();

        return $mail->hasTo('ada@example.com') && $mail->hasReplyTo($user->email) && $mail->subjectLine === 'Re: Portal'
            && str_contains($html, 'Happy to help.') && str_contains($html, 'We need a new customer portal.');
    });

    $reply = $enquiry->activities()->where('type', EnquiryActivity::REPLY)->first();
    expect($reply->meta)->toMatchArray(['subject' => 'Re: Portal', 'to' => 'ada@example.com'])
        ->and($enquiry->fresh()->status)->toBe('replied');

    $this->get(route('admin.enquiries.show', $enquiry))->assertSee('Re: Portal')->assertSee('emailed');
});

test('replying keeps a closed enquiry closed, and needs an email address and permission', function () {
    Mail::fake();
    $user = enquiryUser();
    $closed = websiteEnquiry(['status' => 'closed']);

    $this->actingAs($user)->post(route('admin.enquiries.reply', $closed), ['subject' => 'One more thing', 'body' => 'Thanks again!']);
    expect($closed->fresh()->status)->toBe('closed');

    $phoneOnly = websiteEnquiry(['data' => ['name' => 'Ravi', 'phone' => '+91 98765 43210', 'message' => 'x']]);
    $this->post(route('admin.enquiries.reply', $phoneOnly), ['subject' => 'Hi', 'body' => 'Hello'])->assertStatus(422);
    $this->get(route('admin.enquiries.show', $phoneOnly))->assertSee('no valid email address', false);

    $this->post(route('admin.enquiries.reply', $closed), ['subject' => '', 'body' => ''])->assertSessionHasErrorsIn('reply', ['subject', 'body']);

    $this->actingAs(enquiryUser(['view', 'edit'], 'enquiry editor'))->post(route('admin.enquiries.reply', $closed), ['subject' => 'x', 'body' => 'y'])->assertForbidden();
    Mail::assertSentCount(1);
});

test('a failed send is reported and nothing is logged as sent', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 3]]);
    $enquiry = websiteEnquiry();

    $this->actingAs(enquiryUser())->post(route('admin.enquiries.reply', $enquiry), ['subject' => 'Hi', 'body' => 'Hello'])
        ->assertSessionHasErrorsIn('reply', 'body');

    expect($enquiry->activities()->where('type', EnquiryActivity::REPLY)->exists())->toBeFalse()
        ->and($enquiry->fresh()->status)->toBe('new');
});

test('editing contact details keeps other form fields and notes what changed', function () {
    $user = enquiryUser();
    $enquiry = websiteEnquiry();

    $this->actingAs($user)->get(route('admin.enquiries.edit', $enquiry))->assertOk()->assertDontSee('Where it came from');

    $this->put(route('admin.enquiries.update', $enquiry), ['name' => 'Ada King', 'email' => 'ada@example.com', 'phone' => '+44 20 7946 0000', 'message' => 'We need a new customer portal.'])
        ->assertRedirect(route('admin.enquiries.show', $enquiry));

    $enquiry->refresh();
    expect($enquiry->data)->toMatchArray(['name' => 'Ada King', 'phone' => '+44 20 7946 0000', 'service' => 'Web app'])
        ->and($enquiry->activities()->first()->meta['fields'])->toBe(['Name', 'Phone']);

    $this->put(route('admin.enquiries.update', $enquiry), ['name' => 'Ada King', 'email' => 'ada@example.com', 'phone' => '+44 20 7946 0000', 'message' => 'We need a new customer portal.'])
        ->assertSessionHas('success', 'Nothing changed.');
});

test('bulk status changes are logged on each enquiry', function () {
    $user = enquiryUser();
    $enquiries = collect([websiteEnquiry(), websiteEnquiry()]);

    $this->actingAs($user)->post(route('admin.bulk', 'enquiries'), ['action' => 'mark-spam', 'ids' => $enquiries->pluck('id')->all()])
        ->assertSessionHas('success', '2 enquiries marked as Spam.');

    $enquiries->each(fn ($enquiry) => expect($enquiry->fresh()->status)->toBe('spam')
        ->and($enquiry->activities()->first())->type->toBe(EnquiryActivity::STATUS)->body->toBe('Changed with a bulk action.'));
});

test('the export includes status, assignee, follow-up and the latest note', function () {
    $user = enquiryUser();
    $enquiry = websiteEnquiry(['assigned_to' => $user->id, 'follow_up_at' => now()->addDay(), 'status' => 'on_hold']);
    $enquiry->record(EnquiryActivity::NOTE, 'First note', [], $user);
    $this->travel(1)->minutes();
    $enquiry->record(EnquiryActivity::NOTE, 'Latest note', [], $user);

    $type = new EnquiryTransfer;
    $row = array_combine(array_keys($type->columns()), $type->exportRow($type->exportQuery()->first()));

    expect($row)->toMatchArray([
        'reference' => $enquiry->reference,
        'status' => 'On hold',
        'source' => 'Contact Form',
        'assigned to' => $user->name,
        'follow up' => now()->addDay()->toDateString(),
        'notes' => 2,
        'last note' => 'Latest note',
        'service' => 'Web app',
    ]);
});
