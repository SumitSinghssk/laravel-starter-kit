<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $path = $request->file('file')->store(sprintf('uploads/%s/%s/miscellaneous', now()->year, now()->month), 'public');

        return response()->json(['location' => asset('storage/'.$path)]);
    }
}
