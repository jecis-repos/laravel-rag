<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Tests;

use Jekabs\LaravelRag\Extractors\ClassExtractor;
use Jekabs\LaravelRag\Extractors\ConfigExtractor;
use Jekabs\LaravelRag\Extractors\EventListenerExtractor;
use Jekabs\LaravelRag\Extractors\FilamentResourceExtractor;
use Jekabs\LaravelRag\Extractors\MethodExtractor;
use Jekabs\LaravelRag\Extractors\MiddlewareExtractor;
use Jekabs\LaravelRag\Extractors\MigrationExtractor;
use Jekabs\LaravelRag\Extractors\MorphMapExtractor;
use Jekabs\LaravelRag\Extractors\RelationshipExtractor;
use Jekabs\LaravelRag\Extractors\RouteExtractor;
use Jekabs\LaravelRag\Extractors\ServiceBindingExtractor;
use Jekabs\LaravelRag\Extractors\ValidationRuleExtractor;
use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Search\BM25;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests: realistic Laravel code through all 12 extractors
 * and BM25 search pipeline.
 */
final class EndToEndTest extends TestCase
{
    // ──────────────────────────────────────
    // Full Eloquent Model with relationships, scopes, accessors
    // ──────────────────────────────────────
    public function test_full_eloquent_model(): void
    {
        $code = <<<'PHP'
        <?php

        namespace App\Models;

        use Illuminate\Database\Eloquent\Model;
        use Illuminate\Database\Eloquent\SoftDeletes;
        use Illuminate\Database\Eloquent\Relations\HasMany;
        use Illuminate\Database\Eloquent\Relations\BelongsTo;

        final class Lead extends Model
        {
            use SoftDeletes;

            protected $fillable = ['name', 'email', 'status', 'assigned_to'];

            public function activities(): HasMany
            {
                return $this->hasMany(Activity::class);
            }

            public function assignee(): BelongsTo
            {
                return $this->belongsTo(User::class, 'assigned_to');
            }

            public function comments(): HasMany
            {
                return $this->morphMany(Comment::class, 'commentable');
            }

            public function scopeActive($query)
            {
                return $query->where('status', 'active');
            }

            public function getFullNameAttribute(): string
            {
                return $this->first_name . ' ' . $this->last_name;
            }
        }
        PHP;

        $classResult = (new ClassExtractor())->extract('app/Models/Lead.php', $code);
        $methodResult = (new MethodExtractor())->extract('app/Models/Lead.php', $code);
        $relResult = (new RelationshipExtractor())->extract('app/Models/Lead.php', $code);

        // Class extraction
        $this->assertCount(1, $classResult->nodes);
        $this->assertSame('App\Models\Lead', $classResult->nodes[0]->name);
        $this->assertSame('Model', $classResult->nodes[0]->metadata['extends']);

        // Method extraction — should find all public/protected methods
        $this->assertGreaterThanOrEqual(5, count($methodResult->nodes));
        $methodNames = array_map(fn ($n) => $n->name, $methodResult->nodes);
        $this->assertContains('activities', $methodNames);
        $this->assertContains('assignee', $methodNames);
        $this->assertContains('scopeActive', $methodNames);
        $this->assertContains('getFullNameAttribute', $methodNames);

        // Relationship extraction
        $this->assertCount(3, $relResult->nodes);
        $relTypes = array_map(fn ($n) => $n->metadata['relation_type'], $relResult->nodes);
        $this->assertContains('hasMany', $relTypes);
        $this->assertContains('belongsTo', $relTypes);
        $this->assertContains('morphMany', $relTypes);

        // Edges point to related models
        $edgeTargets = array_map(fn ($e) => $e->targetPath, $relResult->edges);
        $this->assertContains('Activity', $edgeTargets);
        $this->assertContains('User', $edgeTargets);
        $this->assertContains('Comment', $edgeTargets);
    }

