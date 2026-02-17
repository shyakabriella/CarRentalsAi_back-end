<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Vehicle;
use App\Models\ImageGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ImageGeneratorController extends BaseController
{
    /**
     * List images for a vehicle.
     */
    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        $images = ImageGenerator::where('vehicle_id', $vehicle->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->get();

        // ✅ Ensure image_url always exists (fallback from image_path)
        $images->transform(function ($img) {
            if (empty($img->image_url) && !empty($img->image_path)) {
                $img->image_url = Storage::disk('public')->url($img->image_path); // "/storage/...."
            }
            return $img;
        });

        return $this->sendResponse($images, 'Vehicle images fetched.');
    }

    /**
     * Upload a new image OR save generated metadata for a vehicle.
     */
    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $validated = $request->validate([
            'image'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_url'  => ['nullable', 'url', 'max:2048'],
            'source'     => ['nullable', Rule::in(['upload', 'generate'])],
            'is_primary' => ['nullable', 'boolean'],
            'prompt'     => ['nullable', 'string'],
            'seed'       => ['nullable', 'integer', 'min:0'],
            'style'      => ['nullable', 'string', 'max:50'],
            'params'     => ['nullable'],
        ]);

        if (empty($validated['image']) && empty($validated['image_url'])) {
            return $this->sendError('Provide an image file or an image_url.', [], 422);
        }

        $source = $validated['source'] ?? (isset($validated['image']) ? 'upload' : 'generate');

        $path = null;
        $publicUrl = $validated['image_url'] ?? null;

        if (!empty($validated['image'])) {
            $path = $validated['image']->store("vehicles/{$vehicle->id}", 'public');
            $publicUrl = Storage::disk('public')->url($path); // "/storage/vehicles/{id}/file.jpg"
        }

        $params = $validated['params'] ?? null;
        if (is_string($params)) {
            try { $params = json_decode($params, true, 512, JSON_THROW_ON_ERROR); }
            catch (\Throwable $e) { $params = null; }
        }

        $hasAny = ImageGenerator::where('vehicle_id', $vehicle->id)->exists();
        $isPrimary = (bool)($validated['is_primary'] ?? !$hasAny);

        $img = null;

        DB::transaction(function () use (&$img, $request, $vehicle, $source, $path, $publicUrl, $validated, $params, $isPrimary) {
            if ($isPrimary) {
                ImageGenerator::where('vehicle_id', $vehicle->id)->update(['is_primary' => false]);
            }

            $img = ImageGenerator::create([
                'user_id'     => $request->user()->id ?? $vehicle->user_id,
                'vehicle_id'  => $vehicle->id,
                'source'      => $source,
                'image_path'  => $path,
                'image_url'   => $publicUrl,
                'thumb_url'   => null,
                'is_primary'  => $isPrimary,
                'prompt'      => $validated['prompt'] ?? null,
                'seed'        => $validated['seed'] ?? null,
                'style'       => $validated['style'] ?? null,
                'params'      => $params,
                'status'      => 'succeeded',
                'error'       => null,
            ]);
        });

        // ✅ Ensure returned image_url exists even if old style
        if (empty($img->image_url) && !empty($img->image_path)) {
            $img->image_url = Storage::disk('public')->url($img->image_path);
        }

        return $this->sendResponse($img, 'Image saved.');
    }

    /**
     * ✅ Primary image helper (FIXED):
     * - if none marked primary, return latest image
     * - always return image_url (fallback from image_path)
     */
    public function primary(Request $request, Vehicle $vehicle): JsonResponse
    {
        $img = ImageGenerator::where('vehicle_id', $vehicle->id)
            ->orderByDesc('is_primary')  // primary first
            ->orderByDesc('id')          // else latest
            ->first();

        if ($img && empty($img->image_url) && !empty($img->image_path)) {
            $img->image_url = Storage::disk('public')->url($img->image_path);
        }

        return $this->sendResponse($img, 'Primary image fetched.');
    }

    // update() and destroy() can stay the same
}