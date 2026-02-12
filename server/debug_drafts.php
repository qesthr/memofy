<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Draft;

$drafts = Draft::all();
echo "Draft Priorities:\n";
foreach ($drafts as $d) {
    echo "ID[" . $d->_id . "] Priority[" . $d->priority . "] Type[" . gettype($d->priority) . "]\n";
}
