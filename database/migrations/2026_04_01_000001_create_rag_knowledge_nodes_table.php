<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('laravel-rag.connection', 'pgsql');
        $dimensions = config('laravel-rag.embedding_dimensions', 1536);
        $table = config('laravel-rag.tables.nodes', 'rag_knowledge_nodes');

        // Ensure pgvector extension exists
        DB::connection($connection)->statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::connection($connection)->create($table, function (Blueprint $table) {
            $table->id();
            $table->string('source_path', 500)->index();
            $table->string('type', 50)->index();
            $table->string('name', 500);
            $table->text('content');
            $table->integer('start_line')->default(0);
            $table->integer('end_line')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_path', 'type', 'name']);
        });

        // Add vector column (not supported by Blueprint)
        DB::connection($connection)->statement(
            "ALTER TABLE {$table} ADD COLUMN embedding vector({$dimensions})"
        );

        // HNSW index for cosine similarity
        DB::connection($connection)->statement(
            "CREATE INDEX {$table}_embedding_idx ON {$table} USING hnsw (embedding vector_cosine_ops)"
        );
    }

    public function down(): void
    {
        $connection = config('laravel-rag.connection', 'pgsql');
        $table = config('laravel-rag.tables.nodes', 'rag_knowledge_nodes');

        Schema::connection($connection)->dropIfExists($table);
    }
};
