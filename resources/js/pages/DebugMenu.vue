<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import axios from 'axios';

// State for menu items
const menuItems = ref([]);
const loading = ref(true);
const error = ref(null);

// Fetch menu items from the database
const fetchMenuItems = async () => {
  try {
    loading.value = true;
    const response = await axios.get('/api/debug/menu-items');
    menuItems.value = response.data;
    console.log('Menu items loaded:', menuItems.value);
  } catch (err) {
    error.value = 'Failed to load menu items';
    console.error('Error loading menu items:', err);
  } finally {
    loading.value = false;
  }
};

// Selected menu item for inspection
const selectedItem = ref(null);
const showOptionModal = ref(false);
const selectedOptions = ref([]);

// Open the option modal
const openOptionModal = (item) => {
  console.log('Opening modal for item:', JSON.stringify(item, null, 2));
  selectedItem.value = item;
  selectedOptions.value = [];
  showOptionModal.value = true;
  
  // Initialize default selection for required options
  if (item.options) {
    item.options.forEach(option => {
      console.log('Processing option:', JSON.stringify(option, null, 2));
      
      if (option.values && option.values.length > 0) {
        if (option.required !== false) {
          console.log('Adding default selection for required option with values:', option.name);
          selectedOptions.value.push({
            optionName: option.name,
            choices: [option.values[0].name],
            additionalPrice: option.values[0].price || 0
          });
        }
      }
    });
  }
  
  console.log('Selected options after initialization:', JSON.stringify(selectedOptions.value, null, 2));
};

// Toggle option choice
const toggleOptionChoice = (optionName, choiceName, price = 0) => {
  console.log(`Toggling option choice: ${optionName}, ${choiceName}, price: ${price}`);
  
  // Find the current option to check its multiple property
  const currentOption = selectedItem.value.options.find(opt => opt.name === optionName);
  const isMultiple = currentOption?.multiple === true; // Explicitly check for true
  
  console.log(`Option ${optionName} isMultiple from data:`, isMultiple);
  
  const optionIndex = selectedOptions.value.findIndex(opt => opt.optionName === optionName);
  
  if (optionIndex >= 0) {
    const choiceIndex = selectedOptions.value[optionIndex].choices.indexOf(choiceName);
    
    if (choiceIndex >= 0) {
      // If choice is already selected and multiple selection is allowed, remove it
      if (isMultiple) {
        console.log(`Removing choice ${choiceName} from ${optionName}`);
        selectedOptions.value[optionIndex].choices.splice(choiceIndex, 1);
        selectedOptions.value[optionIndex].additionalPrice -= price;
        
        // If no choices left, remove the entire option
        if (selectedOptions.value[optionIndex].choices.length === 0) {
          selectedOptions.value.splice(optionIndex, 1);
        }
      }
      // If not multiple and already selected, do nothing (can't deselect required options)
    } else {
      // Add new choice
      if (isMultiple) {
        // For multiple selection, add to existing choices
        console.log(`Adding choice ${choiceName} to ${optionName} (multiple)`);
        selectedOptions.value[optionIndex].choices.push(choiceName);
        selectedOptions.value[optionIndex].additionalPrice += price;
      } else {
        // For single selection, replace existing choice
        console.log(`Replacing choice with ${choiceName} for ${optionName} (single)`);
        selectedOptions.value[optionIndex].choices = [choiceName];
        selectedOptions.value[optionIndex].additionalPrice = price;
      }
    }
  } else {
    // Add new option with this choice
    console.log(`Adding new option ${optionName} with choice ${choiceName}`);
    selectedOptions.value.push({
      optionName,
      choices: [choiceName],
      additionalPrice: price
    });
  }
  
  console.log('Updated selectedOptions:', JSON.stringify(selectedOptions.value, null, 2));
};

// Check if a choice is selected
const isChoiceSelected = (optionName, choiceName) => {
  const option = selectedOptions.value.find(opt => opt.optionName === optionName);
  return option ? option.choices.includes(choiceName) : false;
};

// Close the option modal
const closeOptionModal = () => {
  showOptionModal.value = false;
  selectedItem.value = null;
};

// Create a test menu item with the correct options structure
const createTestMenuItem = async () => {
  try {
    const response = await axios.post('/api/debug/create-test-menu-item', {
      name: 'Test Milk Tea',
      price: 50,
      category: 'Drinks',
      description: 'Test milk tea with customizable sweetness',
      options: [
        {
          name: 'sweetness',
          values: [
            { name: 'less sweet', price: 0 },
            { name: 'super sweet', price: 0 }
          ],
          multiple: false,
          required: true
        }
      ]
    });
    
    console.log('Test menu item created:', response.data);
    fetchMenuItems(); // Refresh the menu items
  } catch (err) {
    console.error('Error creating test menu item:', err);
  }
};

