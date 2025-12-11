<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add username if it doesn't exist
        if (!Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username', 100)->unique()->nullable()->after('id');
            });
        }
        
        // Add last_login timestamp
        if (!Schema::hasColumn('users', 'last_login')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('last_login')->nullable()->after('password');
            });
        }
        
        // Add active flag
        if (!Schema::hasColumn('users', 'active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->tinyInteger('active')->default(1)->nullable()->after('last_login');
            });
        }
        
        // Add additional user details
        if (!Schema::hasColumn('users', 'first_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('first_name')->nullable()->after('name');
            });
        }
        
        if (!Schema::hasColumn('users', 'last_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('last_name')->nullable()->after('first_name');
            });
        }
        
        if (!Schema::hasColumn('users', 'mobile_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('mobile_number')->nullable()->after('last_name');
            });
        }
        
        if (!Schema::hasColumn('users', 'address')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('address')->nullable()->after('mobile_number');
            });
        }
        
        if (!Schema::hasColumn('users', 'long')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('long')->nullable()->after('address');
            });
        }
        
        if (!Schema::hasColumn('users', 'lat')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('lat')->nullable()->after('long');
            });
        }
        
        // Add soft deletes if not already present
        if (!Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'last_login',
                'active',
                'first_name',
                'last_name',
                'mobile_number',
                'address',
                'long',
                'lat',
                'deleted_at'
            ]);
        });
    }
};
