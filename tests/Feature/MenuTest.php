<?php

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Menus\MenuBuilder;
use Database\Seeders\MenuExampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function menuAdmin(bool $canEdit = true): User
{
    $role = Role::findOrCreate($canEdit ? 'super admin' : 'menu viewer', 'web');
    $role->givePermissionTo(Permission::findOrCreate('admin.menus.view', 'web'));
    if ($canEdit) {
        $role->givePermissionTo(Permission::findOrCreate('admin.menus.update', 'web'));
    }

    return tap(User::factory()->create())->assignRole($role);
}

function menuLink(string $label, string $url, int $depth = 0, array $attributes = []): array
{
    return ['label' => $label, 'url' => $url, 'depth' => $depth, 'new_tab' => false, 'is_active' => true, ...$attributes];
}

function versioned(string $key, array $payload): array
{
    $builder = app(MenuBuilder::class);

    return [...$payload, 'version' => $builder->version($builder->menu($key))];
}

beforeEach(fn () => Cache::flush());

test('menus need permission, and viewers cannot save', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.menus.edit', 'header'))->assertForbidden();

    $viewer = menuAdmin(canEdit: false);
    $this->actingAs($viewer)->get(route('admin.menus.edit', 'header'))->assertOk()->assertSee("don't have permission to change them", false);
    $this->actingAs($viewer)->putJson(route('admin.menus.update', 'header'), versioned('header', ['items' => []]))->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.menus.edit', 'sidebar'))->assertNotFound();
});

test('the editor only offers custom links', function () {
    $this->actingAs(menuAdmin())->get(route('admin.menus.edit', 'header'))->assertOk()
        ->assertSee('Add a link')->assertSee('Paste a web address')
        ->assertDontSee('Blog posts')->assertDontSee('Categories');
});

test('a three level menu is saved as a tree', function () {
    $this->actingAs(menuAdmin())->putJson(route('admin.menus.update', 'header'), versioned('header', ['items' => [
        menuLink('Home', '/'),
        menuLink('Services', '/services'),
        menuLink('Design', '/services/design', 1),
        menuLink('Logos', '/services/design/logos', 2, ['new_tab' => true]),
        menuLink('Websites', 'https://example.com/websites', 2),
        menuLink('Growth', '/services/growth', 1),
        menuLink('Hidden', '/hidden', 0, ['is_active' => false]),
    ]]))->assertOk()->assertJsonCount(7, 'rows')->assertJsonPath('rows.3.depth', 2);

    $items = Menu::where('key', 'header')->first()->items()->get();
    $design = $items->firstWhere('label', 'Design');
    expect($items)->toHaveCount(7)
        ->and($items->where('parent_id', $design->id)->pluck('label')->all())->toBe(['Logos', 'Websites']);

    $tree = app(MenuBuilder::class)->tree('header');
    expect(array_column($tree, 'label'))->toBe(['Home', 'Services'])
        ->and($tree[1]['children'][0]['children'][0])->toMatchArray(['label' => 'Logos', 'url' => url('/services/design/logos'), 'new_tab' => true])
        ->and($tree[1]['children'][0]['children'][1]['url'])->toBe('https://example.com/websites');
});

test('bad links are refused', function (array $item, string $field) {
    $this->actingAs(menuAdmin())->putJson(route('admin.menus.update', 'header'), versioned('header', ['items' => [$item]]))
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => [$field]]);

    expect(MenuItem::count())->toBe(0);
})->with([
    'javascript url' => [menuLink('x', 'javascript:alert(1)'), 'items.0.url'],
    'data url' => [menuLink('x', 'data:text/html,hi'), 'items.0.url'],
    'protocol relative' => [menuLink('x', '//evil.example'), 'items.0.url'],
    'title with nothing under it' => [menuLink('x', ''), 'items.0.url'],
    'missing label' => [menuLink('', '/x'), 'items.0.label'],
    'first item nested' => [menuLink('x', '/x', 1), 'items.0.depth'],
]);

