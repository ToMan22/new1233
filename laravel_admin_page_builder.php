<?php

// composer.json dependencies to add:
/*
{
    "require": {
        "laravel/framework": "^12.0",
        "laravel/jetstream": "^6.0",
        "livewire/livewire": "^3.5",
        "laravel/folio": "^1.0",
        "filament/filament": "^3.2",
        "spatie/laravel-medialibrary": "^11.0",
        "intervention/image": "^3.0",
        "tiptap/tiptap": "^2.0"
    },
    "require-dev": {
        "@tailwindcss/vite": "^4.0",
        "tailwindcss": "^4.0"
    }
}
*/

// Database Migration: pages table
// php artisan make:migration create_pages_table

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('meta_description')->nullable();
            $table->json('seo_data')->nullable();
            $table->longText('content')->nullable();
            $table->json('page_data')->nullable(); // Store page builder data
            $table->string('template')->default('default');
            $table->string('status')->default('draft');
            $table->string('page_type')->default('page'); // page, landing, sales, category
            $table->string('category')->nullable();
            $table->json('form_fields')->nullable();
            $table->json('banners')->nullable();
            $table->json('navigation_items')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->index(['status', 'is_published']);
            $table->index(['page_type', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};

// Database Migration: content_categories table
// php artisan make:migration create_content_categories_table

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('keywords')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->default('#3B82F6');
            $table->json('page_settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_categories');
    }
};

// Database Migration: form_submissions table  
// php artisan make:migration create_form_submissions_table

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->json('form_data');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};

// App/Models/Page.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Page extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title',
        'slug',
        'meta_description',
        'seo_data',
        'content',
        'page_data',
        'template',
        'status',
        'page_type',
        'category',
        'form_fields',
        'banners',
        'navigation_items',
        'is_published',
        'published_at',
        'user_id',
    ];

    protected $casts = [
        'seo_data' => 'array',
        'page_data' => 'array',
        'form_fields' => 'array',
        'banners' => 'array',
        'navigation_items' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('banners')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
            
        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }