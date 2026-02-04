<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;

class UploadController extends Controller
{
    
    public function vehicleImage(Request $request)
    {
        $request->validate([
            
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ]);

        $file = $request->file('image');

        $folder = 'vehicle-images/' . date('Y/m');
        $filename = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs($folder, $filename, 'public');

        $url = asset('storage/' . $path);

        return response()->json([
            'message' => 'Image uploaded successfully',
            'path' => $path,
            'url' => $url,
            'size_kb' => round($file->getSize() / 1024),
        ], 201);
    }
}
