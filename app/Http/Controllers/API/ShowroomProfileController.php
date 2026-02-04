<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ShowroomProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShowroomProfileController extends Controller
{
    /**
     * GET /api/showroom/profile
     * Get current owner showroom profile (or empty).
     */
    public function show(Request $request)
    {
        $user = $request->user();

        $profile = ShowroomProfile::where('owner_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    /**
     * POST /api/showroom/profile
     * Create or update showroom profile (with uploads).
     */
    public function upsert(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],

            // files
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'working_permission_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $profile = ShowroomProfile::firstOrNew(['owner_id' => $user->id]);

        $profile->name = $validated['name'];
        $profile->address = $validated['address'] ?? null;
        $profile->lat = $validated['lat'] ?? null;
        $profile->lng = $validated['lng'] ?? null;

        // ✅ store files in public disk
        // make sure: php artisan storage:link
        if ($request->hasFile('logo')) {
            if ($profile->logo_path) {
                Storage::disk('public')->delete($profile->logo_path);
            }
            $profile->logo_path = $request->file('logo')->store('showrooms/logos', 'public');
        }

        if ($request->hasFile('working_permission_pdf')) {
            if ($profile->working_permission_pdf_path) {
                Storage::disk('public')->delete($profile->working_permission_pdf_path);
            }
            $profile->working_permission_pdf_path =
                $request->file('working_permission_pdf')->store('showrooms/permissions', 'public');
        }

        $profile->save();

        return response()->json([
            'success' => true,
            'message' => 'Showroom profile updated successfully.',
            'data' => $profile->fresh(),
        ]);
    }
}