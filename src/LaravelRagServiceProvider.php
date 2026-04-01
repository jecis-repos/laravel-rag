<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag;

use Illuminate\Support\ServiceProvider;
use Jekabs\LaravelRag\Contracts\EmbeddingDriverContract;
use Jekabs\LaravelRag\Contracts\ExtractorContract;
use Jekabs\LaravelRag\Search\KnowledgeSearch;

class LaravelRagServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-rag.php', 'laravel-rag');

        // Embedding driver
        $this->app->singleton(EmbeddingDriverContract::class, function () {
            $driver = config('laravel-rag.embedding_driver', 'null');

            if ($driver === 'null') {
                return new NullEmbeddingDriver();
            }

            // Allow custom driver class name
            if (class_exists($driver)) {
                return $this->app->make($driver);
            }

            return new NullEmbeddingDriver();
        });

        // Indexer
        $this->app->singleton(Indexer::class, function () {
            $indexer = new Indexer($this->app->make(EmbeddingDriverContract::class));

            foreach (config('laravel-rag.extractors', []) as $extractorClass) {
                if (class_exists($extractorClass) && is_subclass_of($extractorClass, ExtractorContract::class)) {
                    $indexer->addExtractor(new $extractorClass());
                }
            }

            return $indexer;
        });

        // Search
        $this->app->singleton(KnowledgeSearch::class, function () {
            return new KnowledgeSearch($this->app->make(EmbeddingDriverContract::class));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/laravel-rag.php' => config_path('laravel-rag.php'),
        ], 'laravel-rag-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'laravel-rag-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
