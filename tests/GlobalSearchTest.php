<?php

namespace Dzava\GlobalSearch\Tests;

use Dzava\GlobalSearch\GlobalSearch;
use Dzava\GlobalSearch\Tests\Fixtures\AllowPolicy;
use Dzava\GlobalSearch\Tests\Fixtures\DenyPolicy;
use Dzava\GlobalSearch\Tests\Fixtures\Post;
use Dzava\GlobalSearch\Tests\Fixtures\User;
use Dzava\GlobalSearch\Tests\Fixtures\UserWithOrderedQuery;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Assert;

class GlobalSearchTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        EloquentCollection::macro('assertEquals', function ($collection) {
            Assert::assertSame($collection->count(), $this->count());
            $this->zip($collection)->eachSpread(function ($a, $b) {
                Assert::assertTrue($a->is($b));
            });
        });
    }


    /** @test */
    public function searches_searchable_models()
    {
        factory(User::class, 2)->create();

        $results = (new GlobalSearch())
            ->withModels([User::class, Post::class])
            ->search();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertArrayKeys('users', $results->all());
    }

    /** @test */
    public function does_not_search_non_searchable_models()
    {
        $results = (new GlobalSearch())->search();

        $this->assertEmpty($results);
    }

    /** @test */
    public function does_not_include_empty_results()
    {
        $results = (new GlobalSearch())
            ->withModels(User::class)
            ->search();

        $this->assertEmpty($results);
    }

    /** @test */
    public function can_be_configured_to_include_empty_results()
    {
        $results = (new GlobalSearch())
            ->withModels(User::class)
            ->withEmpty()
            ->search();

        $this->assertArrayKeys('users', $results->all());
    }

    /** @test */
    public function can_customize_the_groups()
    {
        $results = (new GlobalSearch())
            ->withModels(['Accounts' => User::class, Post::class,])
            ->withEmpty()
            ->search();

        $this->assertArrayKeys(['Accounts', 'posts'], $results->all());
    }

    /** @test */
    public function can_disable_grouping()
    {
        $user = factory(User::class)->create();
        $post = factory(Post::class)->create();

        $results = (new GlobalSearch())
            ->withModels([User::class, Post::class])
            ->withEmpty()
            ->withoutGroups()
            ->search();

        $this->assertCount(2, $results);
        $this->assertEquals($user->name, array_get($results, '0.name'));
        $this->assertEquals($post->title, array_get($results, '1.title'));
    }

    /** @test */
    public function respects_authorization_policies()
    {
        $user = factory(User::class)->create();
        factory(Post::class)->create();
        Gate::policy(Post::class, DenyPolicy::class);
        Gate::policy(User::class, AllowPolicy::class);
        Auth::login($user);

        $results = (new GlobalSearch())
            ->withModels([User::class, Post::class])
            ->search();

        $this->assertCount(1, $results);
        $this->assertEquals($user->name, array_get($results, 'users.0.name'));
    }

    /** @test */
    public function returns_an_empty_collection_when_no_models_are_configured()
    {
        $results = (new GlobalSearch())->search();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertEmpty($results);
    }

    /** @test */
    public function can_provide_a_custom_search_query()
    {
        Post::$searchQueryCalled = false;

        (new GlobalSearch())
            ->withModels([User::class, Post::class])
            ->search();

        $this->assertTrue(Post::$searchQueryCalled);
    }

    /** @test */
    public function the_default_search_is_applied_to_custom_queries()
    {
        factory(User::class)->create(['name' => 'jane smith']);
        factory(User::class)->create(['name' => 'john smith']);
        $users = EloquentCollection::make([
            factory(User::class)->create(['name' => 'john doe']),
            factory(User::class)->create(['name' => 'jane doe']),
        ]);
        $usersOrderedByIdDesc = $users->sortByDesc('id');

        ['users' => $result] = (new GlobalSearch())
            ->withModels(User::class)
            ->withoutFormatting()
            ->search('doe');
        $users->assertEquals($result);

        ['users' => $result] = (new GlobalSearch())
            ->withModels(['users' => UserWithOrderedQuery::class])
            ->withoutFormatting()
            ->search('doe');
        $usersOrderedByIdDesc->assertEquals($result);
    }

    /** @test */
    public function only_searches_the_searchable_fields()
    {
        $globalsearch = (new GlobalSearch())->withModels(User::class);
        $searchTerm = 'john';

        factory(User::class)->create(['name' => 'Jane Doe', 'email' => 'john@example.com']);
        $results = $globalsearch->search($searchTerm);
        $this->assertEmpty($results);

        factory(User::class)->create(['name' => 'John Doe']);
        $results = $globalsearch->search($searchTerm);
        $this->assertCount(1, $results['users']);
        $this->assertEquals('John Doe', $results['users'][0]['name']);
    }

    /** @test */
    public function can_customize_the_result_format()
    {
        $user = factory(User::class)->create();
        $post = factory(Post::class)->create();
        Config::set('globalsearch.toArray', 'toSearchResult');

        $results = (new GlobalSearch())
            ->withModels([User::class, Post::class])
            ->search();

        $this->assertEquals($post->toSearchResult(), $results['posts'][0]);
        $this->assertNotEquals($post->toArray(), $post->toSearchResult());
        $this->assertEquals($user->toArray(), $results['users'][0]);
    }

    /** @test */
    public function can_disable_formatting()
    {
        $user = factory(User::class)->create();

        ['users' => $users] = (new GlobalSearch())
            ->withModels([User::class, Post::class])
            ->withoutFormatting()
            ->search();

        $this->assertInstanceOf(User::class, $users[0]);
        $this->assertTrue($user->is($users[0]));
    }

    /** @test */
    public function can_limit_the_max_number_of_results_in_each_group()
    {
        $globalsearch = (new GlobalSearch())->withModels(User::class);
        factory(User::class, 10)->create();

        $results = $globalsearch->search();

        $this->assertCount(5, $results['users']);

        $results = $globalsearch->limit(3)->search();

        $this->assertCount(3, $results['users']);
    }

    /** @test */
    public function does_not_apply_the_limit_when_set_to_zero()
    {
        factory(User::class, 10)->create();
        $globalSearch = (new GlobalSearch())->withModels(User::class);

        $results = $globalSearch->limit(3)->search();
        $this->assertCount(3, $results['users']);

        $results = $globalSearch->limit(0)->search();
        $this->assertCount(10, $results['users']);
    }
}
