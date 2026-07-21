{{-- Add these links inside your existing authenticated sidebar/navigation. --}}

@can('view dashboard')
    <a href="{{ route('inventory-dashboard') }}" wire:navigate
       class="block rounded-lg px-3 py-2 {{ request()->routeIs('inventory-dashboard') ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
        Dashboard
    </a>
@endcan

@can('view sale returns')
    <a href="{{ route('sale-returns.manage') }}" wire:navigate
       class="block rounded-lg px-3 py-2 {{ request()->routeIs('sale-returns.*') ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
        Sale Returns
    </a>
@endcan

@can('view purchase returns')
    <a href="{{ route('purchase-returns.manage') }}" wire:navigate
       class="block rounded-lg px-3 py-2 {{ request()->routeIs('purchase-returns.*') ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
        Purchase Returns
    </a>
@endcan

@can('view stock adjustments')
    <a href="{{ route('stock-adjustments.manage') }}" wire:navigate
       class="block rounded-lg px-3 py-2 {{ request()->routeIs('stock-adjustments.*') ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
        Stock Adjustments
    </a>
@endcan

@can('view payments')
    <a href="{{ route('payments.index') }}" wire:navigate
       class="block rounded-lg px-3 py-2 {{ request()->routeIs('payments.*') ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
        Payments
    </a>
@endcan

@can('view reports')
    <a href="{{ route('reports.index') }}" wire:navigate
       class="block rounded-lg px-3 py-2 {{ request()->routeIs('reports.*') ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
        Reports
    </a>
@endcan

@can('view users')
    <a href="{{ route('users.index') }}" wire:navigate
       class="block rounded-lg px-3 py-2 {{ request()->routeIs('users.*') ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
        Users
    </a>
@endcan
