<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Services\JobFilterService;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index(Request $request)
    {
        // Get the filter string from the query parameters
        $filterString = $request->query('filter');

        // Start building the query
        $query = Job::query()
            ->with(['languages', 'locations', 'categories', 'attributes']); // Eager load relationships

        // Apply filters if a filter string is provided
        if ($filterString) {
            $filterService = new JobFilterService($query);
            $query = $filterService->applyFilters($filterString);
        }

        // Paginate the results (10 items per page by default)
        $perPage = $request->query('per_page', 10); // Allow customizing per_page via query parameter
        $jobs = $query->paginate($perPage);

        // Return the paginated results as JSON
        return response()->json($jobs);
    }
}
