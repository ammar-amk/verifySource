<?php

namespace App\Livewire;

use App\Models\VerificationRequest;
use App\Services\ContentHashService;
use App\Services\VerificationService;
use Exception;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;

class VerificationForm extends Component
{
    use WithFileUploads;

    // Form properties
    public $inputType = 'text'; // text, url, file

    public $content = '';

    public $url = '';

    public $file;

    public $metadata = [];

    // State properties
    public $isVerifying = false;

    public $verificationComplete = false;

    public $verificationResult = null;

    public $errorMessage = null;
    
    protected function rules()
    {
        return [
            'content' => 'required_if:inputType,text|min:10|max:50000',
            'url' => 'required_if:inputType,url|url|max:2048', 
            'file' => 'required_if:inputType,file|file|mimes:txt,pdf,doc,docx|max:10240', // 10MB max
        ];
    }
    
    protected $messages = [
        'content.required_if' => 'Content is required when verifying text.',
        'content.min' => 'Content must be at least 10 characters long.',
        'content.max' => 'Content cannot exceed 50,000 characters.',
        'url.required_if' => 'URL is required when verifying a URL.',
        'url.url' => 'Please enter a valid URL.',
        'file.required_if' => 'File is required when verifying a file.',
        'file.mimes' => 'File must be a text, PDF, or Word document.',
        'file.max' => 'File size cannot exceed 10MB.',
    ];

    protected VerificationService $verificationService;

    protected ContentHashService $contentHashService;

    public function boot(
        VerificationService $verificationService,
        ContentHashService $contentHashService
    ) {
        $this->verificationService = $verificationService;
        $this->contentHashService = $contentHashService;
    }

    public function mount()
    {
        // Initialize with sample content for demo purposes
        if (app()->environment('local') && empty($this->content)) {
            $this->content = 'Breaking news: Scientists discover new method for renewable energy storage using advanced battery technology. The breakthrough could revolutionize how we store solar and wind power.';
        }
    }

    public function updatedInputType()
    {
        // Clear other fields when switching input type
        if ($this->inputType !== 'text') {
            $this->content = '';
        }
        if ($this->inputType !== 'url') {
            $this->url = '';
        }
        if ($this->inputType !== 'file') {
            $this->file = null;
        }

        // Clear any previous results
        $this->resetVerification();
    }

    public function verify()
    {
        Log::info('VerificationForm: Starting verification', ['input_type' => $this->inputType, 'url' => $this->url]);
        
        try {
            Log::info('VerificationForm: About to validate', [
                'inputType' => $this->inputType,
                'url' => $this->url,
                'content' => strlen($this->content ?? ''),
                'file' => $this->file ? 'present' : 'null'
            ]);
            
            // Custom validation based on input type
            if ($this->inputType === 'url') {
                $this->validateOnly('url');
            } elseif ($this->inputType === 'text') {
                $this->validateOnly('content');
            } elseif ($this->inputType === 'file') {
                $this->validateOnly('file');
            }
            Log::info('VerificationForm: Validation passed');
        } catch (\Exception $e) {
            Log::error('VerificationForm: Validation failed', [
                'error' => $e->getMessage(),
                'inputType' => $this->inputType,
                'url' => $this->url
            ]);
            throw $e;
        }
        
        $this->isVerifying = true;
        $this->errorMessage = null;

        try {
            // Set timeout for the entire verification process
            set_time_limit(120); // 2 minutes max
            
            Log::info('VerificationForm: Extracting content');
            // Extract content based on input type
            $contentToVerify = $this->extractContent();

            if (empty($contentToVerify)) {
                throw new Exception('No content could be extracted for verification.');
            }
            
            Log::info('VerificationForm: Content extracted', ['length' => strlen($contentToVerify)]);
            
            // Prepare metadata
            $metadata = $this->prepareMetadata();

            // Generate content hash
            $contentHash = $this->contentHashService->generateHash($contentToVerify, $metadata);

            // Check if we've verified this content recently
            $existingRequest = VerificationRequest::where('content_hash', $contentHash)
                ->where('created_at', '>=', now()->subHours(24))
                ->with(['results'])
                ->first();

            if ($existingRequest && $existingRequest->results->isNotEmpty()) {
                Log::info('VerificationForm: Using cached results');
                // Use cached results
                $this->verificationResult = $existingRequest->results->first()->toArray();
                $this->verificationResult['cached'] = true;
                $this->verificationResult['original_request_date'] = $existingRequest->created_at->toISOString();
            } else {
                Log::info('VerificationForm: Calling verification service');
                // Perform new verification
                $this->verificationResult = $this->verificationService->verifyContent(
                    $contentToVerify,
                    array_merge($metadata, ['content_hash' => $contentHash])
                );
                Log::info('VerificationForm: Verification service completed');
                
                // Store the verification request for future caching
                VerificationRequest::create([
                    'content_hash' => $contentHash,
                    'content' => $contentToVerify,
                    'content_type' => $this->inputType,
                    'metadata' => $metadata,
                    'source_url' => $this->inputType === 'url' ? $this->url : null,
                    'status' => 'completed',
                    'results' => $this->verificationResult,
                    'completed_at' => now(),
                ]);
            }
            
            $this->verificationComplete = true;
            
            // Dispatch browser event for analytics/tracking
            $this->dispatch('verification-completed', [
                'type' => $this->inputType,
                'confidence' => $this->verificationResult['overall_confidence'] ?? 0,
                'cached' => $this->verificationResult['cached'] ?? false,
            ]);

        } catch (Exception $e) {
            Log::error('Verification failed in Livewire component', [
                'error' => $e->getMessage(),
                'input_type' => $this->inputType,
                'content_length' => strlen($contentToVerify ?? ''),
            ]);

            $this->errorMessage = 'Verification failed: '.$e->getMessage();
        } finally {
            $this->isVerifying = false;
        }
    }

