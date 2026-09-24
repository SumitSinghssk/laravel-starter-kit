<?php

use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function testimonialAdmin(): User
{
    $role = Role::findOrCreate('super admin', 'web');
    $role->givePermissionTo(Permission::where('name', 'like', 'admin.testimonials.%')->get());

    return tap(User::factory()->create())->assignRole($role);
}

function makeTestimonial(array $attributes = []): Testimonial
{
    return Testimonial::create([
        'name' => 'Priya Nair',
        'designation' => 'Head of Product',
        'company' => 'Finlytic',
        'quote' => 'Their engineers slotted into our workflow seamlessly.',
        'rating' => 5,
        'status' => 'active',
        ...$attributes,
    ]);
}

test('an admin can create a testimonial with a photo', function () {
    Storage::fake('public');

    $this->actingAs(testimonialAdmin())
        ->post(route('admin.testimonials.store'), [
            'name' => 'Daniel Okafor',
            'designation' => 'Founder & CEO',
            'company' => 'Stackline Logistics',
            'quote' => 'We came in with a rough idea and left with a production-ready product.',
            'rating' => 4,
            'is_featured' => 1,
            'sort_order' => 3,
            'status' => 'active',
            'photo' => UploadedFile::fake()->image('daniel.jpg', 400, 400),
        ])
        ->assertSessionHasNoErrors();

    $testimonial = Testimonial::sole();

    expect($testimonial)
        ->name->toBe('Daniel Okafor')
        ->rating->toBe(4)
        ->is_featured->toBeTrue()
        ->sort_order->toBe(3)
        ->by_line->toBe('Founder & CEO, Stackline Logistics');

    Storage::disk('public')->assertExists($testimonial->photo);
});

test('optional fields can be left empty', function () {
    $this->actingAs(testimonialAdmin())
        ->post(route('admin.testimonials.store'), [
            'name' => 'Anonymous client',
            'quote' => 'Great team, would work with them again.',
            'rating' => '',
            'sort_order' => '',
            'is_featured' => 0,
            'status' => 'inactive',
        ])
        ->assertSessionHasNoErrors();

    expect(Testimonial::sole())->rating->toBeNull()->sort_order->toBe(0)->is_featured->toBeFalse()->by_line->toBeNull();
});

test('updating can replace or remove the photo', function () {
    Storage::fake('public');
    $admin = testimonialAdmin();
    $old = UploadedFile::fake()->image('old.jpg')->store('testimonials', 'public');
    $testimonial = makeTestimonial(['photo' => $old]);

    $payload = ['name' => 'Priya Nair', 'quote' => 'Updated words about the team.', 'rating' => '', 'status' => 'active'];

    $this->actingAs($admin)
        ->put(route('admin.testimonials.update', $testimonial), [...$payload, 'photo' => UploadedFile::fake()->image('new.jpg')])
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($old);
    $new = $testimonial->fresh()->photo;
    Storage::disk('public')->assertExists($new);

    $this->actingAs($admin)
        ->put(route('admin.testimonials.update', $testimonial), [...$payload, 'remove_image' => 1])
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($new);
    expect($testimonial->fresh())->photo->toBeNull()->rating->toBeNull()->quote->toBe('Updated words about the team.');
});

test('invalid input is rejected', function (array $input, string $field) {
    $this->actingAs(testimonialAdmin())
        ->post(route('admin.testimonials.store'), [
            'name' => 'Client',
            'quote' => 'A perfectly fine testimonial.',
            'status' => 'active',
            ...$input,
        ])
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'missing quote' => [['quote' => ''], 'quote'],
    'too short quote' => [['quote' => 'Great!'], 'quote'],
    'rating above 5' => [['rating' => 6], 'rating'],
    'rating of 0' => [['rating' => 0], 'rating'],
    'negative order' => [['sort_order' => -1], 'sort_order'],
    'unknown status' => [['status' => 'draft'], 'status'],
    'not an image' => [['photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')], 'photo'],
]);

test('active and ordered scopes put featured, then lowest sort order first', function () {
    makeTestimonial(['name' => 'Third', 'sort_order' => 2]);
    makeTestimonial(['name' => 'Hidden', 'status' => 'inactive']);
    makeTestimonial(['name' => 'Second', 'sort_order' => 1]);
    makeTestimonial(['name' => 'First', 'sort_order' => 9, 'is_featured' => true]);

    expect(Testimonial::active()->ordered()->pluck('name')->all())->toBe(['First', 'Second', 'Third']);
});

test('status can be toggled and testimonials deleted', function () {
    $testimonial = makeTestimonial();
    $this->actingAs(testimonialAdmin());

    $this->patch(route('admin.testimonials.toggle-status', $testimonial))->assertOk()->assertJson(['status' => 'inactive']);
    $this->delete(route('admin.testimonials.destroy', $testimonial))->assertRedirect(route('admin.testimonials.index'));

    expect($testimonial->fresh()->trashed())->toBeTrue();
});

test('the admin pages render', function () {
    $testimonial = makeTestimonial(['is_featured' => true]);
    makeTestimonial(['name' => 'No Rating Person', 'rating' => null]);
    $this->actingAs(testimonialAdmin());

    $this->get(route('admin.testimonials.index'))->assertOk()->assertSee('Priya Nair')->assertSee('Featured')->assertSee('Head of Product, Finlytic');
    $this->get(route('admin.testimonials.index', ['search' => 'Finlytic', 'rating' => 5, 'featured' => 'yes', 'status' => 'active']))
        ->assertOk()
        ->assertSee('Priya Nair')
        ->assertDontSee('No Rating Person');
    $this->get(route('admin.testimonials.create'))->assertOk();
    $this->get(route('admin.testimonials.edit', $testimonial))->assertOk()->assertSee('Their engineers slotted');
});

test('users without the permission cannot manage testimonials', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.testimonials.index'))->assertForbidden();
});
