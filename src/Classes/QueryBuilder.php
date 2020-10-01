<?php

namespace JamesDordoy\LaravelVueDatatable\Classes;

//Casts
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use JamesDordoy\LaravelVueDatatable\Classes\Factories\RelationshipModelFactory;
use JamesDordoy\LaravelVueDatatable\Classes\Filters\FilterBelongsToManyRelationships;
use JamesDordoy\LaravelVueDatatable\Classes\Filters\FilterBelongsToRelationships;
use JamesDordoy\LaravelVueDatatable\Classes\Filters\FilterHasManyRelationships;
use JamesDordoy\LaravelVueDatatable\Classes\Filters\FilterLocalData;
use JamesDordoy\LaravelVueDatatable\Classes\Joins\JoinBelongsToManyRelationships;
use JamesDordoy\LaravelVueDatatable\Classes\Joins\JoinBelongsToRelationships;
use JamesDordoy\LaravelVueDatatable\Classes\Joins\JoinHasManyRelationships;
use JamesDordoy\LaravelVueDatatable\Classes\Relationships\GetBelongsToManyRelationships;
use JamesDordoy\LaravelVueDatatable\Classes\Relationships\GetBelongsToRelationships;
use JamesDordoy\LaravelVueDatatable\Classes\Relationships\GetHasManyRelationships;
use JamesDordoy\LaravelVueDatatable\Contracts\QueryBuilderContract;
use JamesDordoy\LaravelVueDatatable\Exceptions\RelationshipForeignKeyNotSetException;

//Contracts
//Factories
//Filters
//Joins
//Relationships
//Exceptions

class QueryBuilder implements QueryBuilderContract
{
    protected $model;
    protected $query;
    protected $localColumns;
    protected $relationships;
    protected $relationshipModelFactory;

    public function __construct(Model $model, Builder $query, $localColumns = [], $relationships = [])
    {
        $this->model = $model;
        $this->query = $query;
        $this->localColumns = $localColumns;
        $this->relationships = $relationships;
        $this->relationshipModelFactory = new RelationshipModelFactory;

        return $this;
    }

    public function selectData($dataTableColumns = [], $dataTableRelationships = [])
    {
        //Select local data
        $columnKeys = $this->selectModelColumns();
        $columnKeys = $this->selectLocalForeignKeysForJoining($columnKeys);

        $joinBelongsTo = new JoinBelongsToRelationships;
        $this->query = $joinBelongsTo($this->query, $this->model, $this->relationships, $this->relationshipModelFactory);

        $joinHasMany = new JoinHasManyRelationships;
        $this->query = $joinHasMany($this->query, $this->model, $this->relationships, $this->relationshipModelFactory);

        $joinBelongMany = new JoinBelongsToManyRelationships;
        $this->query = $joinBelongMany($this->query, $this->model, $this->relationships, $this->relationshipModelFactory);

        if (count($columnKeys)) {
            $this->query = $this->query->select($columnKeys);
        }

        $this->query->groupBy($this->model->getKeyName());

        return $this;
    }

    public function orderBy($orderBy, $orderByDir = "asc")
    {
        // Set default
        if ($orderBy === 'id') {
            $orderBy = $this->model->getTable() . '.id';
        }

        $orderByDir = isset($orderByDir) ? $orderByDir : 'asc';

        if (isset($orderBy) && !empty($orderBy)) {
            $defaultOrderBy = config('laravel-vue-datatables.models.order_term');
            $tableAndColumn = count(explode(".", $orderBy)) > 1 ? $orderBy : "$orderBy";
            $this->query->orderBy($tableAndColumn, $orderByDir);
        } else {
            $defaultOrderBy = config('laravel-vue-datatables.default_order_by');
            $defaultOrderBy = is_null($defaultOrderBy) ? 'id' : $defaultOrderBy;
            $this->query->orderBy("$defaultOrderBy", $orderByDir);
        }

        return $this;
    }

    public function addRelationships($declaredRelationship, $orderByDir)
    {
        $getBelongsTo = new GetBelongsToRelationships;
        $with = $getBelongsTo($this->relationships, $declaredRelationship);

        $getHasMany = new GetHasManyRelationships;
        $with = $getHasMany($this->relationships, $declaredRelationship, $with);

        $getBelongsToMany = new GetBelongsToManyRelationships;
        $with = $getBelongsToMany($this->relationships, $declaredRelationship, $with, $orderByDir);

        if (count($with)) {
            $this->query->with($with);
        }

        return $this;
    }

    public function filter($searchValue)
    {
        if (isset($searchValue) && !empty($searchValue)) {
            $filterLocalData = new FilterLocalData;
            $this->query = $filterLocalData($this->query, $searchValue, $this->model, $this->localColumns);

            $filterBelongsTo = new FilterBelongsToRelationships;
            $this->query = $filterBelongsTo($this->query, $searchValue, $this->relationshipModelFactory, $this->model, $this->relationships);

            $filterHasMany = new FilterHasManyRelationships;
            $this->query = $filterHasMany($this->query, $searchValue, $this->relationshipModelFactory, $this->model, $this->relationships);

            $filterBelongsToMany = new FilterBelongsToManyRelationships;
            $this->query = $filterBelongsToMany($this->query, $searchValue, $this->relationshipModelFactory, $this->model, $this->relationships);

            return $this;
        }

        return $this;
    }

    protected function selectModelColumns()
    {
        if (isset($this->localColumns) && !empty($this->localColumns)) {
            $columnKeys = array_keys($this->localColumns);
            foreach ($columnKeys as $index => $key) {
                $columnKeys[$index] = "$key";
                if(isset($this->localColumns[$key]['concat'])){
                    $columnKeys[$index] = \DB::raw("CONCAT".$this->localColumns[$key]['concat']);
                }
            }

            return $columnKeys;
        }

        return [];
    }

    protected function selectLocalForeignKeysForJoining($columnKeys)
    {
        if (isset($this->relationships['belongsTo'])) {
            foreach ($this->relationships['belongsTo'] as $tableName => $options) {
                if (!isset($options['foreign_key'])) {
                    throw new RelationshipForeignKeyNotSetException(
                        "Foreign Key not set on relationship: $tableName"
                    );
                }

                $columnKeys[count($columnKeys) + 1] = $options['foreign_key'];
            }
        }

        return $columnKeys;
    }

    public function getQuery()
    {
        return $this->query;
    }
}