test('links cannot go deeper than three levels or skip a level', function () {
    $admin = menuAdmin();

    $this->actingAs($admin)->putJson(route('admin.menus.update', 'header'), versioned('header', ['items' => [
        menuLink('a', '/a'), menuLink('b', '/b', 1), menuLink('c', '/c', 2), menuLink('d', '/d', 3),
    ]]))->assertStatus(422)->assertJsonStructure(['errors' => ['items.3.depth']]);

    $this->actingAs($admin)->putJson(route('admin.menus.update', 'footer'), versioned('footer', ['items' => [
        menuLink('a', '/a'), menuLink('c', '/c', 2),
    ]]))->assertStatus(422)->assertJsonStructure(['errors' => ['items.1.depth']]);
});

test('hidden links hide their nested links on the website', function () {
    $this->actingAs(menuAdmin())->putJson(route('admin.menus.update', 'header'), versioned('header', ['items' => [
        menuLink('Shown', '/shown'),
        menuLink('Secret', '/secret', 0, ['is_active' => false]),
        menuLink('Under secret', '/under', 1),
    ]]))->assertOk();

    expect(array_column(app(MenuBuilder::class)->tree('header'), 'label'))->toBe(['Shown']);
    $this->get('/')->assertSee('Shown')->assertDontSee('Under secret');
});

test('the website shows dropdowns, side menus and footer columns', function () {
    $this->actingAs(menuAdmin())->putJson(route('admin.menus.update', 'header'), versioned('header', ['items' => [
        menuLink('Home', '/'),
        menuLink('Services', '/services'),
        menuLink('Design', '/services/design', 1),
        menuLink('Logos', '/services/design/logos', 2, ['new_tab' => true]),
    ]]))->assertOk();
    $this->putJson(route('admin.menus.update', 'footer'), versioned('footer', ['items' => [
        menuLink('Company', ''),
        menuLink('Careers', 'https://jobs.example.com', 1),
        menuLink('Internships', '/careers/interns', 2),
        menuLink('Privacy', '/privacy'),
    ]]))->assertOk();

    $this->get('/')->assertOk()
        ->assertSee('aria-label="Main"', false)
        ->assertSee('Show links under Services')
        ->assertSee('Show links under Design')
        ->assertSeeHtml('target="_blank" rel="noopener"')
        ->assertSee('href="'.url('/services/design/logos').'"', false)
        ->assertSeeInOrder(['text-site-footer-heading text-sm font-semibold">', 'Company</p>'], false)
        ->assertSee('Internships')
        ->assertSee('aria-label="Footer"', false)
        ->assertSee('aria-current="page"', false);
});

test('a title without a URL opens its links instead of going anywhere', function () {
    $this->actingAs(menuAdmin())->putJson(route('admin.menus.update', 'header'), versioned('header', ['items' => [
        menuLink('Services', '', 0, ['new_tab' => true]),
        menuLink('Design', '/design', 1),
        menuLink('Branding', '', 1),
        menuLink('Logos', '/logos', 2),
    ]]))->assertOk()->assertJsonPath('rows.0.url', '')->assertJsonPath('rows.0.new_tab', false);

    $tree = app(MenuBuilder::class)->tree('header');
    expect($tree[0]['url'])->toBeNull()->and($tree[0]['children'][1]['url'])->toBeNull();

    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->not->toContain('href=""')
        ->and(preg_match('~<button[^>]*>\s*Services~', $html))->toBe(1)
        ->and(preg_match('~<button[^>]*>\s*Branding~', $html))->toBe(1);
});

test('a title whose links are all hidden is left out of the website', function () {
    $this->actingAs(menuAdmin())->putJson(route('admin.menus.update', 'footer'), versioned('footer', ['items' => [
        menuLink('Company', ''),
        menuLink('Careers', '/careers', 1, ['is_active' => false]),
        menuLink('Privacy', '/privacy'),
    ]]))->assertOk();

    expect(array_column(app(MenuBuilder::class)->tree('footer'), 'label'))->toBe(['Privacy']);
});