    // ──────────────────────────────────────
    // ServiceProvider with morph map + bindings + events
    // ──────────────────────────────────────
    public function test_service_provider_full(): void
    {
        $code = <<<'PHP'
        <?php

        namespace App\Providers;

        use Illuminate\Support\ServiceProvider;
        use Illuminate\Database\Eloquent\Relations\Relation;
        use App\Models\Lead;
        use App\Models\Vehicle;
        use App\Contracts\LeadRepositoryContract;
        use App\Repositories\LeadRepository;

        class AppServiceProvider extends ServiceProvider
        {
            public function register(): void
            {
                $this->app->singleton(LeadRepositoryContract::class, LeadRepository::class);
                $this->app->bind('vehicle.service', VehicleService::class);
            }

            public function boot(): void
            {
                Relation::enforceMorphMap([
                    'lead' => Lead::class,
                    'vehicle' => Vehicle::class,
                ]);
            }
        }
        PHP;

        $morphResult = (new MorphMapExtractor())->extract('app/Providers/AppServiceProvider.php', $code);
        $bindResult = (new ServiceBindingExtractor())->extract('app/Providers/AppServiceProvider.php', $code);

        // Morph map
        $this->assertCount(1, $morphResult->nodes);
        $this->assertSame('morph_map', $morphResult->nodes[0]->type);
        $this->assertCount(2, $morphResult->edges);
        $morphTargets = array_map(fn ($e) => $e->targetPath, $morphResult->edges);
        $this->assertContains('Lead', $morphTargets);
        $this->assertContains('Vehicle', $morphTargets);

        // Service bindings
        $this->assertCount(2, $bindResult->nodes);
        $bindTypes = array_map(fn ($n) => $n->metadata['bind_type'], $bindResult->nodes);
        $this->assertContains('singleton', $bindTypes);
        $this->assertContains('bind', $bindTypes);

        // Binding edges
        $this->assertGreaterThanOrEqual(1, count($bindResult->edges));
    }

    // ──────────────────────────────────────
    // EventServiceProvider with $listen array
    // ──────────────────────────────────────
    public function test_event_listener_extraction(): void
    {
        $code = <<<'PHP'
        <?php

        namespace App\Providers;

        use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
        use App\Events\LeadCreated;
        use App\Events\LeadAssigned;
        use App\Listeners\SendLeadNotification;
        use App\Listeners\UpdateLeadStats;
        use App\Listeners\NotifyAssignee;

        class EventServiceProvider extends ServiceProvider
        {
            protected $listen = [
                LeadCreated::class => [
                    SendLeadNotification::class,
                    UpdateLeadStats::class,
                ],
                LeadAssigned::class => [
                    NotifyAssignee::class,
                ],
            ];
        }
        PHP;

        $result = (new EventListenerExtractor())->extract('app/Providers/EventServiceProvider.php', $code);

        $this->assertCount(2, $result->nodes);
        $this->assertCount(3, $result->edges); // 2 listeners for LeadCreated + 1 for LeadAssigned
        $this->assertSame('event_binding', $result->nodes[0]->type);
    }

    // ──────────────────────────────────────
    // Route file
    // ──────────────────────────────────────
    public function test_route_extraction(): void
    {
        $code = <<<'PHP'
        <?php

        use Illuminate\Support\Facades\Route;
        use App\Http\Controllers\LeadController;
        use App\Http\Controllers\VehicleController;

        Route::get('/leads', [LeadController::class, 'index']);
        Route::post('/leads', [LeadController::class, 'store']);
        Route::apiResource('vehicles', VehicleController::class);
        PHP;

        $result = (new RouteExtractor())->extract('routes/api.php', $code);

        $this->assertCount(3, $result->nodes);

        $names = array_map(fn ($n) => $n->name, $result->nodes);
        $this->assertContains('GET /leads', $names);
        $this->assertContains('POST /leads', $names);

        // Should create routes_to edges to controllers
        $edgeTargets = array_map(fn ($e) => $e->targetPath, $result->edges);
        $this->assertContains('LeadController', $edgeTargets);
        $this->assertContains('VehicleController', $edgeTargets);
    }

