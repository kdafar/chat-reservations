<?php

namespace App\Services\WAFlow;

class FlowResponder
{
    public function __construct(private FlowCrypto $crypto) {}

    public function encrypt(array $result, string $aesKey, string $requestIv)
    {
        $b64 = $this->crypto->encrypt($result, $aesKey, $requestIv);
        if ($b64 === null) {
            return response('encryption failed', 500);
        }

        // Flows expect raw base64, not JSON.
        return response($b64, 200)->header('Content-Type', 'text/plain; charset=utf-8');
    }

    public function navigate(FlowRequest $req, string $screen, object|array $data)
    {
        // Cast to array for processing
        $arr = is_array($data) ? $data : (array) $data;

        if ($screen === 'APPOINTMENT') {
            // Prune to allowed keys
            $allowed = [
                'party_size',
                'party_size_prefill',
                'is_date_enabled',
                'min_date',
                'max_date',
                'unavailable_dates',
                'include_days',
                'time',
                'is_time_enabled',
                'confirmation_message',
                'show_time',
            ];
            $arr = array_intersect_key($arr, array_flip($allowed));

            // FIX: Ensure party_size and time are always arrays
            $arr = $this->sanitizeAppointmentData($arr);
        }

        // Convert to object for JSON encoding
        $obj = (object) $arr;

        $payload = [
            'version' => '300',
            'screen' => $screen,
            'data' => $obj,
        ];

        return $this->encrypt($payload, $req->aesKey, $req->requestIv);
    }

    public function error(FlowRequest $req, string $message, array $extra = [])
    {
        $payload = [
            'version' => '300',
            'action' => 'ERROR',
            'data' => (object) array_merge(['error' => $message], $extra),
        ];

        return $this->encrypt($payload, $req->aesKey, $req->requestIv);
    }

    /**
     * Ensure APPOINTMENT screen data has valid array types.
     * This prevents "should be of type <array>" schema errors.
     */
    private function sanitizeAppointmentData(array $data): array
    {
        // Shape for dropdown items
        $shape = static fn (array $item): array => [
            'id' => (string) ($item['id'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'image' => (string) ($item['image'] ?? 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+Z1mQAAAAASUVORK5CYII='),
        ];

        // Ensure party_size is a valid array
        if (! isset($data['party_size']) || ! is_array($data['party_size'])) {
            $data['party_size'] = [];
        }
        $data['party_size'] = array_values(array_map($shape, $data['party_size']));

        // Ensure time is a valid array
        if (! isset($data['time']) || ! is_array($data['time'])) {
            $data['time'] = [];
        }
        $data['time'] = array_values(array_map($shape, $data['time']));

        // Sanitize scalars
        $data['party_size_prefill'] = (string) ($data['party_size_prefill'] ?? '');
        $data['min_date'] = (string) ($data['min_date'] ?? '');
        $data['max_date'] = (string) ($data['max_date'] ?? '');
        $data['is_date_enabled'] = (bool) ($data['is_date_enabled'] ?? true);
        $data['is_time_enabled'] = (bool) ($data['is_time_enabled'] ?? true);
        $data['show_time'] = (bool) ($data['show_time'] ?? false);
        $data['confirmation_message'] = (string) ($data['confirmation_message'] ?? '');

        // Ensure array fields are arrays
        $data['include_days'] = isset($data['include_days']) && is_array($data['include_days'])
            ? array_values($data['include_days'])
            : [];
        $data['unavailable_dates'] = isset($data['unavailable_dates']) && is_array($data['unavailable_dates'])
            ? array_values($data['unavailable_dates'])
            : [];

        return $data;
    }
}
