<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MongoDB cleanup
        $connection = DB::connection('mongodb');

        // Drop collections
        Schema::connection('mongodb')->dropIfExists('memo_templates');
        Schema::connection('mongodb')->dropIfExists('user_signatures');

        // Unset fields in memos collection
        $connection->getCollection('memos')->updateMany(
            [],
            ['$unset' => [
                'signature_id' => '',
                'signature_ids' => '',
                'signature_positions' => ''
            ]]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse for dropping data
    }
};
