<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'employee_id',
        'department',
        'allowed_inventory_categories',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'allowed_inventory_categories' => 'array',
    ];

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is college head
     */
    public function isCollegeHead(): bool
    {
        return $this->role === 'college_head';
    }
    /**
     * Check if user is senior head
     */
    public function isSeniorHead(): bool
    {
        return $this->role === 'senior_head';
    }
    /**
     * Check if user is junior head
     */
    public function isJuniorHead(): bool
    {
        return $this->role === 'junior_head';
    }
    /**
     * Check if user is teacher
     */
    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    /**
     * Check if user is staff
     */
    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /**

     * Check if user can create events
     */
    public function canCreateEvents(): bool
    {
        return in_array($this->role, ['admin', 'college_head', 'senior_head', 'junior_head']);
    }

    /**
     * Check if user can approve events
     */
    public function canApproveEvents(): bool
    {
        return in_array($this->role, ['admin']);
    }

    /**
     * Check if user can manage inventory
     */
    public function canManageInventory(): bool
    {
        return in_array($this->role, ['admin', 'staff', 'Head Maintenance']) || 
               !empty($this->allowed_inventory_categories);
    }

    /**
     * Check if user can access a specific inventory category
     */
    public function canAccessInventoryCategory(?string $category): bool
    {
        $allowedCategories = $this->getAllowedInventoryCategories();

        // Empty means unrestricted access (admin / Head Maintenance / unrestricted staff).
        if (empty($allowedCategories)) {
            return in_array($this->role, ['admin', 'staff', 'Head Maintenance']);
        }

        if ($category === null || trim($category) === '') {
            return false;
        }

        $normalizedCategory = $this->normalizeInventoryCategory($category);
        $normalizedAllowed = array_map([$this, 'normalizeInventoryCategory'], $allowedCategories);

        return in_array($normalizedCategory, $normalizedAllowed, true);
    }

    /**
     * Get allowed inventory categories for this user
     */
    public function getAllowedInventoryCategories(): array
    {
        // Head Maintenance and admins are always unrestricted.
        if (in_array($this->role, ['admin', 'Head Maintenance'])) {
            return [];
        }

        // Explicit user category assignments take precedence.
        if (!empty($this->allowed_inventory_categories)) {
            return array_values(array_filter(
                array_unique(array_map('trim', $this->allowed_inventory_categories)),
                static fn ($category) => $category !== ''
            ));
        }

        // Fallback: restrict by assigned department/category when no explicit list exists.
        $department = trim((string) $this->department);
        if ($department !== '') {
            return [$department];
        }

        return [];
    }

    private function normalizeInventoryCategory(string $category): string
    {
        return mb_strtolower(trim($category));
    }

    /**
     * Check if user is admin or staff
     */
    public function isAdminOrStaff(): bool
    {
        return in_array($this->role, ['admin', 'staff']);
    }

    /**
     * Check if user can confirm item returns
     * Staff, admin, and Head Maintenance can confirm returns
     */
    public function canConfirmReturns(): bool
    {
        return $this->canManageInventory();
    }

    /**
     * Get events created by this user
     */
    public function createdEvents()
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    /**
     * Get events approved by this user
     */
    public function approvedEvents()
    {
        return $this->hasMany(Event::class, 'approved_by');
    }
}
