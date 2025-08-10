<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { ToggleLeft, ToggleRight, Pencil } from 'lucide-vue-next';
import { Link, router } from '@inertiajs/vue3';

interface MenuItem {
  id: number;
  restaurant_id: number;
  name: string;
  description: string | null;
  price: number;
  category: string;
  is_available: boolean;
}

interface Props {
  menuItem: MenuItem;
  restaurantId: number;
}

const props = defineProps<Props>();

// Format currency
const formatCurrency = (amount: number): string => {
  return '฿' + amount.toFixed(2);
};

// Toggle menu item availability
const toggleAvailability = () => {
  // Use Inertia to make a PATCH request to toggle availability
  const url = route('menu.toggle', [props.restaurantId, props.menuItem.id]);
  
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
</script>

<template>
  <div class="rounded-lg border p-4 hover:bg-muted/50 transition-colors">
    <div class="flex items-center justify-between mb-2">
      <h3 class="font-medium">{{ menuItem.name }}</h3>
      <div class="flex items-center gap-2">
        <button 
          @click="toggleAvailability" 
          class="inline-flex h-8 w-8 items-center justify-center rounded-md hover:bg-muted"
          :title="menuItem.is_available ? 'Mark as unavailable' : 'Mark as available'"
        >
          <component 
            :is="menuItem.is_available ? ToggleRight : ToggleLeft" 
            class="h-4 w-4" 
            :class="menuItem.is_available ? 'text-green-500' : 'text-gray-500'"
          />
        </button>
        
        <Link 
          :href="route('menu.edit', [restaurantId, menuItem.id])" 
          class="inline-flex h-8 w-8 items-center justify-center rounded-md hover:bg-muted"
          title="Edit"
        >
          <Pencil class="h-4 w-4" />
        </Link>
      </div>
    </div>
    
    <p v-if="menuItem.description" class="text-sm text-muted-foreground mb-2">
      {{ menuItem.description }}
    </p>
    
    <div class="flex items-center justify-between">
      <Badge :variant="menuItem.is_available ? 'default' : 'secondary'" 
      :class="menuItem.is_available ? 'bg-green-500' : ''"
    >
      {{ menuItem.is_available ? 'Available' : 'Unavailable' }}
    </Badge>
      <span class="font-medium">{{ formatCurrency(menuItem.price) }}</span>
    </div>
  </div>
</template>
