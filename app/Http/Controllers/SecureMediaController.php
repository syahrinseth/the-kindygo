<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecureMediaController extends Controller
{
    /**
     * Serve a secure media file.
     *
     * @param Request $request
     * @param Media $media
     * @return Response|StreamedResponse
     */
    public function show(Request $request, Media $media)
    {
        // Basic authentication check - user must be logged in
        if (!Auth::check()) {
            abort(401);
        }
        
        // Get the file path on the disk
        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        // Check if file exists
        if (!$disk->exists($path)) {
            abort(404);
        }
        
        // Get file content and metadata
        $fileContent = $disk->get($path);
        $mimeType = $media->mime_type;
        $fileName = $media->file_name;
        
        // Create response with appropriate headers
        return response($fileContent, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . $fileName . '"')
            ->header('Cache-Control', 'private, max-age=3600')
            ->header('X-Content-Type-Options', 'nosniff');
    }
    
    /**
     * Download a secure media file.
     *
     * @param Request $request
     * @param Media $media
     * @return Response|StreamedResponse
     */
    public function download(Request $request, Media $media)
    {
        // Basic authentication check - user must be logged in
        if (!Auth::check()) {
            abort(401);
        }
        
        // Get the file path on the disk
        $disk = Storage::disk($media->disk);
        $path = $media->getPath();
        
        // Check if file exists
        if (!$disk->exists($path)) {
            abort(404);
        }
        
        // Return a download response
        return response()->download(
            $disk->path($path),
            $media->file_name,
            ['Content-Type' => $media->mime_type]
        );
    }
    
    /**
     * Serve a media conversion (thumbnail, etc.).
     *
     * @param Request $request
     * @param Media $media
     * @param string $conversion
     * @return Response|StreamedResponse
     */
    public function conversion(Request $request, Media $media, string $conversion)
    {
        // Basic authentication check - user must be logged in
        if (!Auth::check()) {
            abort(401);
        }
        
        // Get the conversion path
        $conversionPath = $media->getPath($conversion);
        $disk = Storage::disk($media->disk);
        
        // Check if conversion exists
        if (!$disk->exists($conversionPath)) {
            // If conversion doesn't exist, try to serve the original
            return $this->show($request, $media);
        }
        
        // Get file content and metadata
        $fileContent = $disk->get($conversionPath);
        $mimeType = $media->getMediaConversion($conversion)->mime_type ?? $media->mime_type;
        $fileName = pathinfo($media->file_name, PATHINFO_FILENAME) . '_' . $conversion . '.' . pathinfo($media->file_name, PATHINFO_EXTENSION);
        
        // Create response with appropriate headers
        return response($fileContent, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . $fileName . '"')
            ->header('Cache-Control', 'private, max-age=3600')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
