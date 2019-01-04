# GlobalSearch

## Installation

You can install the package via composer:
```bash
composer require dzava/global-search
```

Optionally publish the config:
```bash
php artisan vendor:publish --tag=config --provider="Dzava\GlobalSearch\GlobalSearchServiceProvider"
```

## Usage
Add the `searchableFields` method to searchable models.
```php

class User extends Model {
	public function searchableFields() {
    	return ['name'];
    }
}

class Post extends Model {
	public function searchableFields() {
    	return ['title'];
    }
}
```

Perform a search

```php
use Dzava\GlobalSearch\GlobalSearch;

$results = (new GlobalSearch())->withModels([User::class, Post::class])->search('Doe');

//  [
//      'users' => [
//          ['name' => 'John Doe', 'email' => 'john@example.com'],
//          ['name' => 'Jane Doe', 'email' => 'jane@example.com']
//  ],
//      'posts'  => [
//          ['title' => 'Who is John Doe', 'slug' => 'who-is-john-doe'
//      ]
//  ]
```

To limit the number of results per model use the `limit($limit)` method. Given a limit of 0 all matching results are returned.

If no matching records are found for a model then the group is omitted from the results. To include empty groups use the `withEmpty()` method.

You can override the default group key when registering the models

```php
GlobalSearch::registerModels(['Accounts' => User::class, Post::class]);
```

If you don't want to group the results use the `withoutGroups()` method.

### Formatting the results
Results are formatted using the `toArray` method of the model. You can use a different method by changing the `toArray` option in the config file. If the method is missing from the model then it will fallback to `toArray`.

You can disable formatting entirely with the `withoutFormatting()` method, in which case the model is returned.

### Customizing the query
You can customize the search query by implementing the `searchQuery` method in your models. The method will receive two parameters, the query Builder instance and the search term, and it should return the query to be executed.

When a model uses the `Laravel\Scout\Searchable` trait scout will be used automatically.

### Authorization
The package will use Laravel's authorization policies, when available. If a policy is found, then the policy's authorization method is checked. You can change the authorization method used by setting the `policy-method` config option.


### Changelog
Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
