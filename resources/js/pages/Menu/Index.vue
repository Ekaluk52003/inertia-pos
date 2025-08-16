<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge/index';
import {
  PlusCircle,
  Pencil,
  Trash2,
  ToggleLeft,
  ToggleRight,
  ChefHat,
  ClipboardList,
  Menu as MenuIcon,
  QrCode,
  Settings,
  Users
} from 'lucide-vue-next';
import type { BreadcrumbItemType, NavItem } from '@/types';

interface AttributeValue {
  name: string;
  price: number;
}

interface MenuItemAttribute {
  name: string;
  values: AttributeValue[];
}

interface MenuItem {
  id: number;
  restaurant_id: number;
  name: string;
  description: string | null;
  price: number;
  category: string;
  is_available: boolean;
  image_path?: string | null;
  options?: MenuItemAttribute[] | null;
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
  menuItems: MenuItem[];
}

const props = defineProps<Props>();

// Format currency for display
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(amount);
};

// Format attribute value with price
const formatAttributeValue = (value: AttributeValue) => {
  return `${value.name}${value.price > 0 ? ' (+' + value.price + ')' : ''}`;
};

// Toggle menu item availability
const toggleAvailability = (menuItem: MenuItem) => {
  // Use Inertia to make a PATCH request to toggle availability
  const url = route('menu.toggle', [props.restaurant.id, menuItem.id]);

  // Use Inertia router to make a PATCH request
  router.visit(url, {
    method: 'patch',
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      // Success notification could be added here
    }
  });
};

// Delete menu item
const deleteMenuItem = (menuItem: MenuItem) => {
  if (confirm(`Are you sure you want to delete ${menuItem.name}?`)) {
    const url = route('menu.destroy', [props.restaurant.id, menuItem.id]);

    router.visit(url, {
      method: 'delete',
      preserveScroll: true,
      onSuccess: () => {
        // Success notification could be added here
      }
    });
  }
};

// Group menu items by category
const menuItemsByCategory = ref<Record<string, MenuItem[]>>({});

// Process menu items and group them by category
const processMenuItems = () => {
  const grouped: Record<string, MenuItem[]> = {};

  props.menuItems.forEach(item => {
    if (!grouped[item.category]) {
      grouped[item.category] = [];
    }
    grouped[item.category].push(item);
  });

  menuItemsByCategory.value = grouped;
};

// Call the function to process menu items
processMenuItems();

// Define breadcrumb items
const breadcrumbItems = computed(() => {
  return [
    { title: 'Restaurants',
      href: route('restaurants.index')
     },
    { title: props.restaurant.name, href: route('restaurants.show', props.restaurant.id) },
    { title: 'Menu', href: route('menu.index', props.restaurant.id) },
  ] as BreadcrumbItemType[];
});

