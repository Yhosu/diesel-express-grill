<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = '4. Configuración';

    protected static ?string $navigationLabel = 'Usuarios y Roles';

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Gestión de Usuarios y Roles';

    protected static ?int $navigationSort = 3;

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
        $user = Auth::user();

        return $user instanceof User && $user->isSuperAdmin() && $user->id !== $record->getKey();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Credenciales y Nivel de Acceso (Roles del Sistema)')
                    ->description('Super Administrador tiene control total, edición y acceso a la Tabla de Auditoría. Subadministrador solo registra operaciones y visualiza reportes (no edita ni elimina).')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('name')
                                ->label('Nombre Completo')
                                ->required()
                                ->maxLength(120),

                            TextInput::make('email')
                                ->label('Correo Electrónico')
                                ->email()
                                ->required()
                                ->maxLength(150)
                                ->unique(ignoreRecord: true),
                        ]),

                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            Select::make('role')
                                ->label('Rol Asignado')
                                ->options(UserRole::class)
                                ->default(UserRole::SUB_ADMIN->value)
                                ->required(),

                            TextInput::make('password')
                                ->label('Contraseña')
                                ->password()
                                ->revealable()
                                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                                ->dehydrated(fn (?string $state): bool => filled($state))
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->helperText('Dejar en blanco al editar para mantener la contraseña actual.'),
                        ]),

                        Toggle::make('is_active')
                            ->label('Usuario Activo para Iniciar Sesión')
                            ->default(true)
                            ->inline(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                TextColumn::make('role')
                    ->label('Rol en el Sistema')
                    ->badge()
                    ->sortable(),

                TextColumn::make('permissions_summary')
                    ->label('Permisos Efectivos')
                    ->state(fn (User $record): string => $record->isSuperAdmin()
                        ? 'Control Total + Edición + Reportes + Tabla de Auditoría'
                        : 'Solo Registro + Consulta + Reportes (Sin Edición)'),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Fecha Creación')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Rol')
                    ->options(UserRole::class),
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
                    ->slideOver()
                    ->visible(fn (User $record): bool => Auth::id() !== $record->id),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
