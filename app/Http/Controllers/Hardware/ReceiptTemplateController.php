<?php

namespace App\Http\Controllers\Hardware;

use App\Http\Controllers\Controller;
use App\Models\ReceiptTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReceiptTemplateController extends Controller
{
    /**
     * Display a listing of receipt templates
     */
    public function index()
    {
        try {
            $templates = ReceiptTemplate::orderBy('is_default', 'desc')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $templates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created receipt template
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'paper_width' => 'required|integer|in:58,80',
            'is_default' => 'boolean',
            'sections' => 'required|array',
            'styles' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $template = ReceiptTemplate::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $template,
                'message' => 'Template created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified receipt template
     */
    public function show($id)
    {
        try {
            $template = ReceiptTemplate::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $template,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }
    }

    /**
     * Update the specified receipt template
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'paper_width' => 'sometimes|required|integer|in:58,80',
            'is_default' => 'boolean',
            'sections' => 'sometimes|required|array',
            'styles' => 'sometimes|required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $template = ReceiptTemplate::findOrFail($id);
            $template->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $template,
                'message' => 'Template updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified receipt template
     */
    public function destroy($id)
    {
        try {
            $template = ReceiptTemplate::findOrFail($id);

            // Prevent deletion of default template
            if ($template->is_default) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the default template',
                ], 400);
            }

            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the current default template
     */
    public function getDefault()
    {
        try {
            $template = ReceiptTemplate::getDefault();

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'No default template found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $template,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve default template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set a template as default
     */
    public function setDefault($id)
    {
        try {
            $template = ReceiptTemplate::findOrFail($id);
            $template->is_default = true;
            $template->save();

            return response()->json([
                'success' => true,
                'data' => $template,
                'message' => 'Template set as default successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set template as default',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Duplicate a template
     */
    public function duplicate($id)
    {
        try {
            $original = ReceiptTemplate::findOrFail($id);

            $duplicate = $original->replicate();
            $duplicate->name = $original->name . ' (Copy)';
            $duplicate->is_default = false;
            $duplicate->save();

            return response()->json([
                'success' => true,
                'data' => $duplicate,
                'message' => 'Template duplicated successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get default template structure for creating new templates
     */
    public function getDefaultStructure()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'sections' => ReceiptTemplate::getDefaultSections(),
                'styles' => ReceiptTemplate::getDefaultStyles(),
            ],
        ]);
    }
}
