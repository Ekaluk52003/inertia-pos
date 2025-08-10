<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/customerLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { ShoppingCart, Plus, Minus, ChevronDown, ChevronUp } from 'lucide-vue-next';

interface MenuOption {
    name: string;
    // Test format (used in development)
    choices?: {
        name: string;
        price?: number;
    }[];
    // Database format (used in production)
    values?: {
        name: string;
        price?: number;
    }[];
    // These properties may not be present in the database
    required?: boolean;
    multiple?: boolean;
}

interface MenuItem {
    id: number;
    name: string;
    price: number;
    description: string | null;
    category: string;
    image_path: string | null;
    is_available: boolean;
    options: MenuOption[] | null;
}

interface Restaurant {
    id: number;
    name: string;
    description: string | null;
    payBefore: boolean;
}

interface Table {
    number: string | number;
    code: string;
}

interface Props {
    restaurant: Restaurant;
    table: Table;
    menuItems: MenuItem[];
    categories: string[];
    activeOrder: any | null;
}

const props = defineProps<Props>();

interface SelectedOption {
    optionName: string;
    choices: string[];
    additionalPrice: number;
}

interface CartItem {
    item: MenuItem;
    quantity: number;
    notes: string;
    selectedOptions: SelectedOption[];
}

// Cart initialization

// Cart state
const cart = ref<CartItem[]>([]);
const activeCategory = ref<string | null>(props.categories[0] || null);
const showCart = ref(false);

// Option selection state
const showOptionModal = ref(false);
const currentItem = ref<MenuItem | null>(null);
const selectedOptions = ref<SelectedOption[]>([]);
const itemNotes = ref('');

// Helper function to check if an item has valid options with values
const hasValidOptions = (item: MenuItem): boolean => {
    if (!item.options || !Array.isArray(item.options) || item.options.length === 0) {
        return false;
    }
    
    // Check if any option has values (database format) or choices (our test format)
    const hasValidOptionValues = item.options.some(option => {
        // Check if option is a valid object with a name
        if (!option || typeof option !== 'object' || !option.name) {
            return false;
        }
        
        // Check for values array (database format)
        if (option.values && Array.isArray(option.values) && option.values.length > 0) {
            return true;
        }
        
        // Check for choices array (our test format)
        if (option.choices && Array.isArray(option.choices) && option.choices.length > 0) {
            return true;
        }
        
        return false;
    });
    
    return hasValidOptionValues;
};

// Computed properties
const menuItemsByCategory = computed(() => {
    const result: Record<string, MenuItem[]> = {};
    
    // Group menu items by category
    props.categories.forEach((category: string) => {
        result[category] = props.menuItems
            .filter((item: MenuItem) => item.category === category)
            .map((item: MenuItem) => {
                // Return the item as is, without adding test options
                return item;
            });
    });
    
    return result;
});

const cartTotal = computed(() => {
    return cart.value.reduce((total, item) => {
        // Base price
        let itemTotal = Number(item.item.price);
        
        // Add additional price from selected options
        if (item.selectedOptions && item.selectedOptions.length > 0) {
            const optionsPrice = item.selectedOptions.reduce((sum, opt) => {
                return sum + Number(opt.additionalPrice || 0);
            }, 0);
            itemTotal += optionsPrice;
        }
        
        return total + (itemTotal * item.quantity);
    }, 0);
});

const cartItemCount = computed(() => {
    return cart.value.reduce((count, item) => count + item.quantity, 0);
});

