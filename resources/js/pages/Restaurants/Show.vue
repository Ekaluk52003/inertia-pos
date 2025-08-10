<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { PlusCircle } from 'lucide-vue-next';
import MenuItemCard from '@/components/Menu/MenuItemCard.vue';
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

// Format date for display
const formatDate = (dateString: string): string => {
  const date = new Date(dateString);
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date);
};

// Get status color based on order status
const getStatusColor = (status: string): string => {
  switch (status.toLowerCase()) {
    case 'completed':
      return 'bg-green-100 text-green-800';
    case 'pending':
      return 'bg-yellow-100 text-yellow-800';
    case 'processing':
      return 'bg-blue-100 text-blue-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
};

// Navigation items for the restaurant
const navItems = [
  { name: 'Menu', icon: Menu, route: route('menu.index', props.restaurant.id) },
  { name: 'Orders', icon: ClipboardList, route: route('orders.index', props.restaurant.id) },
  { name: 'Kitchen', icon: ChefHat, route: route('kitchen.show', props.restaurant.id) },
  { name: 'QR Codes', icon: QrCode, route: route('qrcodes.index', props.restaurant.id) },
  { name: 'Staff', icon: Users, route: route('staff.index', props.restaurant.id) },
  { name: 'Settings', icon: Settings, route: '#' }, // Will be implemented later
];
</script>

<template>
  <AppLayout>
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

        <!-- Main Content Tabs - With Menu, Orders, and Stats tabs -->
        <Tabs default-value="menu" class="w-full">
          <TabsList class="mb-4">
            <TabsTrigger value="menu">Menu</TabsTrigger>
            <TabsTrigger value="orders">Orders</TabsTrigger>
            <TabsTrigger value="stats">Stats</TabsTrigger>
          </TabsList>
          
          <TabsContent value="menu">
            <Card>
              <CardHeader>
                <div class="flex items-center justify-between">
                  <div>
                    <CardTitle>Menu Items</CardTitle>
                    <CardDescription>Manage your restaurant menu</CardDescription>
                  </div>
                  <Link 
                    :href="route('menu.create', restaurant.id)" 
                    class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary/90"
                  >
                    <PlusCircle class="h-4 w-4" />
                    Add Menu Item
                  </Link>
                </div>
              </CardHeader>
              <CardContent>
                <div v-if="restaurant.menu_items && restaurant.menu_items.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                  <MenuItemCard 
                    v-for="item in restaurant.menu_items" 
                    :key="item.id" 
                    :menuItem="item" 
                    :restaurantId="restaurant.id" 
                  />
                </div>
                <div v-else class="text-center py-8">
                  <p class="text-muted-foreground">No menu items found. Add your first menu item to get started.</p>
                </div>
              </CardContent>
              <CardFooter>
                <Link 
                  :href="route('menu.index', restaurant.id)" 
                  class="text-sm font-medium hover:underline"
                >
                  Manage All Menu Items
                </Link>
              </CardFooter>
            </Card>
          </TabsContent>

          <TabsContent value="orders">
            <Card>
              <CardHeader>
                <CardTitle>Recent Orders</CardTitle>
                <CardDescription>View and manage recent orders</CardDescription>
              </CardHeader>
              <CardContent>
                <div class="text-center py-4">
                  <p>Order management will be implemented here.</p>
                </div>
              </CardContent>
              <CardFooter>
                <Link 
                  :href="route('orders.index', restaurant.id)" 
                  class="text-sm font-medium hover:underline"
                >
                  View All Orders
                </Link>
              </CardFooter>
            </Card>
          </TabsContent>

          <TabsContent value="stats">
            <Card>
              <CardHeader>
                <CardTitle>Restaurant Statistics</CardTitle>
                <CardDescription>Key performance metrics for your restaurant</CardDescription>
              </CardHeader>
              <CardContent>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                  <div class="rounded-lg border p-4">
                    <div class="text-sm font-medium text-muted-foreground">Today's Orders</div>
                    <div class="text-2xl font-bold mt-2">{{ stats.todayOrders }}</div>
                  </div>
                  
                  <div class="rounded-lg border p-4">
                    <div class="text-sm font-medium text-muted-foreground">Today's Revenue</div>
                    <div class="text-2xl font-bold mt-2">฿{{ stats.todayRevenue.toFixed(2) }}</div>
                  </div>
                  
                  <div class="rounded-lg border p-4">
                    <div class="text-sm font-medium text-muted-foreground">Active Tables</div>
                    <div class="text-2xl font-bold mt-2">{{ stats.activeTables }}</div>
                  </div>
                  
                  <div class="rounded-lg border p-4">
                    <div class="text-sm font-medium text-muted-foreground">Pending Orders</div>
                    <div class="text-2xl font-bold mt-2">{{ stats.pendingOrders }}</div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </div>
  </AppLayout>
</template>
