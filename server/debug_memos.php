<?php

use App\Models\Memo;
use App\Models\User;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = User::all();
echo "--- All Users ---\n";
foreach ($users as $u) {
    echo "ID: " . $u->id . " | Name: " . $u->first_name . " " . $u->last_name . " | Role: " . $u->role . "\n";
}
echo "-----------------\n";

$draftsCount = Memo::where('is_draft', true)->count();
echo "Total drafts count: $draftsCount\n";

if ($draftsCount > 0) {
    echo "--- Drafts Details ---\n";
    $drafts = Memo::where('is_draft', true)->get();
    foreach ($drafts as $memo) {
        echo "ID: " . $memo->id . "\n";
        echo "Subject: " . $memo->subject . "\n";
        echo "Sender ID: " . $memo->sender_id . "\n";
        echo "Recipient ID: " . $memo->recipient_id . "\n";
        echo "Status: " . $memo->status . "\n";
        echo "Is Draft: " . ($memo->is_draft ? 'TRUE' : 'FALSE') . "\n";
        
        echo "Deleted At: " . ($memo->deleted_at ? $memo->deleted_at : 'NOT DELETED') . "\n";
        
        $sender = User::find($memo->sender_id);
        echo "Sender Found: " . ($sender ? $sender->first_name . ' ' . $sender->last_name . ' (' . $sender->id . ')' : 'NO') . "\n";
        echo "----------------------\n";
    }
}

$user = User::where('first_name', 'Queen')->first();
if ($user) {
    echo "User Queen ID: " . $user->id . "\n";
}