// Methods
const openOptionModal = (item: MenuItem) => {
    
    // Check if the item has valid options with choices or values
    if (!hasValidOptions(item)) {

        addItemDirectlyToCart(item);
        return;
    }

    // Open the modal for items with valid options
    currentItem.value = item;
    selectedOptions.value = [];
    itemNotes.value = '';
    showOptionModal.value = true;
    
    // Initialize default selections for required options
    if (item.options) {
        item.options.forEach(option => {
            // Skip invalid options
            if (!option || typeof option !== 'object' || !option.name) {
             
                return;
            }
            
       
            const isRequired = option.required !== false; // Default to true unless explicitly false
            
            if (!isRequired) {
 
                return; // Skip non-required options
            }
            
            // Handle choices format (test format)
            if (option.choices && Array.isArray(option.choices) && option.choices.length > 0) {

                selectedOptions.value.push({
                    optionName: option.name,
                    choices: [option.choices[0].name],
                    additionalPrice: option.choices[0].price || 0
                });
            }
            
            // Handle values format (database format)
            else if (option.values && Array.isArray(option.values) && option.values.length > 0) {
       
                selectedOptions.value.push({
                    optionName: option.name,
                    choices: [option.values[0].name],
                    additionalPrice: option.values[0].price || 0
                });
            }
        });
    }
    
};

// Helper function to add an item directly to cart without options
const addItemDirectlyToCart = (item: MenuItem) => {
    const existingItemIndex = cart.value.findIndex(cartItem => 
        cartItem.item.id === item.id && 
        cartItem.selectedOptions.length === 0 &&
        cartItem.notes === ''
    );
    
    if (existingItemIndex >= 0) {
        // If item already exists in cart, increment quantity
        cart.value[existingItemIndex].quantity++;
    } else {
        // Otherwise add new item to cart
        cart.value.push({
            item,
            quantity: 1,
            selectedOptions: [],
            notes: ''
        });
    }
};

const closeOptionModal = () => {
    showOptionModal.value = false;
    currentItem.value = null;
};

