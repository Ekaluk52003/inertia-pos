import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

// Define interfaces
interface MenuOption {
    name: string;
    choices?: {
        name: string;
        price?: number;
    }[];
    values?: {
        name: string;
        price?: number;
    }[];
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

interface SelectedOption {
    option_name: string;
    choices: string[];
    additional_price: number;
}

interface CartItem {
    item: MenuItem;
    quantity: number;
    notes: string;
    selectedOptions: SelectedOption[];
}

interface OrderItem {
    [key: string]: string | number | null | SelectedOption[] | any; // Add index signature for FormDataConvertible
    menu_id: number;
    quantity: number;
    special_instructions: string | null;
    selected_options?: SelectedOption[] | null;
}

interface OrderItemStatus {
    id: number;
    name: string;
    price: number;
    quantity: number;
    status: 'pending' | 'cooking' | 'ready' | 'served';
    special_instructions?: string | null;
    options?: SelectedOption[] | null;
    created_at: string;
}

interface ActiveOrder {
    id: number;
    table_number: string;
    code: string;
    total_amount: number;
    status: 'active' | 'billing' | 'billed' | 'completed';
    is_paid: boolean;
    items: OrderItemStatus[];
    created_at: string;
}

export const useCartStore = defineStore('cart', () => {
    // State
    const cart = ref<CartItem[]>([]);
    const showCart = ref(false);

    // For option selection modal
    const showOptionModal = ref(false);
    const currentItem = ref<MenuItem | null>(null);
    const selectedOptions = ref<SelectedOption[]>([]);
    const itemNotes = ref('');

    // Active order state
    const activeOrder = ref<ActiveOrder | null>(null);

    // Order history state
    const orderHistory = ref<ActiveOrder[]>([]);

    // Computed properties
    const cartTotal = computed(() => {
        return cart.value.reduce((total, item) => {
            // Base price
            let itemTotal = Number(item.item.price);

            // Add additional price from selected options
            if (item.selectedOptions && item.selectedOptions.length > 0) {
                const optionsPrice = item.selectedOptions.reduce((sum, opt) => {
                    return sum + Number(opt.additional_price || 0);
                }, 0);
                itemTotal += optionsPrice;
            }

            return total + itemTotal * item.quantity;
        }, 0);
    });

    const cartItemCount = computed(() => {
        return cart.value.reduce((count, item) => count + item.quantity, 0);
    });

    // Total items count (cart + active order items)
    const totalItemCount = computed(() => {
        // Only count items in the cart, not active orders
        return cart.value.reduce((count, item) => count + item.quantity, 0);
    });

    // Order status computed properties
    const pendingItems = computed(() => {
        if (!activeOrder.value || !activeOrder.value.items) return [];
        return activeOrder.value.items.filter(item => item.status === 'pending');
    });

    const cookingItems = computed(() => {
        if (!activeOrder.value || !activeOrder.value.items) return [];
        return activeOrder.value.items.filter(item => item.status === 'cooking');
    });

    const readyItems = computed(() => {
        if (!activeOrder.value || !activeOrder.value.items) return [];
        return activeOrder.value.items.filter(item => item.status === 'ready');
    });

    const servedItems = computed(() => {
        if (!activeOrder.value || !activeOrder.value.items) return [];
        return activeOrder.value.items.filter(item => item.status === 'served');
    });

    const hasActiveOrderItems = computed(() => {
        return activeOrder.value && activeOrder.value.items && activeOrder.value.items.length > 0;
    });

    const hasOrderHistory = computed(() => {
        return orderHistory.value && orderHistory.value.length > 0;
    });

    // Add computed properties for billing flow
    const canRequestBill = computed(() => {
        if (!activeOrder.value) return false;

        // Can request bill when order is active and has items
        return activeOrder.value.status === 'active' &&
               activeOrder.value.items &&
               activeOrder.value.items.length > 0;
    });

    const shouldShowBillButton = computed(() => {
        if (!activeOrder.value) return false;
        return activeOrder.value.status === 'active';
    });

    const shouldShowInvoice = computed(() => {
        if (!activeOrder.value) return false;
        return ['billing', 'billed', 'completed'].includes(activeOrder.value.status);
    });

    const shouldShowPaymentQR = computed(() => {
        if (!activeOrder.value) return false;
        return activeOrder.value.status === 'billing';
    });

    const shouldShowMenu = computed(() => {
        if (!activeOrder.value) return true;
        return activeOrder.value.status === 'active';
    });

    const isBillingRequested = computed(() => {
        return activeOrder.value?.status === 'billing';
    });

    const isBilled = computed(() => {
        return activeOrder.value?.status === 'billed';
    });

    // Helper function to check if an item has valid options with values
    const hasValidOptions = (item: MenuItem): boolean => {
        if (!item.options || !Array.isArray(item.options) || item.options.length === 0) {
            return false;
        }

        // Check if any option has values (database format) or choices (our test format)
        const hasValidOptionValues = item.options.some((option) => {
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

    // Methods
    const toggleCart = () => {
        showCart.value = !showCart.value;
    };

    const openCart = () => {
        showCart.value = true;
    };

    const closeCart = () => {
        showCart.value = false;
    };

    const openOptionModal = (item: MenuItem) => {
        currentItem.value = item;
        selectedOptions.value = [];
        itemNotes.value = '';
        showOptionModal.value = true;

        // Pre-select required options if they have default values
        if (item.options && Array.isArray(item.options)) {
            item.options.forEach((option) => {
                // If the option is required and has only one choice, pre-select it
                if (option.required !== false) {
                    // Check for choices (test format)
                    if (option.choices && option.choices.length === 1) {
                        toggleOptionChoice(option.name, option.choices[0].name, option.choices[0].price || 0);
                    }
                    // Check for values (database format)
                    else if (option.values && option.values.length === 1) {
                        toggleOptionChoice(option.name, option.values[0].name, option.values[0].price || 0);
                    }
                }
            });
        }
    };

    const closeOptionModal = () => {
        showOptionModal.value = false;
        currentItem.value = null;
    };

    const toggleOptionChoice = (option_name: string, choiceName: string, price: number = 0) => {
        // Find if we already have this option in our selection
        const existingOptionIndex = selectedOptions.value.findIndex((opt) => opt.option_name === option_name);

        // If the option doesn't exist yet, create it
        if (existingOptionIndex === -1) {
            selectedOptions.value.push({
                option_name,
                choices: [choiceName],
                additional_price: price,
            });
            return;
        }

        // Get the existing option
        const existingOption = selectedOptions.value[existingOptionIndex];

        // Find if the choice is already selected
        const choiceIndex = existingOption.choices.indexOf(choiceName);

        // Find the current option in the menu item
        const menuOption = currentItem.value?.options?.find((opt) => opt.name === option_name);

        // Check if this is a multiple-choice option
        const isMultiple = menuOption?.multiple === true;

        if (choiceIndex === -1) {
            // Choice is not selected yet
            if (isMultiple) {
                // For multiple-choice options, add the new choice
                existingOption.choices.push(choiceName);
                existingOption.additional_price += price;
            } else {
                // For single-choice options, replace the existing choice
                existingOption.choices = [choiceName];

                // Recalculate price - first find the old choice's price
                let oldPrice = 0;
                if (menuOption?.choices) {
                    const oldChoice = menuOption.choices.find((c) => c.name === existingOption.choices[0]);
                    oldPrice = oldChoice?.price || 0;
                } else if (menuOption?.values) {
                    const oldValue = menuOption.values.find((v) => v.name === existingOption.choices[0]);
                    oldPrice = oldValue?.price || 0;
                }

                // Set the new price
                existingOption.additional_price = price;
            }
        } else {
            // Choice is already selected
            if (isMultiple) {
                // For multiple-choice options, remove the choice if it's not the last one
                if (existingOption.choices.length > 1) {
                    existingOption.choices.splice(choiceIndex, 1);
                    existingOption.additional_price -= price;
                }
            } else {
                // For single-choice options, do nothing (can't deselect the only choice)
                // Unless there's no required flag or it's explicitly set to false
                if (menuOption?.required === false) {
                    // Remove the entire option
                    selectedOptions.value.splice(existingOptionIndex, 1);
                }
            }
        }
    };

    const isChoiceSelected = (option_name: string, choiceName: string): boolean => {
        const option = selectedOptions.value.find((opt) => opt.option_name === option_name);
        return option ? option.choices.includes(choiceName) : false;
    };

    const addToCart = (item: MenuItem) => {
        if (hasValidOptions(item)) {
            openOptionModal(item);
        } else {
            addItemDirectlyToCart(item);
        }
    };

    const addItemDirectlyToCart = (item: MenuItem) => {
        // Check if the item is already in the cart
        const existingItemIndex = cart.value.findIndex(
            (cartItem) => cartItem.item.id === item.id && cartItem.selectedOptions.length === 0 && !cartItem.notes
        );

        if (existingItemIndex !== -1) {
            // If the item exists, increment its quantity
            cart.value[existingItemIndex].quantity++;
        } else {
            // Otherwise, add it as a new item
            cart.value.push({
                item,
                quantity: 1,
                notes: '',
                selectedOptions: [],
            });
        }

        // Show the cart after adding an item
        showCart.value = true;
    };

    const confirmAddToCart = (quantity: number = 1) => {
        if (!currentItem.value) return;

        // Check if all required options are selected
        if (currentItem.value.options && Array.isArray(currentItem.value.options)) {
            const missingRequiredOptions = currentItem.value.options.filter((option) => {
                // Skip if not required
                if (option.required === false) return false;

                // Check if this option is in our selection
                const isSelected = selectedOptions.value.some((selected) => selected.option_name === option.name);
                return !isSelected;
            });

            if (missingRequiredOptions.length > 0) {
                // Alert the user about missing required options
                alert(`Please select options for: ${missingRequiredOptions.map((o) => o.name).join(', ')}`);
                return;
            }
        }

        // Check if the same item with the same options is already in the cart
        const existingItemIndex = cart.value.findIndex((cartItem) => {
            // Must be the same menu item
            if (cartItem.item.id !== currentItem.value!.id) return false;

            // Must have the same notes
            if (cartItem.notes !== itemNotes.value) return false;

            // Must have the same number of selected options
            if (cartItem.selectedOptions.length !== selectedOptions.value.length) return false;

            // Each option must match exactly
            for (const option of selectedOptions.value) {
                const cartOption = cartItem.selectedOptions.find((opt) => opt.option_name === option.option_name);
                if (!cartOption) return false;

                // Check if choices match
                if (cartOption.choices.length !== option.choices.length) return false;
                for (const choice of option.choices) {
                    if (!cartOption.choices.includes(choice)) return false;
                }
            }

            return true;
        });

        if (existingItemIndex !== -1) {
            // If the exact same item exists, increment its quantity by the requested amount
            cart.value[existingItemIndex].quantity += quantity;
        } else {
            // Otherwise, add it as a new item with specified quantity
            cart.value.push({
                item: currentItem.value,
                quantity: quantity,
                notes: itemNotes.value,
                selectedOptions: [...selectedOptions.value], // Create a copy of the array
            });
        }

        // Close the modal and show the cart
        closeOptionModal();
        showCart.value = true;
    };

    const removeFromCart = (index: number) => {
        cart.value.splice(index, 1);
    };

    const incrementQuantity = (index: number) => {
        cart.value[index].quantity++;
    };

    const decrementQuantity = (index: number) => {
        if (cart.value[index].quantity > 1) {
            cart.value[index].quantity--;
        } else {
            removeFromCart(index);
        }
    };

    const formatPrice = (price: number) => {
        return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(price);
    };

    // Calculate the total for all ordered items
    const getOrderTotal = (): number => {
        if (!activeOrder.value || !activeOrder.value.items) return 0;

        return activeOrder.value.items.reduce((total, item) => {
            // The server persists OrderItem.price as the unit price already including
            // any selected option additional_price. Do not add option prices again here
            // (would double-count). For active orders we therefore use price * qty only.
            const qty = (item.quantity || 1);
            const itemTotal = Number(item.price) * qty;
            return total + itemTotal;
        }, 0);
    };

    const prepareOrderItems = (): OrderItem[] => {
        return cart.value.map((item: CartItem) => {
            // Create a clean order item object with all required fields
            const orderItem: OrderItem = {
                menu_id: item.item.id,
                quantity: item.quantity,
                special_instructions: item.notes || null,
                selected_options: item.selectedOptions.length > 0 ? item.selectedOptions : null,
            };
            return orderItem;
        });
    };

    const clearCart = () => {
        cart.value = [];
        showCart.value = false;
    };

    // Add method to request bill
    const requestBill = async () => {
        if (!activeOrder.value || !canRequestBill.value) return;

        try {
            // Update local state immediately for UI responsiveness
            const originalStatus = activeOrder.value.status;
            activeOrder.value.status = 'billing';

            // Make API call to update server
            // Extract restaurant and table codes from current URL or route params
            const currentPath = window.location.pathname;
            const pathParts = currentPath.split('/');
            const restaurantCode = pathParts[3]; // /public/menu/{restaurantCode}/{tableCode}
            const tableCode = pathParts[4];

            await router.post(`/public/order/${restaurantCode}/${tableCode}/request-bill`);
        } catch (error) {
            // Revert on error
            if (activeOrder.value) {
                activeOrder.value.status = 'active';
            }
            throw error;
        }
    };

    // Active order methods
    const setActiveOrder = (order: ActiveOrder | null) => {
        activeOrder.value = order;
    };

    const updateItemStatus = (itemId: number, status: 'pending' | 'cooking' | 'ready' | 'served') => {
        if (!activeOrder.value) return;

        const item = activeOrder.value.items.find(item => item.id === itemId);
        if (item) {
            item.status = status;
        }
    };

    const setOrderHistory = (orders: ActiveOrder[]) => {
        orderHistory.value = orders;
    };

    return {
        cart,
        showCart,
        cartTotal,
        showOptionModal,
        currentItem,
        selectedOptions,
        itemNotes,
        activeOrder,
        orderHistory,
        pendingItems,
        cookingItems,
        readyItems,
        servedItems,
        hasActiveOrderItems,
        hasOrderHistory,
        totalItemCount,
        canRequestBill,
        shouldShowBillButton,
        shouldShowInvoice,
        shouldShowPaymentQR,
        shouldShowMenu,
        isBillingRequested,
        isBilled,
        toggleCart,
        openCart,
        closeCart,
        addToCart,
        removeFromCart,
        incrementQuantity,
        decrementQuantity,
        openOptionModal,
        closeOptionModal,
        toggleOptionChoice,
        isChoiceSelected,
        confirmAddToCart,
        formatPrice,
        getOrderTotal,
        prepareOrderItems,
        clearCart,
        requestBill,
        setActiveOrder,
        setOrderHistory,
        updateItemStatus
    };
});
