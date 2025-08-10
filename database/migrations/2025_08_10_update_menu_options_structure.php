<?php

use App\Models\Menu;
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
        // Update all existing menu items with options to include required and multiple properties
        $menuItems = Menu::whereNotNull('options')->get();
        
        foreach ($menuItems as $menuItem) {
            $options = $menuItem->options;
            
            if (is_array($options)) {
                $updated = false;
                
                foreach ($options as $key => $option) {
                    // Add required property if not present (default to true)
                    if (!isset($option['required'])) {
                        $options[$key]['required'] = true;
                        $updated = true;
                    }
                    
                    // Add multiple property if not present (default to false)
                    if (!isset($option['multiple'])) {
                        $options[$key]['multiple'] = false;
                        $updated = true;
                    }
                }
                
                if ($updated) {
                    $menuItem->options = $options;
                    $menuItem->save();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this migration as it only adds properties
    }
};
