<?php

// ===================================
// 1. MIGRATIONS
// ===================================

// database/migrations/2025_01_01_000001_create_tags_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->enum('type', ['video', 'creator', 'both'])->default('both');
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
            
            $table->index(['type', 'usage_count']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};

// database/migrations/2025_01_01_000002_create_videos_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_path');
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('duration')->nullable(); // in seconds
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            
            $table->index(['is_published', 'published_at']);
            $table->index(['creator_id', 'is_published']);
            $table->index('views_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};

// database/migrations/2025_01_01_000003_create_creators_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->unsignedInteger('followers_count')->default(0);
            $table->unsignedInteger('videos_count')->default(0);
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            
            $table->index('slug');
            $table->index(['is_verified', 'followers_count']);
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creators');
    }
};

// database/migrations/2025_01_01_000004_create_taggables_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable'); // taggable_id, taggable_type
            $table->timestamps();
            
            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
            $table->index(['taggable_type', 'taggable_id']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};

// database/migrations/2025_01_01_000005_create_categories_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['video', 'creator']);
            $table->json('tag_ids'); // Store array of tag IDs that form this category
            $table->string('tag_combination'); // Sorted string for quick lookups
            $table->unsignedInteger('item_count')->default(0);
            $table->timestamp('last_updated_at')->useCurrent();
            $table->timestamps();
            
            $table->index(['type', 'item_count']);
            $table->index('tag_combination');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

// ===================================
// 2. MODELS
// ===================================

