<?php

namespace Dzava\GlobalSearch\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Post extends Model
{
    public static $searchQueryCalled = false;

    protected $guarded = [];

    public function toSearchResult()
    {
        return array_merge($this->toArray(), ['slug' => Str::slug($this->title)]);
    }

    public function searchQuery($query, $searchTerm)
    {
        static::$searchQueryCalled = true;

        return $query;
    }
}
