<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeNodeModel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'start_line' => 'integer',
        'end_line' => 'integer',
    ];

    public function getTable(): string
    {
        return config('laravel-rag.tables.nodes', 'rag_knowledge_nodes');
    }

    public function getConnectionName(): ?string
    {
        return config('laravel-rag.connection', 'pgsql');
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(KnowledgeEdgeModel::class, 'source_path', 'source_path');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(KnowledgeEdgeModel::class, 'target_path', 'source_path');
    }
}
