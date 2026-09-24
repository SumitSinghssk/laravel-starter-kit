<?php

namespace Database\Seeders;

use App\Models\Enquiry;
use Illuminate\Database\Seeder;

class EnquirySeeder extends Seeder
{
    public function run(): void
    {
        Enquiry::factory(50)->create();

        $this->command->info('✅ 50 dummy enquiries created!');
    }
}
