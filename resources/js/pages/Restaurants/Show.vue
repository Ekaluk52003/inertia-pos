<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItemType } from '@/types';
import { computed } from 'vue';
import { Card } from '@/components/ui/card';

import { ChefHat, ClipboardList, Menu, QrCode, Settings, Users } from 'lucide-vue-next';

interface OrderItem {
  id: number;
  order_id: number;
  menu_id: number;
  name: string;
  quantity: number;
  price: number;
  status: string;
  special_instructions: string | null;
}

interface Order {
  id: number;
  restaurant_id: number;
  table_number: number;
  code: string;
  total_amount: number;
  is_paid: boolean;
  status: string;
  customer_notes: string | null;
  created_at: string;
  updated_at: string;
  order_items: OrderItem[];
}

interface MenuItem {
  id: number;
  restaurant_id: number;
  name: string;
  description: string | null;
  price: number;
  category: string;
  is_available: boolean;
  order_count?: number;
}

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
  menu_items?: MenuItem[];
}

interface Props {
  restaurant: Restaurant;
  recentOrders: Order[];
  popularItems: MenuItem[];
  stats: {
    todayOrders: number;
    todayRevenue: number;
    activeTables: number;
    pendingOrders: number;
  };
}

const props = defineProps<Props>();

const breadcrumbItems = computed(() => {
  return [
    { title: 'Restaurants', 
      href: route('restaurants.index') 
     },
    { title: props.restaurant.name, href: route('restaurants.show', props.restaurant.id) },

  ] as BreadcrumbItemType[];
});

// Navigation items for the restaurant
const navItems = [
  { name: 'Menu', icon: Menu, route: route('menu.index', props.restaurant.id) },
  { name: 'Orders', icon: ClipboardList, route: route('orders.index', props.restaurant.id) },
  { name: 'Kitchen', icon: ChefHat, route: route('kitchen.show', props.restaurant.id) },
  { name: 'QR Codes', icon: QrCode, route: route('qrcodes.index', props.restaurant.id) },
  { name: 'Staff', icon: Users, route: route('staff.index', props.restaurant.id) },
  { name: 'Settings', icon: Settings, route: route('restaurants.edit', props.restaurant.id) }, // Will be implemented later
];
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <Head :title="restaurant.name + ' Dashboard'" />
    
      <div class="container py-6">
        <div class="mb-6">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-3xl font-bold tracking-tight">{{ restaurant.name }}</h1>
              <p class="text-muted-foreground">{{ restaurant.description }}</p>
            </div>
            <Link 
              :href="route('restaurants.index')" 
              class="text-sm font-medium hover:underline"
            >
              Back to All Restaurants
            </Link>
          </div>
        </div>

        <!-- Summary Stats removed for now -->

        <!-- Navigation Cards -->
        <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-6 mb-6">
          <Card 
            v-for="item in navItems" 
            :key="item.name" 
            class="cursor-pointer hover:bg-muted/50 transition-colors"
          >
            <Link :href="item.route" class="block">
              <div class="flex flex-col items-center justify-center p-4">
                <component :is="item.icon" class="h-6 w-6 mb-2" />
                <span class="text-sm font-medium">{{ item.name }}</span>
              </div>
            </Link>
          </Card>
        </div>

   
      </div>
    </div>
  </AppLayout>
</template>
