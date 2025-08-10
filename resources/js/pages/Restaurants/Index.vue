<template>
  <Head title="Restaurants" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold">Restaurants</h1>
        <Button variant="default" as="a" :href="route('restaurants.create')">
          Create Restaurant
        </Button>
      </div>


      <div v-if="props.restaurants.length === 0" class="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-8">
        <div class="mx-auto flex max-w-[420px] flex-col items-center justify-center text-center">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-10 w-10 text-muted-foreground mb-4"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
          <h3 class="text-lg font-semibold mb-1">No restaurants found</h3>
          <p class="text-sm text-muted-foreground mb-4">Create your first restaurant to get started.</p>
          <Button variant="outline" as="a" :href="route('restaurants.create')">
            Create Restaurant
          </Button>
        </div>
      </div>

      <div v-else class="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Description</TableHead>
              <TableHead>Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="restaurant in props.restaurants" :key="restaurant.id">
              <TableCell>{{ restaurant.name }}</TableCell>
              <TableCell>{{ restaurant.description || 'No description' }}</TableCell>
              <TableCell class="space-x-2">
                <Button variant="ghost" size="sm" as="a" :href="route('restaurants.show', restaurant.id)">
                  View
                </Button>
                <Button variant="outline" size="sm" as="a" :href="route('restaurants.edit', restaurant.id)">
                  Edit
                </Button>
                <Button variant="destructive" size="sm" @click="deleteRestaurant(restaurant.id)">
                  Delete
                </Button>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';

// Import shadcn components
import Button from '@/components/ui/button/Button.vue';
import { Table, TableHeader, TableBody, TableHead, TableRow, TableCell } from '@/components/ui/table';

interface Restaurant {
  id: number;
  name: string;
  description: string;
}

const props = defineProps({
  restaurants: {
    type: Array as () => Restaurant[],
    default: () => []
  },
});

const deleteRestaurant = (id: number) => {
  if (confirm('Are you sure you want to delete this restaurant?')) {
    router.delete(route('restaurants.destroy', id));
  }
};

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Restaurants',
    href: '/restaurants',
  },
];
</script>
