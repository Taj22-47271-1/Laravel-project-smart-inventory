<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $editingId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $passwordConfirmation = '';
    public string $role = '';
    public bool $isActive = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        $search = trim($this->search);

        return User::query()
            ->with('roles:id,name')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('roles', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function roles()
    {
        return Role::query()->orderBy('name')->get(['id', 'name']);
    }

    public function create(): void
    {
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $user = User::query()->with('roles')->findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->passwordConfirmation = '';
        $this->role = $user->roles->first()?->name ?? '';
        $this->isActive = (bool) ($user->is_active ?? true);
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('manage users'), 403);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role' => ['required', 'exists:roles,name'],
            'isActive' => ['boolean'],
        ];

        $rules['password'] = $this->editingId
            ? ['nullable', 'string', 'min:8', 'same:passwordConfirmation']
            : ['required', 'string', 'min:8', 'same:passwordConfirmation'];

        $rules['passwordConfirmation'] = $this->editingId
            ? ['nullable', 'string', 'min:8']
            : ['required', 'string', 'min:8'];

        $validated = $this->validate($rules);

        if ($this->editingId === auth()->id() && ! $validated['isActive']) {
            $this->addError('isActive', 'You cannot deactivate your own account.');
            return;
        }

        $user = $this->editingId
            ? User::findOrFail($this->editingId)
            : new User();

        $user->name = trim($validated['name']);
        $user->email = strtolower(trim($validated['email']));
        $user->is_active = $validated['isActive'];

        if (filled($validated['password'] ?? null)) {
            $user->password = Hash::make($validated['password']);
        }

        if (! $user->exists) {
            $user->email_verified_at = now();
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        session()->flash('success', $this->editingId ? 'User updated successfully.' : 'User created successfully.');

        $this->resetForm();
        unset($this->users);
    }

    public function toggleStatus(int $id): void
    {
        abort_unless(auth()->user()->can('manage users'), 403);

        if ($id === auth()->id()) {
            $this->addError('user', 'You cannot deactivate your own account.');
            return;
        }

        $user = User::findOrFail($id);
        $user->update(['is_active' => ! (bool) $user->is_active]);

        session()->flash('success', 'User status updated successfully.');
        unset($this->users);
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->passwordConfirmation = '';
        $this->role = '';
        $this->isActive = true;
        $this->resetValidation();
    }
};
?>

<div class="space-y-6">
    <div><h1 class="text-2xl font-bold">User Management</h1><p class="text-sm text-gray-500">Create users, assign roles and manage account status.</p></div>
    @if (session()->has('success')) <div class="rounded-lg bg-green-50 p-4 text-green-800">{{ session('success') }}</div> @endif
    @error('user') <div class="rounded-lg bg-red-50 p-4 text-red-700">{{ $message }}</div> @enderror

    @can('manage users')
        <form wire:submit="save" class="space-y-4 rounded-xl border bg-white p-5 dark:bg-gray-900">
            <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">{{ $editingId ? 'Edit User' : 'Create User' }}</h2>@if ($editingId)<button type="button" wire:click="cancelEdit" class="rounded border px-3 py-2 text-sm">Cancel Edit</button>@endif</div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div><label class="mb-2 block text-sm font-medium">Name</label><input wire:model="name" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800">@error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-2 block text-sm font-medium">Email</label><input type="email" wire:model="email" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800">@error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-2 block text-sm font-medium">Role</label><select wire:model="role" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"><option value="">Select role</option>@foreach ($this->roles as $option)<option value="{{ $option->name }}">{{ ucfirst($option->name) }}</option>@endforeach</select>@error('role')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-2 block text-sm font-medium">Password {{ $editingId ? '(leave blank to keep)' : '' }}</label><input type="password" wire:model="password" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800">@error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-2 block text-sm font-medium">Confirm Password</label><input type="password" wire:model="passwordConfirmation" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"></div>
                <div class="flex items-center gap-3 pt-8"><input id="isActive" type="checkbox" wire:model="isActive" class="rounded"><label for="isActive" class="text-sm font-medium">Active account</label>@error('isActive')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
            </div>
            <div class="flex justify-end"><button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 font-semibold text-white">{{ $editingId ? 'Update User' : 'Create User' }}</button></div>
        </form>
    @endcan

    <div class="overflow-hidden rounded-xl border bg-white dark:bg-gray-900">
        <div class="border-b p-5"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-semibold">Users</h2><p class="text-sm text-gray-500">Registered system users.</p></div><input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, email or role..." class="rounded-lg border px-3 py-2.5 dark:bg-gray-800"></div></div>
        <div class="overflow-x-auto"><table class="min-w-[850px] w-full text-left text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Verified</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Created</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y">@forelse ($this->users as $user)<tr wire:key="user-{{ $user->id }}"><td class="px-4 py-4"><p class="font-semibold">{{ $user->name }}</p><p class="text-xs text-gray-500">{{ $user->email }}</p></td><td class="px-4 py-4">{{ $user->roles->pluck('name')->map(fn($role)=>ucfirst($role))->join(', ') ?: 'No role' }}</td><td class="px-4 py-4">{{ $user->email_verified_at ? 'Yes' : 'No' }}</td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ ($user->is_active ?? true) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ ($user->is_active ?? true) ? 'Active' : 'Disabled' }}</span></td><td class="px-4 py-4">{{ $user->created_at->format('d M Y') }}</td><td class="px-4 py-4"><div class="flex justify-end gap-2">@can('manage users')<button wire:click="edit({{ $user->id }})" class="rounded bg-blue-50 px-3 py-2 text-blue-700">Edit</button>@if ($user->id !== auth()->id())<button wire:click="toggleStatus({{ $user->id }})" wire:confirm="Change this user's account status?" class="rounded bg-amber-50 px-3 py-2 text-amber-700">{{ ($user->is_active ?? true) ? 'Disable' : 'Enable' }}</button>@endif@endcan</div></td></tr>@empty<tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No users found.</td></tr>@endforelse</tbody></table></div><div class="border-t p-4">{{ $this->users->links() }}</div>
    </div>
</div>
