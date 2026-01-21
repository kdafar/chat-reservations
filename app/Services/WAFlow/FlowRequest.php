<?php

namespace App\Services\WAFlow;

class FlowRequest
{
    public function __construct(
        public string $version,
        public string $flowToken,
        public string $action,
        public string $screen,
        public array $data,
        public string $aesKey,
        public string $requestIv,
    ) {}

    public static function fromArray(array $arr, string $aesKey, string $iv): self
    {
        return new self(
            (string) ($arr['version'] ?? '3.0'),
            (string) ($arr['flow_token'] ?? ''),
            strtoupper((string) ($arr['action'] ?? '')),
            (string) ($arr['screen'] ?? ''),
            (array) ($arr['data'] ?? []),
            $aesKey,
            $iv,
        );
    }

    public function debugMeta(): array
    {
        return [
            'scope' => 'wa.flow.endpoint',
            'action' => $this->action,
            'screen' => $this->screen,
            'version' => $this->version,
            'flow_token' => $this->flowToken,
            'data_keys' => array_keys($this->data),
            'data_sample' => array_slice($this->data, 0, 6, true),
        ];
    }
}
