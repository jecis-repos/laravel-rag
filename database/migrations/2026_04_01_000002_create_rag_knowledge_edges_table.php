<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('laravel-rag.connection', 'pgsql');
        $table = config('laravel-rag.tables.edges', 'rag_knowledge_edges');

        Schema::connection($connection)->create($table, function (Blueprint $table) {
            $table->id();
            $table->string('source_path', 500)->index();
            $table->string('target_path', 500)->index();
            $table->string('edge_type', 50)->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_path', 'target_path', 'edge_type']);
        });
    }

    public function down(): void
    {
        $connection = config('laravel-rag.connection', 'pgsql');
        $table = config('laravel-rag.tables.edges', 'rag_knowledge_edges');

        Schema::connection($connection)->dropIfExists($table);
    }
};
