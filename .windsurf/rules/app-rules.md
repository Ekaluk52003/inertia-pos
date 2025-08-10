---
trigger: always_on
---

Product Requirements Document (PRD): Eattinee - Digital Kitchen & Ordering Platform
1. Overview
This document outlines the requirements for building a real-time, web-based restaurant ordering and kitchen management system. The platform enables restaurant owners to manage their menus, staff, and orders digitally. Customers can scan a QR code at their table to view the menu, place orders, and make payments directly from their mobile devices. The kitchen staff receives and manages these orders through a live dashboard.

2. Core Objective
To create a seamless, responsive, and efficient Single-Page Application (SPA) experience for both restaurant customers and staff, featuring a clean, minimal user interface. The primary goal is to streamline the ordering process, reduce manual errors, improve kitchen workflow, and provide a modern, convenient payment solution using a robust and scalable technology stack.

3. Technical Stack
Backend Framework: Laravel
Frontend Framework: Vue.js 3 (using Composition API)
Frontend/Backend Bridge: Inertia.js
State Management (Frontend): Pinia
Database: MySQL (pls provide docker compose for Msql)
Real-time Server: Laravel Reverb
Real-time Client: Laravel Echo with laravel/echo-vue

Authentication: Laravel Breeze (Inertia + Vue stack) for initial scaffolding
UI Component Library: shadcn-vue
CSS Framework: Tailwind CSS
Asset Bundling: Vite
4. User Personas & Roles
Restaurant Owner: The primary administrator. Can manage all aspects of their restaurants, including settings, menus, and staff accounts.
Staff User (e.g., Kitchen Staff, Manager): An employee account created by the Restaurant Owner. Has permission to view and manage orders (e.g., Kitchen View) but cannot change core restaurant settings or manage other users.

Customer (Diner): A non-authenticated user who scans a QR code to place an order and pay.

5. Core Features & User Stories
5.1. Restaurant Owner/Manager Features

User Story (Authentication): As a restaurant owner, I want to create an account and log in securely to manage my restaurant(s).

User Story (Dashboard): As an owner, I want a central dashboard to view my restaurants and access all management functions.

User Story (Restaurant Settings): As an owner, I want to create and edit my restaurant's details, including its name, address, and operational settings.

User Story (Menu Management): As an owner, I want to add, edit, and delete menu items, organize them by category, and set their availability.

User Story (QR Code Management): As an owner, I want to generate unique QR codes for each table, view active codes, and deactivate old ones.

User Story (Bills & Payments): As an owner, I want to view a history of all bills and payment transactions for my restaurant.

User Story (Staff Management): As a restaurant owner, I want to create, view, and delete staff user accounts for my restaurant, so my employees can access the system with limited permissions.

Requirements:

A "Staff" section in the settings page.

An "Invite Staff" or "Add Staff" form that takes a name, email, and password.

The created user will have a role of "staff" and be associated with the owner's restaurant_id.

A table displaying all staff members for the selected restaurant, with an option to delete them.

5.2. Customer Features

User Story (Menu Access): As a customer, I want to scan a QR code at my table and instantly access the digital menu on my phone.

User Story (Ordering): As a customer, I want to browse the menu, add items to my cart, specify quantity, and add special instructions.

User Story (Checkout & Payment): As a customer, I want to review my order and pay easily, either before or after the order is sent to the kitchen, depending on the restaurant's settings.

User Story (Order Tracking & Billing): As a customer, I want to see the real-time status of my order items and request the final bill when I'm finished.

5.3. Kitchen Staff Features

User Story (Login): As a staff member, I want to log in using the credentials provided by the restaurant owner.

User Story (Real-time Order Display): As kitchen staff, I want to see new orders appear instantly on a dedicated kitchen dashboard with an audible notification.

User Story (Item Status Management): As kitchen staff, I want to update the status of individual items (e.g., from 'pending' to 'cooking' to 'ready') to track progress.

6. Data Models (MySQL Schema)
users:

id, name, email, password

role (ENUM: owner, staff)

restaurant_id (nullable foreign key to restaurants) - NEW, links staff to a restaurant. Null for owners who might manage multiple restaurants.

created_at, updated_at

restaurants: id, owner_id (foreign key to users), name, description, payBefore, promptPayId, billing (JSON), etc.

menus: id, restaurant_id, name, price, category, is_available.

orders: id, restaurant_id, table_number, code, total_amount, is_paid.

order_items: id, order_id, menu_id, name, quantity, status.

qr_codes: id, restaurant_id, table_number, code, is_active.

bills: id, restaurant_id, code, total_amount, status.

payments: id, order_id, trans_ref, amount, sender_name.

7. Real-time Events 

8. Inertia Pages and Components
Layouts: AuthenticatedLayout.vue, GuestLayout.vue

Pages:

Auth/Login.vue, Auth/Register.vue

Dashboard.vue

Menu/Index.vue

Kitchen/Show.vue

QRCode/Index.vue

Settings/Index.vue (will contain tabs for Restaurant Info, Billing, and Staff Management)

Public/Menu.vue (Customer-facing menu)

Components: RestaurantCard.vue, MenuItem.vue, KitchenOrderCard.vue, ShoppingCart.vue, etc.

9. Non-Functional Requirements
Unchanged from the previous version.

10. Frontend UI/UX Specification
Component Library: shadcn-vue. All UI elements (buttons, cards, forms, tables, modals) should be built using this library to ensure consistency and a clean aesthetic.

Theme & Colors:

Primary Background: White (#FFFFFF).

Primary/Accent Color: A vibrant, energetic yellow (e.g., Tailwind's yellow-300) for buttons, links, highlights, and selected states.

Design Philosophy: Minimalist and clean. avoid shadows.

Page-Specific UI Components:



Dashboard:

Use Card for each restaurant listing. The selected restaurant card should have a yellow border.

Use Card for each navigation link (Menu, Orders, etc.), with a simple icon and title.

Menu Management:

Use Button with a plus icon to "Add Item".

Use Dialog (modal) for the Add/Edit Menu Item form.

Use Card for each menu item display.

Use Table to display staff users, with a "Delete" button in each row.

Kitchen View:

Use Card to represent each table's active orders.

The card header should prominently display the table number.

Inside the card, list each item with its quantity and status (Badge).

Use small Buttons to advance the status of each item.

Customer Menu:

Clean, single-column layout for mobile.

Important always ask when you want to implment functionality. dont excute the cmd wihtout asking.

Use Card for each menu item with an image, name, price, and "Add to Cart" Button.