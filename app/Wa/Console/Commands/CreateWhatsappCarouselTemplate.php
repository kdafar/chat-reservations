<?php

namespace App\Wa\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManagerStatic as Image; // Import the Image facade

class CreateWhatsappCarouselTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:create-carousel-template 
                            {name : The name for the new message template (e.g., test_carousel_cuisine_v1)} 
                            {--image-url1= : The public URL for the first header image.} 
                            {--image-url2= : The public URL for the second header image.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a new WhatsApp Media Carousel template by downloading, resizing, and uploading images, then registering the template.';

    private $wabaId;

    private $appId;

    private $apiToken;

    private $graphUrl = 'https://graph.facebook.com/v23.0/';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // --- Configuration Setup ---
        $this->wabaId = config('services.whatsapp.waba_id');
        $this->appId = config('services.whatsapp.app_id');
        $this->apiToken = config('services.whatsapp.api_token');

        if (! $this->wabaId || ! $this->appId || ! $this->apiToken) {
            $this->error('One or more required WhatsApp configuration keys are missing.');
            $this->line('Please ensure your config/services.php file has the following keys inside the "whatsapp" array:');
            $this->line(' - \'waba_id\' => env(\'WHATSAPP_WABA_ID\'),');
            $this->line(' - \'app_id\' => env(\'WHATSAPP_APP_ID\'),');
            $this->line(' - \'api_token\' => env(\'WHATSAPP_API_TOKEN\'),');
            $this->line('And that your .env file has the corresponding values.');

            return Command::FAILURE;
        }

        $templateName = $this->argument('name');
        $imageUrl1 = $this->option('image-url1');
        $imageUrl2 = $this->option('image-url2');

        if (! $imageUrl1 || ! $imageUrl2) {
            $this->error('You must provide URLs for both --image-url1 and --image-url2 options.');

            return Command::FAILURE;
        }

        $this->info("Starting process to create template '{$templateName}'...");

        try {
            // --- Step 1: Download, RESIZE, and Upload images to get media handles ---
            $this->line('Processing images to get media handles...');

            $this->line(" -> Processing image 1 from: {$imageUrl1}");
            $handle1 = $this->getMediaHandle($imageUrl1);
            $this->info(' -> Success! Handle 1: '.substr($handle1, 0, 30).'...');

            $this->line(" -> Processing image 2 from: {$imageUrl2}");
            $handle2 = $this->getMediaHandle($imageUrl2);
            $this->info(' -> Success! Handle 2: '.substr($handle2, 0, 30).'...');

            // --- Step 2: Create the template with the new handles ---
            $this->line("\nCreating message template on Meta's servers...");

            // IMPORTANT: Uncomment ONLY ONE of the lines below based on which template you want to create
            // The template name passed to the command line will be used for the selected template.

            // To create the Cuisine Carousel Template (ULTRA-VANILLA):
            $this->createProductCardCarouselTemplate($templateName, $handle1, $handle2);

            // To create the Restaurant Carousel Template (ULTRA-VANILLA):
            // $this->createTestRestaurantCarouselTemplate($templateName, $handle1, $handle2);

        } catch (Exception $e) {
            $this->error('An error occurred: '.$e->getMessage());
            Log::error('Failed to create WhatsApp template.', ['exception' => $e]);

            return Command::FAILURE;
        }

        $this->info("\n All done! Your template '{$templateName}' has been submitted for review.");

        return Command::SUCCESS;
    }

    /**
     * Downloads an image from a URL, RESIZES it, uploads it to Meta's servers, and returns the media handle.
     *
     * @return string The uploaded file handle.
     *
     * @throws Exception
     */
    private function getMediaHandle(string $imageUrl): string
    {
        $this->line(" -> Downloading image from: {$imageUrl}");
        $response = Http::get($imageUrl);

        if ($response->failed()) {
            throw new Exception("Failed to download image from URL: {$imageUrl}");
        }

        $originalFileContents = $response->body();
        $originalFileMime = $response->header('Content-Type');

        // Verify MIME type, use finfo if header is missing/incorrect
        if (! $originalFileMime || strpos($originalFileMime, 'image/') !== 0) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $originalFileMime = $finfo->buffer($originalFileContents);
            $this->warn(" -> Could not determine MIME type from header for {$imageUrl}. Detected as: {$originalFileMime}");
        }

        if (strpos($originalFileMime, 'image/') !== 0) {
            throw new Exception("Downloaded file is not an image: {$originalFileMime}");
        }

        $this->line(' -> Resizing image to 1200x628 (1.91:1 aspect ratio)...');
        try {
            // Load the image from contents
            $image = Image::make($originalFileContents);

            // Resize the image to 1200x628 pixels (1.91:1 aspect ratio)
            $image->fit(1200, 628); // Width, Height

            // Get the resized image contents and its new MIME type (e.g., 'image/jpeg')
            $resizedFileContents = $image->encode('jpg', 90); // Encode as JPEG with 90 quality
            $resizedFileMime = 'image/jpeg'; // Explicitly set after encoding
            $resizedFileSize = strlen($resizedFileContents);

            $this->info(" -> Image resized. New size: {$resizedFileSize} bytes. New MIME: {$resizedFileMime}");

        } catch (\Intervention\Image\Exception\NotReadableException $e) {
            throw new Exception("Could not read or process image from URL: {$imageUrl}. Error: ".$e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Error during image resizing for {$imageUrl}. Error: ".$e->getMessage());
        }

        // 1. Start an upload session with the RESIZED file's info
        $sessionResponse = Http::post("{$this->graphUrl}{$this->appId}/uploads", [
            'file_length' => $resizedFileSize, // Use resized file size
            'file_type' => $resizedFileMime,   // Use resized file MIME type
            'access_token' => $this->apiToken,
        ]);

        if ($sessionResponse->failed()) {
            throw new Exception("Failed to start upload session for {$imageUrl}. Response: ".$sessionResponse->body());
        }
        $sessionId = $sessionResponse->json('id');
        $this->line(" -> Upload session started. Session ID: {$sessionId}");

        // 2. Upload the RESIZED file binary to the session
        $uploadResponse = Http::withHeaders([
            'Authorization' => 'OAuth '.$this->apiToken,
            'file_offset' => 0,
        ])->withBody($resizedFileContents, $resizedFileMime) // Use resized file contents and MIME
            ->post("{$this->graphUrl}{$sessionId}");

        if ($uploadResponse->failed()) {
            throw new Exception("Failed to upload file binary for {$imageUrl}. Response: ".$uploadResponse->body());
        }

        $handle = $uploadResponse->json('h');
        if (! $handle) {
            throw new Exception("Could not retrieve file handle from upload response for {$imageUrl}.");
        }

        return $handle;
    }

    /**
     * Creates a bare-bones, ultra-simple WhatsApp Media Carousel template for testing approval.
     * For Cuisines.
     *
     * @param  string  $name
     * @param  string  $handle1
     * @param  string  $handle2
     *
     * @throws Exception
     */
    private function createProductCardCarouselTemplate(string $templateName): void
    {
        // Define a card structure once
        $cardComponent = [
            'components' => [
                ['type' => 'HEADER', 'format' => 'PRODUCT'],
                ['type' => 'BUTTONS', 'buttons' => [
                    ['type' => 'spm', 'text' => 'View'],
                ]],
            ],
        ];

        // Create an array with 10 identical card structures
        $cards = array_fill(0, 10, $cardComponent);

        $payload = [
            'name' => $templateName,
            'language' => 'en_US',
            'category' => 'MARKETING',
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => 'Check out our latest dishes, {{1}}!',
                    'example' => ['body_text' => [['Pablo']]],
                ],
                [
                    'type' => 'CAROUSEL',
                    'cards' => $cards, // Use the array of 10 cards
                ],
            ],
        ];

        Http::withToken($this->apiToken)
            ->post("{$this->graphUrl}{$this->wabaId}/message_templates", $payload)
            ->throw();

        $this->info("Submitted {$templateName} with 10 cards for review.");
    }

    /**
     * Creates a bare-bones, ultra-simple WhatsApp Media Carousel template for testing approval.
     * For Restaurants.
     *
     * @throws Exception
     */
    private function createTestRestaurantCarouselTemplate(string $name, string $handle1, string $handle2): void
    {
        $payload = [
            'name' => $name, // e.g., 'test_carousel_restaurant_v1'
            'language' => 'en_US',
            'category' => 'MARKETING',
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => 'See our options below.', // Very simple body text
                    'example' => [
                        'body_text' => [['User', '']], // Simple example for two variables
                    ],
                ],
                [
                    'type' => 'CAROUSEL',
                    'cards' => [
                        [
                            'components' => [
                                ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => [$handle1]]],
                                // Ultra-simple card body for two variables
                                ['type' => 'BODY', 'text' => 'Name: {{1}}\nInfo: {{2}}', 'example' => ['body_text' => [['Option A', 'Short info.']]]],
                                // Simple Quick Reply Button
                                ['type' => 'BUTTONS', 'buttons' => [
                                    [
                                        'type' => 'quick_reply',
                                        'text' => 'Open', // Generic button text
                                    ],
                                ]],
                            ],
                        ],
                        [
                            'components' => [
                                ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => [$handle2]]],
                                // Ultra-simple card body for two variables
                                ['type' => 'BODY', 'text' => 'Name: {{1}}\nInfo: {{2}}', 'example' => ['body_text' => [['Option B', 'More info.']]]],
                                // Simple Quick Reply Button
                                ['type' => 'BUTTONS', 'buttons' => [
                                    [
                                        'type' => 'quick_reply',
                                        'text' => 'Open', // Generic button text
                                    ],
                                ]],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withToken($this->apiToken)
            ->post("{$this->graphUrl}{$this->wabaId}/message_templates", $payload);

        if ($response->failed()) {
            Log::error("Failed to create template '{$name}'.", ['response' => $response->body()]);
            throw new Exception("Failed to create template '{$name}'. Check logs for the full error response.");
        }
        $this->info(" -> Template '{$name}' creation request sent successfully! Meta Template ID: ".$response->json('id'));
    }
}
