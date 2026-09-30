<?php

namespace App\Filament\Resources\Sikluses;

use App\Filament\Resources\Sikluses\Pages\CreateSiklus;
use App\Filament\Resources\Sikluses\Pages\EditSiklus;
use App\Filament\Resources\Sikluses\Pages\ListSikluses;
use App\Filament\Resources\Sikluses\RelationManagers\PenandasRelationManager;
use App\Models\Siklus;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SiklusResource extends Resource
{
    protected static ?string $model = Siklus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $modelLabel = 'Siklus';

    protected static ?string $pluralModelLabel = 'Siklus';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $tenantRelationshipName = 'sikluses';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')->label('Nama siklus')
                ->placeholder('Contoh: Panen Telor Ayam bulan maret - Desember 2026')
                ->required()
                ->maxLength(150),
            DatePicker::make('tanggal_mulai')->label('Tanggal mulai')->native(false)->displayFormat('d/m/Y')->placeholder('Pilih tanggal mulai')->required()->default(today())
                ->disabledOn('edit'),
            TextInput::make('populasi_awal')->label('Jumlah awal (ekor)')->placeholder('Contoh: 1000')->integer()->minValue(1)->maxValue(100000000)->required()
                ->disabledOn('edit'),
            TextInput::make('umur_masuk_hari')->label('Umur saat masuk (hari)')->placeholder('Contoh: 0 untuk bibit baru menetas')->integer()->minValue(0)->maxValue(36500)->default(0)->required()
                ->disabledOn('edit'),
            TextInput::make('harga_bibit')->label('Harga bibit per ekor')->placeholder('Contoh: 2000')->prefix('Rp')->integer()->minValue(0)->maxValue(1000000000)
                ->required()->visibleOn('create'),
            Textarea::make('catatan')->label('Catatan')->placeholder('Contoh: Bibit dari pemasok langganan')->maxLength(5000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('tanggal_mulai', 'desc')->columns([
            TextColumn::make('nama'),
            TextColumn::make('tanggal_mulai')->label('Mulai')->date('d/m/Y')->sortable(),
            TextColumn::make('populasi_awal')->label('Jumlah awal')->numeric()->suffix(' ekor'),
            TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state): string => ucfirst($state))
                ->color(fn (string $state): string => $state === 'berjalan' ? 'success' : 'gray'),
            TextColumn::make('tanggal_selesai')->label('Selesai')->date('d/m/Y')->placeholder('—'),
        ])->filters([
            SelectFilter::make('status')->label('Status')->placeholder('Semua status')->options(['berjalan' => 'Berjalan', 'selesai' => 'Selesai']),
        ])->recordActions([
            EditAction::make()->label('Detail'),
            static::closeAction(),
        ]);
    }

    public static function closeAction(): Action
    {
        return Action::make('tutup')->label('Tutup siklus')->color('warning')
            ->visible(fn (Siklus $record): bool => $record->status === 'berjalan')
            ->authorize('update')
            ->schema([
                DatePicker::make('tanggal_selesai')->label('Tanggal selesai')->native(false)->displayFormat('d/m/Y')->placeholder('Pilih tanggal selesai')->required()->default(today())
                    ->minDate(fn (Siklus $record) => $record->tanggal_mulai),
            ])
            ->action(function (Siklus $record, array $data): void {
                Gate::authorize('update', $record);
                DB::transaction(function () use ($record, $data): void {
                    $siklus = Siklus::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
                    $siklus->forceFill(['status' => 'selesai', 'tanggal_selesai' => $data['tanggal_selesai']])->save();
                });
            });
    }

    public static function getRelations(): array
    {
        return [PenandasRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSikluses::route('/'),
            'create' => CreateSiklus::route('/create'),
            'edit' => EditSiklus::route('/{record}/edit'),
        ];
    }
}
