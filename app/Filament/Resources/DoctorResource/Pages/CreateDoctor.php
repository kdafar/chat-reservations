<?php

namespace App\Filament\Resources\DoctorResource\Pages;

use App\Filament\Resources\DoctorResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateDoctor extends CreateRecord
{
    protected static string $resource = DoctorResource::class;

    protected ?string $generatedPassword = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $email = $data['email'] ?? null;
        $name = $data['name'] ?? 'Doctor';
        $phone = $data['phone'] ?? null;

        $user = $email ? User::where('email', $email)->first() : null;

        if (! $user) {
            $this->generatedPassword = Str::password(12, symbols: false);

            $user = User::create([
                'name' => $name,
                'email' => $email ?: 'doctor-'.Str::random(8).'@clinic.local',
                'phone' => $phone,
                'password' => $this->generatedPassword,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
        }

        if (! $user->hasRole('clinic_doctor')) {
            $user->assignRole('clinic_doctor');
        }

        $data['user_id'] = $user->id;

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return DB::transaction(fn () => static::getModel()::create($data));
    }

    protected function afterCreate(): void
    {
        if ($this->generatedPassword) {
            Notification::make()
                ->title(__('clinic_doctor.notifications.user_created.title'))
                ->body(__('clinic_doctor.notifications.user_created.body', [
                    'email' => $this->record->user?->email,
                    'password' => $this->generatedPassword,
                ]))
                ->success()
                ->persistent()
                ->send();
        }
    }
}
