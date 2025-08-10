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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Restaurants',
        href: '/restaurants',
    },
    {
        title: props.restaurant.name,
        href: `/restaurants/${props.restaurant.id}`,
    },
    {
        title: 'Menu',
        href: `/restaurants/${props.restaurant.id}/menu`,
    },
    {
        title: props.menuItem.name,
        href: `/restaurants/${props.restaurant.id}/menu/${props.menuItem.id}/edit`,
    },
];
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head :title="`Edit ${menuItem.name} - ${restaurant.name}`" />
    
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
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
