<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('admin.staff.index') }}" class="link-underline text-sm">&larr; Staff accounts</a>
        <div class="mt-3"><x-page-header eyebrow="New account" title="Add a staff member" /></div>
    </x-slot>

    <form method="POST" action="{{ route('admin.staff.store') }}" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @csrf
        @include('admin.staff._form')

        <div class="mt-8 flex justify-end gap-3">
            <a href="{{ route('admin.staff.index') }}" class="btn-ghost">Cancel</a>
            <x-primary-button>Create account</x-primary-button>
        </div>
    </form>
</x-app-layout>
