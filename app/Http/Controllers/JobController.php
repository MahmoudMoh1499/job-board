<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Job;
use App\Services\JobFilterService;

class JobController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $jobs = Job::with(['languages', 'locations', 'categories', 'attributes'])
            ->where('status', 'published');

        $jobs = (new JobFilterService())->applyFilters($jobs, $request);

        return response()->json($jobs->paginate(perPage: 10));
    }
}
