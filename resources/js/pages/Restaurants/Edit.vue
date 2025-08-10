<template>
  <Head :title="`Edit ${restaurant.name}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-semibold">Edit Restaurant</h1>
        <div class="space-x-2">
          <Button variant="outline" as="a" :href="route('restaurants.show', restaurant.id)">
            View Restaurant
          </Button>
          <Button variant="outline" as="a" :href="route('restaurants.index')">
            Back to Restaurants
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Restaurant Details</CardTitle>
          <CardDescription>
            Update the details for {{ restaurant.name }}.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form @submit.prevent="submit" class="space-y-6">
            <div class="grid grid-cols-1 gap-6">
              <!-- Restaurant Name -->
              <div class="space-y-2">
                <Label for="name">Restaurant Name</Label>
                <Input 
                  id="name" 
                  v-model="form.name" 
                  placeholder="Enter restaurant name" 
                  :disabled="form.processing"
                  :class="{ 'border-red-500': form.errors.name }"
                />
                <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
              </div>

              <!-- Restaurant Description -->
              <div class="space-y-2">
                <Label for="description">Description</Label>
                <Textarea 
                  id="description" 
                  v-model="form.description" 
                  placeholder="Enter a description of your restaurant" 
                  :disabled="form.processing"
                  :class="{ 'border-red-500': form.errors.description }"
                />
                <p v-if="form.errors.description" class="text-sm text-red-500">{{ form.errors.description }}</p>
              </div>

              <!-- Payment Settings -->
              <div class="space-y-4">
                <h3 class="text-lg font-medium">Payment Settings</h3>
                
                <!-- Pay Before Option -->
                <div class="flex items-center space-x-2">
                  <Switch 
                    id="pay_before" 
                    v-model="form.pay_before" 
                    :disabled="form.processing"
                  />
                  <Label for="pay_before">Require payment before order confirmation</Label>
                </div>

                <!-- PromptPay ID -->
                <div class="space-y-2">
                  <Label for="prompt_pay_id">PromptPay ID</Label>
                  <Input 
                    id="prompt_pay_id" 
                    v-model="form.prompt_pay_id" 
                    placeholder="Enter your PromptPay ID" 
                    :disabled="form.processing"
                    :class="{ 'border-red-500': form.errors.prompt_pay_id }"
                  />
                  <p v-if="form.errors.prompt_pay_id" class="text-sm text-red-500">{{ form.errors.prompt_pay_id }}</p>
                  <p class="text-sm text-muted-foreground">This will be used for customer payments.</p>
                </div>
              </div>
            </div>

            <div class="flex justify-end">
              <Button 
                type="submit" 
                :disabled="form.processing"
                :class="{ 'opacity-75': form.processing }"
              >
                <Loader2Icon v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                Update Restaurant
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItemType } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Loader2Icon } from 'lucide-vue-next';

// Import shadcn components
import Button from '@/components/ui/button/Button.vue';
import { 
  Card, 
  CardContent, 
  CardDescription, 
  CardHeader, 
  CardTitle 
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';

interface Restaurant {
  id: number;
  name: string;
  description: string | null;
  pay_before: boolean;
  prompt_pay_id: string | null;
  billing: any | null;
}

interface Props {
  restaurant: Restaurant;
}

const props = defineProps<Props>();

// Form definition with initial values from restaurant
const form = useForm({
  name: props.restaurant.name,
  description: props.restaurant.description || '',
  pay_before: props.restaurant.pay_before || false,
  prompt_pay_id: props.restaurant.prompt_pay_id || '',
  billing: props.restaurant.billing,
});

// Form submission
const submit = () => {
  form.put(route('restaurants.update', props.restaurant.id), {
    preserveScroll: true,
    onSuccess: () => {
      // Success notification could be added here
    }
  });
};

// Breadcrumbs
const breadcrumbs: BreadcrumbItemType[] = [
  {
    title: 'Restaurants',
    href: '/restaurants',
  },
  {
    title: props.restaurant.name,
    href: `/restaurants/${props.restaurant.id}`,
  },
  {
    title: 'Edit',
    href: `/restaurants/${props.restaurant.id}/edit`,
  },
];
</script>
