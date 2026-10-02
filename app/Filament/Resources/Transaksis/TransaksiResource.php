<?php

namespace App\Filament\Resources\Transaksis;

use App\Actions\SaveTransaksi;
use App\Filament\Resources\Sikluses\SiklusResource;
use App\Filament\Resources\Transaksis\Pages\ManageTransaksis;
use App\Models\Kategori;
use App\Models\Transaksi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class TransaksiResource extends Resource
{
    protected static ?string $model = Transaksi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'Transaksi';

    protected static ?string $pluralModelLabel = 'Transaksi';

    protected static ?string $tenantRelationshipName = 'transaksis';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('usaha');
    }

    public static function category(Get $get): ?Kategori
    {
        return Filament::getTenant()->kategoris()->whereKey($get('kategoris_id'))
            ->where('arah', $get('arah'))->first();
    }

    public static function usesQuantity(Get $get): bool
    {
        return $get('arah') === 'pemasukan' || (bool) static::category($get)?->pakai_kuantitas;
    }

    public static function calculateTotal(Get $get, Set $set): void
    {
        if (is_numeric($get('qty')) && is_numeric($get('harga_satuan'))) {
            $set('total', (int) round((float) $get('qty') * (float) $get('harga_satuan')));
        }
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ToggleButtons::make('arah')->label('Jenis')->options(['pengeluaran' => 'Keluar', 'pemasukan' => 'Masuk'])
                ->inline()->default('pengeluaran')->required()->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('kategoris_id', null);
                    $set('qty', null);
                    $set('satuan', null);
                    $set('harga_satuan', null);
                    $set('total', null);
                }),
            DatePicker::make('tanggal')->label('Tanggal')->native(false)->displayFormat('d/m/Y')->placeholder('Pilih tanggal transaksi')->default(today())->required(),
            Select::make('kategoris_id')->label('Kategori')->placeholder('Pilih kategori transaksi')->searchPrompt('Cari nama kategori')->required()->live()->searchable()
                ->options(fn (Get $get, ?Transaksi $record): array => Filament::getTenant()->kategoris()
                    ->where('arah', $get('arah'))
                    ->where(fn (Builder $query) => $query->where('aktif', true)->orWhere('id', $record?->kategoris_id))
                    ->orderBy('urutan')->pluck('nama', 'id')->all())
                ->afterStateUpdated(function (Get $get, Set $set): void {
                    $category = static::category($get);
                    $set('satuan', $category?->satuan_default);
                    $price = $get('arah') === 'pemasukan'
                        ? Filament::getTenant()->transaksis()->where('kategoris_id', $category?->id)
                            ->whereNotNull('harga_satuan')->orderByDesc('tanggal')->orderByDesc('id')->value('harga_satuan')
                        : null;
                    $set('harga_satuan', $price);
                    $set('qty', null);
                    $set('total', null);
                }),
            Select::make('sikluses_id')->label('Siklus')
                ->placeholder(fn (Get $get): string => $get('arah') === 'pemasukan' ? 'Pilih siklus' : 'Biaya umum usaha')
                ->required(fn (Get $get): bool => $get('arah') === 'pemasukan')
                ->options(fn (?Transaksi $record): array => Filament::getTenant()->sikluses()
                    ->where(fn (Builder $query) => $query->where('status', 'berjalan')->orWhere('id', $record?->sikluses_id))
                    ->orderByDesc('tanggal_mulai')->orderByDesc('id')->pluck('nama', 'id')->all())
                ->default(fn () => Filament::getTenant()->sikluses()->where('status', 'berjalan')
                    ->orderByDesc('tanggal_mulai')->orderByDesc('id')->value('id'))
                ->hintAction(Action::make('buatSiklus')->label('Buat siklus')
                    ->url(fn (): string => SiklusResource::getUrl('create'))
                    ->visible(fn (): bool => ! Filament::getTenant()->sikluses()->where('status', 'berjalan')->exists())),
            TextInput::make('qty')->label('Jumlah')->placeholder('Contoh: 25')->numeric()->minValue(0.001)->maxValue(999999999.999)->step(0.001)
                ->rules(['decimal:0,3'])->required()->visible(fn (Get $get): bool => static::usesQuantity($get))
                ->live(onBlur: true)->afterStateUpdated(fn (Get $get, Set $set) => static::calculateTotal($get, $set)),
            TextInput::make('satuan')->label('Satuan')->placeholder('Contoh: kg atau ekor')->required()->maxLength(20)
                ->visible(fn (Get $get): bool => static::usesQuantity($get)),
            TextInput::make('harga_satuan')->label('Harga satuan')->placeholder('Contoh: 12000')->prefix('Rp')->integer()->minValue(0)->maxValue(1000000000000)
                ->required()->visible(fn (Get $get): bool => static::usesQuantity($get))
                ->live(onBlur: true)->afterStateUpdated(fn (Get $get, Set $set) => static::calculateTotal($get, $set)),
            TextInput::make('total')->label('Total')->placeholder('Contoh: 300000')->prefix('Rp')->integer()->minValue(0)->maxValue(1000000000000000)->required(),
            TextInput::make('pembeli')->label('Pembeli')->placeholder('Contoh: Warung Bu Sari (opsional)')->maxLength(150)
                ->visible(fn (Get $get): bool => $get('arah') === 'pemasukan'),
            Textarea::make('catatan')->label('Catatan')->placeholder('Tambahkan catatan transaksi (opsional)')->maxLength(5000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('tanggal', 'desc')->columns([
            Split::make([
                Stack::make([
                    TextColumn::make('kategori.nama')->label('Kategori')->searchable()
                        ->weight(FontWeight::SemiBold)->wrap()->extraAttributes(['class' => 'wrap-anywhere']),
                    TextColumn::make('siklus.nama')->label('Siklus')->placeholder('Biaya umum usaha')
                        ->icon(Heroicon::OutlinedArrowsRightLeft)->color('gray')->wrap()->extraAttributes(['class' => 'wrap-anywhere']),
                ])->space(1),
                Stack::make([
                    TextColumn::make('total')->label('Total')->money('IDR', locale: 'id')->sortable()
                        ->weight(FontWeight::Bold)->wrap(),
                    Split::make([
                        TextColumn::make('arah')->label('Jenis')->badge()->grow(false)
                            ->formatStateUsing(fn (string $state): string => $state === 'pemasukan' ? 'Masuk' : 'Keluar')
                            ->color(fn (string $state): string => $state === 'pemasukan' ? 'success' : 'danger'),
                        TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable()
                            ->icon(Heroicon::OutlinedCalendarDays)->color('gray')->wrap(),
                    ]),
                ])->space(1),
            ])->from('lg'),
            Panel::make([
                Stack::make([
                    TextColumn::make('pembeli')->label('Pembeli')->description('Pembeli', position: 'above')
                        ->placeholder('Tidak ada pembeli')->wrap()->extraAttributes(['class' => 'wrap-anywhere']),
                    TextColumn::make('catatan')->label('Catatan')->description('Catatan', position: 'above')
                        ->placeholder('Tidak ada catatan')->wrap()->extraAttributes(['class' => 'wrap-anywhere']),
                ])->space(3),
            ])->collapsible(),
        ])->filters([
            SelectFilter::make('arah')->label('Jenis')->placeholder('Semua transaksi')->options(['pengeluaran' => 'Keluar', 'pemasukan' => 'Masuk']),
        ])->recordActions([
            EditAction::make()->using(fn (Transaksi $record, array $data, Action $action): Transaksi => static::saveTransaction($data, $action, $record)),
            DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTransaksis::route('/')];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function saveTransaction(array $data, Action $action, ?Transaksi $record = null): Transaksi
    {
        try {
            return app(SaveTransaksi::class)->handle(Filament::auth()->user(), Filament::getTenant(), $data, $record);
        } catch (ValidationException $exception) {
            $statePath = 'mountedActions.'.$action->getNestingIndex().'.data.';

            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => [$statePath.$field => $messages])->all());
        }
    }
}
