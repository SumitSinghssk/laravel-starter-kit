<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminSearch;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, AdminSearch $search)
    {
        $query = is_string($request->query('q')) ? $request->query('q') : '';

        return response()->json([
            'query' => $query,
            'groups' => $search->search($request->user(), $query),
        ]);
    }
}
