<?php

namespace App\Livewire\Partner;

use App\Models\Branch;
use App\Models\CommerceOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class OrdersBoard extends Component
{
    use WithPagination;

    // Filters
    public ?int $branchId = null;

    public ?string $status = null;           // new|accepted|preparing|ready|out_for_delivery|completed|cancelled

    public ?string $paymentStatus = null;    // paid|pending|failed|refunded (adapt to your enum)

    public ?string $channel = null;          // web|whatsapp|app|pos (adapt)

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $search = '';          // 🔎 new

    public int $perPage = 20;

    public bool $autoRefresh = true;     // 🔁 new

    public int $pollSeconds = 15;        // 🔁 new

    public bool $showFilters = false;          // collapsed by default

    public array $perPageOptions = [10, 20, 30, 50, 100];

    // Simple status map for actions & labels
    public array $statusOptions = [
        'placed' => 'Placed',
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'ready' => 'Ready',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    #[On('refresh-orders')]
    public function refreshOrders(): void {}

    public function updating($name, $value)
    {
        $this->resetPage();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function clearFilters(): void
    {
        $this->reset([
            'branchId', 'status', 'paymentStatus', 'channel', 'dateFrom', 'dateTo', 'search',
        ]);
        $this->resetPage();
    }

    public function render()
    {
        $partnerId = (int) session('active_partner_id');

        $branches = \App\Models\Branch::query()
            ->where('partner_id', $partnerId)
            ->orderBy('name->'.app()->getLocale())
            ->get(['id', 'name']);

        $orders = $this->baseQuery($partnerId)->paginate($this->perPage);

        return view('livewire.partner.orders-board', compact('orders', 'branches'));
    }

    protected function baseQuery(int $partnerId): Builder
    {
        return \App\Models\CommerceOrder::query()
            ->with(['branch', 'user', 'latestPayment'])
            ->where('partner_id', $partnerId)
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->paymentStatus, fn ($q) => $q->whereHas('latestPayment', fn ($p) => $p->where('status', $this->paymentStatus))
            )
            ->when($this->channel, fn ($q) => $q->where('type', $this->channel))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))

            // 🔎 flexible search: code, customer name, or user name/email/phone
            ->when($this->search !== '', function ($q) {
                $s = '%'.$this->search.'%';
                $q->where(function ($qq) use ($s) {
                    $qq->where('code', 'like', $s)
                        ->orWhere('notes', 'like', $s)
                        ->orWhere('customer_name', 'like', $s)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $s)
                            ->orWhere('email', 'like', $s)
                            ->orWhere('phone', 'like', $s)
                        );
                });
            })
            ->latest('id');
    }

    /** --- Actions --- */
    public function accept(int $orderId): void
    {
        $this->transition($orderId, 'accepted');
    }

    public function prepare(int $orderId): void
    {
        $this->transition($orderId, 'preparing');
    }

    public function ready(int $orderId): void
    {
        $this->transition($orderId, 'ready');
    }

    public function outForDelivery(int $orderId): void
    {
        $this->transition($orderId, 'out_for_delivery');
    }

    public function complete(int $orderId): void
    {
        $this->transition($orderId, 'completed');
    }

    public function cancel(int $orderId, ?string $reason = null): void
    {
        $this->transition($orderId, 'cancelled', $reason);
    }

    protected function transition(int $orderId, string $toStatus, ?string $reason = null): void
    {
        $this->validate([
            'branchId' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys($this->statusOptions))],
        ]);

        $user = Auth::user();
        $partnerId = (int) session('active_partner_id');

        /** @var CommerceOrder $order */
        $order = CommerceOrder::query()
            ->where('id', $orderId)
            ->where(function (Builder $q) use ($partnerId) {
                $q->whereHas('branch', fn (Builder $b) => $b->where('partner_id', $partnerId))
                    ->orWhereHas('items', fn (Builder $i) => $i->whereHas('branch', fn (Builder $b) => $b->where('partner_id', $partnerId))
                    );
            })
            ->firstOrFail();

        // Permission check (simple: owner/manager/staff can accept/complete/cancel; kitchen can prepare/ready)
        $allowed = $this->allowedToTransition($user, $order, $toStatus);
        if (! $allowed) {
            $this->dispatch('notify', type: 'danger', message: __('You are not allowed to perform this action.'));

            return;
        }

        // Basic guardrails — adapt to your state machine if present
        $validTransitions = [
            'new' => ['accepted', 'cancelled'],
            'accepted' => ['preparing', 'cancelled'],
            'preparing' => ['ready', 'cancelled'],
            'ready' => ['out_for_delivery', 'completed', 'cancelled'],
            'out_for_delivery' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];

        $from = $order->status;
        if (! in_array($toStatus, $validTransitions[$from] ?? [], true)) {
            $this->dispatch('notify', type: 'warning', message: __("Invalid transition: $from → $toStatus"));

            return;
        }

        $order->status = $toStatus;
        if ($toStatus === 'cancelled' && $reason) {
            $order->cancel_reason = $reason;
        }
        $order->save();

        // Optional: trigger ticket print or kitchen event here
        // PrintOrderJob::dispatch($order->id);

        $this->dispatch('notify', type: 'success', message: __('Order updated.'));
        $this->dispatch('refresh-orders');
    }

    protected function allowedToTransition($user, CommerceOrder $order, string $to): bool
    {
        // If you saved roles in partner_user_branch with pivot 'role'
        $branchId = $order->branch_id;
        $roles = $user->partnerBranches()
            ->when($branchId, fn ($q) => $q->where('branches.id', $branchId))
            ->pluck('pivot.role')
            ->all();

        $roles = array_unique($roles);

        $kitchenActions = ['preparing', 'ready'];
        $staffActions = ['accepted', 'preparing', 'ready', 'out_for_delivery', 'completed'];
        $managerOwner = ['accepted', 'preparing', 'ready', 'out_for_delivery', 'completed', 'cancelled'];

        if (array_intersect($roles, ['owner', 'manager'])) {
            return in_array($to, $managerOwner, true);
        }
        if (in_array('kitchen', $roles, true)) {
            return in_array($to, $kitchenActions, true);
        }
        if (in_array('staff', $roles, true)) {
            return in_array($to, $staffActions, true);
        }
        if (in_array('finance', $roles, true)) {
            return false;
        }

        // If no branch role found, allow partner-level fallback:
        return $user->partners()->whereKey((int) session('active_partner_id'))->exists();
    }
}