// app/Models/Tag.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'usage_count',
    ];

    protected $casts = [
        'usage_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    // Polymorphic relationships
    public function videos(): MorphToMany
    {
        return $this->morphedByMany(Video::class, 'taggable');
    }

    public function creators(): MorphToMany
    {
        return $this->morphedByMany(Creator::class, 'taggable');
    }

    // Scope for filtering by type
    public function scopeForVideos($query)
    {
        return $query->whereIn('type', ['video', 'both']);
    }

    public function scopeForCreators($query)
    {
        return $query->whereIn('type', ['creator', 'both']);
    }

    public function scopePopular($query, $limit = 10)
    {
        return $query->orderBy('usage_count', 'desc')->limit($limit);
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    public function decrementUsage(): void
    {
        $this->decrement('usage_count');
    }
}

// app/Models/Video.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Builder;

class Video extends Model
{
    protected $fillable = [
        'creator_id',
        'title',
        'description',
        'video_path',
        'thumbnail_path',
        'duration',
        'views_count',
        'likes_count',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'duration' => 'integer',
        'views_count' => 'integer',
        'likes_count' => 'integer',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    // Scopes
    public function scopePublished($query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopePopular($query): Builder
    {
        return $query->orderBy('views_count', 'desc');
    }

    public function scopeRecent($query): Builder
    {
        return $query->orderBy('published_at', 'desc');
    }

    public function scopeWithTags($query, array $tagIds): Builder
    {
        return $query->whereHas('tags', function ($q) use ($tagIds) {
            $q->whereIn('tags.id', $tagIds);
        }, '=', count($tagIds)); // Must have ALL tags
    }

    public function scopeWithAnyTags($query, array $tagIds): Builder
    {
        return $query->whereHas('tags', function ($q) use ($tagIds) {
            $q->whereIn('tags.id', $tagIds);
        });
    }

    protected static function boot()
    {
        parent::boot();
        
        static::created(function ($video) {
            $video->creator->increment('videos_count');
        });
        
        static::deleted(function ($video) {
            $video->creator->decrement('videos_count');
        });
    }
}

// app/Models/Creator.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Creator extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'slug',
        'bio',
        'avatar_path',
        'banner_path',
        'followers_count',
        'videos_count',
        'rating',
        'is_verified',
    ];

    protected $casts = [
        'followers_count' => 'integer',
        'videos_count' => 'integer',
        'rating' => 'decimal:2',
        'is_verified' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($creator) {
            if (empty($creator->slug)) {
                $creator->slug = Str::slug($creator->display_name);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function publishedVideos(): HasMany
    {
        return $this->videos()->where('is_published', true);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    // Scopes
    public function scopeVerified($query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopePopular($query): Builder
    {
        return $query->orderBy('followers_count', 'desc');
    }

    public function scopeTopRated($query): Builder
    {
        return $query->orderBy('rating', 'desc');
    }

    public function scopeWithTags($query, array $tagIds): Builder
    {
        return $query->whereHas('tags', function ($q) use ($tagIds) {
            $q->whereIn('tags.id', $tagIds);
        }, '=', count($tagIds));
    }

    public function scopeWithAnyTags($query, array $tagIds): Builder
    {
        return $query->whereHas('tags', function ($q) use ($tagIds) {
            $q->whereIn('tags.id', $tagIds);
        });
    }
}

// app/Models/Category.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'tag_ids',
        'tag_combination',
        'item_count',
        'last_updated_at',
    ];

    protected $casts = [
        'tag_ids' => 'array',
        'item_count' => 'integer',
        'last_updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function scopeForVideos($query): Builder
    {
        return $query->where('type', 'video');
    }

    public function scopeForCreators($query): Builder
    {
        return $query->where('type', 'creator');
    }

    public function scopePopular($query): Builder
    {
        return $query->orderBy('item_count', 'desc');
    }

    public function getTags()
    {
        return Tag::whereIn('id', $this->tag_ids)->get();
    }

    public function getItems()
    {
        $tagIds = $this->tag_ids;
        
        if ($this->type === 'video') {
            return Video::published()->withTags($tagIds)->get();
        }
        
        return Creator::withTags($tagIds)->get();
    }
}

// ===================================
// 3. SERVICES
// ===================================

// app/Services/CategoryService.php
<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\Category;
use App\Models\Video;
use App\Models\Creator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    private const CACHE_TTL = 3600; // 1 hour
    
    public function syncVideoCategories(Video $video): void
    {
        $tagIds = $video->tags()->pluck('tags.id')->toArray();
        
        if (empty($tagIds)) {
            return;
        }

        $this->createCategoriesForTagCombinations($tagIds, 'video');
        $this->updateCategoryCounts('video');
        $this->clearVideoCache();
    }
    
    public function syncCreatorCategories(Creator $creator): void
    {
        $tagIds = $creator->tags()->pluck('tags.id')->toArray();
        
        if (empty($tagIds)) {
            return;
        }

        $this->createCategoriesForTagCombinations($tagIds, 'creator');
        $this->updateCategoryCounts('creator');
        $this->clearCreatorCache();
    }

    private function createCategoriesForTagCombinations(array $tagIds, string $type): void
    {
        $tags = Tag::whereIn('id', $tagIds)->get()->keyBy('id');
        $combinations = $this->generateCombinations($tagIds);

        foreach ($combinations as $combination) {
            $sortedCombination = collect($combination)->sort()->values()->toArray();
            $tagCombination = implode(',', $sortedCombination);
            
            // Check if category already exists
            $category = Category::where('tag_combination', $tagCombination)
                              ->where('type', $type)
                              ->first();
                              
            if (!$category) {
                $names = collect($sortedCombination)
                    ->map(fn($id) => $tags[$id]->name)
                    ->toArray();
                    
                Category::create([
                    'name' => implode(' + ', $names),
                    'type' => $type,
                    'tag_ids' => $sortedCombination,
                    'tag_combination' => $tagCombination,
                    'item_count' => 0,
                ]);
            }
        }
    }

    private function generateCombinations(array $tagIds): array
    {
        $combinations = [];
        $count = count($tagIds);
        
        // Generate all possible combinations (2^n - 1, excluding empty set)
        for ($i = 1; $i < (1 << $count); $i++) {
            $combination = [];
            for ($j = 0; $j < $count; $j++) {
                if ($i & (1 << $j)) {
                    $combination[] = $tagIds[$j];
                }
            }
            $combinations[] = $combination;
        }
        
        return $combinations;
    }

    private function updateCategoryCounts(string $type): void
    {
        $model = $type === 'video' ? Video::class : Creator::class;
        
        $categories = Category::where('type', $type)->get();
        
        foreach ($categories as $category) {
            $query = $model::query();
            
            if ($type === 'video') {
                $query->published();
            }
            
            $count = $query->withTags($category->tag_ids)->count();
            
            $category->update([
                'item_count' => $count,
                'last_updated_at' => now(),
            ]);
        }
    }

    public function getVideoCategories(int $page = 1, int $perPage = 20): array
    {
        $cacheKey = "video_categories_page_{$page}_{$perPage}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($page, $perPage) {
            return Category::forVideos()
                ->where('item_count', '>', 0)
                ->popular()
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get()
                ->toArray();
        });
    }

    public function getCreatorCategories(int $page = 1, int $perPage = 20): array
    {
        $cacheKey = "creator_categories_page_{$page}_{$perPage}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($page, $perPage) {
            return Category::forCreators()
                ->where('item_count', '>', 0)
                ->popular()
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get()
                ->toArray();
        });
    }

    public function getCategoryBySlug(string $slug): ?Category
    {
        $cacheKey = "category_slug_{$slug}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($slug) {
            return Category::where('slug', $slug)->first();
        });
    }

    private function clearVideoCache(): void
    {
        Cache::tags(['video_categories'])->flush();
    }

    private function clearCreatorCache(): void
    {
        Cache::tags(['creator_categories'])->flush();
    }

    public function rebuildAllCategories(): void
    {
        DB::beginTransaction();
        
        try {
            // Clear existing categories
            Category::truncate();
            
            // Rebuild video categories
            $videos = Video::published()->with('tags')->get();
            foreach ($videos as $video) {
                $this->syncVideoCategories($video);
            }
            
            // Rebuild creator categories  
            $creators = Creator::with('tags')->get();
            foreach ($creators as $creator) {
                $this->syncCreatorCategories($creator);
            }
            
            DB::commit();
            
            // Clear all caches
            $this->clearVideoCache();
            $this->clearCreatorCache();
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

// app/Services/TagService.php
<?php

namespace App\Services;

use App\Models\Tag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class TagService
{
    private const CACHE_TTL = 7200; // 2 hours

    public function findOrCreateTags(array $tagNames, string $type = 'both'): Collection
    {
        return collect($tagNames)->map(function ($name) use ($type) {
            return Tag::firstOrCreate(
                ['name' => trim($name)],
                ['type' => $type]
            );
        });
    }

    public function getPopularTags(string $type = 'both', int $limit = 50): Collection
    {
        $cacheKey = "popular_tags_{$type}_{$limit}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($type, $limit) {
            $query = Tag::query();
            
            if ($type !== 'both') {
                if ($type === 'video') {
                    $query->forVideos();
                } else {
                    $query->forCreators();
                }
            }
            
            return $query->popular($limit)->get();
        });
    }

    public function syncTagsToModel($model, array $tagNames): void
    {
        $type = $model instanceof \App\Models\Video ? 'video' : 'creator';
        $tags = $this->findOrCreateTags($tagNames, $type);
        
        // Get current tags to calculate usage changes
        $currentTags = $model->tags;
        $newTagIds = $tags->pluck('id')->toArray();
        $currentTagIds = $currentTags->pluck('id')->toArray();
        
        // Tags being removed
        $removedTagIds = array_diff($currentTagIds, $newTagIds);
        $removedTags = Tag::whereIn('id', $removedTagIds)->get();
        
        // Tags being added
        $addedTagIds = array_diff($newTagIds, $currentTagIds);
        $addedTags = Tag::whereIn('id', $addedTagIds)->get();
        
        // Sync the tags
        $model->tags()->sync($newTagIds);
        
        // Update usage counts
        foreach ($removedTags as $tag) {
            $tag->decrementUsage();
        }
        
        foreach ($addedTags as $tag) {
            $tag->incrementUsage();
        }
        
        // Clear relevant caches
        Cache::forget("popular_tags_{$type}_50");
        Cache::forget("popular_tags_both_50");
    }
}

