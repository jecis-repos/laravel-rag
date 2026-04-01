<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Tests\Extractors;

use Jekabs\LaravelRag\Extractors\RelationshipExtractor;
use PHPUnit\Framework\TestCase;

final class RelationshipExtractorTest extends TestCase
{
    private RelationshipExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new RelationshipExtractor();
    }

    public function test_extracts_has_many(): void
    {
        $code = <<<'PHP'
        <?php
        class User extends Model
        {
            public function posts()
            {
                return $this->hasMany(Post::class);
            }
        }
        PHP;

        $result = $this->extractor->extract('User.php', $code);

        $this->assertCount(1, $result->nodes);
        $this->assertSame('relationship', $result->nodes[0]->type);
        $this->assertSame('posts', $result->nodes[0]->name);
        $this->assertSame('hasMany', $result->nodes[0]->metadata['relation_type']);
        $this->assertSame('Post', $result->nodes[0]->metadata['related_model']);

        $this->assertCount(1, $result->edges);
        $this->assertSame('hasMany', $result->edges[0]->edgeType);
        $this->assertSame('Post', $result->edges[0]->targetPath);
    }

    public function test_extracts_belongs_to(): void
    {
        $code = <<<'PHP'
        <?php
        class Post extends Model
        {
            public function author()
            {
                return $this->belongsTo(User::class);
            }
        }
        PHP;

        $result = $this->extractor->extract('Post.php', $code);

        $this->assertSame('belongsTo', $result->nodes[0]->metadata['relation_type']);
        $this->assertSame('User', $result->edges[0]->targetPath);
    }

    public function test_extracts_morph_many(): void
    {
        $code = <<<'PHP'
        <?php
        class Post extends Model
        {
            public function comments()
            {
                return $this->morphMany(Comment::class, 'commentable');
            }
        }
        PHP;

        $result = $this->extractor->extract('Post.php', $code);

        $this->assertSame('morphMany', $result->nodes[0]->metadata['relation_type']);
    }

    public function test_extracts_multiple_relationships(): void
    {
        $code = <<<'PHP'
        <?php
        class User extends Model
        {
            public function posts() { return $this->hasMany(Post::class); }
            public function profile() { return $this->hasOne(Profile::class); }
            public function roles() { return $this->belongsToMany(Role::class); }
        }
        PHP;

        $result = $this->extractor->extract('User.php', $code);

        $this->assertCount(3, $result->nodes);
        $this->assertCount(3, $result->edges);
    }
}
