<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import MenuItemForm from '@/components/Menu/MenuItemForm.vue';

interface Restaurant {
  id: number;
  name: string;
  description: string;
}

interface Props {
  restaurant: Restaurant;
}

const props = defineProps<Props>();
</script>

<template>
  <AppLayout>
    <Head :title="`Add Menu Item - ${restaurant.name}`" />
    
    <div class="container py-6">
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold tracking-tight">Add Menu Item</h1>
            <p class="text-muted-foreground">Create a new menu item for {{ restaurant.name }}</p>
          </div>
          <Link 
            :href="route('menu.index', restaurant.id)" 
            class="text-sm font-medium hover:underline"
          >
            Back to Menu
          </Link>
        </div>
      </div>
      
      <MenuItemForm 
        :restaurantId="restaurant.id" 
        :submitUrl="route('menu.store', restaurant.id)" 
      />
    </div>
  </AppLayout>
</template>