// ===================================
// 4. CONTROLLERS
// ===================================

// app/Http/Controllers/VideoController.php
<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\CategoryService;
use App\Services\TagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VideoController extends Controller
{
    public function __construct(
        private CategoryService $categoryService,
        private TagService $tagService
    ) {}

    public function index(Request $request)
    {
        $videos = Video::published()
            ->with(['creator', 'tags'])
            ->when($request->tags, function ($query) use ($request) {
                $tagIds = explode(',', $request->tags);
                return $query->withAnyTags($tagIds);
            })
            ->when($request->sort === 'popular', fn($q) => $q->popular())
            ->when($request->sort === 'recent', fn($q) => $q->recent())
            ->paginate(20);

        $popularTags = $this->tagService->getPopularTags('video', 20);
        $categories = $this->categoryService->getVideoCategories();

        return view('videos.index', compact('videos', 'popularTags', 'categories'));
    }

    public function show(Video $video)
    {
        $video->load(['creator', 'tags']);
        $video->increment('views_count');

        $relatedVideos = Cache::remember(
            "related_videos_{$video->id}",
            3600,
            function () use ($video) {
                $tagIds = $video->tags->pluck('id')->toArray();
                
                return Video::published()
                    ->where('id', '!=', $video->id)
                    ->withAnyTags($tagIds)
                    ->limit(6)
                    ->get();
            }
        );

        return view('videos.show', compact('video', 'relatedVideos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_file' => 'required|file|mimes:mp4,mov,avi',
            'tags' => 'required|array|min:1',
            'tags.*' => 'string|max:50',
        ]);

        $video = Video::create([
            'creator_id' => auth()->user()->creator->id,
            'title' => $request->title,
            'description' => $request->description,
            'video_path' => $request->file('video_file')->store('videos'),
        ]);

        // Sync tags and update categories
        $this->tagService->syncTagsToModel($video, $request->tags);
        $this->categoryService->syncVideoCategories($video);

        return redirect()->route('videos.show', $video);
    }
}

// app/Http/Controllers/CreatorController.php
<?php

namespace App\Http\Controllers;

use App\Models\Creator;
use App\Services\CategoryService;
use App\Services\TagService;
use Illuminate\Http\Request;

