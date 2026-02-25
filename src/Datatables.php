<?php

/**
 * jQuery DataTables API for Laravel
 *
 * @author George Papakitsos <papakitsos_george@yahoo.gr>
 * @copyright George Papakitsos
 */

namespace GPapakitsos\LaravelDatatables;

use BadMethodCallException;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Datatables
{
    /**
     * Holds all input data
     *
     * @var array
     */
    protected $options = [];

    /**
     * Holds the driver name
     *
     * @var string
     */
    protected $driver;

    /**
     * The Eloquent model
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * The query builder instance
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $queryBuilder;

    /**
     * Holds the relation fields of model
     *
     * @var array
     */
    protected $relations;

    /**
     * Count of all model's records
     *
     * @var int
     */
    protected $totalCount;

    /**
     * Count of filtered model's records
     *
     * @var int
     */
    protected $filteredCount;

    /**
     * The constructor
     *
     * @param  string  $model
     *
     * @throws BadMethodCallException
     */
    public function __construct(Request $request, $model)
    {
        $this->options = $request->all();

        $model = config('datatables.models_namespace').$model;
        $this->model = new $model();

        if (! method_exists($this->model, 'getDatatablesData')) {
            throw new BadMethodCallException('Method getDatatablesData is not set in '.get_class($this->model));
        }

        $this->queryBuilder = $this->model->query();

        $this->relations = method_exists($this->model, 'getRelationFields') ? $this->model->getRelationFields() : [];

        $this->driver = $this->queryBuilder->getConnection()->getDriverName();
    }

    /**
     * Builds the JSON response
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function response()
    {
        if (! empty($this->options['scope'])) {
            $this->applyScope();
        }

        if (! empty($this->options['extraWhere'])) {
            $this->applyExtraWhere();
        }

        if (method_exists($this->model, 'scopeEagerLoading')) {
            $this->queryBuilder->eagerLoading();
        }

        if (isset($this->options['order']) && (! empty($this->options['order'][0]['column']) || $this->options['order'][0]['column'] === '0')) {
            $this->sortByColumn();
        }

        $this->totalCount = $this->queryBuilder->count();

        $searchOccurred = $this->search();

        $searchByColumnOccurred = $this->searchByColumn();

        $this->filteredCount = $searchOccurred || $searchByColumnOccurred ? $this->queryBuilder->count() : $this->totalCount;

        return response()->json($this->getFormatedData());
    }

    /**
     * Applies a scope to the query builder
     *
     * @return void
     */
    private function applyScope()
    {
        $scopeOpt = $this->options['scope'];

        if (is_array($scopeOpt)) {
            $scope = $scopeOpt[0];
            if (method_exists($this->model, 'scope'.ucwords($scope))) {
                $args = array_slice($scopeOpt, 1);
                $this->queryBuilder->$scope(...$args);
            }
        } else {
            if (method_exists($this->model, 'scope'.ucwords($scopeOpt))) {
                $this->queryBuilder->$scopeOpt();
            }
        }
    }

    /**
     * Applies an extra where condition to the query builder
     *
     * @return void
     */
    private function applyExtraWhere()
    {
        foreach ($this->options['extraWhere'] as $field => $value) {
            is_array($value)
                ? $this->queryBuilder->whereIn($field, $value)
                : $this->queryBuilder->where($field, (Str::startsWith($value, '%') || Str::endsWith($value, '%') ? 'LIKE' : '='), $value);
        }
    }

    /**
     * Applies ORDER BY to the query builder
     *
     * @return void
     */
    private function sortByColumn()
    {
        $field = $this->options['columns'][$this->options['order'][0]['column']]['data'] ?? null;
        if ($field === null) {
            return;
        }
        $direction = $this->options['order'][0]['dir'];

        if (! isset($this->relations[$field])) { // if field exists on model
            $this->queryBuilder->orderBy($field, $direction);
        } else { // if field is relation of model
            $relation = $this->model->$field();
            $table = $this->model->getTable();
            $otherTable = $relation->getRelated()->getTable();

            if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
                $this->queryBuilder
                    ->leftJoin($otherTable, $relation->getQualifiedForeignKeyName(), '=', $relation->getQualifiedOwnerKeyName())
                    ->select($table.'.*');
                foreach ($this->relations[$field] as $otherField) {
                    if (is_string($otherField)) {
                        $this->queryBuilder->orderBy($otherTable.'.'.$otherField, $direction);
                    } else {
                        $relationThrough = $relation->getRelated()->{$otherField[0]}();
                        $relationThroughOtherTable = $relationThrough->getRelated()->getTable();

                        $this->queryBuilder
                            ->leftJoin($relationThroughOtherTable, $relationThrough->getQualifiedForeignKeyName(), '=', $relationThrough->getQualifiedOwnerKeyName())
                            ->orderBy($relationThroughOtherTable.'.'.$otherField[1], $direction);
                    }
                }
            } elseif ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                $this->queryBuilder
                    ->leftJoin($relation->getTable(), $relation->getQualifiedForeignPivotKeyName(), '=', $relation->getQualifiedParentKeyName())
                    ->leftJoin($relation->getRelated()->getTable(), $relation->getQualifiedRelatedPivotKeyName(), '=', $relation->getRelated()->getTable().'.'.$relation->getRelated()->getKeyName())
                    ->select($table.'.*')
                    ->distinct();
                foreach ($this->relations[$field] as $otherField) {
                    $this->queryBuilder->orderBy($otherTable.'.'.$otherField, $direction);
                }
            } elseif ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
                $this->queryBuilder->orderBy(DB::raw('(SELECT COUNT(*) FROM `'.$otherTable.'` WHERE '.$relation->getQualifiedForeignKeyName().' = '.$relation->getQualifiedParentKeyName().')'), $direction);
            } elseif ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasOne) {
                foreach ($this->relations[$field] as $otherField) {
                    $this->queryBuilder->orderBy($otherTable.'.'.$otherField, $direction);
                }
            }
        }
    }

    /**
     * Searches the collection
     *
     * @return bool
     */
    private function search()
    {
        if (empty($this->options['search']['value'])) {
            return false;
        }

        if (! method_exists($this->model, 'scopeSearch')) {
            throw new BadMethodCallException('Method scopeSearch is not set in '.get_class($this->model));
        }

        $terms = explode(' ', trim($this->options['search']['value']));

        foreach ($terms as $term) {
            $term = trim($term);
            if (! empty($term)) {
                $this->queryBuilder->where(function ($query) use ($term) {
                    $query->search($term);
                });
            }
        }

        return true;
    }

    /**
     * Applies datatables global search
     *
     * @return bool
     */
    private function searchByColumn()
    {
        $table = $this->model->getTable();
        $result = false;

        foreach ($this->options['columns'] as $col) {
            $searchValue = $col['search']['value'];
            if (! empty($searchValue) || $searchValue === '0') {
                $result = true;

                $field = $col['data'];
                $this->queryBuilder->where(function ($query) use ($table, $field, $searchValue) {
                    if (! isset($this->relations[$field])) { // if field exists on model
                        if (Str::contains($searchValue, config('datatables.filters.date_delimiter'))) {
                            $dates = explode(config('datatables.filters.date_delimiter'), $searchValue);
                            if (! empty($dates[0])) {
                                $query->whereRaw("DATE(`$table`.`$field`) >= '".Carbon::createFromFormat(config('datatables.filters.date_format'), $dates[0])->toDateString()."'");
                            }
                            if (! empty($dates[1])) {
                                $query->whereRaw("DATE(`$table`.`$field`) <= '".Carbon::createFromFormat(config('datatables.filters.date_format'), $dates[1])->toDateString()."'");
                            }
                        } elseif (Str::contains($searchValue, config('datatables.filters.null_delimiter'))) {
                            $query->where($table.'.'.$field, '')->orWhereNull($table.'.'.$field);
                        } elseif (Str::startsWith($searchValue, '|') && Str::endsWith($searchValue, '|')) {
                            $query->where($table.'.'.$field, trim($searchValue, '|'));
                        } elseif (Schema::hasTable($table) && Schema::getColumnType($table, $field) == 'json') {
                            $query->whereRaw('LOWER(JSON_EXTRACT('.$table.'.'.$field.', "$.*")) LIKE ?', ['%'.strtolower($searchValue).'%']);
                        } else {
                            $query->where($table.'.'.$field, 'LIKE', '%'.$searchValue.'%');
                        }
                    } else { // if field is relation of model
                        $relation = $this->model->$field();
                        $otherTable = $relation->getRelated()->getTable();
                        if (! $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphTo) {
                            if (Str::contains($searchValue, config('datatables.filters.null_delimiter'))) {
                                $query->whereDoesntHave($field);
                            } else {
                                $query->whereHas($field, function ($query) use ($field, $searchValue, $otherTable) {
                                    $query->where(function ($query) use ($field, $searchValue, $otherTable) {
                                        foreach ($this->relations[$field] as $otherField) {
                                            if (is_string($otherField)) {
                                                if (Str::contains($searchValue, config('datatables.filters.date_delimiter'))) {
                                                    $dates = explode(config('datatables.filters.date_delimiter'), $searchValue);
                                                    if (! empty($dates[0])) {
                                                        $query->whereRaw("DATE(`$otherTable`.`$otherField`) >= '".Carbon::createFromFormat(config('datatables.filters.date_format'), $dates[0])->toDateString()."'");
                                                    }
                                                    if (! empty($dates[1])) {
                                                        $query->whereRaw("DATE(`$otherTable`.`$otherField`) <= '".Carbon::createFromFormat(config('datatables.filters.date_format'), $dates[1])->toDateString()."'");
                                                    }
                                                } elseif (Str::startsWith($searchValue, '|') && Str::endsWith($searchValue, '|')) {
                                                    $query->orWhere($otherTable.'.'.$otherField, trim($searchValue, '|'));
                                                } elseif (in_array($otherField, config('datatables.filters.date_columns', []))) {
                                                    $dateFormat = strtr(config('datatables.filters.date_display_format', 'd/m/Y'), [
                                                        'd' => '%d', 'j' => '%e', 'm' => '%m', 'Y' => '%Y', 'y' => '%y',
                                                    ]);
                                                    $searchValues = [$searchValue];

                                                    if (Str::contains($searchValue, '/') || Str::contains($searchValue, '-')) {
                                                        $searchValues[] = strtr($searchValue, '/-', '-/');
                                                    }

                                                    $dateExpr = $this->driver === 'sqlite'
                                                        ? "strftime('".$dateFormat."', `$otherTable`.`$otherField`)"
                                                        : "DATE_FORMAT(`$otherTable`.`$otherField`, '".$dateFormat."')";
                                                    foreach ($searchValues as $value) {
                                                        $query->orWhere($otherTable.'.'.$otherField, 'LIKE', '%'.$value.'%');
                                                        $query->orWhereRaw($dateExpr.' LIKE ?', ['%'.$value.'%']);
                                                    }
                                                } else {
                                                    $query->orWhere($otherTable.'.'.$otherField, 'LIKE', '%'.$searchValue.'%');
                                                }
                                            } else {
                                                $query->whereHas($otherField[0], function ($query) use ($otherField, $searchValue) {
                                                    if (is_string($otherField[1])) {
                                                        $query->where($otherField[1], 'LIKE', '%'.$searchValue.'%');
                                                    } elseif (is_array($otherField[1])) {
                                                        $query->where(function ($query) use ($otherField, $searchValue) {
                                                            foreach ($otherField[1] as $otherFieldItem) {
                                                                $query->orWhere($otherFieldItem, 'LIKE', '%'.$searchValue.'%');
                                                            }
                                                        });
                                                    }
                                                });
                                            }
                                        }
                                    });
                                });
                            }
                        } else {
                            $query->where(function ($query) use ($field, $searchValue) {
                                foreach ($this->relations[$field] as $otherField) {
                                    $query->orWhereHasMorph($field, $otherField['models'], function ($query, $type) use ($otherField, $searchValue) {
                                        foreach ($otherField['fields'] as $morphFieldKey => $morphField) {
                                            $query->{$morphFieldKey == 0 ? 'where' : 'orWhere'}($morphField, 'LIKE', '%'.$searchValue.'%');
                                        }
                                    });
                                }
                            });
                        }
                    }
                });
            }
        }

        return $result;
    }

    /**
     * Formats the data for JSON response
     *
     * @return array
     */
    private function getFormatedData()
    {
        $collection = $this->options['length'] != '-1'
            ? $this->queryBuilder->skip($this->options['start'])->take($this->options['length'])->get()
            : $this->queryBuilder->get();

        return [
            'draw' => $this->options['draw'],
            'recordsTotal' => $this->totalCount,
            'recordsFiltered' => $this->filteredCount,
            'data' => $collection->map(function ($model) {
                return $model->getDatatablesData();
            }),
        ];
    }
}
