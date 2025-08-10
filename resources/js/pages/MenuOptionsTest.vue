<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';

// Test menu item with the correct options structure
const testMenuItem = ref({
  id: 999,
  name: "Milk Tea",
  price: 50,
  description: "Delicious milk tea with customizable sweetness",
  category: "Drinks",
  image_path: null,
  is_available: true,
  options: [
    {
      name: "sweetness",
      values: [
        { name: "less sweet", price: 0 },
        { name: "super sweet", price: 0 }
      ],
      multiple: false,
      required: true
    }
  ]
});

// State for option selection
const showOptionModal = ref(false);
const selectedOptions = ref([]);
const itemNotes = ref('');

// Open the option modal
const openOptionModal = () => {
  console.log('Opening modal for test item with options:', JSON.stringify(testMenuItem.value.options, null, 2));
  showOptionModal.value = true;
  selectedOptions.value = [];
  
  // Initialize default selection for required options
  if (testMenuItem.value.options) {
    testMenuItem.value.options.forEach(option => {
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
  const currentOption = testMenuItem.value.options.find(opt => opt.name === optionName);
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
};

// Add to cart
const addToCart = () => {
  console.log('Adding to cart with options:', JSON.stringify(selectedOptions.value, null, 2));
  closeOptionModal();
};
</script>

<template>
  <Head title="Menu Options Test" />
  
  <div class="container mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold mb-6">Menu Options Test</h1>
    
    <Card class="mb-6">
      <CardHeader>
        <CardTitle>{{ testMenuItem.name }}</CardTitle>
        <CardDescription>{{ testMenuItem.description }}</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="flex justify-between items-center">
          <div>
            <p class="font-medium">{{ testMenuItem.price }}฿</p>
          </div>
          <Button @click="openOptionModal">Customize</Button>
        </div>
      </CardContent>
    </Card>
    
    <div v-if="selectedOptions.length > 0" class="mt-8">
      <h2 class="text-xl font-bold mb-4">Selected Options:</h2>
      <pre class="bg-gray-100 p-4 rounded">{{ JSON.stringify(selectedOptions, null, 2) }}</pre>
    </div>
  </div>
  
  <!-- Option Selection Modal -->
  <div v-show="showOptionModal" 
       class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <Card class="w-full max-w-md max-h-[90vh] overflow-y-auto">
      <CardHeader>
        <CardTitle>Customize {{ testMenuItem.name }}</CardTitle>
        <CardDescription>Select your preferences</CardDescription>
      </CardHeader>
      
      <CardContent>
        <!-- Options -->
        <div v-if="testMenuItem.options && testMenuItem.options.length > 0" class="space-y-6">
          <!-- Display each option -->
          <div v-for="(option, index) in testMenuItem.options" 
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
          </div>
        </div>
        
        <!-- Notes -->
        <div class="mt-6">
          <label for="notes" class="block text-sm font-medium mb-1">Special Instructions</label>
          <textarea 
            id="notes"
            v-model="itemNotes"
            rows="2"
            class="w-full p-2 border rounded-md text-sm"
            placeholder="Any special requests?"
          ></textarea>
        </div>
      </CardContent>
      
      <CardFooter class="flex justify-between">
        <Button variant="outline" @click="closeOptionModal">Cancel</Button>
        <Button @click="addToCart">Add to Cart</Button>
      </CardFooter>
    </Card>
  </div>
</template>
