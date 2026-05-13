<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('bank_statement_score')
                  ->nullable()
                  ->default(null)
                  ->after('community_standing');
            $table->string('bank_statement_path')
                  ->nullable()
                  ->after('bank_statement_score');
            $table->timestamp('bank_statement_analyzed_at')
                  ->nullable()
                  ->after('bank_statement_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'bank_statement_score',
                'bank_statement_path',
                'bank_statement_analyzed_at'
            ]);
        });
    }
};