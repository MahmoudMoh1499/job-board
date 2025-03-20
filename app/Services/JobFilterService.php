<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class JobFilterService
{
    public function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->has('title')) {
            $query->where('title', 'LIKE', '%' . $request->title . '%');
        }

        if ($request->has('salary_min')) {
            $query->where('salary_min', '>=', $request->salary_min);
        }

        if ($request->has('salary_max')) {
            $query->where('salary_max', '<=', $request->salary_max);
        }

        if ($request->has('is_remote')) {
            $query->where('is_remote', $request->is_remote);
        }

        if ($request->has('job_type')) {
            $query->whereIn('job_type', explode(',', $request->job_type));
        }

        if ($request->has('status')) {
            $query->whereIn('status', explode(',', $request->status));
        }

        if ($request->has('published_at')) {
            $query->whereDate('published_at', '=', $request->published_at);
        }

        // Handle relationship filtering
        if ($request->has('languages')) {
            $languages = explode(',', $request->languages);
            $query->whereHas('languages', function ($q) use ($languages) {
                $q->whereIn('name', $languages);
            });
        }

        if ($request->has('locations')) {
            $locations = explode(',', $request->locations);
            $query->whereHas('locations', function ($q) use ($locations) {
                $q->whereIn('city', $locations);
            });
        }

        // Handle EAV filtering
        if ($request->has('attribute')) {
            foreach ($request->attribute as $key => $value) {
                $query->whereHas('attributes', function ($q) use ($key, $value) {
                    $q->where('attribute_id', function ($subQuery) use ($key) {
                        $subQuery->select('id')
                            ->from('attributes')
                            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($key))]);
                    })->where('value', $value);
                });
            }
        }


        return $query;
    }
}
