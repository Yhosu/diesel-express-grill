<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = '3. Reportes y Administración';

    protected static ?string $navigationLabel = 'Tabla de Auditoría';

    protected static ?string $modelLabel = 'Registro de Auditoría';

    protected static ?string $pluralModelLabel = 'Bitácora de Auditoría de Cambios';

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
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Detalle Técnico del Evento Auditado')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('created_at')
                                ->label('Fecha y Hora Exacta')
                                ->formatStateUsing(fn ($state): string => $state ? (string) $state : '-'),

                            TextInput::make('user_name')
                                ->label('Usuario Responsable'),

                            TextInput::make('user_role')
                                ->label('Rol del Usuario'),

                            TextInput::make('event')
                                ->label('Tipo de Acción')
                                ->formatStateUsing(fn (?string $state): string => AuditLog::eventLabel((string) $state)),

                            TextInput::make('module')
                                ->label('Módulo del Sistema'),

                            TextInput::make('record_label')
                                ->label('Registro Afectado'),

                            TextInput::make('ip_address')
                                ->label('Dirección IP'),
                        ]),

                        Textarea::make('summary')
                            ->label('Resumen de Cómo se Realizó el Cambio')
                            ->rows(2)
                            ->columnSpanFull(),

                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            KeyValue::make('old_values')
                                ->label('Valores Anteriores (Antes del Cambio)')
                                ->keyLabel('Campo')
                                ->valueLabel('Valor Anterior'),

                            KeyValue::make('new_values')
                                ->label('Valores Nuevos (Después del Cambio)')
                                ->keyLabel('Campo')
                                ->valueLabel('Valor Nuevo'),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Cuándo (Fecha / Hora)')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('user_name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user_role')
                    ->label('Rol')
                    ->badge()
                    ->color(fn (?string $state): string => str_contains((string) $state, 'Super') ? 'primary' : 'info'),

                TextColumn::make('event')
                    ->label('Acción')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => AuditLog::eventLabel((string) $state))
                    ->color(fn (?string $state): string => AuditLog::eventColor((string) $state))
                    ->sortable(),

                TextColumn::make('module')
                    ->label('Módulo')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('record_label')
                    ->label('Registro')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('summary')
                    ->label('Cómo se hizo el cambio (Detalle)')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('rango_fechas')
                    ->label('Rango de Fechas')
                    ->schema([
                        DatePicker::make('desde')->label('Fecha Desde'),
                        DatePicker::make('hasta')->label('Fecha Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date));
                    }),

                SelectFilter::make('event')
                    ->label('Tipo de Acción')
                    ->options([
                        'created' => 'Creación',
                        'updated' => 'Edición',
                        'deleted' => 'Eliminación',
                    ]),

                SelectFilter::make('module')
                    ->label('Módulo')
                    ->options([
                        'Turno Diario' => 'Turno Diario',
                        'Venta POS' => 'Venta POS',
                        'Merma / Excepción' => 'Merma / Excepción',
                        'Producto' => 'Producto',
                        'Receta BOM' => 'Receta BOM',
                        'Insumo / Empaque' => 'Insumo / Empaque',
                        'Compra de Insumo' => 'Compra de Insumo',
                        'Egreso Operativo' => 'Egreso Operativo',
                        'Usuario y Rol' => 'Usuario y Rol',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver Cambios')
                    ->slideOver()
                    ->modalWidth('4xl'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}

