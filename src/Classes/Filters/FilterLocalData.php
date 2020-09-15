<?php

namespace JamesDordoy\LaravelVueDatatable\Classes\Filters;

class FilterLocalData
{
    public function __invoke($query, $searchValue, $model, $localColumns)
    {
        $searchTerm = config('laravel-vue-datatables.models.search_term');

        if (isset($localColumns)) {
            return $query->where(function ($query) use ($searchValue, $searchTerm, $model, $localColumns) {
                foreach ($localColumns as $key => $column) {
                    if (isset($column[$searchTerm])) {
                        if ($key === key($localColumns)) {
                            if (isset($localColumns[$key]['search'])) {
                                $query->where(\DB::raw("CONCAT" . $localColumns[$key]['search']), 'LIKE', "%" . $searchValue . "%");
                            } else {
                                $query->where("$key", 'like', "%$searchValue%");
                            }
                        } else {
                            if (isset($localColumns[$key]['search'])) {
                                $query->orWhere(\DB::raw("CONCAT" . $localColumns[$key]['search']), 'LIKE', "%" . $searchValue . "%");
                            } else {
                                $query->orWhere("$key", 'like', "%$searchValue%");
                            }
                        }
                    }
                }
            });
        }

        return $query;
    }
}
