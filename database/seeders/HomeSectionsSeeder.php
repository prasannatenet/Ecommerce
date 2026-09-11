<?php

namespace Database\Seeders;

use App\Models\HomeSection;
use Illuminate\Database\Seeder;

class HomeSectionsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'section_key' => 'featured',
                'title' => 'Earring Collection',
                'subtext' => 'Discover our latest collection of handcrafted jewellery.',
                'cta_text' => 'Explore Collection',
                'cta_link' => '/categories',
                'is_active' => true,
            ],
            [
                'section_key' => 'large-image',
                'title' => null,
                'subtext' => null,
                'cta_text' => null,
                'cta_link' => null,
                'is_active' => true,
            ],
        ];

        foreach ($defaults as $defaultsRow) {
            HomeSection::updateOrCreate(
                ['section_key' => $defaultsRow['section_key']],
                $defaultsRow,
            );
        }
    }
}