test('saving a menu refreshes the website straight away', function () {
    $admin = menuAdmin();
    $this->actingAs($admin)->putJson(route('admin.menus.update', 'header'), versioned('header', ['items' => [menuLink('Old', '/old')]]))->assertOk();
    expect(app(MenuBuilder::class)->tree('header')[0]['label'])->toBe('Old');

    $this->putJson(route('admin.menus.update', 'header'), versioned('header', ['items' => [menuLink('New', '/new')]]))->assertOk();
    expect(app(MenuBuilder::class)->tree('header')[0]['label'])->toBe('New');
});

test('an empty menu shows no navigation on the website', function () {
    $this->get('/')->assertOk()->assertDontSee('aria-label="Main"', false)->assertDontSee('aria-label="Footer"', false);
});

test('a save from an out-of-date page is refused instead of overwriting', function () {
    $admin = menuAdmin();
    $stale = versioned('header', ['items' => [menuLink('From old tab', '/old')]]);

    $this->actingAs($admin)->putJson(route('admin.menus.update', 'header'), versioned('header', ['items' => [menuLink('Newer', '/new')]]))->assertOk();

    $this->putJson(route('admin.menus.update', 'header'), $stale)->assertStatus(409)->assertJsonPath('stale', true);
    $this->putJson(route('admin.menus.update', 'header'), ['items' => [menuLink('No version', '/x')]])->assertStatus(409);

    expect(MenuItem::pluck('label')->all())->toBe(['Newer']);
});

test('a mega menu keeps columns, product cards and a simple dropdown', function () {
    $this->actingAs(menuAdmin())->putJson(route('admin.menus.update', 'header'), versioned('header', ['items' => [
        menuLink('Categories', '', 0, ['style' => 'mega']),
        menuLink('Hoodies', '', 1),
        menuLink('Anime Hoodies', '/anime', 2),
        menuLink('Featured Products', '', 1),
        menuLink('Goku Hoodie', '/goku', 2, ['image' => '/storage/menus/goku.png', 'description' => 'Rs 1299']),
        menuLink('Information', '', 0),
        menuLink('About Us', '/about', 1),
    ]]))->assertOk()->assertJsonPath('rows.0.style', 'mega')->assertJsonPath('rows.4.description', 'Rs 1299');

    $tree = app(MenuBuilder::class)->tree('header');
    expect($tree[0]['style'])->toBe('mega')->and($tree[1]['style'])->toBe('dropdown')
        ->and($tree[0]['children'][1]['children'][0]['image'])->toBe(url('/storage/menus/goku.png'));

    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('Featured Products')
        ->toContain('src="'.url('/storage/menus/goku.png').'"')
        ->toContain('Rs 1299')
        ->toContain('Anime Hoodies');
});

test('image and caption are checked, and the footer ignores them', function () {
    $admin = menuAdmin();

    $this->actingAs($admin)->putJson(route('admin.menus.update', 'header'), versioned('header', ['items' => [
        menuLink('x', '/x', 0, ['image' => 'javascript:alert(1)']),
        menuLink('y', '/y', 0, ['description' => str_repeat('a', 121)]),
        menuLink('z', '/z', 0, ['style' => 'popup']),
    ]]))->assertStatus(422)->assertJsonStructure(['errors' => ['items.0.image', 'items.1.description', 'items.2.style']]);

    $this->putJson(route('admin.menus.update', 'footer'), versioned('footer', ['items' => [
        menuLink('Company', '/company', 0, ['style' => 'mega', 'image' => '/storage/x.png', 'description' => 'hi']),
    ]]))->assertOk()->assertJsonPath('rows.0.style', 'dropdown')->assertJsonPath('rows.0.image', '');
});

test('the example menu seeder builds the mega menu', function () {
    Storage::fake('public');

    $this->seed(MenuExampleSeeder::class);

    $tree = app(MenuBuilder::class)->tree('header');
    expect(array_column($tree, 'label'))->toBe(['Categories', 'Information', 'Contact'])
        ->and($tree[0]['style'])->toBe('mega')
        ->and(array_column($tree[0]['children'][1]['children'], 'description'))->toBe(['Rs 1299', 'Rs 1299']);
    Storage::disk('public')->assertExists('menus/examples/goku-shadow-hoodie.png');
});
