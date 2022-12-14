<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Category;
use App\Models\Editorial;
use App\Models\Author;
use App\Models\BookDownload;
use App\Models\BookReview;

class Book extends Model
{
    use HasFactory;

    protected $table = 'books';

    protected $fillable = [
        'id',
        'isbn',
        'title',
        'description',
        'published_date',
        'category_id',
        'editorial_id'
    ];

    public $timestamps = false;

    public function bookDownload() {
        return $this->hasOne(
            bookDownload::class
        );
    }

    public function bookReviews() 
    {
        return $this->hasMany(
            bookReview::class,
            'id'
        );

    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function editorial()
    {
        return $this->belongsTo(Editorial::class, 'editorial_id', 'id');
    }

    public function authors()
    {
        return $this->belongsToMany(
            Author::class,
            'authors_books', // Table
            'books_id', // From
            'authors_id' // To
        );
    }
}
