<?php

namespace App\Filament\Resources\Sikluses\Pages;

use App\Actions\CreateSiklus as CreateSiklusAction;
use App\Filament\Resources\Sikluses\SiklusResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateSiklus extends CreateRecord
{
    protected static string $resource = SiklusResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateSiklusAction::class)->handle(Filament::auth()->user(), Filament::getTenant(), $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => ['data.'.$field => $messages])->all());
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
