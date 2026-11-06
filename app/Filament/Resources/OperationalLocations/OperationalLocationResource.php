<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationalLocations;

use App\Filament\Resources\OperationalLocations\Pages\ListOperationalLocations;
use App\Models\OperationalLocation;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class OperationalLocationResource extends Resource
{
    protected static ?string $model = OperationalLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = '4. Configuración';

    protected static ?string $navigationLabel = 'Ubicaciones y Unidades';

    protected static ?string $modelLabel = 'Ubicación Operativa';

    protected static ?string $pluralModelLabel = 'Ubicaciones Móviles y Unidades Asignadas';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isSuperAdmin();
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Configuración de Punto de Venta Móvil y Unidad Asignada')
                    ->description('Gestione las ubicaciones habilitadas y el código de carro/unidad móvil por defecto.')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('code')
                                ->label('Código de Ubicación')
                                ->placeholder('LOC-SOPOCACHI')
                                ->required()
                                ->maxLength(40)
                                ->unique(ignoreRecord: true),

                            TextInput::make('name')
                                ->label('Nombre de la Zona / Ubicación')
                                ->placeholder('Sopocachi')
                                ->required()
                                ->maxLength(120)
                                ->unique(ignoreRecord: true),

                            TextInput::make('default_unit_code')
                                ->label('Unidad Móvil Asignada')
                                ->default('DN-M01')
                                ->required()
                                ->maxLength(30),

                            Toggle::make('is_active')
                                ->label('Ubicación Activa')
                                ->default(true)
                                ->inline(false),
                        ]),

                        TextInput::make('address_reference')
                            ->label('Dirección / Referencia del Punto')
                            ->placeholder('Ej: Av. 20 de Octubre / Plaza Abaroa')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Ubicación')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('default_unit_code')
                    ->label('Unidad Móvil')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('address_reference')
                    ->label('Dirección / Referencia')
                    ->searchable(),

                TextColumn::make('shifts_count')
                    ->label('Jornadas Realizadas')
                    ->counts('shifts')
                    ->badge()
                    ->color('info'),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->slideOver()
                    ->modalWidth('3xl'),
                EditAction::make()
                    ->label('Editar')
                    ->slideOver()
                    ->modalWidth('3xl'),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->slideOver(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperationalLocations::route('/'),
        ];
    }
}

