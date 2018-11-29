<?php

namespace Dzava\GlobalSearch\Tests\Fixtures;

class UserWithOrderedQuery extends User
{
    protected $table = 'users';

    protected $guarded = [];

    public function searchableFields()
    {
        return [
            'name',
        ];
    }

    public function searchQuery($query, $searchTerm)
    {
        return $query->orderBy('id', 'desc');
    }
}
