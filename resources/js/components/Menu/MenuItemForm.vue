<script setup lang="ts">
// Declare global URL object for TypeScript
declare const URL: {
  createObjectURL(object: Blob): string;
};
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { 
  Card, 
  CardContent, 
  CardDescription, 
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

interface AttributeValue {
  name: string;
  price: number;
}

interface MenuItemAttribute {
  name: string;
  values: AttributeValue[];
}

interface MenuItem {
  id?: number;
  name: string;
  description: string | null;
  price: number;
  category: string;
  is_available: boolean;
  image_path?: string;
  options?: Array<MenuItemAttribute> | null;
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

// Image handling
const imageInputType = ref<'file' | 'url'>('file');
const imageUrl = ref<string>(props.menuItem.image_path && props.menuItem.image_path.startsWith('http') ? props.menuItem.image_path : '');
const imageFile = ref<File | null>(null);

// Initialize reactive state for attributes
const attributes = ref<MenuItemAttribute[]>(props.menuItem.options || []);
const newAttributeName = ref('');
const newAttributeValue = ref('');
const newAttributePrice = ref(0);

// Add a new attribute
const addAttribute = () => {
  if (newAttributeName.value.trim()) {
    attributes.value.push({
      name: newAttributeName.value.trim(),
      values: [],
    });
    newAttributeName.value = '';
  }
};

// Add a new value to an attribute
const addValueToAttribute = (attributeIndex: number) => {
  if (newAttributeValue.value.trim() && attributeIndex >= 0 && attributeIndex < attributes.value.length) {
    attributes.value[attributeIndex].values.push({
      name: newAttributeValue.value.trim(),
      price: newAttributePrice.value,
    });
    newAttributeValue.value = '';
    newAttributePrice.value = 0;
  }
};

// Remove a value from an attribute
const removeValueFromAttribute = (attributeIndex: number, valueIndex: number) => {
  if (attributeIndex >= 0 && attributeIndex < attributes.value.length) {
    attributes.value[attributeIndex].values.splice(valueIndex, 1);
  }
};

// Remove an attribute
const removeAttribute = (index: number) => {
  attributes.value.splice(index, 1);
};

// We don't need this function since we're not implementing edit mode
// Removing to fix the unused function warning

// Create form with Inertia
const form = useForm({
  name: props.menuItem.name,
  description: props.menuItem.description || '',
  price: props.menuItem.price,
  category: props.menuItem.category,
  is_available: props.menuItem.is_available,
  image: null as File | null,
  image_url: imageUrl.value || '',
  options: [] as any, // Will be set before submission
});

// Watch for changes in attributes and update form
watch(attributes, (newAttributes) => {
  form.options = JSON.parse(JSON.stringify(newAttributes));
}, { deep: true });

// Initialize form options
form.options = JSON.parse(JSON.stringify(attributes.value));

// Handle file input change
const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (target.files && target.files.length > 0) {
    form.image = target.files[0];
    imageFile.value = target.files[0];
    // Clear image URL when file is selected
    form.image_url = '';
    imageUrl.value = '';
  }
};

// Watch for changes in image URL input
watch(imageUrl, (newValue) => {
  form.image_url = newValue || '';
  // Clear file input when URL is entered
  if (newValue) {
    form.image = null;
    imageFile.value = null;
  }
});

// Toggle between file upload and URL input
const toggleImageInputType = () => {
  imageInputType.value = imageInputType.value === 'file' ? 'url' : 'file';
  // Clear the other input type when switching
  if (imageInputType.value === 'file') {
    form.image_url = null;
    imageUrl.value = '';
  } else {
    form.image = null;
    imageFile.value = null;
  }
};

// Price is now handled directly with v-model.number

// Form submission
const submit = () => {
  form.submit(props.submitMethod as 'post' | 'put', props.submitUrl, {
    preserveScroll: true,
    onSuccess: () => {
      // Success notification could be added here
    }
  });
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
              v-model.number="form.price" 
              type="number"
              min="0"
              step="0.01"
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
        
        <!-- Image Upload/URL Section -->
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <Label>Menu Item Image</Label>
            <Button 
              type="button" 
              variant="outline" 
              size="sm"
              @click="toggleImageInputType"
              :disabled="form.processing"
            >
              Switch to {{ imageInputType === 'file' ? 'URL' : 'File Upload' }}
            </Button>
          </div>
          
          <!-- File Upload Input -->
          <div v-if="imageInputType === 'file'" class="space-y-2">
            <Input 
              type="file" 
              accept="image/*"
              @change="handleFileChange"
              :disabled="form.processing"
              :class="{ 'border-red-500': form.errors.image }"
            />
            <p v-if="form.errors.image" class="text-sm text-red-500">{{ form.errors.image }}</p>
            <p class="text-xs text-muted-foreground">Upload an image file (JPEG, PNG, etc.)</p>
          </div>
          
          <!-- Image URL Input -->
          <div v-else class="space-y-2">
            <Input 
              v-model="imageUrl"
              placeholder="https://example.com/image.jpg" 
              :disabled="form.processing"
              :class="{ 'border-red-500': form.errors.image_url }"
            />
            <p v-if="form.errors.image_url" class="text-sm text-red-500">{{ form.errors.image_url }}</p>
            <p class="text-xs text-muted-foreground">Enter a direct URL to an image</p>
          </div>
          
          <!-- Preview current image if available -->
          <div v-if="props.menuItem.image_path || imageFile || imageUrl" class="mt-2">
            <p class="text-sm font-medium mb-1">Image Preview:</p>
            <div class="border rounded-md p-2 max-w-xs">
              <img 
                v-if="imageFile" 
                :src="imageFile ? URL.createObjectURL(imageFile) : ''" 
                alt="Preview" 
                class="max-h-40 object-contain mx-auto"
              />
              <img 
                v-else-if="imageUrl" 
                :src="imageUrl" 
                alt="Preview" 
                class="max-h-40 object-contain mx-auto"
              />
              <img 
                v-else-if="props.menuItem.image_path" 
                :src="props.menuItem.image_path.startsWith('http') ? props.menuItem.image_path : `/storage/${props.menuItem.image_path}`" 
                alt="Current image" 
                class="max-h-40 object-contain mx-auto"
              />
            </div>
          </div>
        </div>
        
        <!-- Attributes Section -->
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <Label>Menu Item Attributes</Label>
            <p class="text-xs text-muted-foreground">Add customizable options like size, cooking preference, etc.</p>
          </div>
          
          <!-- Add new attribute -->
          <div class="flex items-end gap-2">
            <div class="flex-1">
              <Label for="new-attribute">New Attribute</Label>
              <Input 
                id="new-attribute" 
                v-model="newAttributeName" 
                placeholder="e.g., Size, Cooking Preference" 
                :disabled="form.processing"
              />
            </div>
            <Button 
              type="button" 
              variant="outline" 
              @click="addAttribute"
              :disabled="form.processing || !newAttributeName.trim()"
            >
              Add
            </Button>
          </div>
          
          <!-- Attribute list -->
          <div v-if="attributes.length > 0" class="space-y-4 border rounded-md p-4">
            <div v-for="(attribute, attrIndex) in attributes" :key="attrIndex" class="space-y-3 pb-3 border-b last:border-b-0">
              <div class="flex items-center justify-between">
                <h4 class="font-medium">{{ attribute.name }}</h4>
                <Button 
                  type="button" 
                  variant="ghost" 
                  size="sm"
                  @click="removeAttribute(attrIndex)"
                  :disabled="form.processing"
                  class="text-red-500 hover:text-red-700 hover:bg-red-50"
                >
                  Remove
                </Button>
              </div>
              
              <!-- Add values to attribute -->
              <div class="flex items-end gap-2">
                <div class="flex-1">
                  <Label :for="`attr-value-${attrIndex}`">Add Value</Label>
                  <Input 
                    :id="`attr-value-${attrIndex}`" 
                    v-model="newAttributeValue" 
                    placeholder="e.g., Small, Medium, Large" 
                    :disabled="form.processing"
                  />
                </div>
                <div class="w-1/3">
                  <Label :for="`attr-price-${attrIndex}`">Price</Label>
                  <Input 
                    :id="`attr-price-${attrIndex}`" 
                    v-model.number="newAttributePrice" 
                    type="number" 
                    min="0" 
                    step="0.01"
                    placeholder="Additional price" 
                    :disabled="form.processing"
                  />
                </div>
                <Button 
                  type="button" 
                  variant="outline" 
                  size="sm"
                  @click="addValueToAttribute(attrIndex)"
                  :disabled="form.processing || !newAttributeValue.trim()"
                >
                  Add
                </Button>
              </div>
              
              <!-- Value chips/tags -->
              <div class="flex flex-wrap gap-2">
                <div 
                  v-for="(value, valueIndex) in attribute.values" 
                  :key="valueIndex"
                  class="bg-muted px-3 py-1 rounded-md text-sm flex items-center gap-2"
                >
                  <span>{{ value.name }}</span>
                  <span v-if="value.price > 0" class="text-xs text-muted-foreground">+{{ value.price }}</span>
                  <button 
                    type="button" 
                    @click="removeValueFromAttribute(attrIndex, valueIndex)"
                    :disabled="form.processing"
                    class="text-muted-foreground hover:text-foreground ml-1"
                  >
                    &times;
                  </button>
                </div>
                <div v-if="attribute.values.length === 0" class="text-sm text-muted-foreground">
                  No values added yet
                </div>
              </div>
            </div>
          </div>
          
          <div v-if="attributes.length === 0" class="text-sm text-muted-foreground border rounded-md p-4 text-center">
            No attributes added yet. Add attributes like "Size" or "Cooking Preference" to give customers options.
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
