<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItemType } from '@/types';
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2Icon } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

interface Restaurant {
  id: number;
  name: string;
}

interface Props {
  restaurant: Restaurant;
}

const props = defineProps<Props>();

// Breadcrumbs
const breadcrumbItems = computed(() => {
  return [
    { title: 'Restaurants', href: route('restaurants.index') },
    { title: props.restaurant.name, href: route('restaurants.show', props.restaurant.id) },
    { title: 'QR Codes', href: route('qrcodes.index', props.restaurant.id) },
    { title: 'Create QR Code', href: route('qrcodes.create', props.restaurant.id) },
  ] as BreadcrumbItemType[];
});

// Form handling
const form = useForm({
  table_number: '',
});

const submit = () => {
  form.post(route('qrcodes.store', props.restaurant.id));
};
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head :title="`Create QR Code - ${restaurant.name}`" />
    
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold">Create QR Code</h1>
        <Button variant="outline" @click="router.get(route('qrcodes.index', restaurant.id))" class="cursor-pointer">
          Back to QR Codes
        </Button>
      </div>
      
      <Card>
        <CardHeader>
          <CardTitle>New QR Code</CardTitle>
          <CardDescription>
            Create a new QR code for a table in your restaurant. Customers can scan this QR code to access the menu and place orders.
          </CardDescription>
        </CardHeader>
        
        <form @submit.prevent="submit">
          <CardContent>
            <div class="grid gap-4">
              <div class="grid gap-2">
                <Label for="table_number">Table Identifier</Label>
                <Input 
                  id="table_number" 
                  v-model="form.table_number" 
                  type="text" 
                  :disabled="form.processing"
                  required
                />
                <p v-if="form.errors.table_number" class="text-sm text-red-500">
                  {{ form.errors.table_number }}
                </p>
                <p class="text-sm text-muted-foreground">
                  Enter the table identifier for this QR code (e.g., "Table 1", "Bar Counter", "Patio 3"). Each table should have a unique identifier.
                </p>
              </div>
            </div>
          </CardContent>
          
          <CardFooter class="flex justify-between">
            <Button 
              type="button" 
              variant="outline" 
              as="a" 
              :href="route('qrcodes.index', restaurant.id)"
              :disabled="form.processing"
            >
              Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
              <Loader2Icon v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
              Create QR Code
            </Button>
          </CardFooter>
        </form>
      </Card>
    </div>
  </AppLayout>
</template>
