<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            [
                'domain' => 'reuters.com',
                'name' => 'Reuters',
                'description' => 'International news agency providing breaking news and analysis',
                'url' => 'https://www.reuters.com',
                'credibility_score' => 0.95,
                'category' => 'news',
                'language' => 'en',
                'country' => 'US',
                'is_verified' => true,
                'is_active' => true,
            ],
            [
                'domain' => 'bbc.com',
                'name' => 'BBC News',
                'description' => 'British Broadcasting Corporation news service',
                'url' => 'https://www.bbc.com/news',
                'credibility_score' => 0.93,
                'category' => 'news',
                'language' => 'en',
                'country' => 'GB',
                'is_verified' => true,
                'is_active' => true,
            ],
            [
                'domain' => 'ap.org',
                'name' => 'Associated Press',
                'description' => 'American not-for-profit news agency',
                'url' => 'https://ap.org',
                'credibility_score' => 0.94,
                'category' => 'news',
                'language' => 'en',
                'country' => 'US',
                'is_verified' => true,
                'is_active' => true,
            ],
            [
                'domain' => 'theguardian.com',
                'name' => 'The Guardian',
                'description' => 'British daily newspaper and website',
                'url' => 'https://www.theguardian.com',
                'credibility_score' => 0.88,
                'category' => 'news',
                'language' => 'en',
                'country' => 'GB',
                'is_verified' => true,
                'is_active' => true,
            ],
            [
                'domain' => 'nytimes.com',
                'name' => 'The New York Times',
                'description' => 'American daily newspaper based in New York City',
                'url' => 'https://www.nytimes.com',
                'credibility_score' => 0.91,
                'category' => 'news',
                'language' => 'en',
                'country' => 'US',
                'is_verified' => true,
                'is_active' => true,
            ],
        ];

        foreach ($sources as $source) {
            Source::create($source);
        }
    }
}
