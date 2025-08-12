<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDistanceToNow } from 'date-fns';
import { ChefHat } from 'lucide-vue-next';

// Define props interface
interface Props {
  restaurant: {
    id: number;
    name: string;
  };
  orders: {
    data: Order[];
    links: any[];
    meta: {
      current_page: number;
      from: number;
      last_page: number;
      links: any[];
      path: string;
      per_page: number;
      to: number;
      total: number;
    };
  };
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
  { title: 'Orders', href: route('orders.index', props.restaurant.id), current: true },
];

// Format currency
const formatPrice = (price: number) => {
  return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(price);
};

// Format date
const formatDate = (dateString: string) => {
  return formatDistanceToNow(new Date(dateString), { addSuffix: true });
};

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

// Get payment status badge class
const getPaymentClass = (isPaid: boolean) => {
  return isPaid ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
};
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head :title="`Orders - ${props.restaurant.name}`" />

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-2xl font-semibold text-gray-900">Orders</h1>
          <Link :href="route('kitchen.show', { restaurant: props.restaurant.id })" class="inline-flex">
            <Button>
              <ChefHat class="mr-2 h-4 w-4" />
              Kitchen View
            </Button>
          </Link>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>All Orders</CardTitle>
            <CardDescription>Manage and track all orders for {{ props.restaurant.name }}</CardDescription>
          </CardHeader>
          <CardContent>
            <Table>
              <caption class="mt-4 mb-2 text-sm text-gray-500">A list of all orders.</caption>
              <TableHeader>
                <TableRow>
                  <TableHead>Order ID</TableHead>
                  <TableHead>Table</TableHead>
                  <TableHead>Items</TableHead>
                  <TableHead>Total</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Payment</TableHead>
                  <TableHead>Created</TableHead>
                  <TableHead>Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="order in orders.data" :key="order.id">
                  <TableCell class="font-medium">{{ order.code.substring(0, 8) }}...</TableCell>
                  <TableCell>{{ order.table_number }}</TableCell>
                  <TableCell>{{ order.orderItems.length }}</TableCell>
                  <TableCell>{{ formatPrice(order.total_amount) }}</TableCell>
                  <TableCell>
                    <span class="px-2 py-1 rounded-full text-xs font-medium" :class="getStatusClass(order.status)">
                      {{ order.status }}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span class="px-2 py-1 rounded-full text-xs font-medium" :class="getPaymentClass(order.is_paid)">
                      {{ order.is_paid ? 'Paid' : 'Unpaid' }}
                    </span>
                  </TableCell>
                  <TableCell>{{ formatDate(order.created_at) }}</TableCell>
                  <TableCell>
                    <div class="flex space-x-2">
                      <Link :href="route('orders.show', { restaurant: props.restaurant.id, order: order.id })">
                        <Button variant="outline" size="sm">View</Button>
                      </Link>
                      <Link v-if="!order.is_paid" :href="route('orders.mark-paid', { restaurant: props.restaurant.id, order: order.id })" method="patch" as="button">
                        <Button variant="outline" size="sm">Mark Paid</Button>
                      </Link>
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
          <CardFooter>
            <!-- Pagination -->
            <div class="flex items-center justify-between w-full">
              <div class="text-sm text-gray-700">
                Showing {{ orders.meta.from }} to {{ orders.meta.to }} of {{ orders.meta.total }} orders
              </div>
              <div class="flex space-x-2">
                <Link v-for="(link, i) in orders.meta.links" :key="i" 
                      :href="link.url" 
                      :class="{ 'text-gray-500 cursor-not-allowed': !link.url, 'text-blue-600 hover:text-blue-800': link.url && !link.active, 'text-blue-800 font-bold': link.active }">
                  <Button v-if="link.url" :variant="link.active ? 'default' : 'outline'" size="sm" :disabled="!link.url">
                    <span v-html="link.label"></span>
                  </Button>
                </Link>
              </div>
            </div>
          </CardFooter>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
