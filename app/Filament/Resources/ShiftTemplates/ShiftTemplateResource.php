<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShiftTemplates;

use App\Filament\Resources\ShiftTemplates\Pages\ListShiftTemplates;
use App\Models\ShiftTemplate;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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

class ShiftTemplateResource extends Resource
{
    protected static ?string $model = ShiftTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = '4. Configuración';

    protected static ?string $navigationLabel = 'Catálogo de Turnos';

    protected static ?string $modelLabel = 'Tipo de Turno';

    protected static ?string $pluralModelLabel = 'Configuración de Turnos y Horarios';

    protected static ?int $navigationSort = 1;

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
                Section::make('Definición del Turno Operativo')
                    ->description('Configure los turnos estándar (horarios y fondo de caja inicial por defecto) que estarán disponibles al abrir una jornada diaria.')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('code')
                                ->label('Código del Turno')
                                ->placeholder('TUR-01')
                                ->default(fn (): string => 'TUR-' . str_pad((string) (ShiftTemplate::query()->count() + 1), 2, '0', STR_PAD_LEFT))
                                ->required()
                                ->maxLength(30)
                                ->unique(ignoreRecord: true),

                            TextInput::make('name')
                                ->label('Nombre del Turno')
                                ->placeholder('Ej: Turno Mediodía / Almuerzo')
                                ->required()
                                ->maxLength(120),

                            TimePicker::make('start_time')
                                ->label('Hora de Inicio')
                                ->default('11:00:00')
                                ->required(),

                            TimePicker::make('end_time')
                                ->label('Hora de Fin')
                                ->default('15:30:00')
                                ->required(),

                            TextInput::make('default_float')
                                ->label('Fondo Inicial por Defecto (Bs.)')
                                ->numeric()
                                ->prefix('Bs.')
                                ->default(200.00)
                                ->required(),

                            Toggle::make('is_active')
                                ->label('Turno Activo')
                                ->default(true)
                                ->inline(false),
                        ]),

                        TextInput::make('description')
                            ->label('Descripción / Referencia Operativa')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nombre del Turno')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('Hora Inicio')
                    ->time('H:i'),

                TextColumn::make('end_time')
                    ->label('Hora Cierre')
                    ->time('H:i'),

                TextColumn::make('default_float')
                    ->label('Fondo Inicial (Bs.)')
                    ->formatStateUsing(fn ($state): string => 'Bs. ' . number_format((float) $state, 2))
                    ->weight('bold'),

                TextColumn::make('shifts_count')
                    ->label('Jornadas Asociadas')
                    ->counts('shifts')
                    ->badge()
                    ->color('info'),

                IconColumn::make('is_active')
                    ->label('Activo')
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
            'index' => ListShiftTemplates::route('/'),
        ];
    }
}

