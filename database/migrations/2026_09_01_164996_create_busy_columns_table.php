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
        Schema::table('party', function (Blueprint $table) {
            $table->string('busyparty_id')->nullable()->after('id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('busyproduct_id')->nullable()->after('id');
        });

        Schema::table('collection', function (Blueprint $table) {
            $table->string('busycollection_id')->nullable()->after('id');
        });

        Schema::table('tax', function (Blueprint $table) {
            $table->string('busytax_id')->nullable()->after('id');
        });

        Schema::table('order', function (Blueprint $table) {
            $table->string('busyorder_id')->nullable()->after('id');
        });
        Schema::table('unit_types', function (Blueprint $table) {
            $table->string('busyunit_id')->nullable()->after('id');
        });
        Schema::table('tax_types', function (Blueprint $table) {
            $table->string('busytax_id')->nullable()->after('id');
        });

        Schema::table('item_categories', function (Blueprint $table) {
            $table->string('busyitemcategory_id')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('party', function (Blueprint $table) {
            $table->dropColumn('busyparty_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('busyproduct_id');
        });

        Schema::table('collection', function (Blueprint $table) {
            $table->dropColumn('busycollection_id');
        });

        Schema::table('tax', function (Blueprint $table) {
            $table->dropColumn('busytax_id');
        });

        Schema::table('order', function (Blueprint $table) {
            $table->dropColumn('busyorder_id');
        });

        Schema::table('unit_types', function (Blueprint $table) {
            $table->dropColumn('busyunit_id');
        });
        Schema::table('tax_types', function (Blueprint $table) {
            $table->dropColumn('busytax_id');
        });
        Schema::table('item_categories', function (Blueprint $table) {
            $table->dropColumn('busyitemcategory_id');
        });
    }
};
