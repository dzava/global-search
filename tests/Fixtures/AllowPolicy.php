<?php

namespace Dzava\GlobalSearch\Tests\Fixtures;

class AllowPolicy
{
    public function search($user)
    {
        return true;
    }
}
