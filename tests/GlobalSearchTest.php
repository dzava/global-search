<?php

namespace Dzava\GlobalSearch\Tests;

use Dzava\GlobalSearch\GlobalSearch;
use Dzava\GlobalSearch\Tests\Fixtures\AllowPolicy;
use Dzava\GlobalSearch\Tests\Fixtures\DenyPolicy;
use Dzava\GlobalSearch\Tests\Fixtures\Post;
use Dzava\GlobalSearch\Tests\Fixtures\User;
use Dzava\GlobalSearch\Tests\Fixtures\UserWithOrderedQuery;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
        GlobalSearch::registerModels([User::class, Post::class]);
        factory(User::class, 2)->create();

        $results = (new GlobalSearch())->search();

        $this->assertArrayKeys('users', $results);
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
        GlobalSearch::registerModels(User::class);

        $results = (new GlobalSearch())->search();

        $this->assertEmpty($results);
    }

    /** @test */
    public function can_be_configured_to_include_empty_results()
    {
        GlobalSearch::registerModels(User::class);

        $results = (new GlobalSearch())
            ->withEmpty()
            ->search();

        $this->assertArrayKeys('users', $results);
    }

    /** @test */
    public function can_customize_the_groups()
    {
        GlobalSearch::registerModels([
            'Accounts' => User::class, Post::class,
        ]);

        $results = (new GlobalSearch())
            ->withEmpty()
            ->search();

        $this->assertArrayKeys(['Accounts', 'posts'], $results);
    }

    /** @test */
    public function can_disable_grouping()
    {
        GlobalSearch::registerModels([User::class, Post::class]);
        $user = factory(User::class)->create();
        $post = factory(Post::class)->create();

        $results = (new GlobalSearch())
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
        GlobalSearch::registerModels([User::class, Post::class]);
        $user = factory(User::class)->create();
        factory(Post::class)->create();
        Gate::policy(Post::class, DenyPolicy::class);
        Gate::policy(User::class, AllowPolicy::class);
        Auth::login($user);

        $results = (new GlobalSearch())->search();

        $this->assertCount(1, $results);
        $this->assertEquals($user->name, array_get($results, 'users.0.name'));
    }

    /** @test */
    public function can_provide_a_custom_search_query()
    {
        GlobalSearch::registerModels(Post::class);
        Post::$searchQueryCalled = false;

        (new GlobalSearch())->search();

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

        GlobalSearch::registerModels(User::class);
        ['users' => $result] = (new GlobalSearch())->withoutFormatting()->search('doe');
        $users->assertEquals($result);

        GlobalSearch::registerModels(['users' => UserWithOrderedQuery::class]);
        ['users' => $result] = (new GlobalSearch())->withoutFormatting()->search('doe');
        $usersOrderedByIdDesc->assertEquals($result);
    }

    /** @test */
    public function only_searches_the_searchable_fields()
    {
        GlobalSearch::registerModels(User::class);
        $searchTerm = 'john';

        factory(User::class)->create(['name' => 'Jane Doe', 'email' => 'john@example.com']);
        $results = (new GlobalSearch())->search($searchTerm);
        $this->assertEmpty($results);

        factory(User::class)->create(['name' => 'John Doe']);
        $results = (new GlobalSearch())->search($searchTerm);
        $this->assertCount(1, $results['users']);
        $this->assertEquals('John Doe', $results['users'][0]['name']);
    }

    /** @test */
    public function can_customize_the_result_format()
    {
        GlobalSearch::registerModels([User::class, Post::class]);
        $user = factory(User::class)->create();
        $post = factory(Post::class)->create();
        Config::set('globalsearch.toArray', 'toSearchResult');

        $results = (new GlobalSearch())->search();

        $this->assertEquals($post->toSearchResult(), $results['posts'][0]);
        $this->assertNotEquals($post->toArray(), $post->toSearchResult());
        $this->assertEquals($user->toArray(), $results['users'][0]);
    }

    /** @test */
    public function can_disable_formatting()
    {
        GlobalSearch::registerModels([User::class, Post::class]);
        $user = factory(User::class)->create();

        ['users' => $users] = (new GlobalSearch())->withoutFormatting()->search();

        $this->assertInstanceOf(User::class, $users[0]);
        $this->assertTrue($user->is($users[0]));
    }

    /** @test */
    public function can_limit_the_max_number_of_results_in_each_group()
    {
        GlobalSearch::registerModels(User::class);
        factory(User::class, 10)->create();

        $results = (new GlobalSearch())->search();

        $this->assertCount(5, $results['users']);

        $results = (new GlobalSearch())->limit(3)->search();

        $this->assertCount(3, $results['users']);
    }

    /** @test */
    public function does_not_apply_the_limit_when_set_to_zero()
    {
        GlobalSearch::registerModels(User::class);
        factory(User::class, 10)->create();
        $globalSearch = new GlobalSearch();

        $results = $globalSearch->limit(3)->search();
        $this->assertCount(3, $results['users']);

        $results = $globalSearch->limit(0)->search();
        $this->assertCount(10, $results['users']);
    }
}