const toggleOptionChoice = (optionName: string, choiceName: string, price: number = 0) => {
    console.log(`Toggling option choice: ${optionName}, ${choiceName}, price: ${price}`);
    
    // Find the current option in the currentItem to check its actual multiple property
    const currentOption = currentItem.value?.options?.find(opt => 
        opt && typeof opt === 'object' && opt.name && 
        opt.name.toLowerCase() === optionName.toLowerCase());
    
    if (!currentOption) {
        console.error(`Option ${optionName} not found in currentItem`);
        return;
    }
    
    const isMultiple = currentOption.multiple === true; // Explicitly check for true
    const isRequired = currentOption.required !== false; // Default to true unless explicitly false
    
    console.log(`Option ${optionName} isMultiple: ${isMultiple}, isRequired: ${isRequired}`);
    
    const optionIndex = selectedOptions.value.findIndex(opt => 
        opt.optionName.toLowerCase() === optionName.toLowerCase());
    
    if (optionIndex >= 0) {
        const choiceIndex = selectedOptions.value[optionIndex].choices.findIndex(c => 
            c.toLowerCase() === choiceName.toLowerCase());
        
        if (choiceIndex >= 0) {
            // If choice is already selected
            if (isMultiple) {
                // For multiple selection, we can remove it (unless it's the last one and required)
                const isLastChoice = selectedOptions.value[optionIndex].choices.length === 1;
                if (!isRequired || !isLastChoice) {
                    console.log(`Removing choice ${choiceName} from ${optionName}`);
                    selectedOptions.value[optionIndex].choices.splice(choiceIndex, 1);
                    selectedOptions.value[optionIndex].additionalPrice -= price;
                    
                    // If no choices left, remove the entire option
                    if (selectedOptions.value[optionIndex].choices.length === 0) {
                        selectedOptions.value.splice(optionIndex, 1);
                    }
                } else {
                    console.log(`Cannot remove last choice from required option ${optionName}`);
                }
            } else if (!isRequired) {
                // For single selection that's not required, we can toggle it off
                console.log(`Removing single choice ${choiceName} from optional ${optionName}`);
                selectedOptions.value.splice(optionIndex, 1);
            } else {
                // For required single selection, do nothing when clicking the same choice
                console.log(`Cannot deselect required single choice ${choiceName} from ${optionName}`);
            }
        } else {
            // Choice not selected yet
            if (isMultiple) {
                // For multiple selection, add to existing choices
                console.log(`Adding choice ${choiceName} to ${optionName} (multiple)`);
                selectedOptions.value[optionIndex].choices.push(choiceName);
                selectedOptions.value[optionIndex].additionalPrice += price;
            } else {
                // For single selection, replace existing choice
                console.log(`Replacing choice with ${choiceName} for ${optionName} (single)`);
                const oldPrice = selectedOptions.value[optionIndex].additionalPrice;
                selectedOptions.value[optionIndex].choices = [choiceName];
                selectedOptions.value[optionIndex].additionalPrice = price;
                console.log(`Changed price from ${oldPrice} to ${price}`);
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

const isChoiceSelected = (optionName: string, choiceName: string): boolean => {
    const option = selectedOptions.value.find(opt => 
        opt.optionName.toLowerCase() === optionName.toLowerCase());
    return option ? option.choices.some(c => c.toLowerCase() === choiceName.toLowerCase()) : false;
};

const addToCart = (item: MenuItem) => {
    
    
    // Check if the item has valid options with choices
    if (hasValidOptions(item)) {
     
        openOptionModal(item);
        return;
    }
    
    addItemDirectlyToCart(item);
};

const confirmAddToCart = () => {
    if (!currentItem.value) return;
    
    // Check if all required options are selected
    if (currentItem.value.options) {
        const missingRequired = currentItem.value.options.some(option => {
            // Default to required unless explicitly set to false
            const isRequired = option.required !== false;
            
            if (!isRequired) return false; // Skip non-required options
            
            // Check if this required option is selected
            return !selectedOptions.value.some(selected => 
                selected.optionName.toLowerCase() === option.name.toLowerCase());
        });
        
        if (missingRequired) {
            // Handle missing required options (could show an error message)
            alert('Please select all required options');
            return;
        }
    }
    
    // Look for an identical item in the cart
    const existingItemIndex = cart.value.findIndex(cartItem => {
        if (cartItem.item.id !== currentItem.value!.id) return false;
        if (cartItem.notes !== itemNotes.value) return false;
        
        // Check if selected options match
        if (cartItem.selectedOptions.length !== selectedOptions.value.length) return false;
        
        // Deep comparison of selected options
        return cartItem.selectedOptions.every(cartOption => {
            const matchingOption = selectedOptions.value.find(opt => opt.optionName === cartOption.optionName);
            if (!matchingOption) return false;
            
            if (cartOption.choices.length !== matchingOption.choices.length) return false;
            
            return cartOption.choices.every(choice => matchingOption.choices.includes(choice));
        });
    });
    
    if (existingItemIndex >= 0) {
        // If identical item exists, increment quantity
        cart.value[existingItemIndex].quantity += 1;
    } else {
        // Otherwise add as new item
        cart.value.push({
            item: currentItem.value,
            quantity: 1,
            notes: itemNotes.value,
            selectedOptions: JSON.parse(JSON.stringify(selectedOptions.value)) // Deep clone to avoid reference issues
        });
    }
    
    // Close the modal
    closeOptionModal();
};

const removeFromCart = (index: number) => {
    cart.value.splice(index, 1);
};

const incrementQuantity = (index: number) => {
    cart.value[index].quantity += 1;
};

const decrementQuantity = (index: number) => {
    if (cart.value[index].quantity > 1) {
        cart.value[index].quantity -= 1;
    } else {
        removeFromCart(index);
    }
};

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(price);
};

const scrollToCategory = (category: string) => {
    activeCategory.value = category;
    const element = document.getElementById(`category-${category}`);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
    }
};

const toggleCart = () => {
    showCart.value = !showCart.value;
};
</script>

<template>
    <CustomerLayout>
        <Head :title="`${restaurant.name} - Table ${table.number}`" />
        
        <div class="flex h-full flex-1 flex-col gap-4 p-4 overflow-x-auto pb-24 md:pb-4">
            <!-- Restaurant Header -->
            <div class="text-center py-4">
                <h1 class="text-2xl font-bold">{{ restaurant.name }}</h1>
                <p v-if="restaurant.description" class="text-muted-foreground mt-1">{{ restaurant.description }}</p>
                <p class="text-sm mt-2">Table {{ table.number }}</p>
            </div>
            
            <!-- Category Navigation -->
            <div class="flex overflow-x-auto gap-2 pb-2 sticky top-0 bg-white z-10">
                <Button 
                    v-for="category in categories" 
                    :key="category"
                    :variant="activeCategory === category ? 'default' : 'outline'"
                    size="sm"
                    @click="scrollToCategory(category)"
                    class="whitespace-nowrap"
                >
                    {{ category }}
                </Button>
            </div>
            
            <!-- Menu Items by Category -->
            <div v-for="category in categories" :key="category" :id="`category-${category}`" class="mb-8">
                <h2 class="text-xl font-semibold mb-4">{{ category }}</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Card v-for="item in menuItemsByCategory[category]" :key="item.id" class="overflow-hidden">
                        <div class="flex">
                            <div v-if="item.image_path" class="w-24 h-24 bg-gray-100 flex-shrink-0">
                                <img :src="item.image_path" :alt="item.name" class="w-full h-full object-cover" />
                            </div>
                            
                            <div class="flex-1 p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-medium">{{ item.name }}</h3>
                                        <p v-if="item.description" class="text-sm text-muted-foreground mt-1">{{ item.description }}</p>
                                        <!-- Options indicator -->
                                        <div v-if="hasValidOptions(item)" class="flex items-center mt-1">
                                            <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full">Customizable</span>
                                        </div>
                                    </div>
                                    <Button 
                                        size="icon" 
                                        variant="outline" 
                                        @click="addToCart(item)"
                                        class="flex-shrink-0 h-8 w-8"
                                        :title="hasValidOptions(item) ? 'Select options' : 'Add to cart'"
                                    >
                                        <Plus class="h-4 w-4" />
                                    </Button>
                                </div>
                                <p class="mt-2 text-yellow-500 font-medium">{{ formatPrice(item.price) }}</p>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </div>
        
        <!-- Cart Button -->
        <div class="fixed bottom-4 right-4 left-4 md:left-auto">
            <Button 
                @click="toggleCart" 
                class="w-full md:w-auto" 
                :variant="cartItemCount > 0 ? 'default' : 'outline'"
            >
                <ShoppingCart class="mr-2 h-4 w-4" />
                <span v-if="cartItemCount > 0">{{ cartItemCount }} items · {{ formatPrice(cartTotal) }}</span>
                <span v-else>Cart</span>
                <ChevronUp v-if="showCart" class="ml-2 h-4 w-4" />
                <ChevronDown v-else class="ml-2 h-4 w-4" />
            </Button>
            
            <!-- Cart Panel -->
            <Card v-if="showCart" class="mt-2 absolute bottom-full mb-2 left-0 right-0 max-h-[70vh] overflow-y-auto">
                <CardHeader>
                    <CardTitle>Your Order</CardTitle>
                    <CardDescription>Table {{ table.number }}</CardDescription>
                </CardHeader>
                
                <CardContent>
                    <div v-if="cart.length === 0" class="text-center py-8">
                        <p class="text-muted-foreground">Your cart is empty</p>
                    </div>
                    
                    <div v-else class="space-y-4">
                        <div v-for="(cartItem, index) in cart" :key="index" class="flex justify-between items-start pb-4 border-b">
                            <div>
                                <h4 class="font-medium">{{ cartItem.item.name }}</h4>
                                <p class="text-sm text-muted-foreground">{{ formatPrice(cartItem.item.price) }} each</p>
                                <!-- Display selected options -->
                                <div v-if="cartItem.selectedOptions.length > 0" class="mt-1">
                                    <div v-for="(option, optIdx) in cartItem.selectedOptions" :key="optIdx" class="text-xs text-muted-foreground">
                                        <span class="font-medium">{{ option.optionName }}:</span> 
                                        {{ option.choices.join(', ') }}
                                        <span v-if="option.additionalPrice > 0">
                                            (+{{ formatPrice(option.additionalPrice) }})
                                        </span>
                                    </div>
                                </div>
                                <!-- Display notes if any -->
                                <p v-if="cartItem.notes" class="text-xs italic mt-1">"{{ cartItem.notes }}"</p>
                                <div class="flex items-center mt-2">
                                    <Button 
                                        size="icon" 
                                        variant="outline" 
                                        @click="decrementQuantity(index)"
                                        class="h-6 w-6"
                                    >
                                        <Minus class="h-3 w-3" />
                                    </Button>
                                    <span class="mx-2">{{ cartItem.quantity }}</span>
                                    <Button 
                                        size="icon" 
                                        variant="outline" 
                                        @click="incrementQuantity(index)"
                                        class="h-6 w-6"
                                    >
                                        <Plus class="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-medium">{{ formatPrice(cartItem.item.price * cartItem.quantity) }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
                
                <CardFooter>
                    <div class="w-full">
                        <div class="flex justify-between py-2">
                            <span class="font-medium">Total</span>
                            <span class="font-medium">{{ formatPrice(cartTotal) }}</span>
                        </div>
                        <Button class="w-full mt-4">
                            {{ restaurant.payBefore ? 'Pay & Order' : 'Place Order' }}
                        </Button>
                    </div>
                </CardFooter>
            </Card>
        </div>
    </CustomerLayout>
    
    <!-- Option Selection Modal -->
    <div v-show="showOptionModal && currentItem" 
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <Card class="w-full max-w-md max-h-[90vh] overflow-y-auto">
            <CardHeader>
                <CardTitle>Customize {{ currentItem?.name || 'Item' }}</CardTitle>
                <CardDescription>
                    <div class="flex justify-between items-center">
                        <span>Select your preferences</span>
                        <span class="font-medium">{{ formatPrice(currentItem?.price || 0) }}</span>
                    </div>
                </CardDescription>
            </CardHeader>
            
            <CardContent>
                <!-- Options -->
                <div v-if="currentItem?.options && currentItem.options.length > 0" class="space-y-6">
                    <!-- Display each option -->
                    <div v-for="(option, index) in currentItem.options" 
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
                        
                        <!-- Display choices (test format) -->
                        <div v-if="option.choices && Array.isArray(option.choices) && option.choices.length > 0" class="space-y-1">
                            <div 
                                v-for="choice in option.choices" 
                                :key="choice.name"
                                @click="toggleOptionChoice(option.name, choice.name, choice.price || 0)"
                                class="flex items-center justify-between p-2 rounded-md cursor-pointer hover:bg-muted"
                                :class="{
                                    'bg-muted': isChoiceSelected(option.name, choice.name),
                                    'border-l-2 border-yellow-500': isChoiceSelected(option.name, choice.name)
                                }"
                            >
                                <div class="flex items-center">
                                    <div 
                                        :class="{
                                            'w-4 h-4 mr-2 rounded-sm border': option.multiple === true,
                                            'w-4 h-4 mr-2 rounded-full border': option.multiple !== true,
                                            'bg-yellow-500 border-yellow-500': isChoiceSelected(option.name, choice.name),
                                            'border-gray-300': !isChoiceSelected(option.name, choice.name)
                                        }"
                                    ></div>
                                    <span>{{ choice.name }}</span>
                                </div>
                                <span v-if="choice.price && choice.price > 0" class="text-sm text-muted-foreground">
                                    +{{ formatPrice(choice.price) }}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Display values (database format) -->
                        <div v-if="option.values && Array.isArray(option.values) && option.values.length > 0" class="space-y-1">
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
                                    +{{ formatPrice(value.price) }}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Show if no options available -->
                        <p v-if="(!option.choices || option.choices.length === 0) && (!option.values || option.values.length === 0)" 
                           class="text-sm text-muted-foreground italic">
                            No options available
                        </p>
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
                <Button @click="confirmAddToCart">Add to Cart</Button>
            </CardFooter>
        </Card>
    </div>
</template>

<style scoped>
/* Add any custom styles here */
</style>