class CreatorController extends Controller
{
    public function __construct(
        private CategoryService $categoryService,
        private TagService $tagService
    ) {}

    public function index(Request $request)
    {
        $creators = Creator::query()
            ->with('tags')
            ->when($request->tags, function ($query) use ($request) {
                $tagIds = explode(',', $request->tags);
                return $query->withAnyTags($tagIds);
            })
            ->when($request->verified, fn($q) => $q->verified())
            ->when($request->sort === 'popular', fn($q) => $q->popular())
            ->when($request->sort === 'rated', fn($q) => $q->topRated())
            ->paginate(20);

        $popularTags = $this->tagService->getPopularTags('creator', 20);
        $categories = $this->categoryService->getCreatorCategories();

        return view('creators.index', compact('creators', 'popularTags', 'categories'));
    }

    public function show(Creator $creator)
    {
        $creator->load(['tags', 'publishedVideos']);
        
        return view('creators.show', compact('creator'));
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'display_name' => 'required|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'tags' => 'required|array|min:1',
            'tags.*' => 'string|max:50',
        ]);

        $creator = auth()->user()->creator;
        
        $creator->update([
            'display_name' => $request->display_name,
            'bio' => $request->bio,
        ]);

        // Sync tags and update categories
        $this->tagService->syncTagsToModel($creator, $request->tags);
        $this->categoryService->syncCreatorCategories($creator);

        return redirect()->route('creators.show', $creator);
    }
}

// app/Http/Controllers/CategoryController.php
<?php

namespace App\Http\Controllers;

use App\Services\CategoryService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private CategoryService $categoryService
    ) {}

    public function videoCategories()
    {
        $categories = $this->categoryService->getVideoCategories(1, 50);
        
        return view('categories.videos', compact('categories'));
    }

    public function creatorCategories()
    {
        $categories = $this->categoryService->getCreatorCategories(1, 50);
        
        return view('categories.creators', compact('categories'));
    }

    public function show(string $slug)
    {
        $category = $this->categoryService->getCategoryBySlug($slug);
        
        if (!$category) {
            abort(404);
        }

        $items = $category->getItems();
        $tags = $category->getTags();

        return view('categories.show', compact('category', 'items', 'tags'));
    }
}

// ===================================
// 5. ROUTES
// ===================================

// routes/web.php
<?php

use App\Http\Controllers\VideoController;
use App\Http\Controllers\CreatorController;
use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Video routes
Route::prefix('videos')->name('videos.')->group(function () {
    Route::get('/', [VideoController::class, 'index'])->name('index');
    Route::get('/{video}', [VideoController::class, 'show'])->name('show');
    Route::post('/', [VideoController::class, 'store'])->name('store')->middleware('auth');
});

// Creator routes
Route::prefix('creators')->name('creators.')->group(function () {
    Route::get('/', [CreatorController::class, 'index'])->name('index');
    Route::get('/{creator}', [CreatorController::class, 'show'])->name('show');
    Route::put('/profile', [CreatorController::class, 'updateProfile'])->name('profile.update')->middleware('auth');
});

// Category routes
Route::prefix('categories')->name('categories.')->group(function () {
    Route::get('/videos', [CategoryController::class, 'videoCategories'])->name('videos');
    Route::get('/creators', [CategoryController::class, 'creatorCategories'])->name('creators');
    Route::get('/{slug}', [CategoryController::class, 'show'])->name('show');
});

// ===================================
// 6. CONSOLE COMMANDS
// ===================================

// app/Console/Commands/RebuildCategories.php
<?php

namespace App\Console\Commands;

use App\Services\CategoryService;
use Illuminate\Console\Command;

class RebuildCategories extends Command
{
    protected $signature = 'categories:rebuild';
    protected $description = 'Rebuild all video and creator categories';

    public function handle(CategoryService $categoryService): int
    {
        $this->info('Starting category rebuild...');
        
        try {
            $categoryService->rebuildAllCategories();
            $this->info('Categories rebuilt successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to rebuild categories: ' . $e->getMessage());
            return 1;
        }
    }
}

// ===================================
// 7. MODEL OBSERVERS
// ===================================

// app/Observers/VideoObserver.php
<?php

namespace App\Observers;

use App\Models\Video;
use App\Services\CategoryService;

class VideoObserver
{
    public function __construct(
        private CategoryService $categoryService
    ) {}

    public function updated(Video $video): void
    {
        if ($video->wasChanged('is_published') && $video->is_published) {
            $this->categoryService->syncVideoCategories($video);
        }
    }

    public function deleted(Video $video): void
    {
        // Update category counts when video is deleted
        $this->categoryService->syncVideoCategories($video);
    }
}

// app/Observers/CreatorObserver.php
<?php

namespace App\Observers;

use App\Models\Creator;
use App\Services\CategoryService;