// Load menu items on mount
onMounted(() => {
  fetchMenuItems();
});
</script>

<template>
  <Head title="Debug Menu Options" />
  
  <div class="container mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold mb-6">Debug Menu Options</h1>
    
    <div class="mb-6">
      <Button @click="createTestMenuItem">Create Test Menu Item</Button>
    </div>
    
    <div v-if="loading" class="text-center py-8">
      <p>Loading menu items...</p>
    </div>
    
    <div v-else-if="error" class="text-center py-8 text-red-500">
      <p>{{ error }}</p>
    </div>
    
    <div v-else-if="menuItems.length === 0" class="text-center py-8">
      <p>No menu items found</p>
    </div>
    
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <Card v-for="item in menuItems" :key="item.id" class="mb-4">
        <CardHeader>
          <CardTitle>{{ item.name }}</CardTitle>
          <CardDescription>{{ item.description }}</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="flex justify-between items-center">
            <div>
              <p class="font-medium">{{ item.price }}฿</p>
              <p class="text-sm text-muted-foreground">{{ item.category }}</p>
            </div>
            <Button @click="openOptionModal(item)">View Options</Button>
          </div>
          
          <div class="mt-4">
            <h3 class="text-sm font-medium mb-2">Raw Options Data:</h3>
            <pre class="bg-gray-100 p-2 rounded text-xs overflow-auto max-h-40">{{ JSON.stringify(item.options, null, 2) }}</pre>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
  
  <!-- Option Selection Modal -->
  <div v-show="showOptionModal && selectedItem" 
       class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <Card class="w-full max-w-md max-h-[90vh] overflow-y-auto">
      <CardHeader>
        <CardTitle>Options for {{ selectedItem?.name || 'Item' }}</CardTitle>
        <CardDescription>Debug view of options structure</CardDescription>
      </CardHeader>
      
      <CardContent>
        <!-- Options -->
        <div v-if="selectedItem?.options && selectedItem.options.length > 0" class="space-y-6">
          <!-- Display each option -->
          <div v-for="(option, index) in selectedItem.options" 
               :key="index" 
               class="space-y-2 border-b pb-4 mb-4 last:border-0">
            <div class="flex justify-between items-center">
              <h3 class="font-medium">{{ option.name }}</h3>
              <div class="flex items-center">
                <span v-if="option.required !== false" class="text-xs text-red-500 mr-2">Required</span>
                <span v-else class="text-xs text-muted-foreground mr-2">Optional</span>
                <span v-if="option.multiple === true" class="text-xs bg-gray-100 px-2 py-1 rounded">Multiple</span>
              </div>
            </div>
            
            <!-- Display values -->
            <div v-if="option.values && option.values.length > 0" class="space-y-1">
              <div 
                v-for="value in option.values" 
                :key="value.name"
                @click="toggleOptionChoice(option.name, value.name, value.price || 0)"
                class="flex items-center justify-between p-2 rounded-md cursor-pointer hover:bg-muted"
                :class="{
                  'bg-muted': isChoiceSelected(option.name, value.name),
                  'border-l-2 border-yellow-500': isChoiceSelected(option.name, value.name)
                }"
              >
                <div class="flex items-center">
                  <div 
                    :class="{
                      'w-4 h-4 mr-2 rounded-sm border': option.multiple === true,
                      'w-4 h-4 mr-2 rounded-full border': option.multiple !== true,
                      'bg-yellow-500 border-yellow-500': isChoiceSelected(option.name, value.name),
                      'border-gray-300': !isChoiceSelected(option.name, value.name)
                    }"
                  ></div>
                  <span>{{ value.name }}</span>
                </div>
                <span v-if="value.price && value.price > 0" class="text-sm text-muted-foreground">
                  +{{ value.price }}฿
                </span>
              </div>
            </div>
            
            <!-- Show if no options available -->
            <p v-if="(!option.values || option.values.length === 0)" 
               class="text-sm text-muted-foreground italic">
                No values available
            </p>
          </div>
        </div>
        
        <div v-else class="text-center py-4">
          <p class="text-muted-foreground">No options available for this item</p>
        </div>
        
        <!-- Selected Options -->
        <div v-if="selectedOptions.length > 0" class="mt-6">
          <h3 class="text-sm font-medium mb-2">Selected Options:</h3>
          <pre class="bg-gray-100 p-2 rounded text-xs overflow-auto max-h-40">{{ JSON.stringify(selectedOptions, null, 2) }}</pre>
        </div>
      </CardContent>
      
      <CardFooter class="flex justify-between">
        <Button variant="outline" @click="closeOptionModal">Close</Button>
      </CardFooter>
    </Card>
  </div>
</template>
