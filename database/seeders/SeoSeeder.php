<?php

namespace Database\Seeders;

use App\Models\Seo;
use Illuminate\Database\Seeder;

class SeoSeeder extends Seeder
{
    public function run(): void
    {
        Seo::updateOrCreate(
            ['slug' => 'default-seo'],
            [
                'page' => 'Default SEO',
                'meta_title' => config('app.name').' | Welcome',
                'meta_description' => 'This is the default SEO description for the website.',
            ]
        );
    }
}
