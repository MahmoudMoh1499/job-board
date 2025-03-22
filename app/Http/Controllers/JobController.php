<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\JobFilterService;
use Illuminate\Http\Request;

class JobController extends Controller
{
    protected $filterService;

    public function __construct(JobFilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * Get filtered jobs.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $filter = $request->query('filter');
            $perPage = $request->query('per_page', 15);

            $query = $this->filterService->filter($filter);

        // Add eager loading to prevent N+1 problems
        $query->with(['languages', 'locations', 'categories', 'attributes']);

            // Add pagination
            $jobs = $query->paginate($perPage);

            return response()->json($jobs);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Invalid filter syntax',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Cast attribute value to the appropriate type.
     *
     * @param \App\Models\JobAttributeValue $value
     * @return mixed
     */
    protected function castAttributeValue($value)
    {
        switch ($value->attribute->type) {
            case 'number':
                return (float) $value->value;
            case 'boolean':
                return (bool) $value->value;
            case 'date':
                return \Carbon\Carbon::parse($value->value)->toDateString();
            case 'select':
                return $value->value;
            default:
                return $value->value;
        }
    }
}
