<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Wraps WhatsAppApiService->listTemplates() and normalizes.
 */
class WhatsAppTemplateCatalog
{
    public function __construct(protected WhatsAppApiService $wa) {}

    /**
     * @return array<int, array{name:string, language:string, status:string, components:array}>
     */
    public function all(): array
    {
        // Cache 10 minutes to keep the form snappy
        return Cache::remember('wa.templates.approved.v1', 600, function () {
            // Expecting WhatsAppApiService->listTemplates(): returns Graph `data` array
            // Each: ['name','language','status','components'=>[['type'=>'BODY','text'=>'...'],['type'=>'HEADER','format'=>'IMAGE'|'TEXT'|..]]]
            $data = $this->wa->listTemplates(fields: ['name', 'language', 'status', 'components']);
            $rows = is_array($data) ? $data : [];
            // Some SDKs wrap it as ['data'=>[]]
            $rows = isset($rows['data']) && is_array($rows['data']) ? $rows['data'] : $rows;

            // keep only APPROVED
            return array_values(array_filter($rows, fn ($t) => ($t['status'] ?? '') === 'APPROVED'));
        });
    }

    /** Options for Filament Select: key=name, value="name (lang)" */
    public function options(): array
    {
        $opts = [];
        foreach ($this->all() as $t) {
            $opts[$t['name']] = $t['name'].' ('.($t['language'] ?? 'lang').')';
        }
        ksort($opts);

        return $opts;
    }

    /** Find by template name */
    public function find(string $name): ?array
    {
        foreach ($this->all() as $t) {
            if (($t['name'] ?? null) === $name) {
                return $t;
            }
        }

        return null;
    }
}
