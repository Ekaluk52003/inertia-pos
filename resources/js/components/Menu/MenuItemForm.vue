<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { 
  Card, 
  CardContent, 
  CardDescription, 
  CardFooter, 
  CardHeader, 
  CardTitle 
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';

interface MenuItem {
  id?: number;
  name: string;
  description: string | null;
  price: number;
  category: string;
  is_available: boolean;
}

interface Props {
  restaurantId: number;
  menuItem?: MenuItem;
  submitUrl: string;
  submitMethod?: string;
}

const props = withDefaults(defineProps<Props>(), {
  menuItem: () => ({
    id: undefined,
    name: '',
    description: '',
    price: 0,
    category: '',
    is_available: true
  }),
  submitMethod: 'post'
});

// Available categories
const categories = [
  'Appetizers',
  'Main Courses',
  'Desserts',
  'Beverages',
  'Sides',
  'Specials'
];

// Create form with Inertia
const form = useForm({
  name: props.menuItem.name,
  description: props.menuItem.description || '',
  price: props.menuItem.price,
  category: props.menuItem.category,
  is_available: props.menuItem.is_available
});

// Form submission
const submit = () => {
  form.submit(props.submitMethod, props.submitUrl, {
    preserveScroll: true,
    onSuccess: () => {
      // Success notification could be added here
    }
  });
};

// Format price for display
const formattedPrice = computed(() => {
  return form.price ? form.price.toString() : '0';
});

// Handle price input changes
const updatePrice = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const value = target.value;
  
  // Remove non-numeric characters except decimal point
  const numericValue = value.replace(/[^0-9.]/g, '');
  
  // Ensure only one decimal point
  const parts = numericValue.split('.');
  if (parts.length > 2) {
    form.price = parseFloat(parts[0] + '.' + parts[1]);
  } else {
    form.price = parseFloat(numericValue) || 0;
  }
};
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>{{ props.menuItem.id ? 'Edit Menu Item' : 'Create Menu Item' }}</CardTitle>
      <CardDescription>
        {{ props.menuItem.id ? 'Update the details of this menu item.' : 'Add a new item to your restaurant menu.' }}
      </CardDescription>
    </CardHeader>
    <CardContent>
      <form @submit.prevent="submit" class="space-y-6">
        <div class="space-y-2">
          <Label for="name">Name</Label>
          <Input 
            id="name" 
            v-model="form.name" 
            placeholder="Enter menu item name" 
            :disabled="form.processing"
            :class="{ 'border-red-500': form.errors.name }"
          />
          <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
        </div>
        
        <div class="space-y-2">
          <Label for="description">Description (optional)</Label>
          <Textarea 
            id="description" 
            v-model="form.description" 
            placeholder="Enter a description of the menu item" 
            :disabled="form.processing"
            :class="{ 'border-red-500': form.errors.description }"
          />
          <p v-if="form.errors.description" class="text-sm text-red-500">{{ form.errors.description }}</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-2">
            <Label for="price">Price (฿)</Label>
            <Input 
              id="price" 
              :value="formattedPrice" 
              @input="updatePrice"
              placeholder="0.00" 
              :disabled="form.processing"
              :class="{ 'border-red-500': form.errors.price }"
            />
            <p v-if="form.errors.price" class="text-sm text-red-500">{{ form.errors.price }}</p>
          </div>
          
          <div class="space-y-2">
            <Label for="category">Category</Label>
            <Select v-model="form.category" :disabled="form.processing">
              <SelectTrigger :class="{ 'border-red-500': form.errors.category }">
                <SelectValue placeholder="Select a category" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="category in categories" :key="category" :value="category">
                  {{ category }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="form.errors.category" class="text-sm text-red-500">{{ form.errors.category }}</p>
          </div>
        </div>
        
        <div class="flex items-center space-x-2">
          <Switch 
            id="is_available" 
            v-model:checked="form.is_available" 
            :disabled="form.processing"
          />
          <Label for="is_available">Available for ordering</Label>
        </div>
        
        <div class="flex justify-end space-x-2">
          <Button
            type="button"
            variant="outline"
            :disabled="form.processing"
            @click="$inertia.visit(route('menu.index', props.restaurantId))"
          >
            Cancel
          </Button>
          <Button type="submit" :disabled="form.processing">
            {{ props.menuItem.id ? 'Update Menu Item' : 'Create Menu Item' }}
          </Button>
        </div>
      </form>
    </CardContent>
  </Card>
</template>