class CreatorObserver
{
    public function __construct(
        private CategoryService $categoryService
    ) {}

    public function updated(Creator $creator): void
    {
        // Sync categories whenever creator is updated
        $this->categoryService->syncCreatorCategories($creator);
    }
}

// ===================================
// 8. SERVICE PROVIDER REGISTRATION
// ===================================

// app/Providers/AppServiceProvider.php (additions)
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Video;
use App\Models\Creator;
use App\Observers\VideoObserver;
use App\Observers\CreatorObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Set up morph map for polymorphic relationships
        Relation::enforceMorphMap([
            'video' => Video::class,
            'creator' => Creator::class,
        ]);

        // Register observers
        Video::observe(VideoObserver::class);
        Creator::observe(CreatorObserver::class);
    }
}

// ===================================
// 9. CONFIGURATION EXAMPLES
// ===================================

/*
// config/cache.php - Redis configuration for Laravel 12
'redis' => [
    'driver' => 'redis',
    'connection' => 'cache',
    'lock_connection' => 'default',
],

// .env file additions
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

// For production with cache tags support
CACHE_DRIVER=redis
REDIS_CLUSTER=true
*/

// ===================================
// 10. BLADE VIEW EXAMPLES
// ===================================

// resources/views/videos/index.blade.php
/*
@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4">
    <h1 class="text-3xl font-bold mb-6">Video Gallery</h1>
    
    <!-- Filter Section -->
    <div class="bg-white p-6 rounded-lg shadow mb-6">
        <h3 class="text-lg font-semibold mb-4">Filter by Tags</h3>
        <div class="flex flex-wrap gap-2">
            @foreach($popularTags as $tag)
                <a href="{{ request()->fullUrlWithQuery(['tags' => $tag->id]) }}" 
                   class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full hover:bg-blue-200 transition-colors">
                    {{ $tag->name }} ({{ $tag->usage_count }})
                </a>
            @endforeach
        </div>
    </div>

    <!-- Categories Section -->
    <div class="bg-white p-6 rounded-lg shadow mb-6">
        <h3 class="text-lg font-semibold mb-4">Browse Categories</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($categories as $category)
                <a href="{{ route('categories.show', $category['slug']) }}" 
                   class="p-4 border rounded-lg hover:shadow-md transition-shadow">
                    <h4 class="font-medium">{{ $category['name'] }}</h4>
                    <p class="text-sm text-gray-600">{{ $category['item_count'] }} videos</p>
                </a>
            @endforeach
        </div>
    </div>

    <!-- Videos Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @foreach($videos as $video)
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="aspect-video bg-gray-200 relative">
                    @if($video->thumbnail_path)
                        <img src="{{ Storage::url($video->thumbnail_path) }}" 
                             alt="{{ $video->title }}" 
                             class="w-full h-full object-cover">
                    @endif
                    <div class="absolute bottom-2 right-2 bg-black bg-opacity-75 text-white px-2 py-1 rounded text-xs">
                        {{ gmdate('i:s', $video->duration) }}
                    </div>
                </div>
                
                <div class="p-4">
                    <h3 class="font-semibold mb-2 line-clamp-2">{{ $video->title }}</h3>
                    <p class="text-sm text-gray-600 mb-2">by {{ $video->creator->display_name }}</p>
                    
                    <div class="flex flex-wrap gap-1 mb-3">
                        @foreach($video->tags->take(3) as $tag)
                            <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs">
                                {{ $tag->name }}
                            </span>
                        @endforeach
                        @if($video->tags->count() > 3)
                            <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs">
                                +{{ $video->tags->count() - 3 }}
                            </span>
                        @endif
                    </div>
                    
                    <div class="flex justify-between text-sm text-gray-500">
                        <span>{{ number_format($video->views_count) }} views</span>
                        <span>{{ $video->published_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Pagination -->
    <div class="mt-8">
        {{ $videos->links() }}
    </div>
</div>
@endsection
*/

// ===================================
// 11. PERFORMANCE OPTIMIZATION UTILITIES
// ===================================

