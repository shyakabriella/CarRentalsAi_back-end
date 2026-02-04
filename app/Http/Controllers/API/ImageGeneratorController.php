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
        // (Optional) authorization: only owner can see
        if ($request->user() && $vehicle->user_id !== $request->user()->id) {
            // return $this->sendError('Forbidden', [], 403);
        }

        $images = ImageGenerator::where('vehicle_id', $vehicle->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->get();

        return $this->sendResponse($images, 'Vehicle images fetched.');
    }

    /**
     * Upload a new image OR save generated metadata for a vehicle.
     *
     * Accepts either:
     * - file 'image' (upload), OR
     * - 'image_url' (already hosted), optional generator metadata.
     *
     * Optional: is_primary, prompt, seed, style, params (json)
     */
    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        // (Optional) authorization
        if ($request->user() && $vehicle->user_id !== $request->user()->id) {
            // return $this->sendError('Forbidden', [], 403);
        }

        $validated = $request->validate([
            'image'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5MB
            'image_url'  => ['nullable', 'url', 'max:2048'],
            'source'     => ['nullable', Rule::in(['upload', 'generate'])],
            'is_primary' => ['nullable', 'boolean'],

            'prompt'     => ['nullable', 'string'],
            'seed'       => ['nullable', 'integer', 'min:0'],
            'style'      => ['nullable', 'string', 'max:50'],
            'params'     => ['nullable'], // json string or array
        ]);

        if (empty($validated['image']) && empty($validated['image_url'])) {
            return $this->sendError('Provide an image file or an image_url.', [], 422);
        }

        $source = $validated['source'] ?? (isset($validated['image']) ? 'upload' : 'generate');

        $path = null;
        $publicUrl = $validated['image_url'] ?? null;

        if (!empty($validated['image'])) {
            // Store on the public disk
            $path = $validated['image']->store("vehicles/{$vehicle->id}", 'public');
            $publicUrl = Storage::disk('public')->url($path);
        }

        // Normalize params to array
        $params = $validated['params'] ?? null;
        if (is_string($params)) {
            try { $params = json_decode($params, true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable $e) { $params = null; }
        }

        // If this is the first image for a vehicle, mark primary
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
                'thumb_url'   => null, // set if you generate thumbnails
                'is_primary'  => $isPrimary,

                'prompt'      => $validated['prompt'] ?? null,
                'seed'        => $validated['seed'] ?? null,
                'style'       => $validated['style'] ?? null,
                'params'      => $params,

                'status'      => 'succeeded',
                'error'       => null,
            ]);
        });

        return $this->sendResponse($img, 'Image saved.');
    }

    /**
     * Update image metadata (and can flip primary).
     */
    public function update(Request $request, Vehicle $vehicle, ImageGenerator $image): JsonResponse
    {
        if ($image->vehicle_id !== $vehicle->id) {
            return $this->sendError('Image does not belong to this vehicle.', [], 422);
        }

        // (Optional) authorization
        if ($request->user() && $vehicle->user_id !== $request->user()->id) {
            // return $this->sendError('Forbidden', [], 403);
        }

        $validated = $request->validate([
            'is_primary' => ['nullable', 'boolean'],
            'prompt'     => ['nullable', 'string'],
            'seed'       => ['nullable', 'integer', 'min:0'],
            'style'      => ['nullable', 'string', 'max:50'],
            'params'     => ['nullable'],
        ]);

        $params = $validated['params'] ?? null;
        if (is_string($params)) {
            try { $params = json_decode($params, true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable $e) { $params = null; }
        }

        DB::transaction(function () use ($validated, $params, $vehicle, $image) {
            if (array_key_exists('is_primary', $validated) && $validated['is_primary']) {
                ImageGenerator::where('vehicle_id', $vehicle->id)->update(['is_primary' => false]);
                $image->is_primary = true;
            }

            if (array_key_exists('prompt', $validated)) $image->prompt = $validated['prompt'];
            if (array_key_exists('seed', $validated))   $image->seed   = $validated['seed'];
            if (array_key_exists('style', $validated))  $image->style  = $validated['style'];
            if ($params !== null)                       $image->params = $params;

            $image->save();
        });

        return $this->sendResponse($image, 'Image updated.');
    }

    /**
     * Delete an image (removes stored file if present).
     */
    public function destroy(Request $request, Vehicle $vehicle, ImageGenerator $image): JsonResponse
    {
        if ($image->vehicle_id !== $vehicle->id) {
            return $this->sendError('Image does not belong to this vehicle.', [], 422);
        }

        // (Optional) authorization
        if ($request->user() && $vehicle->user_id !== $request->user()->id) {
            // return $this->sendError('Forbidden', [], 403);
        }

        DB::transaction(function () use ($image) {
            if ($image->image_path && Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
            $image->delete();
        });

        return $this->sendResponse([], 'Image deleted.');
    }

    /**
     * Quick helper: get the primary image record for a vehicle.
     */
    public function primary(Request $request, Vehicle $vehicle): JsonResponse
    {
        $img = ImageGenerator::where('vehicle_id', $vehicle->id)
            ->where('is_primary', true)
            ->first();

        return $this->sendResponse($img, 'Primary image fetched.');
    }
}