    // ──────────────────────────────────────
    // Migration
    // ──────────────────────────────────────
    public function test_migration_extraction(): void
    {
        $code = <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('leads', function (Blueprint $table) {
                    $table->id();
                    $table->string('name');
                    $table->string('email')->unique();
                    $table->foreignId('assigned_to')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        };
        PHP;

        $result = (new MigrationExtractor())->extract(
            '2026_01_01_000001_create_leads_table.php',
            $code,
        );

        $this->assertCount(1, $result->nodes);
        $this->assertSame('migration', $result->nodes[0]->type);
        $this->assertSame('create:leads', $result->nodes[0]->name);
        $this->assertSame('leads', $result->nodes[0]->metadata['table']);
    }

    // ──────────────────────────────────────
    // Config file
    // ──────────────────────────────────────
    public function test_config_extraction(): void
    {
        $code = <<<'PHP'
        <?php

        return [
            'default' => env('QUEUE_CONNECTION', 'redis'),
            'connections' => [
                'redis' => [
                    'driver' => 'redis',
                    'connection' => 'default',
                    'queue' => 'default',
                ],
            ],
        ];
        PHP;

        $result = (new ConfigExtractor())->extract('config/queue.php', $code);

        $this->assertCount(1, $result->nodes);
        $this->assertSame('config', $result->nodes[0]->type);
        $this->assertSame('queue', $result->nodes[0]->name);
    }

    // ──────────────────────────────────────
    // Filament Resource
    // ──────────────────────────────────────
    public function test_filament_resource_extraction(): void
    {
        $code = <<<'PHP'
        <?php

        namespace App\Filament\Resources;

        use Filament\Resources\Resource;
        use App\Models\Lead;

        class LeadResource extends Resource
        {
            protected static ?string $model = Lead::class;

            public static function form(Form $form): Form
            {
                return $form->schema([]);
            }

            public static function table(Table $table): Table
            {
                return $table->columns([]);
            }
        }
        PHP;

        $result = (new FilamentResourceExtractor())->extract(
            'app/Filament/Resources/LeadResource.php',
            $code,
        );

        $this->assertCount(1, $result->nodes);
        $this->assertSame('filament_resource', $result->nodes[0]->type);
        $this->assertSame('Lead', $result->nodes[0]->metadata['model']);
        $this->assertCount(1, $result->edges);
        $this->assertSame('manages_model', $result->edges[0]->edgeType);
    }

    // ──────────────────────────────────────
    // Middleware
    // ──────────────────────────────────────
    public function test_middleware_extraction(): void
    {
        $code = <<<'PHP'
        <?php

        namespace App\Http\Middleware;

        use Closure;
        use Illuminate\Http\Request;

        class EnsureTenantAccess
        {
            public function handle(Request $request, Closure $next)
            {
                if (!$request->user()->canAccessTenant($request->route('tenant'))) {
                    abort(403);
                }

                return $next($request);
            }
        }
        PHP;

        $result = (new MiddlewareExtractor())->extract(
            'app/Http/Middleware/EnsureTenantAccess.php',
            $code,
        );

        $this->assertCount(1, $result->nodes);
        $this->assertSame('middleware', $result->nodes[0]->type);
        $this->assertStringContainsString('EnsureTenantAccess', $result->nodes[0]->name);
    }

    // ──────────────────────────────────────
    // Validation Rule
    // ──────────────────────────────────────
    public function test_validation_rule_extraction(): void
    {
        $code = <<<'PHP'
        <?php

        namespace App\Rules;

        use Illuminate\Contracts\Validation\ValidationRule;

        class PhoneNumber implements ValidationRule
        {
            public function validate(string $attribute, mixed $value, \Closure $fail): void
            {
                if (!preg_match('/^\+?[1-9]\d{6,14}$/', $value)) {
                    $fail('The :attribute must be a valid phone number.');
                }
            }
        }
        PHP;

        $result = (new ValidationRuleExtractor())->extract('app/Rules/PhoneNumber.php', $code);

        $this->assertCount(1, $result->nodes);
        $this->assertSame('validation_rule', $result->nodes[0]->type);
        $this->assertStringContainsString('PhoneNumber', $result->nodes[0]->name);
    }

    // ──────────────────────────────────────
    // All extractors combined on a single file
    // ──────────────────────────────────────
    public function test_all_extractors_combined(): void
    {
        $code = <<<'PHP'
        <?php

        namespace App\Models;

        use Illuminate\Database\Eloquent\Model;

        final class Order extends Model
        {
            protected $fillable = ['total', 'status'];

            public function items()
            {
                return $this->hasMany(OrderItem::class);
            }

            public function customer()
            {
                return $this->belongsTo(Customer::class);
            }

            public function calculateTotal(): float
            {
                return $this->items->sum('price');
            }
        }
        PHP;

        $extractors = [
            new ClassExtractor(),
            new MethodExtractor(),
            new RelationshipExtractor(),
        ];

        $combined = new ExtractionResult();
        foreach ($extractors as $ex) {
            if ($ex->supports('app/Models/Order.php', $code)) {
                $combined = $combined->merge($ex->extract('app/Models/Order.php', $code));
            }
        }

        // Should have: 1 class + 3 methods + 2 relationships = 6 nodes
        $this->assertCount(6, $combined->nodes);

        // Should have: 1 extends edge + 2 relationship edges = 3 edges
        $types = array_map(fn ($n) => $n->type, $combined->nodes);
        $this->assertContains('class', $types);
        $this->assertContains('method', $types);
        $this->assertContains('relationship', $types);

        $edgeTypes = array_map(fn ($e) => $e->edgeType, $combined->edges);
        $this->assertContains('extends', $edgeTypes);
        $this->assertContains('hasMany', $edgeTypes);
        $this->assertContains('belongsTo', $edgeTypes);
    }

    // ──────────────────────────────────────
    // BM25 full pipeline — index and search like RAG would
    // ──────────────────────────────────────
    public function test_bm25_search_pipeline(): void
    {
        $bm25 = new BM25();

        // Simulate indexing 5 knowledge nodes from different files
        $docs = [
            0 => 'class LeadController extends Controller handles lead CRUD operations create update delete',
            1 => 'class VehicleService calculates pricing insurance quotes financing lease terms',
            2 => 'function activities returns hasMany Activity relationship on Lead model',
            3 => 'Schema create leads table id name email assigned_to timestamps soft_deletes migration',
            4 => 'class SalesKpiService computes conversion rates pipeline metrics lead scoring',
        ];

        foreach ($docs as $id => $text) {
            $bm25->addDocument($id, $text);
        }

        // Search for "lead" — should match docs 0, 2, 3, 4
        $scores = [];
        foreach ($docs as $id => $text) {
            $scores[$id] = $bm25->score($id, 'lead');
        }

        // LeadController should score (direct name match + "lead" in text)
        $this->assertGreaterThan(0.0, $scores[0]);
        // VehicleService should score 0 (no match for "lead")
        $this->assertSame(0.0, $scores[1]);
        // Lead activities should match ("Lead" lowercased = "lead")
        $this->assertGreaterThan(0.0, $scores[2]);
        // Leads migration: "leads" != "lead" (no stemming), scores 0
        $this->assertSame(0.0, $scores[3]);
        // SalesKpiService with "lead scoring" should match
        $this->assertGreaterThan(0.0, $scores[4]);

        // Multi-term query: "lead conversion" should favor SalesKpiService
        $convScores = [];
        foreach ($docs as $id => $text) {
            $convScores[$id] = $bm25->score($id, 'lead conversion');
        }
        // SalesKpiService has both "lead" and "conversion"
        $this->assertGreaterThan($convScores[0], $convScores[4]);
    }

    // ──────────────────────────────────────
    // Extractor skips non-matching files
    // ──────────────────────────────────────
    public function test_extractors_skip_irrelevant_files(): void
    {
        $jsCode = 'export default function App() { return <div>Hello</div> }';
        $cssCode = '.lead { color: red; }';
        $mdCode = '# Lead Management\n\nThis is the lead module.';

        $extractors = [
            new ClassExtractor(),
            new MethodExtractor(),
            new RelationshipExtractor(),
            new RouteExtractor(),
            new MigrationExtractor(),
        ];

        foreach ($extractors as $ex) {
            $this->assertFalse($ex->supports('app.js', $jsCode), get_class($ex) . ' should not support JS');
            $this->assertFalse($ex->supports('style.css', $cssCode), get_class($ex) . ' should not support CSS');
            $this->assertFalse($ex->supports('README.md', $mdCode), get_class($ex) . ' should not support MD');
        }
    }

    // ──────────────────────────────────────
    // Enum extraction (PHP 8.1+)
    // ──────────────────────────────────────
    public function test_enum_extraction(): void
    {
        $code = <<<'PHP'
        <?php

        namespace App\Enums;

        enum LeadStatus: string
        {
            case New = 'new';
            case Contacted = 'contacted';
            case Qualified = 'qualified';
            case Lost = 'lost';
            case Won = 'won';

            public function isTerminal(): bool
            {
                return in_array($this, [self::Lost, self::Won]);
            }

            public function color(): string
            {
                return match($this) {
                    self::New => 'blue',
                    self::Contacted => 'yellow',
                    self::Qualified => 'green',
                    self::Lost => 'red',
                    self::Won => 'emerald',
                };
            }
        }
        PHP;

        $classResult = (new ClassExtractor())->extract('app/Enums/LeadStatus.php', $code);
        $methodResult = (new MethodExtractor())->extract('app/Enums/LeadStatus.php', $code);

        $this->assertCount(1, $classResult->nodes);
        $this->assertSame('enum', $classResult->nodes[0]->metadata['class_type']);

        // Should extract the enum methods
        $methodNames = array_map(fn ($n) => $n->name, $methodResult->nodes);
        $this->assertContains('isTerminal', $methodNames);
        $this->assertContains('color', $methodNames);
    }
}
