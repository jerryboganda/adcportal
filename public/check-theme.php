<?php
require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$business = \App\Models\Business::where('slug', 'amad-diagnostic-centre-gujranwala')->first();

echo "Business: {$business->name}\n";
echo "Form Type: {$business->form_type}\n";
echo "Layout/Theme: {$business->layouts}\n\n";

if ($business->form_type == 'form-layout') {
    $viewPath = "form_layout.{$business->layouts}.index";
    echo "Using view: {$viewPath}\n";
    echo "File location: resources/views/form_layout/{$business->layouts}/index.blade.php\n";
} else {
    echo "Using module package view\n";
    echo "Module: {$business->layouts}\n";
    $modulePath = "packages/adc/{$business->layouts}/src/Resources/views/form_layout/index.blade.php";
    echo "File location: {$modulePath}\n";
    
    if (file_exists(base_path($modulePath))) {
        echo "✅ Theme file exists\n";
    } else {
        echo "❌ Theme file NOT found\n";
    }
}
