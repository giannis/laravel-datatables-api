<?php

namespace GPapakitsos\LaravelDatatables\Tests\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return UserFactory::new();
    }

    /**
     * Relationships
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function userLogins()
    {
        return $this->hasMany(UserLogin::class);
    }

    public function userNameAndEmail()
    {
        return $this->hasOne(User::class, 'id', 'id');
    }

    /**
     * Scopes
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('name', 'LIKE', '%'.$term.'%')->orWhere('email', 'LIKE', '%'.$term.'%');
    }

    public function scopeTest($query)
    {
        return $query->where('id', 1);
    }

    public function scopeByEmail($query, $value)
    {
        return $query->where('email', $value);
    }

    /**
     * Datatable fields
     *
     * @return array
     */
    public function getDatatablesData()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'country' => $this->country->name ?? null,
            'userLogins' => $this->userLogins->count(),
            'settings' => $this->settings,
            'userNameAndEmail' => $this->name.' '.$this->email,
        ];
    }

    /**
     * Datatable related fields for correct sorting & column searching
     *
     * @return array
     */
    public function getRelationFields()
    {
        return [
            'country' => ['name', 'founded_at'],
            'userLogins' => [],
            'userNameAndEmail' => ['name', 'email'],
        ];
    }
}
