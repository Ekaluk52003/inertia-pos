
<template>
    <div class="min-h-screen bg-white flex flex-col">
        <!-- Simple header for customer pages -->
        <header class="border-b py-4 px-4 flex justify-center">
            <h1 class="text-yellow-500 font-bold text-lg">Eattinee</h1>
        </header>

        <!-- Main content -->
        <main class="flex-1">
            <slot />
        </main>
    </div>
</template>