// app/Services/CacheOptimizationService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CacheOptimizationService
{
    /**
     * Warm up frequently accessed cache entries
     */
    public function warmupCache(): void
    {
        $this->warmupPopularTags();
        $this->warmupTopCategories();
        $this->warmupTrendingContent();
    }

    private function warmupPopularTags(): void
    {
        // Cache popular tags for different types
        $types = ['video', 'creator', 'both'];
        $limits = [10, 20, 50];
        
        foreach ($types as $type) {
            foreach ($limits as $limit) {
                $cacheKey = "popular_tags_{$type}_{$limit}";
                if (!Cache::has($cacheKey)) {
                    app(TagService::class)->getPopularTags($type, $limit);
                }
            }
        }
    }

    private function warmupTopCategories(): void
    {
        // Cache top categories for videos and creators
        $categoryService = app(CategoryService::class);
        $categoryService->getVideoCategories(1, 20);
        $categoryService->getCreatorCategories(1, 20);
    }

    private function warmupTrendingContent(): void
    {
        // Cache trending videos and creators
        Cache::remember('trending_videos', 1800, function () {
            return \App\Models\Video::published()
                ->where('published_at', '>=', now()->subDays(7))
                ->orderBy('views_count', 'desc')
                ->limit(20)
                ->with(['creator', 'tags'])
                ->get();
        });

        Cache::remember('trending_creators', 1800, function () {
            return \App\Models\Creator::where('updated_at', '>=', now()->subDays(7))
                ->orderBy('followers_count', 'desc')
                ->limit(20)
                ->with('tags')
                ->get();
        });
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        $redis = app('redis');
        $info = $redis->info();
        
        return [
            'memory_usage' => $info['used_memory_human'] ?? 'N/A',
            'hit_rate' => $this->calculateHitRate(),
            'key_count' => $redis->dbsize(),
            'evicted_keys' => $info['evicted_keys'] ?? 0,
        ];
    }

    private function calculateHitRate(): float
    {
        $redis = app('redis');
        $info = $redis->info();
        
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }
}

// ===================================
// 12. ADVANCED QUERY SCOPES AND UTILITIES
// ===================================

// app/Traits/HasAdvancedFiltering.php
<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasAdvancedFiltering
{
    public function scopeFilterByRequest(Builder $query, array $filters): Builder
    {
        return $query
            ->when(isset($filters['tags']), function ($q) use ($filters) {
                $tagIds = is_array($filters['tags']) ? $filters['tags'] : explode(',', $filters['tags']);
                return $q->withAnyTags($tagIds);
            })
            ->when(isset($filters['sort']), function ($q) use ($filters) {
                return match($filters['sort']) {
                    'popular' => $q->popular(),
                    'recent' => $q->recent(),
                    'rating' => method_exists($q->getModel(), 'scopeTopRated') ? $q->topRated() : $q,
                    default => $q->latest(),
                };
            })
            ->when(isset($filters['date_range']), function ($q) use ($filters) {
                [$start, $end] = explode(',', $filters['date_range']);
                return $q->whereBetween('created_at', [$start, $end]);
            });
    }

    public function scopeSearchByTitle(Builder $query, string $search): Builder
    {
        return $query->where('title', 'LIKE', "%{$search}%");
    }

    public function scopeMinViews(Builder $query, int $minViews): Builder
    {
        return $query->where('views_count', '>=', $minViews);
    }
}

// ===================================
// 13. API CONTROLLERS FOR AJAX/SPA INTEGRATION
// ===================================

// app/Http/Controllers/Api/FilterController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CategoryService;
use App\Services\TagService;
use App\Models\Video;
use App\Models\Creator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FilterController extends Controller
{
    public function __construct(
        private CategoryService $categoryService,
        private TagService $tagService
    ) {}

    public function searchTags(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $type = $request->get('type', 'both');
        
        $tags = $this->tagService->getPopularTags($type, 100)
            ->filter(fn($tag) => str_contains(strtolower($tag->name), strtolower($query)))
            ->take(10)
            ->values();

        return response()->json([
            'data' => $tags->map(fn($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'usage_count' => $tag->usage_count,
            ])
        ]);
    }

    public function getFilteredContent(Request $request): JsonResponse
    {
        $type = $request->get('type', 'video'); // video or creator
        $tagIds = $request->get('tags', []);
        $sort = $request->get('sort', 'recent');
        $page = $request->get('page', 1);
        $perPage = min($request->get('per_page', 20), 50);

        if ($type === 'video') {
            $query = Video::published()->with(['creator', 'tags']);
        } else {
            $query = Creator::with('tags');
        }

        if (!empty($tagIds)) {
            $query->withAnyTags($tagIds);
        }

        $results = $query->filterByRequest($request->all())
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $results->items(),
            'pagination' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ]
        ]);
    }

    public function getCategoryContent(string $categorySlug): JsonResponse
    {
        $category = $this->categoryService->getCategoryBySlug($categorySlug);
        
        if (!$category) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        $items = $category->getItems();
        $tags = $category->getTags();

        return response()->json([
            'category' => $category,
            'items' => $items,
            'tags' => $tags,
        ]);
    }
}

// ===================================
// 14. SCHEDULED JOBS FOR MAINTENANCE
// ===================================

// app/Console/Commands/OptimizeCategories.php
<?php

namespace App\Console\Commands;

use App\Services\CategoryService;
use App\Services\CacheOptimizationService;
use Illuminate\Console\Command;