// Navigation items for the restaurant
const navItems: NavItem[] = [
  { title: 'Menu', icon: MenuIcon, href: route('menu.index', props.restaurant.id), isActive: true },
  { title: 'Orders', icon: ClipboardList, href: route('orders.index', props.restaurant.id) },
  { title: 'Kitchen', icon: ChefHat, href: route('kitchen.show', props.restaurant.id) },
  { title: 'QR Codes', icon: QrCode, href: route('qrcodes.index', props.restaurant.id) },
  { title: 'Staff', icon: Users, href: route('staff.index', props.restaurant.id) },
  { title: 'Settings', icon: Settings, href: '#' }, // Will be implemented later
];
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head :title="`Menu - ${props.restaurant.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">

      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Menu Management</h1>
          <p class="text-muted-foreground">Manage menu items for {{ props.restaurant.name }}</p>
        </div>

        <Link
          :href="route('menu.create', props.restaurant.id)"
          class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary/90"
        >
          <PlusCircle class="h-4 w-4" />
          Add Menu Item
        </Link>
      </div>

      <!-- Menu Items Table -->
      <Card>
        <CardHeader>
          <CardTitle>Menu Items</CardTitle>
          <CardDescription>
            All menu items for {{ props.restaurant.name }}. You can edit, delete, or toggle availability.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Image</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Category</TableHead>
                <TableHead>Price</TableHead>
                <TableHead>Attributes</TableHead>
                <TableHead>Status</TableHead>
                <TableHead class="w-[150px]">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <template v-if="props.menuItems.length > 0">
                <TableRow v-for="item in props.menuItems" :key="item.id" class="hover:bg-muted/50">
                  <TableCell>
                    <div class="h-12 w-12 overflow-hidden rounded-md">
                      <img
                        v-if="item.image_path"
                        :src="item.image_path.startsWith('http') ? item.image_path : `/storage/${item.image_path}`"
                        :alt="item.name"
                        class="h-full w-full object-cover"
                      />
                      <div v-else class="h-full w-full bg-muted flex items-center justify-center text-muted-foreground">
                        <span class="text-xs">No image</span>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>{{ item.name }}</TableCell>
                  <TableCell>{{ item.category }}</TableCell>
                  <TableCell>{{ formatCurrency(item.price) }}</TableCell>
                  <TableCell>
                    <!-- Display attributes if any -->
                    <div v-if="item.options && item.options.length > 0" class="flex flex-col gap-1 mb-1">
                      <div v-for="(attr, index) in item.options" :key="index" class="text-xs">
                        <span class="font-medium">{{ attr.name }}:</span>
                        <span class="text-muted-foreground">
                          {{ attr.values.map(value => formatAttributeValue(value)).join(', ') }}
                        </span>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge :variant="item.is_available ? 'default' : 'secondary'"
                      :class="item.is_available ? 'bg-green-500' : ''"
                    >
                      {{ item.is_available ? 'Available' : 'Unavailable' }}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div class="flex items-center gap-2">
                      <Button
                        variant="ghost"
                        size="icon"
                        @click="toggleAvailability(item)"
                        :title="item.is_available ? 'Mark as unavailable' : 'Mark as available'"
                      >
                        <component
                          :is="item.is_available ? ToggleRight : ToggleLeft"
                          class="h-4 w-4"
                          :class="item.is_available ? 'text-green-500' : 'text-gray-500'"
                        />
                      </Button>

                      <Link
                        :href="route('menu.edit', [restaurant.id, item.id])"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md hover:bg-muted"
                        title="Edit"
                      >
                        <Pencil class="h-4 w-4" />
                      </Link>

                      <Button
                        variant="ghost"
                        size="icon"
                        @click="deleteMenuItem(item)"
                        title="Delete"
                      >
                        <Trash2 class="h-4 w-4 text-red-500" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              </template>
              <TableRow v-if="props.menuItems.length === 0">
                <TableCell colspan="7" class="text-center py-6 text-muted-foreground">
                  No menu items found. Click "Add Menu Item" to create your first menu item.
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <!-- Menu Items By Category -->
      <div class="mt-8">
        <h2 class="text-2xl font-bold mb-4">Menu Items by Category</h2>

        <div v-if="Object.keys(menuItemsByCategory).length === 0" class="text-center py-8 text-muted-foreground">
          No menu items found. Add some menu items to see them organized by category.
        </div>

        <div v-else class="grid gap-6 md:grid-cols-2">
          <Card v-for="(items, category) in menuItemsByCategory" :key="category">
            <CardHeader>
              <CardTitle>{{ category }}</CardTitle>
              <CardDescription>{{ items.length }} item(s)</CardDescription>
            </CardHeader>
            <CardContent>
              <ul class="space-y-2">
                <li v-for="item in items" :key="item.id" class="flex items-center justify-between border-b pb-2">
                  <div class="flex items-center gap-3">
                    <div class="h-12 w-12 overflow-hidden rounded-md flex-shrink-0">
                      <img
                        v-if="item.image_path"
                        :src="item.image_path.startsWith('http') ? item.image_path : `/storage/${item.image_path}`"
                        :alt="item.name"
                        class="h-full w-full object-cover"
                      />
                      <div v-else class="h-full w-full bg-muted flex items-center justify-center text-muted-foreground">
                        <span class="text-xs">No image</span>
                      </div>
                    </div>
                    <div>
                      <div class="font-medium">{{ item.name }}</div>
                      <div class="text-sm text-muted-foreground" v-if="item.description">{{ item.description }}</div>
                      <!-- Display attributes in category view -->
                      <div v-if="item.options && item.options.length > 0" class="flex flex-wrap gap-x-3 gap-y-1 mt-1">
                        <div v-for="(attr, index) in item.options" :key="index" class="text-xs">
                          <span class="font-medium">{{ attr.name }}:</span>
                          <span class="text-muted-foreground">
                            {{ attr.values.map(value => formatAttributeValue(value)).join(', ') }}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <Badge :variant="item.is_available ? 'default' : 'secondary'"
                      :class="[item.is_available ? 'bg-green-500' : '', 'mr-2']"
                    >
                      {{ item.is_available ? 'Available' : 'Unavailable' }}
                    </Badge>
                    <div class="font-medium">{{ formatCurrency(item.price) }}</div>
                  </div>
                </li>
              </ul>
            </CardContent>
          </Card>
        </div>
      </div>

      <div class="mt-6">
        <Link
          :href="route('restaurants.show', props.restaurant.id)"
          class="text-sm font-medium hover:underline"
        >
          Back to Restaurant Dashboard
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
