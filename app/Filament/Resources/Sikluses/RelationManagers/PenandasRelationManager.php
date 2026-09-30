<?php

namespace App\Filament\Resources\Sikluses\RelationManagers;

use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PenandasRelationManager extends RelationManager
{
    protected static string $relationship = 'penandas';

    protected static ?string $title = 'Jadwal';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('tanggal')->label('Tanggal')->native(false)->displayFormat('d/m/Y')->placeholder('Pilih tanggal penanda')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('nama')->defaultSort('tanggal')->columns([
            TextColumn::make('nama')->label('Penanda'),
            TextColumn::make('tanggal')->label('Tanggal')->date('d/m/Y'),
        ])->recordActions([
            EditAction::make()->label('Ubah tanggal'),
        ]);
    }
}
