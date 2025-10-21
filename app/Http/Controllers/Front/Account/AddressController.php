<?php

namespace App\Http\Controllers\Front\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Block;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $addresses = $user->addresses()
            ->with(['city', 'block'])
            ->latest()
            ->get();

        $cities = method_exists(City::class, 'active')
            ? City::active()->orderBy('name->'.app()->getLocale())->get(['id', 'name'])
            : City::orderBy('name->'.app()->getLocale())->get(['id', 'name']);

        $blocks = method_exists(Block::class, 'active')
            ? Block::active()->orderBy('name->'.app()->getLocale())->get(['id', 'name'])
            : Block::orderBy('name->'.app()->getLocale())->get(['id', 'name']);

        return view('front.account.addresses.index', compact('addresses', 'cities', 'blocks', 'user'));
    }

    public function create()
    {
        $cities = method_exists(City::class, 'active')
            ? City::active()->orderBy('name->'.app()->getLocale())->get(['id', 'name'])
            : City::orderBy('name->'.app()->getLocale())->get(['id', 'name']);

        $blocks = method_exists(Block::class, 'active')
            ? Block::active()->orderBy('name->'.app()->getLocale())->get(['id', 'name'])
            : Block::orderBy('name->'.app()->getLocale())->get(['id', 'name']);

        return view('front.account.addresses.create', compact('cities', 'blocks'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'block_id' => ['required', 'integer', 'exists:blocks,id'],
            'street' => ['required', 'string', 'max:190'],
            'building' => ['nullable', 'string', 'max:190'],
            'house' => ['nullable', 'string', 'max:190'],
            'apartment' => ['nullable', 'string', 'max:190'],
            'floor' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'make_default' => ['sometimes', 'boolean'],
        ]);

        /** @var \App\Models\Address $address */
        $address = $request->user()->addresses()->create($data);

        if ($request->boolean('make_default')) {
            $this->setDefaultInternal($request->user(), $address);
        }

        return redirect()->route('account.addresses.index')->with('success', __('Address added.'));
    }

    public function edit(Request $request, $id)
    {
        $address = $request->user()->addresses()->with(['city', 'block'])->findOrFail($id);

        $cities = method_exists(City::class, 'active')
            ? City::active()->orderBy('name->'.app()->getLocale())->get(['id', 'name'])
            : City::orderBy('name->'.app()->getLocale())->get(['id', 'name']);

        $blocks = method_exists(Block::class, 'active')
            ? Block::active()->orderBy('name->'.app()->getLocale())->get(['id', 'name'])
            : Block::orderBy('name->'.app()->getLocale())->get(['id', 'name']);

        return view('front.account.addresses.edit', compact('address', 'cities', 'blocks'));
    }

    public function update(Request $request, $id)
    {
        $address = $request->user()->addresses()->findOrFail($id);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'block_id' => ['required', 'integer', 'exists:blocks,id'],
            'street' => ['required', 'string', 'max:190'],
            'building' => ['nullable', 'string', 'max:190'],
            'house' => ['nullable', 'string', 'max:190'],
            'apartment' => ['nullable', 'string', 'max:190'],
            'floor' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'make_default' => ['sometimes', 'boolean'],
        ]);

        $address->update($data);

        if ($request->boolean('make_default')) {
            $this->setDefaultInternal($request->user(), $address);
        }

        return redirect()->route('account.addresses.index')->with('success', __('Address updated.'));
    }

    public function destroy(Request $request, $id)
    {
        $address = $request->user()->addresses()->findOrFail($id);

        $wasDefault = (bool) $address->is_default;
        $address->delete();

        // If default was deleted, unset FK and/or pick another as default
        if ($wasDefault) {
            if (Schema::hasColumn('users', 'default_address_id')) {
                $request->user()->forceFill(['default_address_id' => null])->save();
            }
            $another = $request->user()->addresses()->latest()->first();
            if ($another) {
                $this->setDefaultInternal($request->user(), $another);
            }
        }

        return redirect()->route('account.addresses.index')->with('success', __('Address deleted.'));
    }

    public function setDefault(Request $request, $id)
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $this->setDefaultInternal($request->user(), $address);

        return back()->with('success', __('Default address updated.'));
    }

    protected function setDefaultInternal($user, Address $address): void
    {
        $user->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        if (Schema::hasColumn('users', 'default_address_id')) {
            $user->forceFill(['default_address_id' => $address->id])->save();
        }
    }
}
