<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('laravel-rag.connection', 'pgsql');
        $table = config('laravel-rag.tables.nodes', 'rag_knowledge_nodes');

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        $connection = config('laravel-rag.connection', 'pgsql');
        $table = config('laravel-rag.tables.nodes', 'rag_knowledge_nodes');

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            $table->dropColumn('content_hash');
        });
    }
};