class OptimizeCategories extends Command
{
    protected $signature = 'categories:optimize';
    protected $description = 'Optimize categories and warm up cache';

    public function handle(): int
    {
        $this->info('Starting category optimization...');
        
        // Remove empty categories
        $deleted = \App\Models\Category::where('item_count', 0)
            ->where('created_at', '<', now()->subDays(7))
            ->delete();
        
        $this->info("Removed {$deleted} empty categories");
        
        // Update category counts
        app(CategoryService::class)->rebuildAllCategories();
        $this->info('Updated category counts');
        
        // Warm up cache
        app(CacheOptimizationService::class)->warmupCache();
        $this->info('Warmed up cache');
        
        $this->info('Category optimization completed!');
        return 0;
    }
}

// ===================================
// 16. DATABASE OPTIMIZATION & INDEXING
// ===================================

// database/migrations/2025_01_01_000006_add_performance_indexes.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Composite indexes for better query performance
        Schema::table('taggables', function (Blueprint $table) {
            $table->index(['taggable_type', 'tag_id'], 'idx_taggables_type_tag');
            $table->index(['tag_id', 'taggable_type', 'taggable_id'], 'idx_taggables_complete');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->index(['is_published', 'views_count'], 'idx_videos_published_views');
            $table->index(['is_published', 'published_at'], 'idx_videos_published_date');
            $table->index(['creator_id', 'is_published', 'published_at'], 'idx_videos_creator_published');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['type', 'item_count', 'id'], 'idx_categories_type_count');
            $table->index(['last_updated_at'], 'idx_categories_last_updated');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->index(['type', 'usage_count', 'name'], 'idx_tags_type_usage_name');
        });
    }

    public function down(): void
    {
        Schema::table('taggables', function (Blueprint $table) {
            $table->dropIndex('idx_taggables_type_tag');
            $table->dropIndex('idx_taggables_complete');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('idx_videos_published_views');
            $table->dropIndex('idx_videos_published_date');
            $table->dropIndex('idx_videos_creator_published');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('idx_categories_type_count');
            $table->dropIndex('idx_categories_last_updated');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropIndex('idx_tags_type_usage_name');
        });
    }
};

// ===================================
// 17. REDIS OPTIMIZATION CONFIGURATION
// ===================================

// config/cache.php - Optimized for Laravel 12
<?php

return [
    'default' => env('CACHE_DRIVER', 'redis'),

    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
            'serializer' => 'igbinary', // Better performance than default
        ],

        'redis_tags' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'options' => [
                'prefix' => 'laravel_cache_tags:',
            ],
        ],
    ],

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache'),
];

// config/database.php - Redis connections optimized
<?php

return [
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'options' => [
                'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
            ],
        ],

        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'options' => [
                'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache_'),
                'serializer' => 'igbinary',
            ],
        ],
    ],
];

// ===================================
// 18. ADVANCED CACHING STRATEGIES
// ===================================

