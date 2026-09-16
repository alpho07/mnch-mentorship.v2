<?php

namespace App\Filament\Resources\Shared\RelationManagers;

use App\Models\User;
use App\Models\Facility;
use App\Models\Cadre;
use App\Models\Department;
use App\Models\County;
use App\Models\Subcounty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Collection;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';
    protected static ?string $recordTitleAttribute = 'full_name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Personal Information')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('First Name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('middle_name')
                            ->label('Middle Name')
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('last_name')
                            ->label('Last Name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('id_number')
                            ->label('ID Number')
                            ->unique(User::class, 'id_number', ignoreRecord: true)
                            ->maxLength(255),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('System Access & Permissions')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Account Status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'suspended' => 'Suspended',
                                'trainee' => 'Trainee (No Login)',
                            ])
                            ->required()
                            ->default('active')
                            ->live(),
                        
                        Forms\Components\Select::make('roles')
                            ->label('System Roles')
                            ->options(fn() => Role::query()
                                ->where('guard_name', 'web')
                                ->orderBy('name')
                                ->pluck('name', 'name')
                                ->toArray())
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->optionsLimit(50)
                            ->hidden(fn (Forms\Get $get) => $get('status') === 'trainee'),
                        
                        Forms\Components\Select::make('permissions')
                            ->label('Direct Permissions')
                            ->relationship('permissions', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->optionsLimit(100)
                            ->helperText('Additional permissions beyond role permissions')
                            ->hidden(fn (Forms\Get $get) => $get('status') === 'trainee'),
                        
                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->hidden(fn (Forms\Get $get) => $get('status') === 'trainee'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Organizational Assignment')
                    ->schema([
                        Forms\Components\Select::make('facility_id')
                            ->label('Primary Facility')
                            ->options(Facility::with('subcounty.county')->get()->mapWithKeys(function ($facility) {
                                return [$facility->id => $facility->name . ' - ' . $facility->subcounty->county->name];
                            }))
                            ->searchable()
                            ->preload()
                            ->default($this->getOwnerFacilityId()),
                        
                        Forms\Components\Select::make('department_id')
                            ->label('Department')
                            ->options(Department::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->default($this->getOwnerDepartmentId()),
                        
                        Forms\Components\Select::make('cadre_id')
                            ->label('Cadre')
                            ->options(Cadre::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->default($this->getOwnerCadreId()),
                    ])
                    ->columns(3),
                
                Forms\Components\Section::make('Geographic Access Scope')
                    ->description('Define which geographic areas this user can access.')
                    ->schema([
                        Forms\Components\Select::make('counties')
                            ->label('County Access')
                            ->relationship('counties', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live(),
                        
                        Forms\Components\Select::make('subcounties')
                            ->label('Subcounty Access')
                            ->relationship('subcounties', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function (Forms\Get $get) {
                                $countyIds = $get('counties');
                                if (empty($countyIds)) {
                                    return Subcounty::pluck('name', 'id');
                                }
                                return Subcounty::whereIn('county_id', $countyIds)->pluck('name', 'id');
                            })
                            ->live(),
                        
                        Forms\Components\Select::make('facilities')
                            ->label('Facility Access')
                            ->relationship('facilities', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function (Forms\Get $get) {
                                $subcountyIds = $get('subcounties');
                                if (empty($subcountyIds)) {
                                    return Facility::pluck('name', 'id');
                                }
                                return Facility::whereIn('subcounty_id', $subcountyIds)->pluck('name', 'id');
                            }),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Full Name')
                    ->sortable(['first_name', 'last_name'])
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->limit(30),
                
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('facility.name')
                    ->label('Primary Facility')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->limit(25)
                    ->visible($this->shouldShowFacilityColumn()),
                
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->badge()
                    ->color('success')
                    ->visible($this->shouldShowDepartmentColumn()),
                
                Tables\Columns\TextColumn::make('cadre.name')
                    ->label('Cadre')
                    ->badge()
                    ->color('warning')
                    ->visible($this->shouldShowCadreColumn()),
                
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(',')
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'danger',
                        'division_lead', 'division' => 'warning',
                        'national_mentor_lead', 'national' => 'info',
                        'county_mentor_lead', 'subcounty_mentor_lead' => 'success',
                        default => 'gray',
                    })
                    ->limit(20),
                
                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                        'trainee' => 'Trainee',
                    ])
                    ->selectablePlaceholder(false),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                        'trainee' => 'Trainee',
                    ]),
                
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\SelectFilter::make('facility_id')
                    ->label('Primary Facility')
                    ->relationship('facility', 'name')
                    ->searchable()
                    ->preload()
                    ->visible($this->shouldShowFacilityColumn()),
                
                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->visible($this->shouldShowDepartmentColumn()),
                
                Tables\Filters\SelectFilter::make('cadre_id')
                    ->label('Cadre')
                    ->relationship('cadre', 'name')
                    ->searchable()
                    ->preload()
                    ->visible($this->shouldShowCadreColumn()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Auto-assign based on parent context
                        if ($this->getOwnerDepartmentId()) {
                            $data['department_id'] = $this->getOwnerDepartmentId();
                        }
                        if ($this->getOwnerCadreId()) {
                            $data['cadre_id'] = $this->getOwnerCadreId();
                        }
                        if ($this->getOwnerFacilityId()) {
                            $data['facility_id'] = $this->getOwnerFacilityId();
                        }
                        
                        // Set default password if not provided and not a trainee
                        if (empty($data['password']) && $data['status'] !== 'trainee') {
                            $data['password'] = bcrypt('default123');
                        }
                        
                        return $data;
                    })
                    ->after(function (User $record, array $data): void {
                        // Handle geographic access assignments
                        if (!empty($data['counties'])) {
                            $record->counties()->sync($data['counties']);
                        }
                        if (!empty($data['subcounties'])) {
                            $record->subcounties()->sync($data['subcounties']);
                        }
                        if (!empty($data['facilities'])) {
                            $record->facilities()->sync($data['facilities']);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (User $record): string => 
                        \App\Filament\Resources\UserResource::getUrl('view', ['record' => $record])
                    )
                    ->visible(fn (): bool => class_exists(\App\Filament\Resources\UserResource::class)),
                
                Tables\Actions\EditAction::make()
                    ->fillForm(function (User $record): array {
                        // Pre-fill geographic access data
                        return [
                            'counties' => $record->counties->pluck('id')->toArray(),
                            'subcounties' => $record->subcounties->pluck('id')->toArray(),
                            'facilities' => $record->facilities->pluck('id')->toArray(),
                        ];
                    })
                    ->action(function (User $record, array $data): void {
                        // Update the user record
                        $record->update($data);
                        
                        // Handle geographic access assignments
                        $record->counties()->sync($data['counties'] ?? []);
                        $record->subcounties()->sync($data['subcounties'] ?? []);
                        $record->facilities()->sync($data['facilities'] ?? []);
                        
                        // Handle roles and permissions
                        if (isset($data['roles'])) {
                            $record->syncRoles($data['roles']);
                        }
                        if (isset($data['permissions'])) {
                            $record->syncPermissions($data['permissions']);
                        }
                    }),
                
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('assign_role')
                        ->label('Assign Role')
                        ->icon('heroicon-o-user-plus')
                        ->color('success')
                        ->form([
                            Forms\Components\Select::make('role')
                                ->label('Role to Assign')
                                ->options(Role::pluck('name', 'name'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(function (User $user) use ($data) {
                                if ($user->status !== 'trainee') {
                                    $user->assignRole($data['role']);
                                }
                            });
                            
                            Notification::make()
                                ->title('Role assigned to selected users')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),
                    
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading($this->getEmptyStateHeading())
            ->emptyStateDescription($this->getEmptyStateDescription())
            ->emptyStateIcon('heroicon-o-users');
    }

    // Helper methods to detect context
    protected function getOwnerDepartmentId(): ?int
    {
        return $this->ownerRecord instanceof Department ? $this->ownerRecord->id : null;
    }

    protected function getOwnerCadreId(): ?int
    {
        return $this->ownerRecord instanceof Cadre ? $this->ownerRecord->id : null;
    }

    protected function getOwnerFacilityId(): ?int
    {
        return $this->ownerRecord instanceof \App\Models\Facility ? $this->ownerRecord->id : null;
    }

    protected function shouldShowDepartmentColumn(): bool
    {
        return !($this->ownerRecord instanceof Department);
    }

    protected function shouldShowCadreColumn(): bool
    {
        return !($this->ownerRecord instanceof Cadre);
    }

    protected function shouldShowFacilityColumn(): bool
    {
        return !($this->ownerRecord instanceof \App\Models\Facility);
    }

    protected function getEmptyStateHeading(): string
    {
        if ($this->ownerRecord instanceof Department) {
            return 'No users in this department yet';
        }
        if ($this->ownerRecord instanceof Cadre) {
            return 'No users in this cadre yet';
        }
        if ($this->ownerRecord instanceof \App\Models\Facility) {
            return 'No staff in this facility yet';
        }
        return 'No users yet';
    }

    protected function getEmptyStateDescription(): string
    {
        if ($this->ownerRecord instanceof Department) {
            return 'Start by adding users to this department.';
        }
        if ($this->ownerRecord instanceof Cadre) {
            return 'Start by assigning users to this cadre.';
        }
        if ($this->ownerRecord instanceof \App\Models\Facility) {
            return 'Start by adding staff members to this facility.';
        }
        return 'Start by creating your first user.';
    }
}
