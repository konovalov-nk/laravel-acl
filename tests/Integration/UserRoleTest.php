<?php namespace Kodeine\Acl\Tests\Integration;

use Kodeine\Acl\Models\Eloquent\Permission;
use Kodeine\Acl\Models\Eloquent\Role;
use Kodeine\Acl\Models\Eloquent\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserTest extends IntegrationTest
{
    /* ------------------------------------------------------------------------------------------------
     |  Properties
     | ------------------------------------------------------------------------------------------------
     */
    /** @var User */
    protected $userModel;

    /* ------------------------------------------------------------------------------------------------
     |  Main Functions
     | ------------------------------------------------------------------------------------------------
     */
    public function setUp()
    {
        parent::setUp();

        $this->userModel = new User;
    }

    public function tearDown()
    {
        parent::tearDown();

        unset($this->userModel);
    }

    /* ------------------------------------------------------------------------------------------------
     |  Test Functions
     | ------------------------------------------------------------------------------------------------
     */
    /** @test */
    public function itCanBeInstantiated()
    {
        $expectations = [
            \Illuminate\Database\Eloquent\Model::class,
            \Kodeine\Acl\Models\Eloquent\User::class,
        ];

        foreach ($expectations as $expected) {
            $this->assertInstanceOf($expected, $this->userModel);
        }
    }

    /** @test */
    public function itCanCheckRolePermissionsForModelId()
    {
        $objRole = new Role();
        $roleAttributes = [
            'name'        => 'Admin',
            'slug'        => str_slug('Admin role', config('laravel-auth.slug-separator')),
            'description' => 'Admin role descriptions.',
        ];
        $role = $objRole->create($roleAttributes);

        $objPermission = new Permission();
        $permissionAttributes = [
            'name'        => 'post',
            'slug'        => [
                'create'     => true,
                'view'       => true,
                'update'     => true,
                'delete'     => true,
            ],
            'description' => 'manage post permissions'
        ];
        $permission = $objPermission->create($permissionAttributes);

        $role->syncPermissions($permission);

        $user = new User();
        $user->username = 'Role test';
        $user->email = 'role@test.com';
        $user->password = 'RoleTest';
        $user->save();

        // Assign user role to model entity & id.
        $example_model = 'example_model';
        $example_model_id = 42;
        $user->assignRole($role, $example_model, $example_model_id);

        $this->assertEquals($user->getRoles(), [ 1 => str_slug('Admin role', config('laravel-auth.slug-separator'))]);
        $this->assertEquals($user->getPermissions(), ['post' => $permissionAttributes['slug']]);

        // Permissions given to certain model and id.
        $this->assertTrue($user->able('create.view', $example_model, $example_model_id));
        // User doesn't have permissions on all models and ids.
        $this->assertFalse($user->able('create.view'));
        // User role must have exact model id.
        $this->assertFalse($user->able('create.view'), $example_model, 43);

    }

}
