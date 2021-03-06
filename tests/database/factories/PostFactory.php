<?php

use Dzava\GlobalSearch\Tests\Fixtures\Post;
use Faker\Generator as Faker;

$factory->define(Post::class, function (Faker $faker) {
    return [
        'title' => $faker->sentence(),
        'body' => $faker->text(),
    ];
});
