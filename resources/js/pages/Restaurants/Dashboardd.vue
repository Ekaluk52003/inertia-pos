<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { computed } from 'vue';

interface Restaurant {
  id: number;
  name: string;
  description: string;
  pay_before: boolean;
  prompt_pay_id: string | null;
  billing: object | null;
  created_at: string;
  updated_at: string;
  owner_id: number;
  // Stats that might be included from the controller
  order_count?: number;
  total_revenue?: number;
  active_tables?: number;
}

interface Props {
  restaurants: Restaurant[];
}

const props = defineProps<Props>();

// Calculate total stats across all restaurants
const totalStats = computed(() => {
  return {
    orderCount: props.restaurants.reduce((sum, restaurant) => sum + (restaurant.order_count || 0), 0),
    revenue: props.restaurants.reduce((sum, restaurant) => sum + (restaurant.total_revenue || 0), 0),
    activeTables: props.restaurants.reduce((sum, restaurant) => sum + (restaurant.active_tables || 0), 0),
  };
});
</script>

<template>
  <AppLayout>
    <Head title="Restaurant Dashboard" />
    
    <div class="container py-6">
      <div class="mb-6">
        <h1 class="text-3xl font-bold tracking-tight">Restaurant Dashboard</h1>
        <p class="text-muted-foreground">Manage all your restaurants from one place</p>
      </div>

      <!-- Summary Stats -->
      <div class="grid gap-4 md:grid-cols-3 mb-6">
        <Card>
          <CardHeader class="pb-2">
            <CardTitle class="text-sm font-medium">Total Orders Today</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ totalStats.orderCount }}</div>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader class="pb-2">
            <CardTitle class="text-sm font-medium">Total Revenue Today</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">฿{{ totalStats.revenue.toFixed(2) }}</div>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader class="pb-2">
            <CardTitle class="text-sm font-medium">Active Tables</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ totalStats.activeTables }}</div>
          </CardContent>
        </Card>
      </div>

      <!-- Restaurants List -->
      <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-bold">Your Restaurants</h2>
          <Link 
            :href="route('restaurants.create')" 
            class="bg-yellow-300 hover:bg-yellow-400 text-black font-medium py-2 px-4 rounded"
          >
            Add New Restaurant
          </Link>
        </div>

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          <Card v-for="restaurant in props.restaurants" :key="restaurant.id" class="overflow-hidden">
            <CardHeader>
              <CardTitle>{{ restaurant.name }}</CardTitle>
              <CardDescription class="line-clamp-2">{{ restaurant.description }}</CardDescription>
            </CardHeader>
            
            <CardContent>
              <div class="grid grid-cols-2 gap-2 text-sm">
                <div>
                  <p class="text-muted-foreground">Orders Today</p>
                  <p class="font-medium">{{ restaurant.order_count || 0 }}</p>
                </div>
                <div>
                  <p class="text-muted-foreground">Revenue Today</p>
                  <p class="font-medium">฿{{ (restaurant.total_revenue || 0).toFixed(2) }}</p>
                </div>
              </div>
            </CardContent>
            
            <CardFooter class="bg-muted/50 flex justify-between">
              <Link 
                :href="route('restaurants.show', restaurant.id)" 
                class="text-sm font-medium hover:underline"
              >
                View Details
              </Link>
              <Link 
                :href="route('menu.index', restaurant.id)" 
                class="text-sm font-medium hover:underline"
              >
                Manage Menu
              </Link>
            </CardFooter>
          </Card>
        </div>
      </div>

      <!-- No Restaurants Message -->
      <Card v-if="props.restaurants.length === 0" class="bg-muted/50">
        <CardContent class="flex flex-col items-center justify-center py-10">
          <p class="mb-4 text-center text-muted-foreground">You don't have any restaurants yet.</p>
          <Link 
            :href="route('restaurants.create')" 
            class="bg-yellow-300 hover:bg-yellow-400 text-black font-medium py-2 px-4 rounded"
          >
            Create Your First Restaurant
          </Link>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
