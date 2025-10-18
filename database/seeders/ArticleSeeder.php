<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Source;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = Source::all();
        
        if ($sources->isEmpty()) {
            $this->command->info('No sources found. Please run SourceSeeder first.');
            return;
        }

        $articles = [
            [
                'title' => 'Breaking: Major Technology Breakthrough Announced',
                'content' => 'Scientists have announced a major breakthrough in quantum computing technology that could revolutionize the way we process information. The new system demonstrates unprecedented stability and processing power, opening doors to applications previously thought impossible. This development represents years of research and collaboration between leading institutions worldwide.',
                'url' => 'https://example.com/tech-breakthrough',
                'author' => 'Tech Reporter',
                'published_at' => now()->subDays(1),
            ],
            [
                'title' => 'Climate Change: New Study Shows Alarming Trends',
                'content' => 'A comprehensive new study published in Nature reveals alarming trends in global climate patterns. The research, conducted over five years across multiple continents, shows accelerating rates of temperature increase and ecosystem disruption. Scientists warn that immediate action is required to prevent irreversible damage to our planet\'s delicate environmental balance.',
                'url' => 'https://example.com/climate-study',
                'author' => 'Environmental Correspondent',
                'published_at' => now()->subDays(2),
            ],
            [
                'title' => 'Economic Recovery Shows Strong Momentum',
                'content' => 'Latest economic indicators suggest a robust recovery is underway across major global markets. Employment figures have improved significantly, with unemployment rates dropping to pre-pandemic levels in several key regions. Consumer confidence is rising, and business investment is showing renewed vigor as companies adapt to the new economic landscape.',
                'url' => 'https://example.com/economic-recovery',
                'author' => 'Business Analyst',
                'published_at' => now()->subDays(3),
            ],
            [
                'title' => 'Space Exploration: Mission to Mars Reaches Milestone',
                'content' => 'The international Mars exploration mission has reached a critical milestone, successfully deploying advanced scientific instruments on the red planet\'s surface. The mission aims to search for signs of ancient life and study the planet\'s geology and atmosphere. This achievement represents a significant step forward in humanity\'s quest to understand our solar system.',
                'url' => 'https://example.com/mars-mission',
                'author' => 'Space Correspondent',
                'published_at' => now()->subDays(4),
            ],
            [
                'title' => 'Healthcare Innovation: New Treatment Shows Promise',
                'content' => 'Medical researchers have developed a promising new treatment approach that could help millions of patients worldwide. The innovative therapy combines cutting-edge biotechnology with personalized medicine principles, showing remarkable results in early clinical trials. The treatment targets previously untreatable conditions and offers hope for improved patient outcomes.',
                'url' => 'https://example.com/healthcare-innovation',
                'author' => 'Medical Reporter',
                'published_at' => now()->subDays(5),
            ],
        ];

        foreach ($articles as $articleData) {
            $source = $sources->random();
            $content = $articleData['content'];
            
            Article::create([
                'source_id' => $source->id,
                'url' => $articleData['url'],
                'title' => $articleData['title'],
                'content' => $content,
                'author' => $articleData['author'],
                'published_at' => $articleData['published_at'],
                'crawled_at' => now(),
                'content_hash' => hash('sha256', $content),
                'is_processed' => true,
                'is_duplicate' => false,
            ]);
        }

        $this->command->info('Created ' . count($articles) . ' sample articles.');
    }
}
