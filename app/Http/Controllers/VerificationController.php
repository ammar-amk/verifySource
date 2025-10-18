<?php

namespace App\Http\Controllers;

use App\Models\VerificationRequest;
use App\Services\ContentVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class VerificationController extends Controller
{
    protected ContentVerificationService $verificationService;

    public function __construct(ContentVerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }
    public function index()
    {
        return view('verification.index');
    }

    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'input_text' => 'required_without:input_url|string|max:10000',
            'input_url' => 'required_without:input_text|url|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $inputText = $request->input('input_text');
        $inputUrl = $request->input('input_url');
        
        $content = $inputText ?: $this->extractContentFromUrl($inputUrl);
        $contentHash = hash('sha256', $content);

        $verificationRequest = VerificationRequest::create([
            'user_id' => auth()->id(),
            'request_type' => $inputText ? 'text' : 'url',
            'input_text' => $inputText,
            'input_url' => $inputUrl,
            'content_hash' => $contentHash,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'pending',
        ]);

        // Process verification in background
        try {
            $results = $this->verificationService->verifyContent($verificationRequest);
            
            return response()->json([
                'success' => true,
                'request_id' => $verificationRequest->id,
                'message' => 'Verification completed successfully',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function results($id)
    {
        $verificationRequest = VerificationRequest::with(['verificationResults.article.source'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'request' => $verificationRequest,
            'results' => $verificationRequest->verificationResults
        ]);
    }

    private function extractContentFromUrl($url)
    {
        try {
            $content = file_get_contents($url);
            return strip_tags($content);
        } catch (\Exception $e) {
            return '';
        }
    }
}