    public function resetVerification()
    {
        $this->verificationComplete = false;
        $this->verificationResult = null;
        $this->errorMessage = null;
    }

    public function newVerification()
    {
        $this->resetVerification();
        $this->content = '';
        $this->url = '';
        $this->file = null;
        $this->metadata = [];
    }

    protected function extractContent(): string
    {
        switch ($this->inputType) {
            case 'text':
                return trim($this->content);

            case 'url':
                return $this->extractContentFromUrl();

            case 'file':
                return $this->extractContentFromFile();

            default:
                throw new Exception('Invalid input type specified.');
        }
    }

    protected function extractContentFromUrl(): string
    {
        try {
            Log::info('VerificationForm: Extracting content from URL using Python crawler', ['url' => $this->url]);
            
            // Use the Python crawler service for proper content extraction
            $pythonCrawler = app(\App\Services\PythonCrawlerService::class);
            $result = $pythonCrawler->crawlUrl($this->url);
            
            if (!$result['success']) {
                throw new Exception('Python crawler failed: ' . ($result['error'] ?? 'Unknown error'));
            }
            
            $content = $result['data']['content'] ?? $result['data']['text'] ?? '';
            
            // Check if there was an extraction error
            if (isset($result['data']['error'])) {
                throw new Exception('Content extraction failed: ' . $result['data']['error']);
            }
            
            if (empty($content) || strlen($content) < 100) {
                throw new Exception('Insufficient content extracted from URL. Content must be at least 100 characters.');
            }
            
            Log::info('VerificationForm: Content extracted successfully', ['length' => strlen($content)]);
            return $content;

        } catch (Exception $e) {
            throw new Exception('Failed to extract content from URL: '.$e->getMessage());
        }
    }

    protected function extractContentFromFile(): string
    {
        try {
            $extension = $this->file->getClientOriginalExtension();
            $content = '';

            switch (strtolower($extension)) {
                case 'txt':
                    $content = $this->file->get();
                    break;

                case 'pdf':
                    // For PDF extraction, you would typically use a library like smalot/pdfparser
                    // For now, we'll throw an error suggesting text files
                    throw new Exception('PDF extraction not yet implemented. Please use text files or copy/paste the content.');
                case 'doc':
                case 'docx':
                    // For Word document extraction, you would use libraries like phpoffice/phpword
                    throw new Exception('Word document extraction not yet implemented. Please use text files or copy/paste the content.');
                default:
                    throw new Exception('Unsupported file type.');
            }

            if (strlen(trim($content)) < 10) {
                throw new Exception('File contains insufficient content for verification.');
            }

            return trim($content);

        } catch (Exception $e) {
            throw new Exception('Failed to extract content from file: '.$e->getMessage());
        }
    }

    protected function prepareMetadata(): array
    {
        $metadata = [
            'input_type' => $this->inputType,
            'verification_timestamp' => now()->toISOString(),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
        ];

        if ($this->inputType === 'url') {
            $metadata['source_url'] = $this->url;
            $metadata['domain'] = parse_url($this->url, PHP_URL_HOST);
        }

        if ($this->inputType === 'file' && $this->file) {
            $metadata['original_filename'] = $this->file->getClientOriginalName();
            $metadata['file_size'] = $this->file->getSize();
            $metadata['file_type'] = $this->file->getClientOriginalExtension();
        }

        return array_merge($metadata, $this->metadata);
    }

    public function getConfidenceLevelProperty()
    {
        if (! $this->verificationResult) {
            return 'Unknown';
        }

        $confidence = $this->verificationResult['overall_confidence'] ?? 0;

        if ($confidence >= 0.8) {
            return 'Very High';
        }
        if ($confidence >= 0.6) {
            return 'High';
        }
        if ($confidence >= 0.4) {
            return 'Medium';
        }
        if ($confidence >= 0.2) {
            return 'Low';
        }

        return 'Very Low';
    }

    public function getConfidenceColorProperty()
    {
        if (! $this->verificationResult) {
            return 'gray';
        }

        $confidence = $this->verificationResult['overall_confidence'] ?? 0;

        if ($confidence >= 0.7) {
            return 'green';
        }
        if ($confidence >= 0.4) {
            return 'yellow';
        }

        return 'red';
    }

    public function render()
    {
        return view('livewire.verification-form');
    }
}
