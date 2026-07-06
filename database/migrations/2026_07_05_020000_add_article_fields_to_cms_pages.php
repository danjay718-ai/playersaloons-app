<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->string('type')->default('page')->after('slug')->index();
            $table->string('featured_image_path')->nullable()->after('type');
            $table->boolean('is_featured')->default(false)->after('featured_image_path');
        });

        Schema::table('cms_page_translations', function (Blueprint $table) {
            $table->text('excerpt')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('cms_page_translations', function (Blueprint $table) {
            $table->dropColumn('excerpt');
        });

        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropColumn(['type', 'featured_image_path', 'is_featured']);
        });
    }
};
