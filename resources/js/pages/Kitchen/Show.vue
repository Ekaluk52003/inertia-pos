<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft } from 'lucide-vue-next';

// Simple date formatting function
const formatDate = (dateString: string) => {
  try {
    const date = new Date(dateString);
    return date.toLocaleString();
  } catch {
    // Return the original string if date parsing fails
    return dateString;
  }
};

// Define props interface
interface Props {
  restaurant: {
    id: number;
    name: string;
  };
  activeOrders: Order[];
  ordersByTable: {
    table_number: string;
    orders: Order[];
  }[];
}

interface Order {
  id: number;
  table_number: string;
  code: string;
  total_amount: number;
  is_paid: boolean;
  status: string;
  created_at: string;
  orderItems: OrderItem[];
}

interface OrderItem {
  id: number;
  order_id: number;
  menu_id: number;
  name: string;
  price: number;
  quantity: number;
  status: string;
  special_instructions: string | null;
}

const props = defineProps<Props>();

// Breadcrumb items
const breadcrumbItems = [
  { title: 'Restaurants', href: route('restaurants.index') },
  { title: props.restaurant.name, href: route('restaurants.show', props.restaurant.id) },
  { title: 'Kitchen View', href: route('kitchen.show', props.restaurant.id), current: true },
];

// Already defined formatDate function at the top, so removing this duplicate

// Get status badge class
const getStatusClass = (status: string) => {
  switch (status) {
    case 'pending':
      return 'bg-yellow-100 text-yellow-800';
    case 'cooking':
      return 'bg-orange-100 text-orange-800';
    case 'ready':
      return 'bg-green-100 text-green-800';
    case 'served':
      return 'bg-blue-100 text-blue-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
};

// Audio notification
const audioRef = ref<HTMLAudioElement | null>(null);
const playNotification = () => {
  if (audioRef.value) {
    audioRef.value.play();
  }
};



// Track processing status for each item
const processingItems = ref<Record<string, boolean>>({});

// Update item status using Inertia.js
const updateItemStatus = (orderId: number, itemId: number, newStatus: string) => {
  // Set this specific item as processing
  const itemKey = `${orderId}-${itemId}`;
  processingItems.value[itemKey] = true;
  
  // Play notification sound if available
  playNotification();
  
  // Use Inertia router to make a PATCH request
  router.patch(
    route('orders.update-item-status', { restaurant: props.restaurant.id, order: orderId }),
    {
      order_item_id: itemId,
      status: newStatus
    },
    {
      preserveScroll: true,
      onFinish: () => { processingItems.value[itemKey] = false; }
    }
  );
};

// Check if an item is currently being processed
const isProcessing = (orderId: number, itemId: number) => {
  const itemKey = `${orderId}-${itemId}`;
  return processingItems.value[itemKey] === true;
};

// Get next status
const getNextStatus = (currentStatus: string) => {
  switch (currentStatus) {
    case 'pending':
      return 'cooking';
    case 'cooking':
      return 'ready';
    case 'ready':
      return 'served';
    default:
      return currentStatus;
  }
};

// Get next status button text
const getNextStatusText = (currentStatus: string) => {
  switch (currentStatus) {
    case 'pending':
      return 'Start Cooking';
    case 'cooking':
      return 'Mark Ready';
    case 'ready':
      return 'Mark Served';
    default:
      return 'Update';
  }
};


</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head :title="`Kitchen View - ${props.restaurant.name}`" />

    <audio ref="audioRef" src="/audio/notification.mp3" preload="auto"></audio>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
          <div class="flex items-center">
            <Link :href="route('orders.index', { restaurant: props.restaurant.id })" class="mr-4">
              <Button variant="outline" size="sm">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to Orders
              </Button>
            </Link>
            <h1 class="text-2xl font-semibold text-gray-900">Kitchen View</h1>
          </div>
          <!-- Auto-refresh UI removed as requested -->
        </div>

        <div v-if="props.ordersByTable.length === 0" class="text-center py-12">
          <p class="text-gray-500 text-lg">No active orders at the moment.</p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Group by table number -->
          <div v-for="tableGroup in props.ordersByTable" :key="tableGroup.table_number" class="h-full">
            <Card class="h-full">
              <CardHeader class="bg-gray-50">
                <div class="flex justify-between items-center">
                  <CardTitle class="text-xl font-bold">Table {{ tableGroup.table_number }}</CardTitle>
                  <Badge variant="outline">{{ tableGroup.orders.length }} Order(s)</Badge>
                </div>
              </CardHeader>
              <CardContent class="p-0">
                <!-- Loop through each order for this table -->
                <div v-for="order in tableGroup.orders" :key="order.id" class="border-b last:border-b-0">
                  <div class="p-4">
                    <div class="flex justify-between items-center mb-2">
                      <div class="text-sm font-medium">Order#{{ order.code.substring(0, 8) }}...</div>
                      <div class="text-sm text-gray-500">{{ formatDate(order.created_at) }}</div>
                    </div>
                    
              
                    
                    <!-- Order items -->
                    <!-- Debug info to show raw order items data -->
                    <div class="bg-blue-50 p-2 mb-3 rounded text-sm">
                      Order items count: {{ order.orderItems ? order.orderItems.length : 0 }}
                    </div>
                    
                    <ul v-if="order.orderItems && order.orderItems.length > 0" class="space-y-3">
                      <li v-for="item in order.orderItems" :key="item.id" class="p-2 rounded-md" :class="getStatusClass(item.status)">
                        <div class="flex justify-between items-start">
                          <div>
                            <div class="font-medium">{{ item.name }} × {{ item.quantity }}</div>
                            <div v-if="item.special_instructions" class="text-xs italic mt-1">
                              {{ item.special_instructions }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Status: {{ item.status }}</div>
                          </div>
                          <Button 
                            v-if="item.status !== 'served'"
                            @click="updateItemStatus(order.id, item.id, getNextStatus(item.status))" 
                            size="sm" 
                            variant="outline"
                            :disabled="isProcessing(order.id, item.id)"
                          >
                            <span v-if="isProcessing(order.id, item.id)" class="mr-1">⏳</span>
                            {{ getNextStatusText(item.status) }}
                          </Button>
                          <Badge v-else variant="outline" class="bg-blue-50">Served</Badge>
                        </div>
                      </li>
                    </ul>
                    
                    <!-- Show message if no items in this order -->
                    <div v-if="!order.orderItems || order.orderItems.length === 0" class="text-center py-2 text-sm text-gray-500">
                      No items in this order
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Add any component-specific styles here */
</style>
