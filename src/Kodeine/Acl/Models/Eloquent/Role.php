<?php namespace Kodeine\Acl\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Kodeine\Acl\Traits\HasPermission;

class Role extends Model
{
    use HasPermission;

    /**
     * The attributes that are fillable via mass assignment.
     *
     * @var array
     */
    protected $fillable = ['name', 'slug', 'description'];

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'roles';

    /**
     * Roles can belong to many users.
     *
     * @return Model
     */
    public function users()
    {
        return $this->belongsToMany(config('auth.providers.users.model', config('auth.model')))->withTimestamps();
    }

    /**
     * List all permissions
     *
     * @return mixed
     */
    public function getPermissions()
    {
        return \Cache::remember(
            'acl.getPermissionsInheritedById_'.$this->id,
            config('acl.cacheMinutes'),
            function () {
                return $this->getPermissionsInherited();
            }
        );
    }

    /**
     * List all permissions
     *
     * @return mixed
     */
    public function getPermissionsModel()
    {
        return \Cache::remember(
            'acl.getPermissionsInheritedById_'.$this->id,
            config('acl.cacheMinutes'),
            function () {
                return $this->getPermissionsInheritedModel();
            }
        );
    }


    /**
     * Checks if the role has the given permission.
     *
     * @param string $permission
     * @param string $model
     * @param int    $reference_id
     * @param string $operator
     * @param array  $mergePermissions
     *
     * @return bool
     */
    public function able($permission, $model = '', $reference_id =0, $operator = null, $mergePermissions = [])
    {
        $operator = is_null($operator) ? $this->parseOperator($permission) : $operator;

        $permission = $this->hasDelimiterToArray($permission);
        $permissions = $this->getPermissionsModel() + $mergePermissions;


        // make permissions to dot notation.
        // create.user, delete.admin etc.
        $permissions = $this->toDotPermissions($permissions);

        // validate permissions array
        if ( is_array($permission) ) {

            if ( ! in_array($operator, ['and', 'or']) ) {
                $e = 'Invalid operator, available operators are "and", "or".';
                throw new \InvalidArgumentException($e);
            }

            $call = 'ableWith' . ucwords($operator);

            return $this->$call($permission, $permissions, $model, $reference_id);
        }

        // Validate single permission.
        $permission_key = "{$permission}:{$model}:{$reference_id}";
        // User have all permissions on this.
        if (isset($permissions[$permission]) && $permissions[$permission] == true) {
            return true;
        }
        return isset($permissions[$permission_key]) && $permissions[$permission_key] == true;
    }


    /**
     * @param        $permission
     * @param        $permissions
     * @param string $model
     * @param int    $reference_id
     *
     * @return bool
     */
    protected function ableWithAnd($permission, $permissions, $model = '', $reference_id = 0)
    {
        foreach ($permission as $check) {
            $permission_ok = false;
            $permission_key = "{$check}:{$model}:{$reference_id}";
            if (isset($permissions[$check]) && $permissions[$check] == true) {
                $permission_ok = true;
            }
            if (isset($permissions[$permission_key]) && $permissions[$permission_key] == true) {
                $permission_ok = true;
            }
            if ( ! $permission_ok) {
                return false;
            }
        }

        return true;
    }


    /**
     * @param        $permission
     * @param        $permissions
     * @param string $model
     * @param int    $reference_id
     *
     * @return bool
     */
    protected function ableWithOr($permission, $permissions, $model = '', $reference_id = 0)
    {
        foreach ($permission as $check) {
            $permission_key = "{$check}:{$model}:{$reference_id}";
            if (isset($permissions[$check]) && $permissions[$check] == true) {
                return true;
            }
            if (isset($permissions[$permission_key]) && $permissions[$permission_key] == true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the role has the given permission.
     *
     * @param string $permission
     * @param string $operator
     * @param array  $mergePermissions
     * @return bool
     */
    public function can($permission, $operator = null, $mergePermissions = [])
    {
        $operator = is_null($operator) ? $this->parseOperator($permission) : $operator;

        $permission = $this->hasDelimiterToArray($permission);
        $permissions = $this->getPermissions() + $mergePermissions;

        // make permissions to dot notation.
        // create.user, delete.admin etc.
        $permissions = $this->toDotPermissions($permissions);

        // validate permissions array
        if ( is_array($permission) ) {

            if ( ! in_array($operator, ['and', 'or']) ) {
                $e = 'Invalid operator, available operators are "and", "or".';
                throw new \InvalidArgumentException($e);
            }

            $call = 'canWith' . ucwords($operator);

            return $this->$call($permission, $permissions);
        }

        // validate single permission
        return isset($permissions[$permission]) && $permissions[$permission] == true;
    }

    /**
     * @param $permission
     * @param $permissions
     * @return bool
     */
    protected function canWithAnd($permission, $permissions)
    {
        foreach ($permission as $check) {
            if ( ! in_array($check, $permissions) || ! isset($permissions[$check]) || $permissions[$check] != true ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $permission
     * @param $permissions
     * @return bool
     */
    protected function canWithOr($permission, $permissions)
    {
        foreach ($permission as $check) {
            if ( in_array($check, $permissions) && isset($permissions[$check]) && $permissions[$check] == true ) {
                return true;
            }
        }

        return false;
    }

}