// app/Services/SmartCacheService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SmartCacheService
{
    private const SHORT_TTL = 300;    // 5 minutes
    private const MEDIUM_TTL = 3600;  // 1 hour  
    private const LONG_TTL = 86400;   // 24 hours

    /**
     * Multi-level caching with automatic TTL selection
     */
    public function rememberSmart(string $key, $callback, string $level = 'medium'): mixed
    {
        $ttl = match($level) {
            'short' => self::SHORT_TTL,
            'medium' => self::MEDIUM_TTL,
            'long' => self::LONG_TTL,
            default => self::MEDIUM_TTL,
        };

        return Cache::tags(['smart_cache', $level])->remember($key, $ttl, $callback);
    }

    /**
     * Cache with dependency tracking
     */
    public function rememberWithDependencies(string $key, array $dependencies, $callback, int $ttl = 3600): mixed
    {
        // Create compound key including dependency versions
        $dependencyHash = $this->getDependencyHash($dependencies);
        $cacheKey = "{$key}:deps:{$dependencyHash}";

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    private function getDependencyHash(array $dependencies): string
    {
        $versions = [];
        
        foreach ($dependencies as $table) {
            // Get last modification time for table
            $versions[$table] = Cache::remember(
                "table_version:{$table}",
                3600,
                fn() => now()->timestamp
            );
        }

        return md5(serialize($versions));
    }

    /**
     * Invalidate cache by dependencies
     */
    public function invalidateByDependency(string $table): void
    {
        Cache::forget("table_version:{$table}");
        Cache::tags(["dep:{$table}"])->flush();
    }

    /**
     * Warmup cache for predictable queries
     */
    public function warmupPredictableQueries(): void
    {
        $queries = [
            'popular_videos_home' => fn() => \App\Models\Video::published()
                ->popular()
                ->with(['creator', 'tags'])
                ->limit(12)
                ->get(),
                
            'trending_categories' => fn() => \App\Models\Category::where('item_count', '>', 10)
                ->orderBy('last_updated_at', 'desc')
                ->limit(20)
                ->get(),
                
            'featured_creators' => fn() => \App\Models\Creator::verified()
                ->popular()
                ->with('tags')
                ->limit(8)
                ->get(),
        ];

        foreach ($queries as $key => $callback) {
            $this->rememberSmart($key, $callback, 'medium');
        }
    }
}

// ===================================
// 19. MONITORING AND ANALYTICS
// ===================================

// app/Services/FilterAnalyticsService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class FilterAnalyticsService
{
    /**
     * Track popular tag combinations
     */
    public function trackTagCombination(array $tagIds, string $type): void
    {
        $key = 'tag_combo:' . $type . ':' . implode(',', sort($tagIds));
        
        Cache::increment($key, 1);
        Cache::expire($key, 86400 * 30); // 30 days
    }

    /**
     * Get most popular tag combinations
     */
    public function getPopularCombinations(string $type, int $limit = 10): array
    {
        return Cache::remember("popular_combos:{$type}", 3600, function () use ($type, $limit) {
            $pattern = "tag_combo:{$type}:*";
            $keys = Cache::getRedis()->keys($pattern);
            
            $combinations = [];
            foreach ($keys as $key) {
                $count = Cache::get($key, 0);
                if ($count > 5) { // Minimum threshold
                    $tagIds = explode(':', str_replace("tag_combo:{$type}:", '', $key));
                    $combinations[] = [
                        'tag_ids' => $tagIds,
                        'count' => $count,
                    ];
                }
            }

            return collect($combinations)
                ->sortByDesc('count')
                ->take($limit)
                ->values()
                ->toArray();
        });
    }

    /**
     * Generate filter performance report
     */
    public function generatePerformanceReport(): array
    {
        return [
            'cache_stats' => app(CacheOptimizationService::class)->getCacheStats(),
            'category_stats' => $this->getCategoryStats(),
            'tag_usage_stats' => $this->getTagUsageStats(),
            'query_performance' => $this->getQueryPerformanceStats(),
        ];
    }

    private function getCategoryStats(): array
    {
        return Cache::remember('category_stats', 3600, function () {
            return [
                'total_categories' => \App\Models\Category::count(),
                'video_categories' => \App\Models\Category::forVideos()->count(),
                'creator_categories' => \App\Models\Category::forCreators()->count(),
                'empty_categories' => \App\Models\Category::where('item_count', 0)->count(),
                'avg_items_per_category' => \App\Models\Category::avg('item_count'),
            ];
        });
    }

    private function getTagUsageStats(): array
    {
        return Cache::remember('tag_usage_stats', 3600, function () {
            return [
                'total_tags' => \App\Models\Tag::count(),
                'video_tags' => \App\Models\Tag::forVideos()->count(),
                'creator_tags' => \App\Models\Tag::forCreators()->count(),
                'unused_tags' => \App\Models\Tag::where('usage_count', 0)->count(),
                'most_used_tag' => \App\Models\Tag::orderBy('usage_count', 'desc')->first(),
            ];
        });
    }

    private function getQueryPerformanceStats(): array
    {
        // This would integrate with Laravel Telescope or similar monitoring
        return [
            'avg_query_time' => '15ms', // Placeholder
            'slow_queries_count' => 0,
            'cache_hit_rate' => '95%',
        ];
    }
}

// ===================================
// 20. DEPLOYMENT AND SCALING CONSIDERATIONS
// ===================================

/*
// docker-compose.yml for Laravel 12 with Redis cluster
version: '3.8'

services:
  app:
    build: .
    environment:
      - CACHE_DRIVER=redis
      - REDIS_CLUSTER=true
      - DB_CONNECTION=mysql
    depends_on:
      - redis-cluster
      - mysql

  redis-cluster:
    image: redis:7-alpine
    command: redis-server --appendonly yes --cluster-enabled yes
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: laravel_filtering
      MYSQL_USER: laravel
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: secret
    volumes:
      - mysql_data:/var/lib/mysql

volumes:
  redis_data:
  mysql_data:

// Production optimization commands:
// php artisan config:cache
// php artisan route:cache  
// php artisan view:cache
// php artisan queue:work --daemon
// php artisan schedule:run
// php artisan categories:optimize (daily)

// Recommended server configuration:
// - PHP 8.2+ with OPcache enabled
// - Redis 7+ with clustering for high availability
// - MySQL 8.0+ with proper indexing
// - Load balancer for multiple app servers
// - CDN for static assets

// Monitoring recommendations:
// - Laravel Telescope for debugging
// - Laravel Pulse for performance monitoring  
// - Redis monitoring for cache performance
// - Database query analysis tools
// - Application Performance Monitoring (APM)
*/