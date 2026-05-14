<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'parent_id',
        'ym_id',
        'description',
        'parameters_schema',
        'parameters_fetched_at',
        'image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'parameters_schema' => 'array',
        'parameters_fetched_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name.'-'.$category->ym_id);
            }
        });

        static::saving(function ($category) {
            if (empty($category->meta_title)) {
                $category->meta_title = $category->name.' | Купить онлайн';
            }

            if (empty($category->meta_description) && ! empty($category->description)) {
                $category->meta_description = Str::limit(strip_tags($category->description), 160);
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'market_category_id', 'ym_id');
    }
}
