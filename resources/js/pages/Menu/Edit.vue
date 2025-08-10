<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import MenuItemForm from '@/components/Menu/MenuItemForm.vue';

interface MenuItem {
  id: number;
  restaurant_id: number;
  name: string;
  description: string | null;
  price: number;
  category: string;
  is_available: boolean;
  created_at: string;
  updated_at: string;
}

interface Restaurant {
  id: number;
  name: string;
  description: string;
}

interface Props {
  restaurant: Restaurant;
  menuItem: MenuItem;
}

const props = defineProps<Props>();
</script>

<template>
  <AppLayout>
    <Head :title="`Edit ${menuItem.name} - ${restaurant.name}`" />
    
    <div class="container py-6">
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold tracking-tight">Edit Menu Item</h1>
            <p class="text-muted-foreground">Update details for {{ menuItem.name }}</p>
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
        :menuItem="menuItem"
        :submitUrl="route('menu.update', [restaurant.id, menuItem.id])" 
        submitMethod="put"
      />
    </div>
  </AppLayout>
</template>
