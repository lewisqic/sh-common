<?php

namespace Lewisqic\SHCommon\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Watson\Validating\ValidatingTrait;
use Lewisqic\SHCommon\Scopes\ContentObjectPermissionsScope;

abstract class BaseModel extends Model
{
    use ValidatingTrait;


    /******************************************************************
     * MODEL PROPERTIES
     ******************************************************************/


    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at', 'updated_at', 'deleted_at',
    ];

    /**
     * Default validation rules
     *
     * @var array
     */
    protected $rules = [
        //
    ];

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        //
    ];

    /**
     * Whether the model should throw a ValidationException if it
     * fails validation. If not set, it will default to false.
     *
     * @var bool
     */
    protected $throwValidationExceptions = true;

    /**
     * The default order by clause for a model
     *
     * @var array
     */
    protected static $defaultOrder = [];

    /**
     * Does this model use content object permissions
     *
     * @var bool
     */
    protected static $usesContentObjectPermissions = false;

    /**
     * Set a group ID for the model
     *
     * @var null|int
     */
    protected static $modelGroupId = null;


    /******************************************************************
     * MODEL METHODS
     ******************************************************************/

    /**
     * Set the group ID for this model
     *
     * @param $id
     */
    public static function setGroupId($id)
    {
        self::$modelGroupId = $id;
    }

    /**
     * Get the group ID for this model
     *
     * @return int
     */
    public static function getGroupId()
    {
        return self::$modelGroupId;
    }


    /******************************************************************
     * MODEL BOOT METHOD
     ******************************************************************/


    /**
     * The "booting" method of the model.
     */
    protected static function boot()
    {
        parent::boot();

        // check for content object permissions
        if (static::$usesContentObjectPermissions) {
            static::addGlobalScope(new ContentObjectPermissionsScope);
        }

        // apply our default order by clause
        if (!empty(static::$defaultOrder) && is_array(static::$defaultOrder)) {
            static::addGlobalScope('order_by', function (Builder $builder) {
                if ( empty($builder->getQuery()->orders) ) {
                    $builder->orderBy(static::$defaultOrder[0], static::$defaultOrder[1]);
                }
            });
        }

        // listen for model events
        static::created(function($model) {
            if (static::$usesContentObjectPermissions && !empty(app('role'))) {
                $type = get_class($model);
                $group_id = $type::getGroupId();
                $model_group_id = !empty($group_id) && $group_id > 0 ? $group_id : null;
                // create content object permission record
                DB::table('content_object_permissions')->insert([
                    'role_id' => app('role')['id'],
                    'model_id' => $model->id,
                    'model_group_id' => $model_group_id,
                    'model_type' => $type,
                ]);
            }
        });

    }

}
