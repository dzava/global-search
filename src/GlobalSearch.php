<?php

namespace Dzava\GlobalSearch;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class GlobalSearch
{
    /**
     * All the searchable models
     *
     * @var \Illuminate\Support\Collection
     */
    protected static $models = [];

    /**
     * Whether an empty group should be returned for models that, no records match the search
     *
     * @var bool
     */
    protected $includeEmpty = false;

    /**
     * Whether results should be grouped
     *
     * @var bool
     */
    protected $useGroups = true;

    /**
     * The maximum number of results per model to return
     *
     * @var int
     */
    protected $limit = 5;

    /**
     * Whether to format the results or return the eloquent models
     *
     * @var bool
     */
    protected $dontFormat = false;

    protected $searchTerm;

    /**
     * Register the searchable models
     *
     * @param array|string $models
     */
    public static function registerModels($models)
    {
        self::$models = collect(Arr::wrap($models))
            ->mapWithKeys(function ($model, $group) {
                if (is_int($group)) {
                    $group = static::keyFor($model);
                }

                return [$group => new $model];
            });
    }

    /**
     * Finds records matching the search term
     *
     * @param string $searchTerm
     * @return array
     */
    public function search($searchTerm = '')
    {
        $this->searchTerm = $searchTerm;

        return collect(self::$models)->filter(Closure::fromCallable([$this, 'authorizedToSearch']))
            ->map(Closure::fromCallable([$this, 'getResultsFor']))
            ->filter(function ($results) {
                return count($results) > 0 || $this->includeEmpty;
            })
            ->unless($this->useGroups, function ($collection) {
                return $collection->flatten(1);
            });
    }

    /**
     * Include groups with no matching records
     *
     * @return $this
     */
    public function withEmpty()
    {
        $this->includeEmpty = true;

        return $this;
    }


    /**
     * Do not group the results
     *
     * @return $this
     */
    public function withoutGroups()
    {
        $this->useGroups = false;

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutFormatting()
    {
        $this->dontFormat = true;

        return $this;
    }

    /**
     * Limit the number of results per model
     *
     * @param int $limit
     * @return $this
     */
    public function limit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Get the key the model results will be grouped under
     *
     * @param \Illuminate\Database\Eloquent\Model|string $model
     * @return string
     */
    protected static function keyFor($model)
    {
        return Str::plural(Str::snake(class_basename($model), '-'));
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return bool
     */
    protected function authorizedToSearch($model)
    {
        $policy = Gate::getPolicyFor($model);

        if ($policy === null) {
            return true;
        }

        $methodName = config('globalsearch.policy-method');

        if (!method_exists($policy, $methodName)) {
            return true;
        }

        return Gate::check($methodName, $model);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return mixed
     */
    protected function getResultsFor($model)
    {
        return $this->getQueryFor($model)
            ->when($this->limit > 0, function ($query) {
                return $query->limit($this->limit);
            })
            ->get()
            ->map($this->getFormatterFor($model));
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return \Closure
     */
    protected function getFormatterFor($model)
    {
        if ($this->dontFormat) {
            return function ($record) {
                return $record;
            };
        }

        $formatMethod = config('globalsearch.toArray');
        $formatMethod = method_exists($model, $formatMethod) ? $formatMethod : 'toArray';

        return function ($record) use ($formatMethod) {
            return call_user_func([$record, $formatMethod]);
        };
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getQueryFor($model)
    {
        $query = $this->defaultSearchQuery($model);

        if (method_exists($model, ($searchMethod = config('globalsearch.searchQuery')))) {
            return $model->$searchMethod($query, $this->searchTerm);
        }

        return $query;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function defaultSearchQuery($model)
    {
        $query = $model->newQuery();

        if ($this->isUsingScout($model)) {
            return $model->search($this->searchTerm);
        }

        if (!method_exists($model, 'searchableFields')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($model) {
            foreach (Arr::wrap($model->searchableFields()) as $field) {
                $query->orWhere($field, 'LIKE', "%$this->searchTerm%");
            }
        });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return bool
     */
    protected function isUsingScout($model)
    {
        return in_array(Searchable::class, class_uses_recursive($model));
    }
}
