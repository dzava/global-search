<?php

namespace Dzava\GlobalSearch\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    public function searchableFields()
    {
        return [
            'name',
        ];
    }
}
