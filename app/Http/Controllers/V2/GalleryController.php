<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\GalleryCase;
use App\Models\Service;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Before/after cases for the public results gallery.
 *
 * Publishing a patient's photographs is a consent decision, not a formatting
 * one: a case can only go live with `consent_on_file` ticked, and the public
 * endpoint filters on it as well (GalleryCase::scopePublic) so a mistake here
 * cannot leak a case onto the website.
 */
class GalleryController extends Controller
{
    use ResolvesAccessibleClinics;

    protected function authorize(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_clinic_packages')) {
            abort(403, 'Not authorized to manage the results gallery.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorize($request);

        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status', 'all');

        $query = GalleryCase::query()->with(['service:id,name', 'branch:id,name', 'doctor:id,name']);

        if ($q !== '') {
            $query->where('title', 'like', "%{$q}%");
        }
        if ($status === 'published') {
            $query->where('is_published', true);
        } elseif ($status === 'draft') {
            $query->where('is_published', false);
        }

        $page = $query->orderBy('sort_order')->orderByDesc('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(fn (GalleryCase $c) => [
            'id' => $c->id,
            'title' => $c->getTranslations('title'),
            'summary' => $c->getTranslations('summary'),
            'protocol' => $c->getTranslations('protocol'),
            'title_label' => $c->localized_title,
            'before_image_url' => $c->before_image_url,
            'after_image_url' => $c->after_image_url,
            'service_id' => $c->service_id,
            'branch_id' => $c->branch_id,
            'doctor_id' => $c->doctor_id,
            'service' => $c->service?->name_label,
            'branch' => $c->branch?->localized_name,
            'doctor' => $c->doctor?->name,
            'consent_on_file' => $c->consent_on_file,
            'is_published' => $c->is_published,
            'sort_order' => $c->sort_order,
            // What the website will actually do with this row.
            'live' => $c->is_published && $c->consent_on_file,
        ]);

        $branchIds = $this->accessibleBranchIds();

        return Inertia::render('Gallery/Index', [
            'filters' => ['q' => $q, 'status' => $status],
            'page' => $page,
            'services' => Service::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name_label])->all(),
            'branches' => Branch::query()
                ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds ?: [0]))
                ->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name])->all(),
            'doctors' => Doctor::query()->where('is_active', true)
                ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds ?: [0]))
                ->orderBy('name')->get(['id', 'name', 'branch_id'])->all(),
            'counts' => [
                'total' => GalleryCase::query()->count(),
                'live' => GalleryCase::query()->public()->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize($request);
        GalleryCase::create($this->validated($request));

        return back()->with('flash', ['type' => 'success', 'message' => 'Case added.']);
    }

    public function update(Request $request, GalleryCase $galleryCase): RedirectResponse
    {
        $this->authorize($request);
        $galleryCase->update($this->validated($request));

        return back()->with('flash', ['type' => 'success', 'message' => 'Case updated.']);
    }

    public function destroy(Request $request, GalleryCase $galleryCase): RedirectResponse
    {
        $this->authorize($request);
        $galleryCase->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Case removed.']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title_en' => ['required', 'string', 'max:160'],
            'title_ar' => ['nullable', 'string', 'max:160'],
            'summary_en' => ['nullable', 'string', 'max:600'],
            'summary_ar' => ['nullable', 'string', 'max:600'],
            'protocol_en' => ['nullable', 'string', 'max:120'],
            'protocol_ar' => ['nullable', 'string', 'max:120'],
            'before_image_url' => ['required', 'string', 'max:2048'],
            'after_image_url' => ['required', 'string', 'max:2048'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'consent_on_file' => ['boolean'],
            'is_published' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        return [
            'title' => ['en' => $data['title_en'], 'ar' => $data['title_ar'] ?: $data['title_en']],
            'summary' => ($data['summary_en'] ?? null) || ($data['summary_ar'] ?? null)
                ? ['en' => $data['summary_en'] ?? '', 'ar' => $data['summary_ar'] ?? ($data['summary_en'] ?? '')]
                : null,
            'protocol' => ($data['protocol_en'] ?? null) || ($data['protocol_ar'] ?? null)
                ? ['en' => $data['protocol_en'] ?? '', 'ar' => $data['protocol_ar'] ?? ($data['protocol_en'] ?? '')]
                : null,
            'before_image_url' => $data['before_image_url'],
            'after_image_url' => $data['after_image_url'],
            'service_id' => $data['service_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'doctor_id' => $data['doctor_id'] ?? null,
            'consent_on_file' => (bool) ($data['consent_on_file'] ?? false),
            'is_published' => (bool) ($data['is_published'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
