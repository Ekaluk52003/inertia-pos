<script setup lang="ts">
import { ArrowLeft } from 'lucide-vue-next';

function goBack() {
    // Using type assertion to handle window reference in TypeScript
    (window as Window).history.back();
}
</script>

<template>
    <div class="min-h-screen bg-white flex flex-col">
        <!-- Simple header for customer pages -->
        <header class="border-b py-4 px-4 flex items-center justify-between">
            <button @click="goBack" class="flex items-center text-sm text-muted-foreground">
                <ArrowLeft class="h-4 w-4 mr-1" />
                Back
            </button>
            <h1 class="text-yellow-500 font-bold text-lg">Eattinee</h1>
            <div class="w-8"></div> <!-- Spacer for alignment -->
        </header>
        
        <!-- Main content -->
        <main class="flex-1">
            <slot />
        </main>
    </div>
</template>
