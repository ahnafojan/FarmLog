<?php

namespace App\Filament\Pages\Tenancy;

use App\Actions\CreateUsaha;
use App\Models\TemplateUsaha;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisterUsaha extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Buat usaha';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('template_usahas_id')->label('Jenis usaha')->placeholder('Pilih jenis usaha')->required()
                ->options(fn (): array => TemplateUsaha::query()->where('aktif', true)->orderBy('urutan')->pluck('nama', 'id')->all()),
            TextInput::make('nama')->label('Nama usaha')->placeholder('Contoh: Ternak Maju Bersama')->required()->maxLength(150)
                ->rules([Rule::unique('usahas', 'nama')->where('user_id', Filament::auth()->id())->whereNull('deleted_at')]),
        ]);
    }

    /**
     * @param  array{nama: string, template_usahas_id: int|string}  $data
     */
    protected function handleRegistration(array $data): Model
    {
        try {
            return app(CreateUsaha::class)->handle(Filament::auth()->user(), $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => ['data.'.$field => $messages])->all());
        }
    }
}
