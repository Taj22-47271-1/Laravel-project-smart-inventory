<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="dark"
>
<head>
    @include('partials.head')
</head>

<body class="min-h-screen antialiased">
    {{-- Desktop and mobile sidebar --}}
    <flux:sidebar
        sticky
        stashable
        class="app-sidebar"
    >
        {{-- Mobile close button --}}
        <flux:sidebar.toggle
            class="lg:hidden"
            icon="x-mark"
        />

        {{-- Brand --}}
        <a
            href="{{ route('dashboard') }}"
            wire:navigate
            class="app-brand"
        >
            <div class="app-brand-logo">
                SI
            </div>

            <div class="min-w-0">
                <p class="app-brand-title">
                    Smart Inventory
                </p>

                <p class="app-brand-subtitle">
                    Management System
                </p>
            </div>
        </a>

        {{-- Navigation --}}
        <flux:navlist
            variant="outline"
            class="app-navigation"
        >
            {{-- Overview --}}
            <flux:navlist.group
                heading="Overview"
                class="grid"
            >
                @can('view dashboard')
                    <flux:navlist.item
                        icon="home"
                        :href="route('dashboard')"
                        :current="request()->routeIs('dashboard')"
                        wire:navigate
                    >
                        Dashboard
                    </flux:navlist.item>
                @endcan
            </flux:navlist.group>

            {{-- Product management --}}
            <flux:navlist.group
                heading="Product Management"
                class="grid"
            >
                @can('view products')
                    <flux:navlist.item
                        icon="cube"
                        :href="route('products.index')"
                        :current="request()->routeIs('products.*')"
                        wire:navigate
                    >
                        Products
                    </flux:navlist.item>
                @endcan

                @can('view categories')
                    <flux:navlist.item
                        icon="squares-2x2"
                        :href="route('categories.index')"
                        :current="request()->routeIs('categories.*')"
                        wire:navigate
                    >
                        Categories
                    </flux:navlist.item>
                @endcan

                @can('view brands')
                    <flux:navlist.item
                        icon="tag"
                        :href="route('brands.index')"
                        :current="request()->routeIs('brands.*')"
                        wire:navigate
                    >
                        Brands
                    </flux:navlist.item>
                @endcan

                @can('view units')
                    <flux:navlist.item
                        icon="scale"
                        :href="route('units.index')"
                        :current="request()->routeIs('units.*')"
                        wire:navigate
                    >
                        Units
                    </flux:navlist.item>
                @endcan
            </flux:navlist.group>

            {{-- Contacts --}}
            <flux:navlist.group
                heading="Contacts"
                class="grid"
            >
                @can('view suppliers')
                    <flux:navlist.item
                        icon="truck"
                        :href="route('suppliers.index')"
                        :current="request()->routeIs('suppliers.*')"
                        wire:navigate
                    >
                        Suppliers
                    </flux:navlist.item>
                @endcan

                @can('view customers')
                    <flux:navlist.item
                        icon="user-group"
                        :href="route('customers.index')"
                        :current="request()->routeIs('customers.*')"
                        wire:navigate
                    >
                        Customers
                    </flux:navlist.item>
                @endcan
            </flux:navlist.group>

            {{-- Purchases --}}
            <flux:navlist.group
                heading="Purchases"
                class="grid"
            >
                @can('create purchases')
                    <flux:navlist.item
                        icon="shopping-bag"
                        :href="route('purchases.index')"
                        :current="request()->routeIs(
                            'purchases.index'
                        )"
                        wire:navigate
                    >
                        Create Purchase
                    </flux:navlist.item>
                @endcan

                @can('view purchases')
                    <flux:navlist.item
                        icon="clipboard-document-list"
                        :href="route('purchases.manage')"
                        :current="request()->routeIs(
                            'purchases.manage'
                        )"
                        wire:navigate
                    >
                        Manage Purchases
                    </flux:navlist.item>
                @endcan

                @can('create purchase returns')
                    <flux:navlist.item
                        icon="arrow-uturn-left"
                        :href="route('purchase-returns.index')"
                        :current="request()->routeIs(
                            'purchase-returns.index'
                        )"
                        wire:navigate
                    >
                        Create Purchase Return
                    </flux:navlist.item>
                @endcan

                @can('view purchase returns')
                    <flux:navlist.item
                        icon="document-magnifying-glass"
                        :href="route('purchase-returns.manage')"
                        :current="request()->routeIs(
                            'purchase-returns.manage'
                        )"
                        wire:navigate
                    >
                        Manage Purchase Returns
                    </flux:navlist.item>
                @endcan
            </flux:navlist.group>

            {{-- Sales --}}
            <flux:navlist.group
                heading="Sales"
                class="grid"
            >
                @can('create sales')
                    <flux:navlist.item
                        icon="shopping-cart"
                        :href="route('sales.index')"
                        :current="request()->routeIs('sales.index')"
                        wire:navigate
                    >
                        Create Sale
                    </flux:navlist.item>
                @endcan

                @can('view sales')
                    <flux:navlist.item
                        icon="clipboard-document-check"
                        :href="route('sales.manage')"
                        :current="request()->routeIs('sales.manage')"
                        wire:navigate
                    >
                        Manage Sales
                    </flux:navlist.item>
                @endcan

                @can('create sale returns')
                    <flux:navlist.item
                        icon="arrow-uturn-left"
                        :href="route('sale-returns.index')"
                        :current="request()->routeIs(
                            'sale-returns.index'
                        )"
                        wire:navigate
                    >
                        Create Sale Return
                    </flux:navlist.item>
                @endcan

                @can('view sale returns')
                    <flux:navlist.item
                        icon="document-magnifying-glass"
                        :href="route('sale-returns.manage')"
                        :current="request()->routeIs(
                            'sale-returns.manage'
                        )"
                        wire:navigate
                    >
                        Manage Sale Returns
                    </flux:navlist.item>
                @endcan
            </flux:navlist.group>

            {{-- Inventory --}}
            <flux:navlist.group
                heading="Inventory"
                class="grid"
            >
                @can('view inventory')
                    <flux:navlist.item
                        icon="archive-box"
                        :href="route('inventory.index')"
                        :current="request()->routeIs(
                            'inventory.index'
                        )"
                        wire:navigate
                    >
                        Current Inventory
                    </flux:navlist.item>

                    <flux:navlist.item
                        icon="arrows-right-left"
                        :href="route('inventory.movements')"
                        :current="request()->routeIs(
                            'inventory.movements'
                        )"
                        wire:navigate
                    >
                        Stock Movements
                    </flux:navlist.item>
                @endcan

                @can('view stock adjustments')
                    <flux:navlist.item
                        icon="adjustments-horizontal"
                        :href="route('stock-adjustments.index')"
                        :current="request()->routeIs(
                            'stock-adjustments.index'
                        )"
                        wire:navigate
                    >
                        Create Adjustment
                    </flux:navlist.item>

                    <flux:navlist.item
                        icon="clock"
                        :href="route('stock-adjustments.manage')"
                        :current="request()->routeIs(
                            'stock-adjustments.manage'
                        )"
                        wire:navigate
                    >
                        Adjustment History
                    </flux:navlist.item>
                @endcan
            </flux:navlist.group>

            {{-- Finance and reports --}}
            <flux:navlist.group
                heading="Finance & Reports"
                class="grid"
            >
                @can('view payments')
                    <flux:navlist.item
                        icon="banknotes"
                        :href="route('payments.index')"
                        :current="request()->routeIs('payments.*')"
                        wire:navigate
                    >
                        Payments
                    </flux:navlist.item>
                @endcan

                @can('view reports')
                    <flux:navlist.item
                        icon="chart-bar"
                        :href="route('reports.index')"
                        :current="request()->routeIs('reports.*')"
                        wire:navigate
                    >
                        Reports
                    </flux:navlist.item>
                @endcan
            </flux:navlist.group>

            {{-- Administration --}}
            @can('view users')
                <flux:navlist.group
                    heading="Administration"
                    class="grid"
                >
                    <flux:navlist.item
                        icon="users"
                        :href="route('users.index')"
                        :current="request()->routeIs('users.*')"
                        wire:navigate
                    >
                        Users & Roles
                    </flux:navlist.item>
                </flux:navlist.group>
            @endcan
        </flux:navlist>

        <flux:spacer />

        {{-- User menu --}}
        <div class="app-user-menu">
            <x-desktop-user-menu />
        </div>
    </flux:sidebar>

    {{-- Mobile header --}}
    <flux:header class="app-mobile-header lg:hidden">
        <flux:sidebar.toggle
            icon="bars-2"
            inset="left"
        />

        <div class="ml-2">
            <p class="text-sm font-bold text-white">
                Smart Inventory
            </p>
        </div>

        <flux:spacer />

        <div class="size-8 rounded-full bg-blue-600 text-center text-xs font-bold leading-8 text-white">
            {{ auth()->user()?->initials() ?? 'U' }}
        </div>
    </flux:header>

    {{-- Full-width page content --}}
    <flux:main class="app-main-shell">
        <div class="app-page-shell">
            {{ $slot }}
        </div>
    </flux:main>

    @fluxScripts
</body>
</html>