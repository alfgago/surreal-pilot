<?php

namespace App\Tools;

use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\System\AgentContext;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class KnowledgeBaseTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'knowledge_base_search',
            'description' => 'Search the knowledge base for specific engine concepts, patterns, and best practices',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'engine' => [
                        'type' => 'string',
                        'enum' => ['playcanvas', 'unreal'],
                        'description' => 'The game engine to search knowledge for',
                    ],
                    'topic' => [
                        'type' => 'string',
                        'description' => 'The specific topic to search for (e.g., "input handling", "performance", "animation")',
                    ],
                    'category' => [
                        'type' => 'string',
                        'enum' => ['core-concepts', 'blueprint-patterns', 'performance', 'examples', 'troubleshooting'],
                        'description' => 'The category of knowledge to search in',
                        'required' => false,
                    ],
                ],
                'required' => ['engine', 'topic'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, \Vizra\VizraADK\Memory\AgentMemory $memory): string
    {
        $engine = $arguments['engine'];
        $topic = strtolower($arguments['topic']);
        $category = $arguments['category'] ?? null;

        try {
            // Get knowledge base path
            $knowledgeBasePath = storage_path("knowledge-base/{$engine}");
            
            if (!File::exists($knowledgeBasePath)) {
                return $this->formatError("Knowledge base not found for engine: {$engine}");
            }

            $results = [];
            $files = File::allFiles($knowledgeBasePath);

            foreach ($files as $file) {
                // Filter by category if specified
                if ($category && !str_contains($file->getFilenameWithoutExtension(), $category)) {
                    continue;
                }

                $content = File::get($file->getPathname());
                
                // Search for topic in content (case-insensitive)
                if (str_contains(strtolower($content), $topic)) {
                    $results[] = [
                        'file' => $file->getFilenameWithoutExtension(),
                        'relevance' => $this->calculateRelevance($content, $topic),
                        'excerpt' => $this->extractRelevantExcerpt($content, $topic),
                        'full_content' => $content,
                    ];
                }
            }

            if (empty($results)) {
                return $this->formatError("No knowledge found for topic '{$topic}' in {$engine} engine.");
            }

            // Sort by relevance
            usort($results, fn($a, $b) => $b['relevance'] <=> $a['relevance']);

            return $this->formatResults($results, $engine, $topic);

        } catch (\Exception $e) {
            return $this->formatError("Error searching knowledge base: " . $e->getMessage());
        }
    }

    private function calculateRelevance(string $content, string $topic): float
    {
        $topic = strtolower($topic);
        $content = strtolower($content);
        
        // Count occurrences
        $directMatches = substr_count($content, $topic);
        
        // Bonus for matches in headers
        $headerMatches = preg_match_all('/#+.*' . preg_quote($topic, '/') . '.*$/im', $content);
        
        // Bonus for code block matches
        $codeMatches = preg_match_all('/```[^`]*' . preg_quote($topic, '/') . '[^`]*```/is', $content);
        
        return $directMatches + ($headerMatches * 2) + ($codeMatches * 1.5);
    }

    private function extractRelevantExcerpt(string $content, string $topic): string
    {
        $topic = preg_quote($topic, '/');
        
        // Try to find a paragraph containing the topic
        if (preg_match('/(.{0,200}' . $topic . '.{0,200})/uis', $content, $matches)) {
            return trim($matches[1]);
        }
        
        // Fallback to first 300 characters
        return trim(substr($content, 0, 300)) . '...';
    }

    private function formatResults(array $results, string $engine, string $topic): string
    {
        $output = "# Knowledge Base Results for '{$topic}' in {$engine}\n\n";
        
        foreach (array_slice($results, 0, 3) as $index => $result) {
            $output .= "## Result " . ($index + 1) . ": {$result['file']}\n";
            $output .= "**Relevance Score:** {$result['relevance']}\n\n";
            
            // Include the most relevant content
            if ($result['relevance'] > 5) {
                // High relevance - include full content
                $output .= $result['full_content'] . "\n\n";
            } else {
                // Lower relevance - include excerpt
                $output .= "**Excerpt:** {$result['excerpt']}\n\n";
            }
            
            $output .= "---\n\n";
        }

        return $output;
    }

    private function formatError(string $message): string
    {
        return json_encode([
            'status' => 'error',
            'message' => $message,
            'suggestion' => 'Try different search terms or check if the knowledge base is properly set up.',
        ]);
    }
}
