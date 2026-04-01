<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeEdgeModel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('laravel-rag.tables.edges', 'rag_knowledge_edges');
    }

    public function getConnectionName(): ?string
    {
        return config('laravel-rag.connection', 'pgsql');
    }
}
