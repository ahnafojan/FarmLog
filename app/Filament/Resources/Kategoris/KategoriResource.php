<?php

namespace App\Filament\Resources\Kategoris;

use App\Filament\Resources\Kategoris\Pages\ManageKategoris;
use App\Models\Kategori;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class KategoriResource extends Resource
{
    protected static ?string $model = Kategori::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'Kategori';

    protected static ?string $pluralModelLabel = 'Kategori';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $tenantRelationshipName = 'kategoris';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('usaha');
    }

    public static function classificationLocked(?Kategori $record): bool
    {
        return $record !== null && ($record->kode_sistem !== null || $record->transaksis()->withTrashed()->exists());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')->label('Nama kategori')->placeholder('Contoh: Pakan atau Penjualan telur')->required()->maxLength(100)
                ->rules(fn (Get $get, ?Kategori $record): array => [
                    Rule::unique('kategoris', 'nama')->where('usahas_id', Filament::getTenant()->id)
                        ->where('arah', $record && static::classificationLocked($record) ? $record->arah : $get('arah'))
                        ->ignore($record?->id),
                ]),
            Select::make('arah')->label('Jenis')->placeholder('Pilih jenis transaksi')->options(['pengeluaran' => 'Pengeluaran', 'pemasukan' => 'Pemasukan'])
                ->default('pengeluaran')->required()->live()
                ->disabled(fn (?Kategori $record): bool => static::classificationLocked($record))
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('klasifikasi', $state === 'pemasukan' ? null : 'operasional')),
            Select::make('klasifikasi')->label('Klasifikasi')->placeholder('Pilih klasifikasi biaya')->options([
                'operasional' => 'Operasional', 'investasi' => 'Investasi',
            ])->default('operasional')->required(fn (Get $get): bool => $get('arah') === 'pengeluaran')
                ->visible(fn (Get $get): bool => $get('arah') === 'pengeluaran')
                ->disabled(fn (?Kategori $record): bool => static::classificationLocked($record)),
            Toggle::make('pakai_kuantitas')->label('Gunakan jumlah dan harga satuan')->default(false)->live()
                ->disabled(fn (?Kategori $record): bool => $record?->kode_sistem === 'bibit'),
            TextInput::make('satuan_default')->label('Satuan bawaan')->placeholder('Contoh: kg, ekor, atau karung')->maxLength(20)
                ->visible(fn (Get $get): bool => (bool) $get('pakai_kuantitas') || $get('arah') === 'pemasukan'),
            Toggle::make('aktif')->label('Aktif')->default(true)
                ->disabled(fn (?Kategori $record): bool => $record?->kode_sistem === 'bibit'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareData(array $data, ?Kategori $record = null): array
    {
        if (static::classificationLocked($record)) {
            $data['arah'] = $record->arah;
            $data['klasifikasi'] = $record->klasifikasi;
        }

        if (($data['arah'] ?? null) === 'pemasukan') {
            $data['klasifikasi'] = null;
        }

        if ($record?->kode_sistem === 'bibit') {
            $data['aktif'] = true;
            $data['pakai_kuantitas'] = true;
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('urutan')->columns([
            Split::make([
                Stack::make([
                    TextColumn::make('nama')->label('Kategori')->searchable()
                        ->weight(FontWeight::SemiBold)->wrap()->extraAttributes(['class' => 'wrap-anywhere']),
                    TextColumn::make('klasifikasi')->label('Klasifikasi')->placeholder('Tanpa klasifikasi')
                        ->formatStateUsing(fn (string $state): string => 'Klasifikasi: '.ucfirst($state))
                        ->color('gray')->wrap(),
                ])->space(1),
                Stack::make([
                    Split::make([
                        TextColumn::make('arah')->label('Jenis')->badge()->grow(false)
                            ->formatStateUsing(fn (string $state): string => ucfirst($state))
                            ->color(fn (string $state): string => $state === 'pemasukan' ? 'success' : 'danger'),
                        TextColumn::make('aktif')->label('Status')->badge()->grow(false)
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Nonaktif')
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                    ]),
                    TextColumn::make('pakai_kuantitas')->label('Kuantitas')
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Menggunakan kuantitas' : 'Tanpa kuantitas')
                        ->color('gray')->wrap(),
                ])->space(1),
            ])->from('lg'),
        ])->filters([
            SelectFilter::make('arah')->label('Jenis')->placeholder('Semua jenis')->options(['pengeluaran' => 'Pengeluaran', 'pemasukan' => 'Pemasukan']),
        ])->recordActions([
            EditAction::make()->mutateFormDataUsing(fn (array $data, Kategori $record): array => static::prepareData($data, $record)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageKategoris::route('/')];
    }
}
