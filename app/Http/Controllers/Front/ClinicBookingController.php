<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\Doctor;
use App\Models\Partner;
use App\Models\Service;
use App\Models\WhatsappSession;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\MessageCatalog;
use App\Services\OtpService;
use App\Services\QrPassService;
use App\Services\WhatsAppApiServiceFactory;
use App\Services\WhatsAppSender;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClinicBookingController extends Controller
{
    // 1. View: Serves the React App
    public function index()
    {
        return view('front.clinic.booking');
    }

    // 2. API: Get List of Partners (Clinics)
    public function partners()
    {
        $partners = Partner::query()
            ->where('is_active', true)
            ->select('id', 'name', 'slug', 'logo_path')
            ->get();

        return response()->json($partners);
    }

    // 3. API: Get List of Branches (Filtered by Partner)
    public function branches(Request $request)
    {
        $query = Branch::query()->where('is_available', true);

        if ($request->has('partner_id')) {
            $query->where('partner_id', $request->partner_id);
        }

        $branches = $query->select('id', 'name', 'address', 'partner_id')->get();

        return response()->json($branches);
    }

    public function branchesIndex(Request $request): JsonResponse
    {
        $query = Branch::query()
            ->where('is_available', true);

        if ($request->filled('partner_id')) {
            $query->where('partner_id', (int) $request->partner_id);
        }

        // optional: filter by service (requires branch_service pivot)
        if ($request->filled('service_id')) {
            $query->forService((int) $request->service_id);
        }

        $branches = $query
            ->with(['partner:id,name,slug,logo_path'])
            ->select([
                'id', 'partner_id', 'slug',
                'name', 'address', 'phone',
                'rating_avg', 'rating_count',
                'logo_path', 'cover_image_path',
            ])
            ->orderByDesc('rating_avg')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Branch $b) {
                return [
                    'id' => $b->id,
                    'slug' => $b->slug,
                    'name' => $b->name,
                    'address' => $b->address,
                    'phone' => $b->phone,
                    'rating_avg' => $b->rating_avg,
                    'rating_count' => $b->rating_count,
                    'open_now' => $b->open_now,
                    'logo_url' => $b->logo_url,
                    'cover_image_url' => $b->cover_image_url,
                    'partner' => $b->partner,
                ];
            });

        return response()->json($branches);
    }

    // 4. API: Get List of Doctors for a Branch
    public function doctors(Request $request)
    {
        $request->validate(['branch_id' => 'required|integer']);

        $doctors = Doctor::query()
            ->where('branch_id', $request->branch_id)
            ->where('is_active', true)
            ->select('id', 'name', 'specialty', 'avatar_path', 'working_hours')
            ->get();

        return response()->json($doctors);
    }

    // 5. API: Get Time Slots
    public function slots(Request $request, AvailabilityService $availability)
    {
        $request->validate([
            'branch_id' => 'required|integer',
            'doctor_id' => 'required|integer',
            'date' => 'required|date',
            'party_size' => 'required|integer',
        ]);

        $slots = $availability->timesFor(
            (int) $request->branch_id,
            (string) $request->date,
            (int) $request->party_size,
            (int) $request->doctor_id
        );

        return response()->json($slots);
    }

    /**
     * Request a WhatsApp OTP code for a msisdn that's about to be used in
     * a public booking. No-op (returns ok) when the gate is disabled so
     * the frontend can probe the flag.
     */
    public function requestOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'msisdn' => 'required|string',
        ]);

        if (! (bool) config('clinic.booking_otp_enabled', false)) {
            return response()->json([
                'ok' => true,
                'enabled' => false,
            ]);
        }

        try {
            $expiresAt = $otp->request(
                channel: OtpService::CHANNEL_WHATSAPP,
                purpose: \App\Models\OtpCode::PURPOSE_BOOKING,
                recipient: (string) $data['msisdn'],
                ip: $request->ip(),
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 429);
        } catch (\Throwable $e) {
            Log::error('OTP request failed', ['msisdn' => $data['msisdn'], 'err' => $e->getMessage()]);

            return response()->json([
                'ok' => false,
                'message' => 'Could not send verification code. Try again.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'enabled' => true,
            'expires_in_seconds' => max(0, $expiresAt->getTimestamp() - now()->getTimestamp()),
        ]);
    }

    // 6. API: Create Booking (UPDATED to use BookingService)
    public function store(
        Request $request,
        BookingService $bookingService,
        WhatsAppApiServiceFactory $waFactory,
        QrPassService $qrService,
        MessageCatalog $messages,
        OtpService $otp
    ) {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'doctor_id' => 'required|exists:doctors,id',
            'party_size' => 'required|integer|min:1',
            'res_date' => 'required|date',
            'res_time' => 'required|string',
            'msisdn' => 'required|string',
            'name' => 'required|string',
            'notes' => 'nullable|string',
            // keep optional fields if frontend sends them later
            'email' => 'nullable|string',
            'otp_code' => 'nullable|string|max:8',
        ]);

        // Normalize msisdn (same idea you used in BookingService; keep it stable here too)
        $msisdn = trim((string) $data['msisdn']);
        $msisdnDigits = $msisdn !== '' ? preg_replace('/\D+/', '', $msisdn) : '';
        $msisdnFinal = $msisdnDigits ?: $msisdn;

        // OTP gate (only on web bookings; WA-originated bookings skip).
        if ((bool) config('clinic.booking_otp_enabled', false)) {
            $submittedCode = trim((string) ($data['otp_code'] ?? ''));

            if ($submittedCode === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Verification code required. Please request one and try again.',
                    'code' => 'otp_required',
                ], 422);
            }

            $verified = $otp->verify(
                purpose: \App\Models\OtpCode::PURPOSE_BOOKING,
                recipient: $msisdnFinal,
                code: $submittedCode,
            );

            if (! $verified) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Verification code is invalid or expired.',
                    'code' => 'otp_invalid',
                ], 422);
            }
        }

        // Normalize date/time (match BookingService expectations)
        $date = preg_replace('/\s.*/', '', trim((string) $data['res_date'])) ?: (string) $data['res_date'];
        $time = trim((string) $data['res_time']);
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }

        // Validation: Check for existing active, unattended bookings
        $hasActiveBooking = Booking::query()
            ->where('msisdn', $msisdnFinal)
            ->where('status', Booking::S_CONFIRMED)
            ->upcoming()
            ->whereNull('checked_in_at')
            ->exists();

        if ($hasActiveBooking) {
            return response()->json([
                'ok' => false,
                'message' => 'You already have an upcoming appointment. Please attend or cancel it before booking a new one.',
            ], 422);
        }

        $branchId = (int) $data['branch_id'];
        $partySize = max(1, (int) $data['party_size']);

        // Hold-like payload for unified booking service (web does not need to persist holds)
        $slotKey = "{$date}@{$time}@{$partySize}@{$branchId}";
        $holdRow = [
            'branch_id' => $branchId,
            'res_date' => $date,
            'res_time' => $time,
            'party_size' => $partySize,
            'msisdn' => $msisdnFinal,
            'slot_key' => $slotKey,
            'source' => 'web',
        ];

        try {
            $booking = $bookingService->confirmFromHold($holdRow, [
                'branch_id' => $branchId,
                'doctor_id' => (int) $data['doctor_id'],
                'party_size' => $partySize,
                'res_date' => $date,
                'res_time' => $time,

                'msisdn' => $msisdnFinal,
                'name' => (string) $data['name'],
                'email' => (string) ($data['email'] ?? ''),
                'notes' => (string) ($data['notes'] ?? ''),
                'locale' => (string) ($request->get('locale') ?? app()->getLocale()),
                'agree_terms' => true, // web submission implies acceptance; if you have checkbox, pass real value here

                'status' => Booking::S_CONFIRMED,
                'source' => 'web',
                // optional source_ref (safe add-only)
                'source_ref' => (string) ($request->header('X-Request-Id') ?? ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('Web Booking: confirmFromHold failed', [
                'branch_id' => $branchId,
                'doctor_id' => (int) $data['doctor_id'],
                'res_date' => $date,
                'res_time' => $time,
                'msisdn' => $msisdnFinal,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Failed to create booking. Please try another time slot.',
            ], 422);
        }

        // Burn the verified OTP so it can't be reused for a second booking.
        if ((bool) config('clinic.booking_otp_enabled', false)) {
            try {
                $otp->consume(\App\Models\OtpCode::PURPOSE_BOOKING, $msisdnFinal);
            } catch (\Throwable $e) {
                Log::warning('OTP consume failed (booking still created)', [
                    'booking_id' => $booking->id,
                    'err' => $e->getMessage(),
                ]);
            }
        }

        // Keep existing behavior: attempt WhatsApp confirmation
        try {
            $this->sendWhatsAppConfirmation($booking, $waFactory, $qrService, $messages);
        } catch (\Throwable $e) {
            Log::error("Web Booking: Failed to send WA QR for #{$booking->id}", ['error' => $e->getMessage()]);
        }

        return response()->json([
            'ok' => true,
            'booking' => $booking,
        ]);
    }

    // 7. API: Cancel Booking
    public function cancel(Request $request)
    {
        $data = $request->validate([
            'msisdn' => 'required|string',
            'booking_code' => 'required|string',
        ]);

        $msisdn = trim((string) $data['msisdn']);
        $msisdnDigits = $msisdn !== '' ? preg_replace('/\D+/', '', $msisdn) : '';
        $msisdnFinal = $msisdnDigits ?: $msisdn;

        // Secure Lookup: Must match Phone AND Code AND be Confirmed AND be Upcoming
        $booking = Booking::query()
            ->where('msisdn', $msisdnFinal)
            ->where('booking_code', strtoupper($data['booking_code']))
            ->where('status', Booking::S_CONFIRMED)
            ->upcoming()
            ->first();

        if (! $booking) {
            return response()->json([
                'ok' => false,
                'message' => 'No active booking found matching these details. Please check your Booking Reference.',
            ], 404);
        }

        $booking->update(['status' => Booking::S_CANCELLED]);

        return response()->json([
            'ok' => true,
            'message' => 'Booking cancelled successfully.',
        ]);
    }

    /**
     * Uses sendTemplateAdvanced to handle Header Image + Body Vars
     */
    private function sendWhatsAppConfirmation(
        Booking $booking,
        WhatsAppApiServiceFactory $waFactory,
        QrPassService $qrService,
        MessageCatalog $messages
    ) {
        $phone = $booking->msisdn;
        if (! $phone) {
            return;
        }

        $session = WhatsappSession::where('phone', $phone)->first();
        $locale = $session?->locale ?? 'en';

        // 1. Generate Data
        $tokenRow = $qrService->ensureToken($booking);
        $qrUrl = route('bookings.qr', ['token' => $tokenRow->qr_token]);
        $passUrl = $qrService->passUrl($booking);

        $datePart = Carbon::parse($booking->res_date)->format('Y-m-d');
        $dt = Carbon::parse("{$datePart} {$booking->res_time}");

        $dateText = $locale === 'ar' ? $dt->translatedFormat('l j F') : $dt->format('D, M j');
        $timeText = $dt->format('g:i A');

        $templateName = 'clinic_confirmed_v3';

        $headerParams = [
            [
                'type' => 'image',
                'image' => [
                    'link' => $qrUrl,
                ],
            ],
        ];

        $bodyParams = [
            ['type' => 'text', 'text' => $dateText],
            ['type' => 'text', 'text' => $timeText],
            ['type' => 'text', 'text' => $booking->booking_code],
            ['type' => 'text', 'text' => $passUrl],
        ];

        $sender = new WhatsAppSender($waFactory->make());

        if (method_exists($sender, 'sendTemplateAdvanced')) {
            $sender->sendTemplateAdvanced($phone, $templateName, $locale, $headerParams, $bodyParams);
        } else {
            Log::warning('WhatsAppSender::sendTemplateAdvanced missing in service class.');
        }

        Log::info("Web Booking: Template '{$templateName}' sent to {$phone} for #{$booking->id}");
    }

    public function services(): JsonResponse
    {
        $services = Service::query()
            ->where('is_active', true)
            ->select('id', 'name', 'slug', 'icon')
            ->orderBy('id')
            ->get();

        return response()->json($services);
    }

    /**
     * Public headline counts for the marketing site — real data, no fake stats.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'treatments' => ClinicItem::query()->where('type', 'service')->where('is_active', true)->count(),
            'categories' => Service::query()->where('is_active', true)->count(),
            'doctors' => Doctor::query()->where('is_active', true)->count(),
            'branches' => Branch::query()->count(),
        ]);
    }

    public function branchShow(Branch $branch): JsonResponse
    {
        $branch->load([
            'partner:id,name,slug,logo_path',
            'services:id,name,slug,icon',
            'openingHours',
        ]);

        $doctors = Doctor::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->select('id', 'name', 'specialty', 'avatar_path', 'working_hours', 'consultation_fee', 'branch_id')
            ->get();

        return response()->json([
            'branch' => [
                'id' => $branch->id,
                'slug' => $branch->slug,
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'email' => $branch->email,
                'license_number' => $branch->license_number,
                'rating_avg' => $branch->rating_avg,
                'rating_count' => $branch->rating_count,
                'open_now' => $branch->open_now,
                'logo_url' => $branch->logo_url,
                'cover_image_url' => $branch->cover_image_url,
                'partner' => $branch->partner,
                'services' => $branch->services,
            ],
            'doctors' => $doctors,
        ]);
    }

    public function doctorShow(Doctor $doctor): JsonResponse
    {
        $doctor->load([
            'partner:id,name,slug,logo_path',
            'branch:id,name,slug,address,phone,logo_path,cover_image_path,rating_avg,rating_count,is_available',
        ]);

        $branch = $doctor->branch;

        return response()->json([
            'doctor' => [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'specialty' => $doctor->specialty,
                'avatar_path' => $doctor->avatar_path,
                'working_hours' => $doctor->working_hours,
                'consultation_fee' => $doctor->consultation_fee,
                'partner' => $doctor->partner,
                'branch' => $branch ? [
                    'id' => $branch->id,
                    'slug' => $branch->slug,
                    'name' => $branch->name,
                    'address' => $branch->address,
                    'phone' => $branch->phone,
                    'rating_avg' => $branch->rating_avg,
                    'rating_count' => $branch->rating_count,
                    'open_now' => $branch->open_now,
                    'logo_url' => $branch->logo_url,
                    'cover_image_url' => $branch->cover_image_url,
                ] : null,
            ],
        ]);
    }
}
