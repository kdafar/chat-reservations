<?php

namespace App\Wa\Http\Controllers;

use App\Wa\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppManagementController extends Controller
{
    public function __construct(
        private readonly WhatsAppService $whatsAppService,
    ) {}

    public function sendTestMessage(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'body' => ['required', 'string', 'max:1024'],
        ]);

        try {
            // Reuse your existing service – no new tables.
            $this->whatsAppService->sendTextMessage($data['phone'], $data['body']);

            return back()->with('send_test_ok', true);
        } catch (\Throwable $e) {
            Log::error('[WA-TOOLS] sendTestMessage failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'send_test' => 'Failed to send message. Check logs / access token / phone_number_id.',
            ]);
        }
    }

    public function createTemplate(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'regex:/^[a-z0-9_]+$/', 'max:255'],
            'category' => ['required', 'in:UTILITY,MARKETING,AUTHENTICATION'],
            'language' => ['required', 'string', 'max:20'],
            'body' => ['required', 'string', 'max:1024'],
        ], [
            'name.regex' => 'Template name can only contain lowercase letters, numbers, and underscores.',
        ]);

        try {
            $result = $this->whatsAppService->createSimpleTextTemplate(
                $data['name'],
                $data['category'],
                $data['language'],
                $data['body'],
            );

            return back()
                ->with('template_ok', true)
                ->with('template_name', $data['name']);
        } catch (\Throwable $e) {
            Log::error('[WA-TOOLS] createTemplate failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'create_template' => 'Failed to create template via API. Check logs for details.',
            ]);
        }
    }
}
